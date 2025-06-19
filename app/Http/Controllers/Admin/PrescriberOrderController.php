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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Carbon\Carbon;

class PrescriberOrderController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Prescriber')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        })->except('index'); // <- This line skips index()

    }


    //     function fetchRealIDVerificationStatus(string $checkId): ?array
    // {
    //     $apiUrl   = env('REAL_ID_API_URL') . "/checks/{$checkId}";
    //     $apiToken = env('REAL_ID_API_TOKEN');

    //     $response = Http::withHeaders([
    //         'Authorization' => "Bearer {$apiToken}",
    //         'Accept'        => 'application/json',
    //     ])->get($apiUrl);

    //     if ($response->successful()) {
    //        $data = $response->json();

    //     Log::info('✅ Real ID check sent successfully.', $data);
    //         return $data; // Or handle accordingly
    //     }

    //     Log::error('Failed to fetch Real ID status', [
    //         'status' => $response->status(),
    //         'body'   => $response->body(),
    //     ]);

    //     return null;
    // }

    //  $apiUrl   = env('REAL_ID_API_URL') . '/checks'; // Should end up like: https://real-id.getverdict.com/api/v1/checks
    //             $apiToken = env('REAL_ID_API_TOKEN');

    //             $payload = [
    //                         "customer" => [
    //                             "first_name" => "John",
    //                             "last_name" => "Smith",
    //                             "email" => "deepak.dotsquares.11@gmail.com", // test email
    //                             "phone" => "+918955497512",
    //                             "shopify_admin_graphql_id" => "gid://shopify/Customer/1234567890",
    //                         ],
    //                         "order" => [
    //                             "shopify_admin_graphql_id" => "gid://shopify/Order/1234567890",
    //                             "name" => "#1234",
    //                         ],
    //                     ];

    //             $response = Http::withHeaders([
    //                 'Authorization' => "Bearer {$apiToken}",
    //                 'Accept'        => 'application/json',
    //                 'Content-Type'  => 'application/json',
    //             ])->post($apiUrl, $payload);

    //             if ($response->successful()) {
    //                 $data = $response->json();
    //                 // pr($data);die;
    //                 pr($this->fetchRealIDVerificationStatus($data['check']['id']));
    //             }

    //             Log::error('Real ID check creation failed', [
    //                 'status' => $response->status(),
    //                 'body'   => $response->body(),
    //             ]);


    //         // }
    //         die;

    public function index(Request $request)
    {

        $query = Order::with('orderaction')
            ->whereNull('fulfillment_status')
            // Exclude cancelled orders
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
            })
            // Exclude orders whose prescription decision_status is 'approved'
            ->whereDoesntHave('orderaction', function ($q) {
                $q->where('decision_status', 'approved');
            });


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

        // Filter by date range
        if ($request->filled('date_range')) {
            $dates = explode(' to ', $request->date_range);
            if (count($dates) == 2) {
                $from = $dates[0];
                $to = $dates[1];

                $query->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            }
        }

        // Filter by custom type
        if ($request->filled('filter_type')) {
            switch ($request->filter_type) {
                case 'new':
                    $emailsWithMultipleOrders = Order::select('email')
                        ->groupBy('email')
                        ->havingRaw('COUNT(*) > 1')
                        ->pluck('email')
                        ->toArray();

                    $query->whereNotIn('email', $emailsWithMultipleOrders);
                    break;

                case 'repeat':
                    $query->where(function ($q) {
                        $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.source_name')) = 'subscription_contract'")
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.tags')) LIKE '%Subscription Recurring Order%'");
                    });
                    break;

                case 'international':
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.shipping_address.country_code')) != 'GB'");
                    break;

                case 'all':
                    // No additional filters
                    break;
            }
        }

        // Get paginated result
        $orders = $query->latest()->paginate(config('Reading.nodes_per_page'));
        // Get distinct statuses
        $statuses = Order::select('financial_status')->distinct()->pluck('financial_status');


        // Initialize
        $startDate = null;
        $endDate = null;
        $applyDateFilter = false;

        // Handle date range
        $dateRange = $request->input('date_range');
        if ($dateRange && str_contains($dateRange, 'to')) {
            [$startDateRaw, $endDateRaw] = array_map('trim', explode('to', $dateRange));

            try {
                $startDate = Carbon::parse($startDateRaw)->startOfDay();
                $endDate = Carbon::parse($endDateRaw)->endOfDay();
                $applyDateFilter = true;
            } catch (\Exception $e) {
                // Leave $applyDateFilter as false
            }
        }

        // Total Pending Orders (from orders table)
        $totalPendingQuery = Order::whereNull('fulfillment_status')
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
            })
            ->whereDoesntHave('orderaction', function ($q) {
                $q->where('decision_status', 'approved');
            });

        if ($applyDateFilter) {
            $totalPendingQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $totalPending = $totalPendingQuery->count();

        // Shared query for actions
        $actionsQuery = OrderAction::join('orders', 'order_actions.order_id', '=', 'orders.order_number')
            ->where('order_actions.role', 'Prescriber')
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) = 'null'");
            });

        if ($applyDateFilter) {
            $actionsQuery->whereBetween('order_actions.created_at', [$startDate, $endDate]);
        }

        // Counts by decision_status
        $totalApproved = (clone $actionsQuery)->where('decision_status', 'approved')->count();
        $totalOnHold   = (clone $actionsQuery)->where('decision_status', 'on_hold')->count();
        $totalRejected = (clone $actionsQuery)->where('decision_status', 'rejected')->count();

        // Final result
        $counts = [
            'total_pending'  => $totalPending,
            'total_approved' => $totalApproved,
            'total_on_hold'  => $totalOnHold,
            'total_rejected' => $totalRejected,
        ];

        return view('admin.prescriber.index', compact('orders', 'statuses', 'counts'));
    }




    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderMetafields = getOrderMetafields($order->order_number) ?? null;
        // dd($orderMetafields);

        $orderData = json_decode($order->order_data, true);
        return view('admin.prescriber.view', compact('order', 'orderData', 'orderMetafields'));
    }




    // public function downloadPDF($orderId)
    // {
    //     $order = Order::findOrFail($orderId);
    //     $orderData = json_decode($order->order_data, true);
    //     $items = [];
    //     $orderMetafields = getOrderMetafields($order->order_number); // Shopify ID is stored as order_number

    //     foreach ($orderData['line_items'] as $item) {
    //         $productId = $item['product_id'];
    //         $title = $item['title'];
    //         $quantity = $item['quantity'];

    //         $directionOfUse = getProductMetafield($productId); // Shopify API call

    //         $items[] = [
    //             'title' => $title,
    //             'quantity' => $quantity,
    //             'direction_of_use' => $directionOfUse,
    //         ];
    //     }

    //     return Pdf::loadView('admin.orders.prescription_pdf', [
    //         'orderData' => $orderData,
    //         'items' => $items,
    //         'prescriber_name' => 'Abdullah Sabyah',
    //         'prescriber_reg' => '2224180',
    //         'order' => $order,
    //         'prescriber_s_name' => $orderMetafields['prescriber_s_name'] ?? 'N/A',
    //         'gphc_number_' => $orderMetafields['gphc_number_'] ?? 'N/A',
    //         'patient_s_dob' => $orderMetafields['patient_s_dob'] ?? 'N/A',
    //         'approval' => $orderMetafields['approval'],
    //         'prescriber_signature' => $orderMetafields['prescriber_s_signature'] ?? null,

    //     ])->download("Prescription-Order-{$order->id}.pdf");
    // }



    public function prescribe(Request $request, $orderId)
    {
        $request->validate([
            'decision_status' => 'required|in:approved,rejected,on_hold',
            'clinical_reasoning' => 'required_if:decision_status,approved',
            'rejection_reason' => 'required_if:decision_status,rejected',
            'on_hold_reason' => 'required_if:decision_status,on_hold',
        ]);

        $decisionStatus = $request->decision_status;
        // $pdfUrl = $this->generateAndStorePDF($orderId);
        $pdfPath = $this->generateAndStorePDF($orderId);
        $pdfUrl = rtrim(config('app.url'), '/') . '/' . ltrim($pdfPath, '/');
        $metafields = buildCommonMetafields($request, $decisionStatus, $orderId, $pdfUrl);
     
        $shopDomain = env('SHOP_DOMAIN');
        $accessToken = env('ACCESS_TOKEN');
        // ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);

        DB::beginTransaction();
        try {
            // Step 1: Push metafields to Shopify
            foreach ($metafields as $field) {
                Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->post("https://{$shopDomain}/admin/api/2023-10/orders/{$orderId}/metafields.json", [
                    'metafield' => $field
                ]);
            }
           
            // Step 2: Take action based on decision
            if ($decisionStatus === 'on_hold') {
                markFulfillmentOnHold($orderId, $request->on_hold_reason);
                Order::where('order_number', $orderId)->update([
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
                    // 'cancelled_at' => $cancelTime,
                ]);
            }
      
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
                    'decision_timestamp' => now(),
                    'prescribed_pdf' => $pdfPath,
                    'role' => auth()->user()->getRoleNames()->first(),

                ]
            );
        

            // Step 4: Log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $decisionStatus,
                'order_id' => $orderId,
                // 'details' => $request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason,
                'details' =>  'Order prescribed by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y') . ' at ' . now()->format('H:i') .'. Reason: "'.$request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason.'"' ,
            ]);

            DB::commit();
            return back()->with('success', 'Order status changed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Failed to update order: ' . $e->getMessage());
        }
    }



    public function generateAndStorePDF($orderId)
    {
        $order = Order::where('order_number', $orderId)->first();
        $orderData = json_decode($order->order_data, true);
        $items = [];
        $orderMetafields = getOrderMetafields($order->order_number);
        $user = auth()->user();
        $prescriberData = $user->prescriber;

        $filePath = "signature-images/{$prescriberData->signature_image}";
		// $image_path = rtrim(config('app.url'), '/') . '/' . ltrim(Storage::url($filePath), '/');
      
        $image_path = public_path(Storage::url($filePath));

        foreach ($orderData['line_items'] as $item) {
            $productId = $item['product_id'];
            $title = $item['title'];
            $quantity = $item['quantity'];
            $directionOfUse = getProductMetafield($productId);

            $items[] = [
                'title' => $title,
                'quantity' => $quantity,
                'direction_of_use' => $directionOfUse,
            ];
        }
        $pdf = Pdf::loadView('admin.orders.prescription_pdf', [
            'orderData' => $orderData,
            'items' => $items,
            // 'prescriber_name' => 'Abdullah Sabyah',
            'prescriber_reg' => '2224180',
            'order' => $order,
            'prescriber_s_name' => $orderMetafields['prescriber_s_name'] ?? 'N/A',
            'gphc_number' => $prescriberData->gphc_number ?? 'N/A',
            'patient_s_dob' => $orderMetafields['patient_s_dob'] ?? 'N/A',
            'approval' => $orderMetafields['approval'],
            'prescriber_signature' => $image_path ?? null,
        ]);

        $fileName = "Prescription-Order-{$order->order_number}.pdf";
        $filePath = "prescriptions/{$fileName}";

        Storage::disk('public')->put($filePath, $pdf->output());

        return Storage::url($filePath); // returns public path (requires `php artisan storage:link`)
    }

    // public function uploadImageToShopifyViaGraphQL($publicImageUrl)
    // {
    //     $shop = env('SHOP_DOMAIN'); // your-store.myshopify.com
    //     $token = env('ACCESS_TOKEN');

    //     $query = <<<'GRAPHQL'
    //         mutation fileCreate($files: [FileCreateInput!]!) {
    //         fileCreate(files: $files) {
    //             files {
    //             alt
    //             createdAt
    //             ... on MediaImage {
    //                 image {
    //                 url
    //                 }
    //             }
    //             }
    //             userErrors {
    //             field
    //             message
    //             }
    //         }
    //         }
    //         GRAPHQL;

    //     $variables = [
    //         'files' => [
    //             [
    //                 'alt' => 'Uploaded image',
    //                 'contentType' => 'IMAGE',
    //                 'originalSource' => $publicImageUrl,
    //             ]
    //         ]
    //     ];

    //     $response = Http::withHeaders([
    //         'X-Shopify-Access-Token' => $token,
    //         'Content-Type' => 'application/json',
    //     ])->post("https://{$shop}/admin/api/2025-04/graphql.json", [
    //         'query' => $query,
    //         'variables' => $variables
    //     ]);

    //     return $response->json();
    // }

}
