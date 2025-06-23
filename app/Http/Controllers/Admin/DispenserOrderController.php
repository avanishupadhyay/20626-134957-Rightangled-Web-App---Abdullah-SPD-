<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use App\Models\AuditLog;
use App\Models\OrderAction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderDispense;
use App\Models\DispenseBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Milon\Barcode\DNS2D;



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

        $approvedOrderNumbers = OrderAction::latest('created_at')
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
        $orderMetafields = getOrderMetafields($order->order_number) ?? null;

        $orderData = json_decode($order->order_data, true);

        return view('admin.dispenser.view', compact('order', 'orderData', 'orderMetafields'));
    }

    //original code old
    // public function printDispenseBatch(Request $request)
    // {
    //     $request->validate([
    //         'order_ids' => 'required|array|min:1',
    //     ]);

    //     $orderNumbers = $request->order_ids;

    //     // Fetch orders
    //     $orders = Order::whereIn('order_number', $orderNumbers)->get();

    //     // Process and sort orders
    //     $processedOrders = $orders->map(function ($order) {
    //         $orderData = json_decode($order->order_data, true);
    //         $lineItems = collect($orderData['line_items'] ?? [])->map(function ($item) {
    //             $productId = $item['product_id'] ?? null;
    //             $item['direction_of_use'] = $productId ? getProductMetafield($productId) : 'N/A';
    //             return $item;
    //         })
    //             ->sortByDesc('quantity') // Highest quantity comes first
    //             ->values();

    //         $order->order_data = $orderData;
    //         $order->line_items = $lineItems;
    //         $order->total_quantity = $lineItems->sum('quantity');

    //         return $order;
    //     })
    //         ->sortByDesc('total_quantity') // Orders with more total quantity come first
    //         ->values();
    //     // Create Dispense Batch
    //     $batch = DispenseBatch::create([
    //         'batch_number' => 'BATCH-' . now()->format('YmdHis') . '-' . Str::random(4),
    //         'user_id' => auth()->id(),
    //     ]);

    //     // Generate PDF
    //     $pdfHtml = view('admin.dispenser.dispenselabel', compact('processedOrders', 'batch'))->render();
    //     $pdf = PDF::loadHTML($pdfHtml)->setPaper('A4');


    //     $fileName = "{$batch->batch_number}.pdf";
    //     $filePath = "dispense_batches/{$fileName}";

    //     Storage::disk('public')->put($filePath, $pdf->output());
    //     $batch->update(['pdf_path' => $filePath]);

    //     // $batch->update(['pdf_path' => "storage/{$pdfFileName}"]);

    //     // Log in OrderDispense
    //     foreach ($processedOrders as $order) {
    //         OrderDispense::create([
    //             'order_id' => $order->order_number,
    //             'batch_id' => $batch->id,
    //             'dispensed_at' => now(),
    //             'reprint_count' => 0,
    //         ]);
    //         AuditLog::create([
    //             'user_id' => auth()->id(),
    //             'action' => 'dispensed',
    //             'order_id' => $order->order_number,
    //             'details' => 'Order dispensed by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y') . ' at ' . now()->format('H:i'),
    //         ]);
    //     }

    //     // Step 4: Log


    //     return redirect()->route('dispenser_orders.index')->with('success', 'Dispensing PDF generated and saved.');
    // }

    //code 19-06

    public function printDispenseBatch(Request $request)
    {
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
            $shipper = (array) DB::table('stores')->first();

            $destination = $shippingAddress;
            // $response = $this->createRoyalMailShipment($authToken, $shipper, $destination, $orderData);

            // pr($orderData);die;
            // Sort items by quantity (descending)
            $lineItems = collect($orderData['line_items'] ?? [])->map(function ($item) use($order) {
                $productId = $item['product_id'] ?? null;
                $item['direction_of_use'] = $productId ? getProductMetafield($productId,$order->order_number) : 'N/A';
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
        // Generate PDF
        $pdfHtml = view('admin.dispenser.dispenselabel', compact('processedOrders', 'batch'))->render();
        $pdf = PDF::loadHTML($pdfHtml)->setPaper('A4');

        $fileName = "{$batch->batch_number}.pdf";
        $filePath = "dispense_batches/{$fileName}";

        Storage::disk('public')->put($filePath, $pdf->output());
        $batch->update(['pdf_path' => $filePath]);

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
            \App\Models\OrderAction::updateOrCreate(
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

        // $orderGIDs = $processedOrders->pluck('order_number')->map(fn($id) => "gid://shopify/Order/{$id}")->toArray();
        // bulkAddShopifyTags($orderGIDs, 'dispensed',$order->id);


        // return $pdf->stream("{$batch->batch_number}.pdf"); // force download
        // return view('admin.dispenser.dispenselabel', compact('processedOrders', 'batch'));

        return redirect()->route('dispenser.batches.list')->with('success', 'Dispensing PDF generated and ready to download');
    }


    // public function printDispenseBatch(Request $request)
    // {
    //     $request->validate([
    //         'order_ids' => 'required|array|min:1',
    //     ]);

    //     $orderNumbers = $request->order_ids;

    //     $orders = Order::whereIn('order_number', $orderNumbers)->get();

    //     $dispenseLabels = [];
    //     $shippingLabels = [];
    //     $packingSlips = [];

    //     $processedOrders = $orders->map(function ($order) use (&$dispenseLabels, &$shippingLabels, &$packingSlips) {
    //         $orderData = json_decode($order->order_data, true);
    //         $lineItems = collect($orderData['line_items'] ?? [])->map(function ($item) {
    //             $productId = $item['product_id'] ?? null;
    //             $item['direction_of_use'] = $productId ? getProductMetafield($productId) : 'N/A';
    //             return $item;
    //         })->sortByDesc('quantity')->values();

    //         $order->order_data = $orderData;
    //         $order->line_items = $lineItems;
    //         $order->total_quantity = $lineItems->sum('quantity');

    //         $shippingCountry = strtolower($orderData['shipping_address']['country_code'] ?? 'gb');
    //         $shippingMethod = strtolower($orderData['shipping_lines'][0]['title'] ?? '');

    //         // QR Code
    //         $qrPayload = json_encode([
    //             'order_number' => $order->order_number,
    //             'name' => $orderData['shipping_address']['name'] ?? '',
    //             'country' => strtoupper($shippingCountry),
    //         ]);
    //         $order->qr_code = (new \Milon\Barcode\DNS2D)->getBarcodeHTML($qrPayload, 'QRCODE');

    //         // Dispense Label
    //         // $dispenseLabels[] = view('admin.dispenser.partials.dispense_label', compact('order'))->render();

    //         // Label type
    //         // if ($shippingMethod === 'local_delivery' || $shippingMethod === 'local_pickup') {
    //         //     $packingSlips[] = view('admin.dispenser.partials.packing_slip', compact('order'))->render();
    //         // }
    //         //elseif ($shippingCountry !== 'gb') {
    //         //     // DHL label placeholder (replace later with real label from API)
    //         //     $shippingLabels[] = view('admin.dispenser.partials.dhl_placeholder_label', compact('order'))->render();
    //         // }
    //         // elseif ($shippingCountry !== 'gb') {
    //         $labelPath = $this->generateRoyalMailLabelFromShopify($orderData);
    //         if ($labelPath) {
    //             $order->label_type = 'dhl_shipping_label';
    //             $order->label_html = '<embed src="' . asset('storage/' . basename($labelPath)) . '" type="application/pdf" width="100%" height="400px">';
    //         } else {
    //             $order->label_html = '<p style="color:red;">DHL Label Failed</p>';
    //         }
    //         // } else {
    //         // Royal Mail (future, optional)
    //         // $shippingLabels[] = view('admin.dispenser.partials.royal_mail_placeholder_label', compact('order'))->render();
    //         // }

    //         return $order;
    //     })->sortByDesc('total_quantity')->values();

    //     // ✅ Create a new batch
    //     $batch = DispenseBatch::create([
    //         'batch_number' => 'BATCH-' . now()->format('YmdHis') . '-' . \Str::random(4),
    //         'user_id' => auth()->id(),
    //     ]);

    //     // ✅ Combine PDFs
    //     $filePaths = [];

    //     // Dispense label PDF
    //     $dispensePdf = \PDF::loadHTML(implode('<div style="page-break-after:always;"></div>', $dispenseLabels))->setPaper('A4');
    //     $dispenseFilePath = "dispense_batches/{$batch->batch_number}_dispense.pdf";
    //     Storage::disk('public')->put($dispenseFilePath, $dispensePdf->output());
    //     $filePaths['dispense'] = $dispenseFilePath;

    //     // Shipping label PDF
    //     $shippingPdf = \PDF::loadHTML(implode('<div style="page-break-after:always;"></div>', $shippingLabels))->setPaper('A4');
    //     $shippingFilePath = "dispense_batches/{$batch->batch_number}_shipping.pdf";
    //     Storage::disk('public')->put($shippingFilePath, $shippingPdf->output());
    //     $filePaths['shipping'] = $shippingFilePath;

    //     // Packing slip PDF
    //     // $packingPdf = \PDF::loadHTML(implode('<div style="page-break-after:always;"></div>', $packingSlips))->setPaper('A4');
    //     // $packingFilePath = "dispense_batches/{$batch->batch_number}_packing.pdf";
    //     // Storage::disk('public')->put($packingFilePath, $packingPdf->output());
    //     // $filePaths['packing'] = $packingFilePath;

    //     // ✅ Combined PDF
    //     $combinedHtml = implode('<div style="page-break-after:always;"></div>', array_merge(
    //         $dispenseLabels,
    //         $shippingLabels,
    //         $packingSlips
    //     ));
    //     $combinedPdf = \PDF::loadHTML($combinedHtml)->setPaper('A4');
    //     $combinedFilePath = "dispense_batches/{$batch->batch_number}_combined.pdf";
    //     Storage::disk('public')->put($combinedFilePath, $combinedPdf->output());
    //     $batch->update(['pdf_path' => $combinedFilePath]);

    //     // ✅ Logs
    //     foreach ($processedOrders as $order) {
    //         \App\Models\OrderDispense::create([
    //             'order_id' => $order->order_number,
    //             'batch_id' => $batch->id,
    //             'dispensed_at' => now(),
    //             'reprint_count' => 0,
    //         ]);

    //         \App\Models\AuditLog::create([
    //             'user_id' => auth()->id(),
    //             'action' => 'dispensed',
    //             'order_id' => $order->order_number,
    //             'details' => 'Order dispensed by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y H:i'),
    //         ]);
    //     }

    //     return redirect()->route('dispenser_orders.index')->with('success', 'Dispensing PDF and all labels generated successfully.');
    // }


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


    // private function createDHLShippingLabel($order)
    // {
    //     $orderData = $order;
    //     // dd($orderData);

    //     $shipper = [
    //         'name'       => 'Demo DS',
    //         'company'    => 'DS Ecom',
    //         'phone'      => '0123456789',
    //         'address'    => [
    //             'streetLines' => ['123 Shipper Street'],
    //             'city'        => 'London',
    //             'postalCode'  => 'E1 6AN',
    //             'countryCode' => 'GB'
    //         ],
    //         'email'      => 'support@yourcompany.com'
    //     ];

    //     $recipientAddress = $orderData['shipping_address'] ?? [];

    //     $recipient = [
    //         'name'       => $recipientAddress['name'] ?? 'Customer',
    //         'phone'      => $recipientAddress['phone'] ?? '0000000000',
    //         'address'    => [
    //             'streetLines' => [$recipientAddress['address1'] ?? '', $recipientAddress['address2'] ?? ''],
    //             'city'        => $recipientAddress['city'] ?? '',
    //             'postalCode'  => $recipientAddress['zip'] ?? '',
    //             'countryCode' => strtoupper($recipientAddress['country_code'] ?? 'US')
    //         ],
    //         'email'      => $orderData['email'] ?? 'unknown@example.com'
    //     ];

    //     $payload = [
    //         'plannedShippingDateAndTime' => now()->addDay()->toIso8601String(),
    //         'productCode' => 'P', // Express Worldwide
    //         'payerAccountNumber' => env('DHL_ACCOUNT_NUMBER'),
    //         'customerDetails' => [
    //             'shipperDetails' => $shipper,
    //             'receiverDetails' => $recipient,
    //         ],
    //         'content' => [
    //             'packages' => [
    //                 [
    //                     'weight' => 1,
    //                     'dimensions' => [
    //                         'length' => 10,
    //                         'width' => 10,
    //                         'height' => 10
    //                     ]
    //                 ]
    //             ],
    //             'description' => 'Medical Order - ' . $orderData['id']
    //         ],
    //         'outputImageProperties' => [
    //             'printerDPI' => 300,
    //             'encodingFormat' => 'PDF'
    //         ]
    //     ];

    //     try {
    //         $client = new \GuzzleHttp\Client();
    //         $response = $client->post('https://api-eu.dhl.com/shipments', [
    //             'headers' => [
    //                 'DHL-API-Key'       => env('DHL_API_KEY'), // Use config() instead of env() directly
    //                 'Subscription-Key'  => env('DHL_SUBS_KEY'), // Include if needed
    //                 'Message-Reference' => uniqid('ref_', true),
    //                 'Message-Reference-Date' => now()->toIso8601String(),
    //                 'Content-Type'      => 'application/json',
    //                 'Accept'            => 'application/json',
    //             ],
    //             'json' => $payload,
    //             'timeout' => 15, // optional: timeout for request
    //         ]);

    //         $body = json_decode($response->getBody(), true);
    //         dd($body);

    //         // Extract base64 PDF label and save to storage
    //         $base64 = $body['label']['labelData'] ?? null;
    //         if ($base64) {
    //             $filePath = 'shipping_labels/DHL_' . $order->order_number . '.pdf';
    //             Storage::disk('public')->put($filePath, base64_decode($base64));
    //             return storage_path('app/public/' . $filePath); // Full path to saved label
    //         } else {
    //             return null;
    //         }
    //     } catch (\Exception $e) {
    //         dd("error");
    //         \Log::error('DHL Label Creation Failed: ' . $e->getMessage());
    //                     dd("error");

    //         return null;
    //     }
    // }

    // public function generateDgfShipmentLabel()
    // {
    //     try {
    //         $client = new \GuzzleHttp\Client();

    //         $response = $client->post('https://api-sandbox.dhl.com/dgff/transportation/shipment-label', [
    //             'headers' => [
    //                 'Authorization' => 'Bearer ' . env('DHL_DGF_TOKEN'), // Replace with actual token
    //                 'Content-Type' => 'application/json',
    //                 'Accept' => 'application/octet-stream', // For binary response
    //             ],
    //             'json' => [
    //                 'shipmentID' => 'S21000645937',
    //                 'housebillNumber' => '8FE7018',
    //                 'additionalInformation' => 'This is a test label',
    //                 'mimeType' => 'pdf',
    //                 'acceptContentType' => 'application/octet-stream',
    //             ],
    //             'stream' => true, // Important for binary download
    //         ]);

    //         // Save PDF locally
    //         $pdfPath = storage_path('app/public/dgf_labels/label_' . now()->format('YmdHis') . '.pdf');
    //         Storage::disk('public')->put('dgf_labels/label_' . now()->format('YmdHis') . '.pdf', $response->getBody());

    //         return response()->json(['success' => true, 'message' => 'Label downloaded successfully.', 'path' => $pdfPath]);
    //     } catch (\Exception $e) {
    //         \Log::error('DGF Label Error: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to generate DGF label.'], 500);
    //     }
    // }

    //     public function generateRoyalMailLabelFromShopify($order)
    // {
    //     $token =env('AUTH_TOKEN'); // From earlier

    //     if (!$token) {
    //         throw new \Exception("Royal Mail auth token failed.");
    //     }

    //     $orderData = $order;
    //     $recipient = [
    //         'name' => $orderData['shipping_address']['name'] ?? '',
    //         'telephoneNumber' => $orderData['shipping_address']['phone'] ?? '07000000000',
    //         'emailAddress' => $orderData['email'] ?? 'support@example.com',
    //         'addressLine1' => $orderData['shipping_address']['address1'] ?? '',
    //         'postTown' => $orderData['shipping_address']['city'] ?? '',
    //         'postcode' => $orderData['shipping_address']['zip'] ?? '',
    //         'countryCode' => strtoupper($orderData['shipping_address']['country_code'] ?? 'GB'),
    //     ];

    //     $payload = [
    //         "shipment" => [
    //             "shipmentNumber" => "ORD-" . $order['id'],
    //             "shipmentDate" => now()->format('Y-m-d'),
    //             "serviceCode" => "TPND", // or your agreed service code
    //             "recipientContact" => $recipient,
    //             "recipientAddress" => $recipient,
    //             "senderReference" => $order['id'],
    //             "items" => [
    //                 [
    //                     "weight" => 0.5,
    //                     "packageFormat" => "PARCEL"
    //                 ]
    //             ]
    //         ]
    //     ];

    //     $client = new \GuzzleHttp\Client();

    //     $response = $client->post('https://api.royalmail.net/shipping/v3/shipments', [
    //         'headers' => [
    //             'Authorization' => "Bearer $token",
    //             'Content-Type' => 'application/json',
    //         ],
    //         'json' => $payload
    //     ]);

    //     $data = json_decode($response->getBody(), true);
    // dd($data);
    //     // Example: extract label URL or PDF
    //     return $data; // Later you'll extract label from here
    // }

    public function download($id)
    {
        $batch = DispenseBatch::findOrFail($id);

        if (!$batch->pdf_path || !Storage::disk('public')->exists($batch->pdf_path)) {
            return redirect()->back()->with('error', 'PDF not found for this batch.');
        }

        return Storage::disk('public')->download($batch->pdf_path);
    }

    // try {
    //     $response = createRoyalMailShipment($shipmentData, 'your_access_token_here');
    //     dd($response); // Show success response
    // } catch (\Exception $e) {
    //     dd($e->getMessage()); // Show error if any
    // }

    function createRoyalMailShipment(string $authToken, $shipper, $destination, $orderData)
    {
        $items = $orderData['line_items'];
        $url = 'https://api.royalmail.net/shipping/v3/shipments';
        // pr($destination);die; 
        // dd($shipper);
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
}
