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
use Illuminate\Cache\NullStore;
use App\Models\OrderDispense;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\Models\DispenseBatch;


class OrderController extends Controller
{


    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Admin')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        }); // <- This line skips index()
    }

    public function index(Request $request)
    {
        // $query = Order::query();
        $query = Order::with(['orderaction' => function ($q) {
            $q->orderBy('id', 'DESC');
        }, 'store']);

        // Search by name, email or order number
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('order_number', 'like', '%' . $request->search . '%');
            });
        }
        // Filter by financial status
        if ($request->filled('financial_status')) {
            $query->where('financial_status', $request->financial_status);
        }

        if ($request->filled('date_range')) {
            $dates = explode(' to ', $request->date_range);
            if (count($dates) == 2) {
                $from = $dates[0];
                $to = $dates[1];

                $query->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            }
        }
        // Get filtered results
        $orders = $query->latest()->paginate(config('Reading.nodes_per_page'));
        // Get distinct statuses for the filter dropdown
        $statuses = Order::select('financial_status')->distinct()->pluck('financial_status');
        return view('admin.orders.index', compact('orders', 'statuses'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        // $orderMetafields = getOrderMetafields($order->order_number) ?? null;

        $orderData = json_decode($order->order_data, true);

        // $url = $this->getMediaImageUrlFromGid($orderMetafields['prescriber_s_signature']);
        // dd($url);
        $auditDetails = getAuditLogDetailsForOrder($order->order_number) ?? null;

        return view('admin.orders.view', compact('order', 'orderData', 'auditDetails'));
    }




    public function downloadPDF($orderId)
    {
        $order = Order::findOrFail($orderId);
        $orderData = json_decode($order->order_data, true);
        $items = [];
        $orderMetafields = getOrderMetafields($order->order_number); // Shopify ID is stored as order_number

        foreach ($orderData['line_items'] as $item) {
            $productId = $item['product_id'];
            $title = $item['title'];
            $quantity = $item['quantity'];

            $directionOfUse = getProductMetafield($productId, $orderId); // Shopify API call

            $items[] = [
                'title' => $title,
                'quantity' => $quantity,
                'direction_of_use' => $directionOfUse,
            ];
        }
        // return view('orders.prescription_pdf', [
        //     'orderData' => $orderData,
        //     'items' => $items,
        //     'prescriber_name' => 'Abdullah Sabyah',
        //     'prescriber_reg' => '2224180',
        //     'order' => $order,
        //     'prescriber_s_name' => $orderMetafields['prescriber_s_name'] ?? 'N/A',
        //     'gphc_number_' => $orderMetafields['gphc_number_'] ?? 'N/A',
        //     'patient_s_dob' => $orderMetafields['patient_s_dob'] ?? 'N/A',
        //     'approval' => $orderMetafields['approval'],
        //     'prescriber_signature' => $orderMetafields['prescriber_s_signature'] ?? null,
        // ]);


        return Pdf::loadView('admin.orders.prescription_pdf', [
            'orderData' => $orderData,
            'items' => $items,
            'prescriber_name' => 'Abdullah Sabyah',
            'prescriber_reg' => '2224180',
            'order' => $order,
            'prescriber_s_name' => $orderMetafields['prescriber_s_name'] ?? 'N/A',
            'gphc_number_' => $orderMetafields['gphc_number_'] ?? 'N/A',
            'patient_s_dob' => $orderMetafields['patient_s_dob'] ?? 'N/A',
            'approval' => $orderMetafields['approval'],
            'prescriber_signature' => $orderMetafields['prescriber_s_signature'] ?? null,

        ])->download("Prescription-Order-{$order->id}.pdf");
    }


    function addMetafields($orderId)
    {
        $shopDomain = 'ds-demo-testing.myshopify.com';
        $accessToken = '';



        $metafields = [
            [
                'namespace' => 'custom',
                'key' => 'prescriber_s_name',
                'type' => 'single_line_text_field',
                'value' => 'Dr. John Doe',
            ],
            [
                'namespace' => 'custom',
                'key' => 'gphc_number_',
                'type' => 'single_line_text_field',
                'value' => 'GPHC123456',
            ],
            [
                'namespace' => 'custom',
                'key' => 'patient_s_dob',
                'type' => 'date',
                'value' => '1990-05-15',
            ],
            [
                'namespace' => 'custom',
                'key' => 'approval',
                'type' => 'boolean',
                'value' => true,
            ],
            [
                'namespace' => 'custom',
                'key' => 'prescriber_s_signature',
                'type' => 'single_line_text_field',
                'value' => 'Signed by Dr. John Doe',
            ],
        ];

        foreach ($metafields as $field) {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$shopDomain}/admin/api/2024-04/orders/{$orderId}/metafields.json", [
                'metafield' => $field
            ]);

            if (!$response->successful()) {
                // Handle errors or log response
                \Log::error('Shopify Metafield Error', [
                    'field' => $field['key'],
                    'response' => $response->body(),
                ]);
            }
        }

        return 'Metafields added successfully.';
    }


    public function overrideaction(Request $request, $orderId)
    {
        $request->validate([
            'decision_status' => 'required|in:approved,rejected,on_hold,release_hold',
            'clinical_reasoning' => 'required_if:decision_status,approved',
            'rejection_reason' => 'required_if:decision_status,rejected',
            'on_hold_reason' => 'required_if:decision_status,on_hold',
            'release_hold_reason' => 'required_if:decision_status,release_hold',
        ]);
        $decisionStatus = $request->decision_status;
        // $metafieldsInput = metaiFieldAdmin($request, $decisionStatus, $orderId);

        // [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));
        // ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);
        $roleName = auth()->user()->getRoleNames()->first(); // Returns string or null

        DB::beginTransaction();
        try {
            // Step 1: Push metafields to Shopify
            // foreach ($metafields as $field) {
            //     Http::withHeaders([
            //         'X-Shopify-Access-Token' => $accessToken,
            //         'Content-Type' => 'application/json',
            //     ])->post("https://{$shopDomain}/admin/api/2023-10/orders/{$orderId}/metafields.json", [
            //         'metafield' => $field
            //     ]);
            // }
            // $query = <<<'GRAPHQL'
            //         mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
            //         metafieldsSet(metafields: $metafields) {
            //             metafields {
            //             key
            //             namespace
            //             id
            //             }
            //             userErrors {
            //             field
            //             message
            //             }
            //         }
            //         }
            //         GRAPHQL;
            // Http::withHeaders([
            //     'X-Shopify-Access-Token' => $accessToken,
            //     'Content-Type' => 'application/json',
            // ])->post("{$shopDomain}/admin/api/2023-10/graphql.json", [
            //     'query' => $query,
            //     'variables' => [
            //         'metafields' => $metafieldsInput
            //     ]
            // ]);

            if ($decisionStatus === 'approved') {
                triggerShopifyTimelineNote($orderId);
            }
            // Step 2: Take action based on decision
            if ($decisionStatus === 'on_hold') {

                markFulfillmentOnHold($orderId, $request->on_hold_reason);
                $data = Order::where('order_number', $orderId)->update([
                    'fulfillment_status' => 'on_hold',
                ]);
            } elseif ($decisionStatus === 'rejected') {
                cancelOrder($orderId, $request->rejection_reason);
                $cancelReason = $request->rejection_reason;
                $cancelTime = now();

                // Fetch existing order_data and decode
                $order = Order::where('order_number', $orderId)->first();

                $orderData = json_decode($order->order_data, true); // convert JSON to array

                // Add cancel reason
                $orderData['cancel_reason'] = $cancelReason;
                $orderData['cancelled_at'] = $cancelTime->toDateTimeString();

                // Update the order
                $order->update([
                    'fulfillment_status' => '',
                    'order_data' => json_encode($orderData),
                    'cancelled_at' => $cancelTime,
                ]);
            } elseif ($decisionStatus === 'release_hold') {
                releaseFulfillmentHold($orderId, $request->release_hold_reason);
                Order::where('order_number', $orderId)->update([
                    'fulfillment_status' => null,
                ]);
            }



            // Step 3: Save to DB
            OrderAction::updateOrCreate(
                [
                    'order_id' => $orderId,
                    'user_id' => auth()->id(),
                ],
                [
                    'clinical_reasoning' => $request->clinical_reasoning,
                    'decision_status' => $decisionStatus,
                    'rejection_reason' => $request->rejection_reason,
                    'on_hold_reason' => $request->on_hold_reason,
                    'release_hold_reason' => $request->release_hold_reason,
                    'decision_timestamp' => now(),
                    'role' => $roleName

                ]
            );

            $reason = $request->clinical_reasoning
                ?: ($request->rejection_reason
                    ?: ($request->on_hold_reason
                        ?: ($request->release_hold_reason
                            ?: 'N/A')));

            // Decide action text based on which field is filled
            if ($request->clinical_reasoning) {
                $actionText = 'Order approved by ';
            } elseif ($request->rejection_reason) {
                $actionText = 'Order Rejected by ';
            } elseif ($request->on_hold_reason) {
                $actionText = 'Order Put On Hold by ';
            } else {
                $actionText = 'Order Hold Released by ';
            }

            AuditLog::create([
                'user_id'   => auth()->id(),
                'action'    => $decisionStatus, // could be 'approved', 'rejected', etc.
                'order_id'  => $orderId,
                'details'   => $actionText . auth()->user()->name .
                    ' on ' . now()->format('d/m/Y') .
                    ' at ' . now()->format('H:i') .
                    '. Reason: "' . $reason . '"',
            ]);


            DB::commit();
            return back()->with('suceess', 'Order status changed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Failed to update order: ' . $e->getMessage());
        }
    }



    // public function indexing(Request $request)
    // {
    //     $search = $request->input('search');

    //     $query = OrderDispense::with(['order', 'batch'])
    //         ->whereHas('order', fn($q) => $q->whereNotNull('shipment_pdf_path'));

    //     if ($search) {
    //         $query->where(function ($q) use ($search) {
    //             $q->whereHas('order', fn($oq) => $oq->where('order_number', 'like', "%$search%"))
    //                 ->orWhereHas('batch', fn($bq) => $bq->where('batch_number', 'like', "%$search%"));
    //         });
    //     }

    //     $dispenses = $query->latest()->paginate(config('Reading.nodes_per_page'))->withQueryString();
    //     $grouped = $dispenses->getCollection()->groupBy('batch_id');

    //     return view('admin.orders.batches.index', compact('grouped', 'dispenses'));
    // }

    public function indexing(Request $request)
    {
        $search = $request->input('search');

        $query = OrderDispense::with(['order', 'batch'])
            ->whereHas('order', fn($q) => $q->whereNotNull('shipment_pdf_path'));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', fn($oq) => $oq->where('order_number', 'like', "%$search%"))
                    ->orWhereHas('batch', fn($bq) => $bq->where('batch_number', 'like', "%$search%"));
            });
        }

        $paginator = $query->latest()->paginate(config('Reading.nodes_per_page'))->withQueryString();

        // Group the collection (not the paginator)
        $grouped = $paginator->getCollection()->groupBy('batch_id');

        return view('admin.orders.batches.index', [
            'dispenses' => $paginator, // Use this for pagination links
            'grouped' => $grouped,     // Use this to loop through grouped results
        ]);
    }
  
    public function checkReprint(Request $request, $batchId)
    {
        $user = auth()->user();

        // Get all dispense records in the batch with their related orders
        $dispenses = OrderDispense::with('order')->where('batch_id', $batchId)->get();

        if ($dispenses->isEmpty()) {
            return response()->json(['alreadyPrinted' => false]);
        }

        // Check if any dispense record has already been printed
        $alreadyPrinted = $dispenses->firstWhere('reprint_count', '>=', 1);
        $firstDispense = $dispenses->first();
        $order = $firstDispense->order;

        $nowFormatted = now()->format('d/m/Y \a\t H:i');

        if ($alreadyPrinted) {
            // ✅ Increment reprint_count for all
            foreach ($dispenses as $dispense) {
                $dispense->increment('reprint_count');
            }

            // ✅ Log reprint audit
            AuditLog::create([
                'user_id'  => $user->id,
                'order_id' => $batchId ?? null,
                'action'   => 'batch_reprint',
                'details'  => "Dispensing & Shipping label reprinted by {$user->name} on {$nowFormatted}.",
            ]);

            return response()->json([
                'alreadyPrinted' => true,
            ]);
        } else {
            $dispenses->each(function ($dispense) {
                $dispense->increment('reprint_count');
            });
            // ✅ First-time print
            AuditLog::create([
                'user_id'  => $user->id,
                'order_id' => $batchId ?? null,
                'action'   => 'batch_print',
                'details'  => "Dispensing & Shipping label printed by {$user->name} on {$nowFormatted}.",
            ]);

            return response()->json([
                'alreadyPrinted' => false,
            ]);
        }
    }




    public function downloadBatchPdf($batchId)
    {
        // Get all orders dispensed in this batch
        $dispenses = OrderDispense::with('order')
            ->where('batch_id', $batchId)
            ->whereHas('order', fn($q) => $q->whereNotNull('shipment_pdf_path'))
            ->get();

        if ($dispenses->isEmpty()) {
            return back()->with('error', 'No shipment PDFs found for this batch.');
        }

        // Directory to store the merged PDF
        $outputDir = storage_path('app/public/batches');
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $outputFile = $outputDir . "/batch_{$batchId}.pdf";

        // Collect all full paths of shipment PDFs
        $pdfPaths = [];

        foreach ($dispenses as $dispense) {
            $relativePath = $dispense->order->shipment_pdf_path;
            $publicPath = public_path(Storage::url($relativePath)); // e.g. public/storage/shippments_pdf/abc.pdf

            if (file_exists($publicPath)) {
                $pdfPaths[] = $publicPath;
            } else {
                \Log::warning("PDF file missing: $publicPath");
            }
        }

        if (empty($pdfPaths)) {
            return back()->with('error', 'No valid PDF files found for this batch.');
        }

        // Detect OS and set Ghostscript path
        if (stripos(PHP_OS, 'WIN') === 0) {
            $gsExecutable = 'C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe'; // Adjust if needed
        } else {
            $gsExecutable = '/usr/bin/gs'; // Typical on Linux servers
        }

        // Escape all file paths
        $escapedFiles = array_map('escapeshellarg', $pdfPaths);

        // Build the Ghostscript command
        $cmd = "\"{$gsExecutable}\" -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite " .
            "-sOutputFile=" . escapeshellarg($outputFile) . " " .
            implode(' ', $escapedFiles);

        // Execute the command
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputFile)) {
            \Log::error("Ghostscript PDF merge failed for batch {$batchId}", [
                'command' => $cmd,
                'output' => $output,
                'code' => $returnCode
            ]);
            return back()->with('error', 'Failed to merge shipment PDFs.');
        }

        // Serve the merged PDF file as a download
        return response()->download($outputFile)->deleteFileAfterSend(true);
    }


    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,order_number',
            'details' => 'required|string',
            'file' => 'nullable|file|mimes:pdf|max:5048',
        ]);
        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $filePath = 'checker_prescription/' . $filename;

            Storage::disk('public')->putFileAs('checker_prescription', $file, $filename);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'order_id' => $request->order_id,
            'action' => 'manual_log',
            'details' => $request->details . ' (' . Carbon::now()->format('Y-m-d H:i:s') . ')',
            'checker_prescription_file' => $filePath,
        ]);

        return redirect()->back()->with('success', 'Log submitted successfully.');
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
}
