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
use Carbon\Carbon;



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


            $authToken = '';
            $shipper = Store::where('id',$order->store_id)->first()->toArray();
           
            $destination = $shippingAddress;

            // $response = Http::withHeaders([
            //     'Authorization' => 'f3e7618c-d590-4e85-9246-1c39fcefd4f2',
            //     'Content-Type' => 'application/json',
            // ])->post(
            //     'https://api.parcel.royalmail.com/api/v1/Orders');

            // dd([
            //     'status' => $response->status(),
            //     'body' => $response->body(),
            // ]);
            // if(isset($shippingAddress) && isset($shippingAddress['country']) && $shippingAddress['country'] == "United Kingdom" && $shippingCode != 'rightangled hq' && $shippingCode != 'local delivery'){
            //     // For UK Shippment
            //     $response = $this->createRoyalMailShipment($authToken, $shipper, $destination, $orderData);
            // }elseif(isset($shippingAddress) && isset($shippingAddress['country']) && $shippingAddress['country'] != "United Kingdom"){
            //     // For International Shippment

            $shippingDateAndTime = Carbon::now('Europe/Berlin')
                ->addDay()
                ->format('Y-m-d\TH:i:s \G\M\TP');
            $response = $this->createDHLShipment($authToken, $shipper, $destination, $orderData, $shippingDateAndTime, $order->order_number);

            // }

            // Sort items by quantity (descending)
            $lineItems = collect($orderData['line_items'] ?? [])->map(function ($item) use ($order) {
                $productId = $item['product_id'] ?? null;
                $item['direction_of_use'] = $productId ? getProductMetafield($productId, $order->order_number) : 'N/A';
                $item['direction_of_use'] = $productId ? getProductMetafield($productId, $order->order_number) : 'N/A';
                return $item;
            })->sortByDesc('quantity')->values();

            $order->order_data = $orderData;
            $order->line_items = $lineItems;
            $order->total_quantity = $lineItems->sum('quantity');

            // Determine slip type
            if ($shippingCode === 'rightangled hq') {
                $order->slip_type = 'hq';
            } elseif ($shippingCode === 'local delivery') {
                $order->slip_type = 'local';
            } else {
                $order->slip_type = 'other';
            }

            // Set ship_to
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

            // Bill to (same for all)
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
        // dd($processedOrders);

        // Create new batch
        $batch = DispenseBatch::create([
            'batch_number' => 'BATCH-' . now()->format('YmdHis') . '-' . Str::random(4),
            'user_id' => auth()->id(),
        ]);

        $pdfHtml = view('admin.dispenser.dispenselabel', compact('processedOrders', 'batch'))->render();
        $pdf = PDF::loadHTML($pdfHtml)->setPaper('A4');
        $fileName = "{$batch->batch_number}.pdf";
        $filePath = "dispense_batches/{$fileName}";
        Storage::disk('public')->put($filePath, $pdf->output());


        // Merge shipping label pdf 
        $first_path = public_path(Storage::url($filePath));
        $s_path = "shippments_pdf/{$batch->batch_number}.pdf";
        $outputFile = public_path("storage/$s_path");

        $second_path = [];
        foreach ($orders as $key => $value) {
            $details = Order::where('order_number', $value['order_number'])->first();
            if ($details && $details->shipment_pdf_path) {
                $second_path[] = public_path(Storage::url($details->shipment_pdf_path));
            }
        }

        if(isset($second_path) && is_array($second_path)){
            // Detect OS
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows path
                $exe = 'C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe';
            } else {
                // Linux path on cPanel
                $exe = '/usr/bin/gs';
            }
            // $exe = 'C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe';

            $allFiles = array_merge([$first_path], $second_path);
            $escapedFiles = array_map('escapeshellarg', $allFiles);

            $cmd = "\"$exe\" -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="
                . escapeshellarg($outputFile) . " "
                . implode(' ', $escapedFiles);

            exec($cmd, $output, $returnCode);
            $batch->update(['shipment_pdf_path' => $s_path]);
        }else{
            $batch->update(['pdf_path' => $filePath]);
        }
       


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

            // Step 4: Log or update order decision
            OrderAction::updateOrCreate(
                [
                    'order_id' => $order->order_number, // Assuming this links to Order.id (not order_number)
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
                'store_id' => $order->store_id, // assuming you store this
            ];
        })->toArray();

        bulkAddShopifyTagsAndNotes($orderGIDsWithStoreIds, 'dispensed');

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


    function createRoyalMailShipment(string $authToken, $shipper, $destination, $orderData)
    {
        $items = $orderData['line_items'];
        $url = 'https://api.royalmail.net/shipping/v3/shipments';
     
        $shipmentData = [
            "Shipper" => [
                "AddressId" => $shipper['AddressId'] ?? '',
                "ShipperReference" => $shipper['ShipperReference'] ?? '',
                "ShipperReference2" => $shipper['ShipperReference2'] ?? '',
                "ShipperDepartment" => $shipper['ShipperDepartment'] ?? '',
                "CompanyName" => $shipper['name'] ?? '',
                "ContactName" => $shipper['ContactName'] ?? '',
                "AddressLine1" => $shipper['AddressLine1'] ?? '',
                "AddressLine2" => $shipper['AddressLine1'] ?? '',
                "AddressLine3" => $shipper['AddressLine1'] ?? '',
                "Town" => $shipper['Town'] ?? '',
                "County" => $shipper['County'] ?? '',
                "CountryCode" => $shipper['CountryCode'] ?? '',
                "Postcode" => $shipper['Postcode'] ?? '',
                "PhoneNumber" => $shipper['PhoneNumber'] ?? '',
                "EmailAddress" => $shipper['EmailAddress'] ?? '',
                "VatNumber" => $shipper['VatNumber'] ?? '',
            ],
            "Destination" => [
                // "AddressId" => "UNIQUEID123",
                "CompanyName" => $destination['company'] ?? '',
                "ContactName" => $destination['name'] ?? '',
                "AddressLine1" => $destination['address1'] ?? '',
                "AddressLine2" => $destination['address2'] ?? '',
                "AddressLine3" => $destination['address3'] ?? '',
                "Town" => $destination['city'] ?? '',
                "County" => $destination['country'] ?? '',
                "CountryCode" => $destination['country_code'] ?? '',
                "Postcode" => $destination['zip'] ?? '',
                "PhoneNumber" => $destination['phone'] ?? '',
                "EmailAddress" => $orderData['customer']['email'] ?? '',
                // "VatNumber" => $destination[''] ?? '',
            ],
            "ShipmentInformation" => [
                "ShipmentDate" => "2025-06-20",
                "ServiceCode" => "TPLN",
                "ServiceOptions" => [
                    "PostingLocation" => "123456789",
                    "ServiceLevel" => "01",
                    "ServiceFormat" => "P",
                    "Safeplace" => "Front Porch",
                    "SaturdayGuaranteed" => false,
                    "ConsequentialLoss" => "Level4",
                    "LocalCollect" => false,
                    "TrackingNotifications" => "EmailAndSMS",
                    "RecordedSignedFor" => false
                ],
                "TotalPackages" => 1,
                "TotalWeight" => $orderData['total_weight'] ?? 0,
                "WeightUnitOfMeasure" => "G",
                "Product" => "NDX",
                "DescriptionOfGoods" => "Clothing",
                "ReasonForExport" => "Sale of goods",
                "Value" => $orderData['current_subtotal_price'] ?? '',
                "Currency" => $orderData['currency'] ?? '',
                "Incoterms" => "DDU",
                "LabelFormat" => "PDF",
                "SilentPrintProfile" => "75b59db8-3cd3-4578-888e-54be016f07cc",
                "ShipmentAction" => "Process",
                "Packages" => [
                    [
                        "PackageOccurrence" => 1,
                        "PackagingId" => "UNIQUEID123",
                        "Weight" => 2.2,
                        "Length" => 15,
                        "Width" => 15,
                        "Height" => 5
                    ]
                ],
                "Items" => [
                    [
                        "ItemId" => $items['id'] ?? '',
                        "Quantity" => $items['quantity'] ?? '',
                        "Description" => $items[''] ?? '',
                        "Value" => $items['price'] ?? '',
                        "Weight" => $items['grams'] ?? '',
                        "PackageOccurrence" => $items[''] ?? '',
                        "HsCode" => $items[''] ?? '',
                        "SkuCode" => $items['sku'] ?? '',
                        "CountryOfOrigin" => $items[''] ?? '',
                        "ImageUrl" => "http://www.myimagestore.com/myimage.jpg"
                    ]
                ]
            ],
            "CustomsInformation" => [
                "PreRegistrationNumber" => "GB13132313",
                "PreRegistrationType" => "EORI",
                "ShippingCharges" => $orderData['shipping_lines']['price'] ?? '',
                "OtherCharges" => "0.00",
                "QuotedLandedCost" => "0.00",
                "InvoiceNumber" => "1234567890",
                "InvoiceDate" => "2020-12-31",
                "ExportLicence" => false,
                "AddresseeIdentificationReferenceNumber" => "1234567890"
            ]
        ];

        $response = Http::withHeaders([
            'X-RMG-Auth-Token' => $authToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($url, [
            'shipment' => $shipmentData
        ]);

        if ($response->successful()) {
            return $response->json(); // success response
        } else {
            // Handle error (log or throw)
            throw new \Exception("Royal Mail API Error: " . $response->body());
        }
    }

    function createDHLShipment(string $authToken, $shipper, $destination, $orderData, $shippingDateAndTime, $orderId)
    {
        // $payload = [
        //     "plannedShippingDateAndTime" => $shippingDateAndTime,
        //     "pickup" => [
        //         "isRequested" => false
        //     ],
        //     "productCode" => "P",
        //     "localProductCode" => "P",
        //     "getRateEstimates" => false,
        //     "accounts" => [
        //         [
        //             "typeCode" => "shipper",
        //             "number" => "422890238"
        //         ]
        //     ],
        //     "valueAddedServices" => [
        //         [
        //             "serviceCode" => "II",
        //             "value" => 10,
        //             "currency" => "USD"
        //         ]
        //     ],
        //     "outputImageProperties" => [
        //         "printerDPI" => 300,
        //         "encodingFormat" => "pdf",
        //         "imageOptions" => [
        //             [
        //                 "typeCode" => "invoice",
        //                 "templateName" => "COMMERCIAL_INVOICE_P_10",
        //                 "isRequested" => true,
        //                 "invoiceType" => "commercial",
        //                 "languageCode" => "eng",
        //                 "languageCountryCode" => "US"
        //             ],
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
        //                 "renderDHLLogo" => true,
        //                 "fitLabelsToA4" => false
        //             ]
        //         ],
        //         "splitTransportAndWaybillDocLabels" => true,
        //         "allDocumentsInOneImage" => false,
        //         "splitDocumentsByPages" => false,
        //         "splitInvoiceAndReceipt" => true,
        //         "receiptAndLabelsInOneImage" => false
        //     ],
        //        "AddressId" => $shipper['AddressId'] ?? '',
        //         "ShipperReference" => $shipper['ShipperReference'] ?? '',
        //         "ShipperReference2" => $shipper['ShipperReference2'] ?? '',
        //         "ShipperDepartment" => $shipper['ShipperDepartment'] ?? '',
        //         "CompanyName" => $shipper['name'] ?? '',
        //         "ContactName" => $shipper['ContactName'] ?? '',
        //         "AddressLine1" => $shipper['AddressLine1'] ?? '',
        //         "AddressLine2" => $shipper['AddressLine1'] ?? '',
        //         "AddressLine3" => $shipper['AddressLine1'] ?? '',
        //         "Town" => $shipper['Town'] ?? '',
        //         "County" => $shipper['County'] ?? '',
        //         "CountryCode" => $shipper['CountryCode'] ?? '',
        //         "Postcode" => $shipper['Postcode'] ?? '',
        //         "PhoneNumber" => $shipper['PhoneNumber'] ?? '',
        //         "EmailAddress" => $shipper['EmailAddress'] ?? '',
        //         "VatNumber" => $shipper['VatNumber'] ?? '',

        //     "customerDetails" => [
        //         "shipperDetails" => [
        //             "postalAddress" => [
        //                 "postalCode" => $shipper['Postcode'] ?? '',
        //                 "cityName" => "Zhaoqing",
        //                 "countryCode" => $shipper['CountryCode'] ?? '',
        //                 "addressLine1" => $shipper['AddressLine1'] ?? '',
        //                 "addressLine2" => $shipper['AddressLine2'] ?? '',
        //                 "addressLine3" => $shipper['AddressLine3'] ?? '',
        //                 "countyName" => $shipper['County'] ?? '',
        //                 "countryName" => $shipper['County'] ?? '',
        //             ],
        //             "contactInformation" => [
        //                 "email" => $shipper['EmailAddress'] ?? '',
        //                 "phone" => $shipper['PhoneNumber'] ?? '',
        //                 "mobilePhone" => "18211309039",
        //                 "companyName" => $shipper['name'] ?? '',
        //                 "fullName" => $shipper['ContactName'] ?? '',
        //             ],
        //             "registrationNumbers" => [
        //                 [
        //                     "typeCode" => "SDT",
        //                     "number" => "CN123456789",
        //                     "issuerCountryCode" => "CN"
        //                 ]
        //             ],
        //             "bankDetails" => [
        //                 [
        //                     "name" => "Bank of China",
        //                     "settlementLocalCurrency" => "RMB",
        //                     "settlementForeignCurrency" => "USD"
        //                 ]
        //             ],
        //             "typeCode" => "business"
        //         ],
        //         "receiverDetails" => [
        //             "postalAddress" => [
        //                 "cityName" => $destination['city'] ?? '',
        //                 "countryCode" => $destination['country_code'] ?? '',
        //                 "postalCode" => $destination['zip'] ?? '',
        //                 "addressLine1" => $destination['address1'] ?? '',
        //                 "countryName" => $destination['country'] ?? '',
        //             ],
        //             "contactInformation" => [
        //                 "email" => $orderData['customer']['email'] ?? '',
        //                 "phone" =>  $destination['phone'] ?? '',
        //                 "mobilePhone" => "9402825666",
        //                 "companyName" => $destination['company'] ?? '',
        //                 "fullName" => $destination['name'] ?? '',
        //             ],
        //             "registrationNumbers" => [
        //                 [
        //                     "typeCode" => "SSN",
        //                     "number" => "US123456789",
        //                     "issuerCountryCode" => "US"
        //                 ]
        //             ],
        //             "bankDetails" => [
        //                 [
        //                     "name" => "Bank of America",
        //                     "settlementLocalCurrency" => "USD",
        //                     "settlementForeignCurrency" => "USD"
        //                 ]
        //             ],
        //             "typeCode" => "business"
        //         ]
        //     ],
        //     "content" => [
        //         "packages" => [
        //             [
        //                 "typeCode" => "2BP",
        //                 "weight" => 0.5,
        //                 "dimensions" => [
        //                     "length" => 1,
        //                     "width" => 1,
        //                     "height" => 1
        //                 ],
        //                 "customerReferences" => [
        //                     [
        //                         "value" => "3654673",
        //                         "typeCode" => "CU"
        //                     ]
        //                 ],
        //                 "description" => "Piece content description",
        //                 "labelDescription" => "bespoke label description"
        //             ]
        //         ],
        //         "isCustomsDeclarable" => true,
        //         "declaredValue" => 120,
        //         "declaredValueCurrency" => "USD",
        //         "exportDeclaration" => [
        //             "lineItems" => [
        //                 [
        //                     "number" => 1,
        //                     "description" => "Harry Steward biography first edition",
        //                     "price" => 15,
        //                     "quantity" => [
        //                         "value" => 4,
        //                         "unitOfMeasurement" => "GM"
        //                     ],
        //                     "commodityCodes" => [
        //                         ["typeCode" => "outbound", "value" => "84713000"],
        //                         ["typeCode" => "inbound", "value" => "5109101110"]
        //                     ],
        //                     "exportReasonType" => "permanent",
        //                     "manufacturerCountry" => "US",
        //                     "exportControlClassificationNumber" => "US123456789",
        //                     "weight" => ["netValue" => 0.1, "grossValue" => 0.7],
        //                     "isTaxesPaid" => true,
        //                     "additionalInformation" => ["450pages"],
        //                     "customerReferences" => [["typeCode" => "AFE", "value" => "1299210"]],
        //                     "customsDocuments" => [["typeCode" => "COO", "value" => "MyDHLAPI - LN#1-CUSDOC-001"]]
        //                 ],
        //                 [
        //                     "number" => 2,
        //                     "description" => "Andromeda Chapter 394 - Revenge of Brook",
        //                     "price" => 15,
        //                     "quantity" => [
        //                         "value" => 4,
        //                         "unitOfMeasurement" => "GM"
        //                     ],
        //                     "commodityCodes" => [
        //                         ["typeCode" => "outbound", "value" => "6109100011"],
        //                         ["typeCode" => "inbound", "value" => "5109101111"]
        //                     ],
        //                     "exportReasonType" => "permanent",
        //                     "manufacturerCountry" => "US",
        //                     "exportControlClassificationNumber" => "US123456789",
        //                     "weight" => ["netValue" => 0.1, "grossValue" => 0.7],
        //                     "isTaxesPaid" => true,
        //                     "additionalInformation" => ["36pages"],
        //                     "customerReferences" => [["typeCode" => "AFE", "value" => "1299211"]],
        //                     "customsDocuments" => [["typeCode" => "COO", "value" => "MyDHLAPI - LN#1-CUSDOC-001"]]
        //                 ]
        //             ],
        //             "invoice" => [
        //                 "number" => "2667168671",
        //                 "date" => "2022-10-22",
        //                 "instructions" => ["Handle with care"],
        //                 "totalNetWeight" => 0.4,
        //                 "totalGrossWeight" => 0.5,
        //                 "customerReferences" => [
        //                     ["typeCode" => "UCN", "value" => "UCN-783974937"],
        //                     ["typeCode" => "CN", "value" => "CUN-76498376498"],
        //                     ["typeCode" => "RMA", "value" => "MyDHLAPI-TESTREF-001"]
        //                 ],
        //                 "termsOfPayment" => "100 days",
        //                 "indicativeCustomsValues" => [
        //                     "importCustomsDutyValue" => 150.57,
        //                     "importTaxesValue" => 49.43
        //                 ]
        //             ],
        //             "remarks" => [["value" => "Right side up only"]],
        //             "additionalCharges" => [
        //                 ["value" => 10, "caption" => "fee", "typeCode" => "freight"],
        //                 ["value" => 20, "caption" => "freight charges", "typeCode" => "other"],
        //                 ["value" => 10, "caption" => "ins charges", "typeCode" => "insurance"],
        //                 ["value" => 7, "caption" => "rev charges", "typeCode" => "reverse_charge"]
        //             ],
        //             "destinationPortName" => "New York Port",
        //             "placeOfIncoterm" => "ShenZhen Port",
        //             "payerVATNumber" => "12345ED",
        //             "recipientReference" => "01291344",
        //             "exporter" => ["id" => "121233", "code" => "S"],
        //             "packageMarks" => "Fragile glass bottle",
        //             "declarationNotes" => [["value" => "up to three declaration notes"]],
        //             "exportReference" => "export reference",
        //             "exportReason" => "export reason",
        //             "exportReasonType" => "permanent",
        //             "licenses" => [["typeCode" => "export", "value" => "123127233"]],
        //             "shipmentType" => "personal",
        //             "customsDocuments" => [["typeCode" => "INV", "value" => "MyDHLAPI - CUSDOC-001"]]
        //         ],
        //         "description" => "Shipment",
        //         "USFilingTypeValue" => "12345",
        //         "incoterm" => "DAP",
        //         "unitOfMeasurement" => "metric"
        //     ],
        //     "shipmentNotification" => [
        //         [
        //             "typeCode" => "email",
        //             "receiverId" => "shipmentnotification@mydhlapisample.com",
        //             "languageCode" => "eng",
        //             "languageCountryCode" => "UK",
        //             "bespokeMessage" => "message to be included in the notification"
        //         ]
        //     ],
        //     "getTransliteratedResponse" => false,
        //     "estimatedDeliveryDate" => [
        //         "isRequested" => true,
        //         "typeCode" => "QDDC"
        //     ],
        //     "getAdditionalInformation" => [
        //         [
        //             "typeCode" => "pickupDetails",
        //             "isRequested" => true
        //         ]
        //     ]
        // ];

        $data = [
            "plannedShippingDateAndTime" => $shippingDateAndTime,
            "pickup" => [
                "isRequested" => false
            ],
            "productCode" => "I",
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
                        "typeCode" => "waybillDoc",
                        "templateName" => "ARCH_8x4",
                        "isRequested" => true,
                        "hideAccountNumber" => false,
                        "numberOfCopies" => 1
                    ],
                    [
                        "typeCode" => "label",
                        "templateName" => "ECOM26_84_001",
                        "isRequested" => true
                    ]
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
                        "postalCode" => "EN3 7SN",
                        "cityName" => "Enfield",
                        "countryCode" => "GB",
                        "addressLine1" => "17",
                        "addressLine2" => "Suez Rd",
                        "countryName" => "UNITED KINGDOM"
                    ],
                    "contactInformation" => [
                        "email" => "shipper_create_shipmentapi@dhltestmail.com",
                        "phone" => "4972463",
                        "mobilePhone" => "2563456227231",
                        "companyName" => "DPR Wholesalers",
                        "fullName" => "Johnny Steward"
                    ],
                    "registrationNumbers" => [
                        [
                            "typeCode" => "VAT",
                            "number" => "244444911",
                            "issuerCountryCode" => "GB"
                        ]
                    ],
                    "typeCode" => "business"
                ],
                "receiverDetails" => [
                    "postalAddress" => [
                        "postalCode" => "TW4 6FD",
                        "cityName" => "Hounslow",
                        "countryCode" => "GB",
                        "addressLine1" => "200",
                        "addressLine2" => "Great South-West Rd",
                        "addressLine3" => "McFarley Drive",
                        "countryName" => "UNITED KINGDOM"
                    ],
                    "contactInformation" => [
                        "email" => "recipient_create_shipmentapi@dhltestmail.com",
                        "phone" => "1123123",
                        "mobilePhone" => "256345123",
                        "companyName" => "DoCo Event Airline Catering",
                        "fullName" => "Hillary Dickins"
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
            'Authorization' => 'Basic ' . base64_encode('apX2aQ3yA3kF3p:J^9kM@8nD@8pS@1y'),
        ])->post('https://express.api.dhl.com/mydhlapi/test/shipments', $data);


        if (!$response->json()) {
            return response()->json(['error' => 'DHL API failed', 'details' => $response], 400);
        }

        $responseData = $response->json();

        $shipmentTrackingNumber = $responseData['shipmentTrackingNumber'] ?? 'no-tracking';

        $pdfContentCombined = '';
        foreach ($responseData['documents'] as $doc) {
            if ($doc['imageFormat'] === 'PDF') {
                $pdfContentCombined .= base64_decode($doc['content']);
            }
        }

        // Save the PDF to storage
        $fileName = "{$shipmentTrackingNumber}.pdf";
        $folder = 'shippments_pdf';

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $filePath = "{$folder}/{$fileName}";

        Storage::disk('public')->put($filePath, $pdfContentCombined);
        $order = Order::where('order_number', $orderId)->first();

        // Optional: Save PDF path to order if needed
        $order->shipment_pdf_path = $filePath; // Only if you have this column

        // STEP 3: Save full shipment details in DB (as JSON)
        $order->shipment_details = $responseData;
        $order->save();

        return response()->json([
            'message' => 'Shipment created and saved successfully!',
            'shipment_tracking' => $shipmentTrackingNumber,
            'pdf_path' => $filePath,
        ]);
    }
}
