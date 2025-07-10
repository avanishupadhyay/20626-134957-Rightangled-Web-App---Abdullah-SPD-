<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use App\Models\AuditLog;
use App\Models\OrderAction;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderDispense;
use App\Models\DispenseBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Milon\Barcode\DNS2D;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Carbon;



class DispenserOrderController extends Controller
{


    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Dispenser')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        })->except('index');
    }

    public function index(Request $request)
    {
        // $approvedOrderNumbers = OrderAction::where('decision_status', 'approved')
        //     ->latest('created_at')
        //     ->pluck('order_id');

        $approvedOrderNumbers = OrderAction::latest('updated_at')
            ->get()
            ->unique('order_id') // Keep only the latest action per order_id
            ->filter(function ($action) {
                return $action->decision_status === 'approved';
            })
            ->pluck('order_id')
            ->toArray();

        $alreadyDispensed = OrderDispense::pluck('order_id')->toArray();

        $query = Order::whereIn('order_number', $approvedOrderNumbers)
            ->whereNotIn('order_number', $alreadyDispensed);


        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('order_number', 'like', '%' . $request->search . '%');
            })
                ->where(function ($q) {
                    $q->whereRaw("JSON_EXTRACT(order_data, '$.cancelled_at') IS NULL")
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
                });
        }

        $orders = $query->latest()->paginate(config('Reading.nodes_per_page'));

        $orders->getCollection()->transform(function ($order) {
            // Ensure order_data is array (decode if it's string)
            $orderData = is_array($order->order_data)
                ? $order->order_data
                : json_decode($order->order_data, true);

            $lineItems = collect($orderData['line_items'] ?? []);
            $order->total_quantity = $lineItems->sum('current_quantity');
            // dd($order->total_quantity );
            $order->line_items_titles = $lineItems->pluck('title')->toArray();
            // dd( $order->line_items_titles);
            $order->decoded_order_data = $orderData; // store for blade if needed
            return $order;
        });

        return view('admin.dispenser.index', compact('orders'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderData = json_decode($order->order_data, true);
        $auditDetails = getAuditLogDetailsForOrder($order->order_number) ?? null;
        return view('admin.dispenser.view', compact('order', 'orderData', 'auditDetails'));
    }

    public function printDispenseBatch(Request $request)
    {
        ini_set('max_execution_time', 3000);
        $request->validate([
            'order_ids' => 'required|array|min:1',
        ]);
        $orderNumbers = $request->order_ids;

        $orders = Order::whereIn('order_number', $orderNumbers)->get();

        $processedOrders = $orders->map(function ($order) {

            $orderData = is_array($order->order_data)
                ? $order->order_data
                : json_decode($order->order_data, true);

            $shippingLines = $orderData['shipping_lines'][0] ?? [];
            $shippingCode = strtolower($shippingLines['code'] ?? '');
            $customer = $orderData['customer'] ?? [];
            $shippingAddress = $orderData['shipping_address'] ?? [];
            $billingAddress = $orderData['billing_address'] ?? [];


            $shipper = Store::where('id', $order->store_id)->first()->toArray();

            $destination = $shippingAddress;
            //  && $shippingCode != 'rightangled hq' && $shippingCode != 'local delivery'

            if(isset($shippingAddress) && isset($shippingAddress['country']) && $shippingAddress['country'] == "United Kingdom"){
            // For UK Shippment
            $authToken = 'f3e7618c-d590-4e85-9246-1c39fcefd4f2';
            $response =  $this->createRoyalMailShipment($authToken, $shipper, $shippingAddress, $billingAddress, $orderData, $order->order_number);
            
            }elseif(isset($shippingAddress) && isset($shippingAddress['country']) && $shippingAddress['country'] != "United Kingdom"){

            $authToken = 'Basic ' . base64_encode('apX2aQ3yA3kF3p:J^9kM@8nD@8pS@1y');
            $shippingDateAndTime = Carbon::now('Europe/Berlin')
                ->addDay()
                ->format('Y-m-d\TH:i:s \G\M\TP');

            $response = $this->createDHLShipment($authToken, $shipper, $destination, $orderData, $shippingDateAndTime, $order->order_number);

            }

            $lineItems = collect($orderData['line_items'] ?? [])->map(function ($item) use ($order) {
                $productId = $item['product_id'] ?? null;
                $item['direction_of_use'] = $productId ? getProductMetafield($productId, $order->order_number) : 'N/A';
                // $item['direction_of_use'] = $productId ? getProductMetafield($productId, $order->order_number) : 'N/A';
                return $item;
            })->sortByDesc('quantity')->values();

            $order->trackingNumber = $response['trackingNumber'];
            $order->shipment_pdf_path  = $response['shipment_pdf_path'];

            $order->order_data = $orderData;
            $order->line_items = $lineItems;
            $order->total_quantity = $lineItems->sum('quantity');

            if ($shippingCode === 'rightangled hq') {
                $order->slip_type = 'hq';
            } elseif ($shippingCode === 'local delivery') {
                $order->slip_type = 'local';
            } else {
                $order->slip_type = 'other';
            }

            if ($order->slip_type === 'hq') {
                $order->ship_to = 'No shipping address';
            } elseif ($order->slip_type === 'local') {
                $order->ship_to = [
                    'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                    'address1' => $shippingAddress['address1'] ?? '',
                    'city' => $shippingAddress['city'] ?? '',
                    'province' => $shippingAddress['province'] ?? '',
                    'zip' => $shippingAddress['zip'] ?? '',
                    'country' => $shippingAddress['country'] ?? '',
                ];
            } else {
                $order->ship_to = [
                    'name' => trim($shippingAddress['name'] ?? ''),
                    'address1' => $shippingAddress['address1'] ?? '',
                    'city' => $shippingAddress['city'] ?? '',
                    'province' => $shippingAddress['province'] ?? '',
                    'zip' => $shippingAddress['zip'] ?? '',
                    'country' => $shippingAddress['country'] ?? '',
                ];
            }

            $order->bill_to = [
                'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                'address1' => $billingAddress['address1'] ?? '',
                'city' => $billingAddress['city'] ?? '',
                'province' => $billingAddress['province'] ?? '',
                'zip' => $billingAddress['zip'] ?? '',
                'country' => $billingAddress['country'] ?? '',
            ];

            return $order;
        })->sortByDesc('total_quantity')->values();

        // Create new batch
        $batch = DispenseBatch::create([
            'batch_number' => 'BATCH-' . now()->format('YmdHis') . '-' . Str::random(4),
            'user_id' => auth()->id(),
        ]);

        // Begin final PDF generation
        $s_path = "shippments_pdf/{$batch->batch_number}.pdf";
        $outputFile = public_path("storage/$s_path");

        // Ghostscript executable
        if (stripos(PHP_OS, 'WIN') === 0) {
            $exe = 'C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe';
        } else {
            $exe = '/usr/bin/gs';
        }

        $tempDir = storage_path("app/batch_{$batch->id}");
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $individualFiles = [];

        foreach ($processedOrders as $order) {
            // Render individual dispensing label PDF
            $pdfHtml = view('admin.dispenser.dispenselabel', [
                'processedOrders' => collect([$order]),
                'batch' => $batch
            ])->render();

            $dispensePath = "{$tempDir}/dispense_{$order->order_number}.pdf";
            PDF::loadHTML($pdfHtml)->setPaper('A4')->save($dispensePath);
            $individualFiles[] = $dispensePath;

            // Append shipping label if available
            if ($order->shipment_pdf_path) {
                $shippingPath = public_path(Storage::url($order->shipment_pdf_path));
                if (file_exists($shippingPath)) {
                    $individualFiles[] = $shippingPath;
                }
            }
        }

        // Merge all files using Ghostscript
        $escapedFiles = array_map('escapeshellarg', $individualFiles);
        $cmd = "\"$exe\" -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($outputFile) . " " . implode(' ', $escapedFiles);
        exec($cmd, $output, $returnCode);

        // Save path
        $batch->update(['shipment_pdf_path' => $s_path]);

        // Clean temp
        foreach ($individualFiles as $f) {
            @unlink($f);
        }
        @rmdir($tempDir);

        foreach ($processedOrders as $order) {
            OrderDispense::create([
                'order_id' => $order->order_number,
                'batch_id' => $batch->id,
                'dispensed_at' => now(),
                'reprint_count' => 0,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'dispensed',
                'order_id' => $order->order_number,
                'details' => 'Order dispensed by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y') . ' at ' . now()->format('H:i'),
            ]);

            $roleName = auth()->user()?->roles?->first()?->name ?? 'unknown';

            OrderAction::updateOrCreate(
                [
                    'order_id' => $order->order_number,
                    'user_id' => auth()->id(),
                ],
                [
                    'decision_status' => 'dispensed',
                    'decision_timestamp' => now(),
                    'role' => $roleName,
                ]
            );
        }

        $orderGIDsWithStoreIds = $processedOrders->map(function ($order) {
            return [
                'gid' => "gid://shopify/Order/{$order->order_number}",
                'shopify_order_id' => $order->order_number,
                'store_id' => $order->store_id,
            ];
        })->toArray();

        bulkAddShopifyTagsAndNotes($orderGIDsWithStoreIds, 'dispensed');
        // return redirect()->route('dispenser.batches.download', ['batch' => $batch->id]);

        return redirect()->route('dispenser.batches.list')->with('success', 'Dispensing PDF generated and ready to download');
    }



    function mergePdfs(array $pdfFiles, string $outputPath)
    {
        $pdf = new Fpdi();

        foreach ($pdfFiles as $filePath) {
            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        }

        $pdf->Output('F', $outputPath);
    }


    public function showQrData()
    {
        //     // return QrCode::generate(
        //     //     'Hello, World!',
        //     // );

        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->post('https://api.royalmail.net/shipping/v3/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => env('ROYALMAIL_CLIENT_ID'),
                    'client_secret' => env('ROYALMAIL_CLIENT_SECRET'),
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            dd($data['access_token']);
        } catch (\Exception $e) {
            \Log::error('Royal Mail Token Error: ' . $e->getMessage());
            dd("error");

            return null;
        }
    }

    public function listBatches(Request $request)
    {
        $query = DispenseBatch::query();

        if ($request->filled('search')) {
            $query->where('batch_number', 'like', '%' . $request->search . '%');
        }

        $batches = $query->latest()->paginate(config('Reading.nodes_per_page'));

        return view('admin.dispenser.batches.list', compact('batches'));
    }


    public function getRoyalMailToken()
    {
        try {
            $response = Http::withHeaders([
                'X-IBM-Client-Id' => env('ROYALMAIL_CLIENT_ID'),
                'X-IBM-Client-Secret' => env('ROYALMAIL_CLIENT_SECRET'),
                'X-RMG-Security-Username' => env('ROYALMAIL_USERNAME'),
                'X-RMG-Security-Password' => env('ROYALMAIL_PASSWORD'),
                'accept' => 'application/json',
            ])->post('https://api.royalmail.net/shipping/v3/token');

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }

            Log::error('Royal Mail token request failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Royal Mail Token Error: ' . $e->getMessage());
            return null;
        }
    }

    public function download($id)
    {
        $batch = DispenseBatch::findOrFail($id);
        $path = $batch->pdf_path ?? $batch->shipment_pdf_path;
        if (!$path || !Storage::disk('public')->exists($path)) {
            return redirect()->back()->with('error', 'PDF not found for this batch.');
        }

        return Storage::disk('public')->download($path);
    }


    function createRoyalMailShipment(string $authToken, $shipper, $shippingAddress, $billingAddress, $orderData, $orderId)
    {
        $items = $orderData['line_items'];
        $url = 'https://api.parcel.royalmail.com/api/v1/orders';

        // $data = [
        //     "items" => [
        //         [
        //             "orderReference" => "TEST-ORDER-002",
        //             "isRecipientABusiness" => false,
        //             "recipient" => [
        //                 "address" => [
        //                     "fullName" => "John Doe",
        //                     "companyName" => "",
        //                     "addressLine1" => "10 Downing Street",
        //                     "addressLine2" => "",
        //                     "addressLine3" => "",
        //                     "city" => "London",
        //                     "county" => "Greater London",
        //                     "postcode" => "SW1A 2AA",
        //                     "countryCode" => "GB"
        //                 ],
        //                 "phoneNumber" => "07000000000",
        //                 "emailAddress" => "john.doe@example.com",
        //                 "addressBookReference" => ""
        //             ],
        //             "sender" => [
        //                 "tradingName" => "Test Company Ltd",
        //                 "phoneNumber" => "07000000001",
        //                 "emailAddress" => "sender@example.com"
        //             ],
        //             "billing" => [
        //                 "address" => [
        //                     "fullName" => "Accounts Team",
        //                     "companyName" => "Test Company Ltd",
        //                     "addressLine1" => "123 Billing St",
        //                     "addressLine2" => "",
        //                     "addressLine3" => "",
        //                     "city" => "London",
        //                     "county" => "Greater London",
        //                     "postcode" => "E1 6AN",
        //                     "countryCode" => "GB"
        //                 ],
        //                 "phoneNumber" => "07000000002",
        //                 "emailAddress" => "accounts@example.com"
        //             ],
        //             "packages" => [
        //                 [
        //                     "weightInGrams" => 250,
        //                     "packageFormatIdentifier" => "Parcel",
        //                     "dimensions" => [
        //                         "heightInMms" => 120,
        //                         "widthInMms" => 200,
        //                         "depthInMms" => 50
        //                     ],
        //                     "contents" => [
        //                         [
        //                             "name" => "Test Product",
        //                             "SKU" => "TP-001",
        //                             "quantity" => 1,
        //                             "unitValue" => 19.99,
        //                             "unitWeightInGrams" => 250,
        //                             "customsDescription" => "Sample Product",
        //                             "extendedCustomsDescription" => "Sample Test Product",
        //                             "customsCode" => "8517620000",
        //                             "originCountryCode" => "GB",
        //                             "customsDeclarationCategory" => "none",
        //                             "requiresExportLicence" => false,
        //                             "stockLocation" => "WH1",
        //                             "useOriginPreference" => true,
        //                             "supplementaryUnits" => "1",
        //                             "licenseNumber" => "",
        //                             "certificateNumber" => ""
        //                         ]
        //                     ]
        //                 ]
        //             ],
        //             "orderDate" => "2025-07-08T10:00:00Z",
        //             "plannedDespatchDate" => "",
        //             "specialInstructions" => "Leave at porch if not home.",
        //             "subtotal" => 19.99,
        //             "shippingCostCharged" => 4.99,
        //             "otherCosts" => 0.00,
        //             "total" => 24.98,
        //             "currencyCode" => "GBP",
        //             "postageDetails" => [
        //                 "sendNotificationsTo" => "recipient",
        //                 "serviceCode" => "TPN24", // Tracked 24 – adjust to match your account
        //                 "serviceRegisterCode" => "",
        //                 "consequentialLoss" => 0,
        //                 "receiveEmailNotification" => true,
        //                 "receiveSmsNotification" => false,
        //                 "guaranteedSaturdayDelivery" => false,
        //                 "requestSignatureUponDelivery" => true,
        //                 "isLocalCollect" => false,
        //                 "safePlace" => "Porch",
        //                 "department" => null,
        //                 "AIRNumber" => "",
        //                 "IOSSNumber" => "",
        //                 "requiresExportLicense" => false,
        //                 "commercialInvoiceNumber" => "INV-TEST-001",
        //                 "commercialInvoiceDate" => "2025-07-08T10:00:00Z"
        //             ],
        //             "tags" => [],
        //             "label" => [
        //                 "includeLabelInResponse" => true,
        //                 "includeCN" => false,
        //                 "includeReturnsLabel" => false
        //             ],
        //             "orderTax" => 0.00,
        //             "containsDangerousGoods" => false
        //         ]
        //     ]
        // ];
        $data = [
            "items" => [
                [
                    "orderReference" => $orderId,
                    "isRecipientABusiness" => false,
                    "recipient" => [
                        "address" => [
                            "fullName" => $shippingAddress['name'],
                            "companyName" => $shippingAddress['company'],
                            "addressLine1" => $shippingAddress['address1'],
                            "addressLine2" => $shippingAddress['address2'],
                            "addressLine3" => "",
                            "city" => $shippingAddress['city'],
                            "county" => $shippingAddress['country'],
                            "postcode" => $shippingAddress['zip'],
                            "countryCode" => $shippingAddress['country_code']
                        ],
                        "phoneNumber" => $shippingAddress['phone'],
                        "emailAddress" => "",
                        "addressBookReference" => ""
                    ],
                    "sender" => [
                        "tradingName" => "Test Company Ltd",
                        "phoneNumber" => "07000000001",
                        "emailAddress" => "sender@example.com"
                    ],
                    "billing" => [
                        "address" => [
                            "fullName" => $billingAddress['name'],
                            "companyName" => $billingAddress['company'],
                            "addressLine1" => $billingAddress['address1'],
                            "addressLine2" => $billingAddress['address2'],
                            "addressLine3" => "",
                            "city" => $billingAddress['city'],
                            "county" => $billingAddress['country'],
                            "postcode" => $billingAddress['zip'],
                            "countryCode" => $billingAddress['country_code']
                        ],
                        "phoneNumber" => $billingAddress['phone'],
                        "emailAddress" => ""
                    ],
                    "packages" => [
                        [
                            "weightInGrams" => 250,
                            "packageFormatIdentifier" => "Parcel",
                            "dimensions" => [
                                "heightInMms" => 120,
                                "widthInMms" => 200,
                                "depthInMms" => 50
                            ],
                            "contents" => [
                                [
                                    "name" => "Test Product",
                                    "SKU" => "TP-001",
                                    "quantity" => 1,
                                    "unitValue" => 19.99,
                                    "unitWeightInGrams" => 250,
                                    "customsDescription" => "Sample Product",
                                    "extendedCustomsDescription" => "Sample Test Product",
                                    "customsCode" => "8517620000",
                                    "originCountryCode" => "GB",
                                    "customsDeclarationCategory" => "none",
                                    "requiresExportLicence" => false,
                                    "stockLocation" => "WH1",
                                    "useOriginPreference" => true,
                                    "supplementaryUnits" => "1",
                                    "licenseNumber" => "",
                                    "certificateNumber" => ""
                                ]
                            ]
                        ]
                    ],
                    "orderDate" => $orderData['created_at'],
                    "plannedDespatchDate" => "",
                    "specialInstructions" => "Leave at porch if not home.",
                    "subtotal" => 19.99,
                    "shippingCostCharged" => 4.99,
                    "otherCosts" => 0.00,
                    "total" => 24.98,
                    "currencyCode" => "GBP",
                    "postageDetails" => [
                        "sendNotificationsTo" => "recipient",
                        "serviceCode" => "TPN24", // Tracked 24 – adjust to match your account
                        "serviceRegisterCode" => "",
                        "consequentialLoss" => 0,
                        "receiveEmailNotification" => true,
                        "receiveSmsNotification" => false,
                        "guaranteedSaturdayDelivery" => false,
                        "requestSignatureUponDelivery" => true,
                        "isLocalCollect" => false,
                        "safePlace" => "Porch",
                        "department" => null,
                        "AIRNumber" => "",
                        "IOSSNumber" => "",
                        "requiresExportLicense" => false,
                        "commercialInvoiceNumber" => "INV-TEST-001",
                        "commercialInvoiceDate" => "2025-07-08T10:00:00Z"
                    ],
                    "tags" => [],
                    "label" => [
                        "includeLabelInResponse" => true,
                        "includeCN" => false,
                        "includeReturnsLabel" => false
                    ],
                    "orderTax" => 0.00,
                    "containsDangerousGoods" => false
                ]
            ]
        ];

        $response = Http::withToken($authToken)
            ->withHeaders([
                'Accept' => 'application/pdf',
                'Content-Type' => 'application/json',
            ])->post($url, $data);

        $responseData = $response->json();
        $createdOrder = $responseData['createdOrders'][0] ?? null;

        if ($createdOrder) {
            $trackingNumber = $createdOrder['trackingNumber'];
            $base64Pdf = $createdOrder['label'];

            // Decode Base64 PDF
            $pdfBinary = base64_decode($base64Pdf);

            // Generate file path
            $fileName = "{$trackingNumber}.pdf";
            $folder = 'shippments_pdf';
            $filePath = "{$folder}/{$fileName}";

            // Store PDF on public disk
            Storage::disk('public')->put($filePath, $pdfBinary);

            // Find your order by order number
            $order = Order::where('order_number', $orderId)->first();
            if ($order) {
                // Optional: Save PDF file path (if column exists)
                $order->shipment_pdf_path = $filePath;
                $order->trackingNumber = $trackingNumber;

                // Save entire shipment details JSON
                $order->shipment_details = $responseData;

                $order->save();
            }

            return [
                'trackingNumber' => $trackingNumber,
                'shipment_pdf_path' => $filePath,
            ];
        }
    }

    function createDHLShipment(string $authToken, $shipper, $destination, $orderData, $shippingDateAndTime, $orderId)
    {
        // $data = [
        //     "plannedShippingDateAndTime" => $shippingDateAndTime,
        //     "pickup" => [
        //         "isRequested" => false
        //     ],
        //     "productCode" => "I",
        //     "getRateEstimates" => false,
        //     "accounts" => [
        //         [
        //             "number" => "422890238",
        //             "typeCode" => "shipper"
        //         ]
        //     ],
        //     "valueAddedServices" => [
        //         [
        //             "serviceCode" => "IB",
        //             "value" => 10,
        //             "currency" => "GBP",
        //             "method" => "cash"
        //         ]
        //     ],
        //     "outputImageProperties" => [
        //         "printerDPI" => 300,
        //         "encodingFormat" => "pdf",
        //         "imageOptions" => [
        //             [
        //                 "typeCode" => "waybillDoc",
        //                 "templateName" => "ARCH_8x4",
        //                 "isRequested" => true,
        //                 "hideAccountNumber" => false,
        //                 "numberOfCopies" => 1
        //             ],
        //             [
        //                 "typeCode" => "label",
        //                 "templateName" => "ECOM26_84_001",
        //                 "isRequested" => true
        //             ]
        //         ],
        //         "splitTransportAndWaybillDocLabels" => true,
        //         "allDocumentsInOneImage" => false,
        //         "splitDocumentsByPages" => true,
        //         "splitInvoiceAndReceipt" => true,
        //         "receiptAndLabelsInOneImage" => false
        //     ],
        //     "customerDetails" => [
        //         "shipperDetails" => [
        //             "postalAddress" => [
        //                 "postalCode" => "EN3 7SN",
        //                 "cityName" => "Enfield",
        //                 "countryCode" => "GB",
        //                 "addressLine1" => "17",
        //                 "addressLine2" => "Suez Rd",
        //                 "countryName" => "UNITED KINGDOM"
        //             ],
        //             "contactInformation" => [
        //                 "email" => "shipper_create_shipmentapi@dhltestmail.com",
        //                 "phone" => "4972463",
        //                 "mobilePhone" => "2563456227231",
        //                 "companyName" => "DPR Wholesalers",
        //                 "fullName" => "Johnny Steward"
        //             ],
        //             "registrationNumbers" => [
        //                 [
        //                     "typeCode" => "VAT",
        //                     "number" => "244444911",
        //                     "issuerCountryCode" => "GB"
        //                 ]
        //             ],
        //             "typeCode" => "business"
        //         ],
        //         "receiverDetails" => [
        //             "postalAddress" => [
        //                 "postalCode" => "TW4 6FD",
        //                 "cityName" => "Hounslow",
        //                 "countryCode" => "GB",
        //                 "addressLine1" => "200",
        //                 "addressLine2" => "Great South-West Rd",
        //                 "addressLine3" => "McFarley Drive",
        //                 "countryName" => "UNITED KINGDOM"
        //             ],
        //             "contactInformation" => [
        //                 "email" => "recipient_create_shipmentapi@dhltestmail.com",
        //                 "phone" => "1123123",
        //                 "mobilePhone" => "256345123",
        //                 "companyName" => "DoCo Event Airline Catering",
        //                 "fullName" => "Hillary Dickins"
        //             ],
        //             "registrationNumbers" => [
        //                 [
        //                     "typeCode" => "VAT",
        //                     "number" => "12345678",
        //                     "issuerCountryCode" => "GB"
        //                 ]
        //             ],
        //             "typeCode" => "business"
        //         ]
        //     ],
        //     "content" => [
        //         "packages" => [
        //             [
        //                 "typeCode" => "2BP",
        //                 "weight" => 0.296,
        //                 "dimensions" => [
        //                     "length" => 1,
        //                     "width" => 1,
        //                     "height" => 1
        //                 ]
        //             ]
        //         ],
        //         "isCustomsDeclarable" => false,
        //         "description" => "Shipment Description",
        //         "incoterm" => "DAP",
        //         "unitOfMeasurement" => "metric"
        //     ],
        //     "getTransliteratedResponse" => false,
        //     "estimatedDeliveryDate" => [
        //         "isRequested" => false,
        //         "typeCode" => "QDDC"
        //     ],
        //     "getAdditionalInformation" => [
        //         [
        //             "typeCode" => "pickupDetails",
        //             "isRequested" => true
        //         ]
        //     ]
        // ];

        $maxLength = 45;

        // Your defaults
        $defaults = [
            'address1' => "200",
            'address2' => "Great South-West Rd",
            'address3' => "McFarley Drive",
        ];

        // Address 1
        $address1 = !empty($destination['address1'])
            ? $destination['address1']
            : $defaults['address1'];

        // Address 2 only if address1 exists
        if (!empty($address1)) {
            $address2 = !empty($destination['address2'])
                ? $destination['address2']
                : $defaults['address2'];
        } else {
            $address2 = '';
        }

        // Address 3 only if address2 exists
        if (!empty($address2)) {
            $address3 = !empty($destination['address3'])
                ? $destination['address3']
                : $defaults['address3'];
        } else {
            $address3 = '';
        }

        // Now process overflow
        $addresses = [$address1, $address2, $address3];

        for ($i = 0; $i < count($addresses); $i++) {
            if (strlen($addresses[$i]) > $maxLength) {
                $allowed = substr($addresses[$i], 0, $maxLength);
                $overflow = substr($addresses[$i], $maxLength);
                $addresses[$i] = $allowed;

                if (isset($addresses[$i + 1])) {
                    $addresses[$i + 1] = trim($overflow . ' ' . $addresses[$i + 1]);
                }
            }
        }



        $data = [
            "plannedShippingDateAndTime" => $shippingDateAndTime,
            "pickup" => [
                "isRequested" => false
            ],
            "productCode" => "D",
            "getRateEstimates" => false,
            "accounts" => [
                [
                    "number" => "422890238",
                    "typeCode" => "shipper"
                ]
            ],
            "valueAddedServices" => [
                [
                    "serviceCode" => "IB",
                    "value" => 10,
                    "currency" => "GBP",
                    "method" => "cash"
                ]
            ],
            "outputImageProperties" => [
                "printerDPI" => 300,
                "encodingFormat" => "pdf",
                "imageOptions" => [
                    [
                        "typeCode" => "shipmentReceipt",
                        "templateName" => "SHIPRCPT_EN_001",
                        // "isRequested" => true,
                        // "hideAccountNumber" => false,
                        // "numberOfCopies" => 1
                    ],
                    // [
                    //     "typeCode" => "label",
                    //     "templateName" => "ECOM26_84_001",
                    //     "isRequested" => true
                    // ]
                ],
                "splitTransportAndWaybillDocLabels" => true,
                "allDocumentsInOneImage" => false,
                "splitDocumentsByPages" => true,
                "splitInvoiceAndReceipt" => true,
                "receiptAndLabelsInOneImage" => false
            ],
            "customerDetails" => [
                "shipperDetails" => [
                    "postalAddress" => [
                        "postalCode" => $shipper['Postcode'],
                        "cityName" => $shipper['Town'],
                        "countryCode" => $shipper['CountryCode'],
                        "addressLine1" => $shipper['AddressLine1'],
                        "addressLine2" => $shipper['AddressLine1'],
                        "countryName" => $shipper['County'],
                    ],
                    "contactInformation" => [
                        "email" => $shipper['EmailAddress'],
                        "phone" => $shipper['PhoneNumber'],
                        "mobilePhone" => "2563456227231",
                        "companyName" => $shipper['name'], //"DPR Wholesalers"
                        "fullName" => $shipper['ContactName'] //Johnny Steward
                    ],
                    "registrationNumbers" => [
                        [
                            "typeCode" => "VAT",
                            "number" => $shipper['VatNumber'],
                            "issuerCountryCode" => !empty($shipper['country_code']) ? $shipper['country_code'] : "GB",
                        ]
                    ],
                    "typeCode" => "business"
                ],
                "receiverDetails" => [
                    "postalAddress" => [
                        "postalCode" => !empty($destination['zip']) ? $destination['zip'] : "TW4 6FD",
                        "cityName" => !empty($destination['city']) ? $destination['city'] : "Hounslow",
                        "countryCode" => !empty($destination['country_code']) ? $destination['country_code'] : "GB",
                        "addressLine1" => $addresses[0],
                        "addressLine2" => $addresses[1],
                        "addressLine3" => $addresses[2],
                        "countryName" => !empty($destination['country']) ? $destination['country'] : "UNITED KINGDOM"
                    ],
                    "contactInformation" => [
                        "email" => $orderData['customer']['email'] ?? "recipient_create_shipmentapi@dhltestmail.com",
                        "phone" => $destination['phone'] ?? "1123123",
                        "mobilePhone" => "256345123",
                        "companyName" =>  $destination['company'] ?? "DoCo Event Airline Catering",
                        "fullName" => $destination['name'] ?? "Hillary Dickins"
                    ],
                    "registrationNumbers" => [
                        [
                            "typeCode" => "VAT",
                            "number" => "12345678",
                            "issuerCountryCode" => "GB"
                        ]
                    ],
                    "typeCode" => "business"
                ]
            ],
            "content" => [
                "packages" => [
                    [
                        "typeCode" => "2BP",
                        "weight" => 0.296,
                        "dimensions" => [
                            "length" => 1,
                            "width" => 1,
                            "height" => 1
                        ]
                    ]
                ],
                "isCustomsDeclarable" => false,
                "description" => "Shipment Description",
                "incoterm" => "DAP",
                "unitOfMeasurement" => "metric"
            ],
            "getTransliteratedResponse" => false,
            "estimatedDeliveryDate" => [
                "isRequested" => false,
                "typeCode" => "QDDC"
            ],
            "getAdditionalInformation" => [
                [
                    "typeCode" => "pickupDetails",
                    "isRequested" => true
                ]
            ]
        ];

        //     'Plugin-Version' => 'SOME_STRING_VALUE',
        //     'Shipping-System-Platform-Name' => 'SOME_STRING_VALUE',
        //     'Shipping-System-Platform-Version' => 'SOME_STRING_VALUE',
        //     'Webstore-Platform-Name' => 'SOME_STRING_VALUE',
        //     'Webstore-Platform-Version' => 'SOME_STRING_VALUE',

        $response = Http::withHeaders([
            'content-type' => 'application/json',
            'Message-Reference' => 'd0e7832e-5c98-11ea-bc55-0242ac13',
            'Message-Reference-Date' => $shippingDateAndTime,
            'x-version' => '2.12.0',
            'Authorization' => $authToken,
        ])->post('https://express.api.dhl.com/mydhlapi/test/shipments', $data);


        if (!$response->json()) {
            return response()->json(['error' => 'DHL API failed', 'details' => $response], 400);
        }

        $responseData = $response->json();
       
        $trackingNumber = $responseData['packages'][0]['trackingNumber'] ?? 'no-tracking';

        $pdfContentCombined = '';
        foreach ($responseData['documents'] as $doc) {
            if ($doc['imageFormat'] === 'PDF') {
                $pdfContentCombined .= base64_decode($doc['content']);
            }
        }

        // Save the PDF to storage
        $fileName = "{$trackingNumber}.pdf";
        $folder = 'shippments_pdf';

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $filePath = "{$folder}/{$fileName}";

        Storage::disk('public')->put($filePath, $pdfContentCombined);
        $order = Order::where('order_number', $orderId)->first();

        // Optional: Save PDF path to order if needed
        $order->shipment_pdf_path = $filePath; // Only if you have this column
        $order->trackingNumber = $trackingNumber;
        // STEP 3: Save full shipment details in DB (as JSON)
        $order->shipment_details = $responseData;
        $order->save();

        return [
            'trackingNumber' => $trackingNumber,
            'shipment_pdf_path' => $filePath,
        ];
    }

    public function incrementReprint($id)
    {
        $user = auth()->user();
        $batchItems = OrderDispense::where('batch_id', $id)->get();

        if ($batchItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Batch not found'], 404);
        }

        // Check if already printed
        $alreadyPrinted = $batchItems->firstWhere('reprint_count', '>=', 1);

        if ($alreadyPrinted) {
            return response()->json([
                'success' => false,
                'message' => 'This batch has already been printed. Please contact the admin.'
            ], 403);
        }

        // Increment reprint_count for each item
        foreach ($batchItems as $item) {
            $item->increment('reprint_count');
        }

        // Log audit
        AuditLog::create([
            'user_id'   => $user->id,
            'order_id'  => $id, // As requested: using batch_id in order_id column
            'action'    => 'batch_print',
            'details'   => "Order dispensed by {$user->name} on " . now()->format('d/m/Y \a\t H:i') . ".",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch reprint count updated. Opening PDF for print...'
        ]);
    }
}
