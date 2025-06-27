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
        $excludedStatuses = ['approved', 'on_hold', 'accurately_checked', 'dispensed'];

        $excludedOrderIds = \App\Models\OrderAction::orderBy('created_at', 'desc')
            ->get()
            ->unique('order_id') // Keep only the latest action per order_id
            ->filter(function ($action) use ($excludedStatuses) {
                return in_array($action->decision_status, $excludedStatuses);
            })
            ->pluck('order_id')
            ->toArray();

        $query = Order::whereNull('fulfillment_status')
            // Exclude cancelled orders
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NULL")
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NULL")
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
            })->whereNotIn('order_number', $excludedOrderIds);


        $query = $this->filter_queries($request, $query);

        $totalPending = (clone $query)->count();
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

        // Shared query for actions
        $actionsQuery = OrderAction::join('orders', 'order_actions.order_id', '=', 'orders.order_number')
            ->where('order_actions.role', 'Prescriber')
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) = 'null'");
            });

        $actionsQuery = $this->filter_queries($request, $actionsQuery, $isAction = false);

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

    private function filter_queries($request, $query, $isAction = true)
    {
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
        if ($isAction) {
            if ($request->filled('date_range')) {
                $dates = explode(' to ', $request->date_range);
                if (count($dates) == 2) {
                    $from = $dates[0];
                    $to = $dates[1];

                    $query->whereDate('created_at', '>=', $from)
                        ->whereDate('created_at', '<=', $to);
                }
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
        return $query;
    }


    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderData = json_decode($order->order_data); // decode JSON string into object
        $order_images = [];

        // foreach ($orderData->line_items as $item) {
        //     $images = getProductImages($order->order_number, $item->product_id);
        //     if (!empty($images)) {
        //         $order_images[] = $images[0]; // only store the first image
        //     }
        // }

        $orderMetafields = getOrderMetafields($order->order_number) ?? null;
        // $orderMetafields = [];
        // dd($orderMetafields);

        $orderData = json_decode($order->order_data, true);
        return view('admin.prescriber.view', compact('order', 'orderData', 'orderMetafields', 'order_images'));
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
        [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));


        $order_detail = Order::where('order_number', $orderId)->first();

        if ($order_detail) {
            $order_data = json_decode($order_detail->order_data, true) ?? [];
            $check_id = '';
            $birth_date = '';

            if (isset($order_data['customer']) && !empty($order_data['customer'])) {
                // Get customer ID from order
                $customerId = $order_data['customer']['id'] ?? null;

                // Continue only if dob is NOT already set
                if ($customerId && empty($order_data['customer']['dob'])) {

                    // Fetch metafields from Shopify
                    $response = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken
                    ])->get("{$shopDomain}/admin/api/2023-10/customers/{$customerId}/metafields.json");

                    $data = $response->json();

                    // Extract check_id
                    if (isset($data['metafields'])) {
                        foreach ($data['metafields'] as $meta) {
                            if ($meta['key'] === 'check_id') {
                                $check_id = $meta['value'];
                                break;
                            }
                        }
                    }

                    // Fetch Real ID details
                    if (!empty($check_id)) {
                        $realIdResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . env('REAL_ID_API_TOKEN'),
                            'Accept' => 'application/json',
                        ])->get("https://real-id.getverdict.com/api/v1/checks/{$check_id}");

                        $realIdData = $realIdResponse->json();

                        if (!empty($realIdData['check']['result']['document']['birth_date'])) {
                            $birth_date = $realIdData['check']['result']['document']['birth_date'];

                            // Update the order_data
                            $order_data['customer']['dob'] = $birth_date;
                            $order_detail->order_data = json_encode($order_data);
                            $order_detail->save();
                        }
                    }
                }
            }
        }

        // $pdfUrl = $this->generateAndStorePDF($orderId);
        $pdfPath = $this->generateAndStorePDF($orderId);

        $pdfUrl = rtrim(config('app.url'), '/') . '/' . ltrim($pdfPath, '/');
        // $metafields = buildCommonMetafields($request, $decisionStatus, $orderId, $pdfUrl);
        $metafieldsInput  = buildCommonMetafields($request, $decisionStatus, $orderId, $pdfUrl);

        $roleName = auth()->user()->getRoleNames()->first(); // Returns string or null

        // $shopDomain = env('SHOP_DOMAIN');
        // $accessToken = env('ACCESS_TOKEN');


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

            // -----------------GraphQl---------------------------
            $query = <<<'GRAPHQL'
                    mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
                    metafieldsSet(metafields: $metafields) {
                        metafields {
                        key
                        namespace
                        id
                        }
                        userErrors {
                        field
                        message
                        }
                    }
                    }
                    GRAPHQL;
            Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$shopDomain}/admin/api/2023-10/graphql.json", [
                'query' => $query,
                'variables' => [
                    'metafields' => $metafieldsInput
                ]
            ]);

            // -----------------GraphQl---------------------------

            if ($decisionStatus === 'approved') {
                triggerShopifyTimelineNote($orderId);
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
                    'role' => $roleName
                ]
            );


            // Step 4: Log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $decisionStatus,
                'order_id' => $orderId,
                // 'details' => $request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason,
                'details' =>  'Order prescribed by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y') . ' at ' . now()->format('H:i') . '. Reason: "' . $request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason . '"',
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
        //  $orderMetafields = [];
        $user = auth()->user();
        $prescriberData = $user->prescriber;

        $filePath = "signature-images/{$prescriberData->signature_image}";
        // $image_path = rtrim(config('app.url'), '/') . '/' . ltrim(Storage::url($filePath), '/');
        $image_path = public_path(Storage::url($filePath));

        
        $order_detail = Order::where('order_number', $orderId)->first();
        $order_data = json_decode($order_detail->order_data, true) ?? [];
        $dob = $order_data['customer']['dob'] ?? '';
       


        foreach ($orderData['line_items'] as $item) {
            $productId = $item['product_id'];
            $title = $item['title'];
            $quantity = $item['quantity'];
            $directionOfUse = getProductMetafield($productId, $orderId);
            // $directionOfUse = '';

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
            'patient_s_dob' => $dob ?? 'N/A',
            'approval' => $orderMetafields['approval'] ?? '',
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
