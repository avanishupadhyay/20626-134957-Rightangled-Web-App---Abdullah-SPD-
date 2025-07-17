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
use App\Mail\SendMail;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailTemplate;

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

    public function index(Request $request)
    {
        // $excludedStatuses = ['approved', 'accurately_checked', 'dispensed'];

        // $excludedOrderIds = \App\Models\OrderAction::orderBy('created_at', 'desc')
        //     ->get()
        //     ->unique('order_id') // Keep only the latest action per order_id
        //     ->filter(function ($action) use ($excludedStatuses) {
        //         return in_array($action->decision_status, $excludedStatuses);
        //     })
        //     ->pluck('order_id')
        //     ->toArray();

        // $query = Order::query()
        //     ->where(function ($q) {
        //         $q->whereNull('fulfillment_status')
        //             ->orWhere('fulfillment_status', '!=', 'Fulfilled');
        //     })
        //     // whereNull('fulfillment_status')
        //     // Exclude cancelled orders
        //     ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NULL")
        //     ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NULL")
        //     ->where(function ($q) {
        //         $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) IS NULL")
        //             ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
        //     })->whereNotIn('order_number', $excludedOrderIds);
        $totalPending = 0;
        $orderStatus = $request->input('order_status');

        if ($orderStatus && ($orderStatus === "approved" || $orderStatus === "on_hold")) {
            // Fetch only orders with latest action status = approved
            $approvedOrderIds = \App\Models\OrderAction::orderBy('updated_at', 'desc')
                ->get()
                ->unique('order_id')
                ->filter(function ($action) use ($orderStatus) {
                    return $action->decision_status === $orderStatus;
                })
                ->pluck('order_id')
                ->toArray();

            $query = Order::query()
                ->whereIn('order_number', $approvedOrderIds);
        } else {
            // Original logic: exclude orders whose latest action is in excludedStatuses
            $excludedStatuses = ['approved', 'on_hold', 'accurately_checked', 'dispensed'];
            if ($request->input('search')) {
                $excludedStatuses = ['accurately_checked', 'dispensed'];
            }

            $excludedOrderIds = \App\Models\OrderAction::orderBy('updated_at', 'desc')
                ->get()
                ->unique('order_id')
                ->filter(function ($action) use ($excludedStatuses) {
                    return in_array($action->decision_status, $excludedStatuses);
                })
                ->pluck('order_id')
                ->toArray();

            $query = Order::query()
                ->where(function ($q) {
                    $q->whereNull('fulfillment_status')
                        ->orWhere('fulfillment_status', '!=', 'Fulfilled');
                })
                ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NULL")
                ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NULL")
                ->where(function ($q) {
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) IS NULL")
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
                })
                ->whereNotIn('order_number', $excludedOrderIds);
        }

        $query = $this->filter_queries($request, $query);
        if (!$orderStatus) {
            $totalPending = (clone $query)->count();
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
        // $orderMetafields = getOrderMetafields($order->order_number) ?? null;
        $orderData = json_decode($order->order_data, true);
        $auditDetails = getAuditLogDetailsForOrder($order->order_number) ?? null;
        return view('admin.prescriber.view', compact('order', 'orderData', 'order_images', 'auditDetails'));
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
        // [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));


        $order_detail = Order::where('order_number', $orderId)->first();

        if ($order_detail) {
            $order_data = json_decode($order_detail->order_data, true) ?? [];
            $check_id = '';
            $birth_date = '';

            if (isset($order_data['customer']) && !empty($order_data['customer'])) {
                // Get customer ID from order
                // $customerId = $order_data['customer']['id'] ?? null;
                $customerId = 23044010213763;

                // Continue only if dob is NOT already set
                if ($customerId && empty($order_data['customer']['dob'])) {
                    // Fetch metafields from Shopify
                    $response = Http::withHeaders([
                        'X-Shopify-Access-Token' => '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'
                    ])->get("https://rightangled-store.myshopify.com/admin/api/2023-10/customers/{$customerId}/metafields.json");

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

        // // $pdfUrl = $this->generateAndStorePDF($orderId);
        // $pdfPath = $this->generateAndStorePDF($orderId);

        // $pdfUrl = rtrim(config('app.url'), '/') . '/' . ltrim($pdfPath, '/');
        $pdfPath = null;
        $pdfUrl = null;

        if ($decisionStatus === 'approved') {
            $pdfPath = $this->generateAndStorePDF($orderId); // passing `true` for approved
            // return $pdfPath;
            $pdfUrl = rtrim(config('app.url'), '/') . '/' . ltrim($pdfPath, '/');
        }
        // $metafields = buildCommonMetafields($request, $decisionStatus, $orderId, $pdfUrl);
        // $metafieldsInput  = buildCommonMetafields($request, $decisionStatus, $orderId, $pdfUrl);

        $roleName = auth()->user()->getRoleNames()->first(); // Returns string or null

        DB::beginTransaction();
        try {

            // -----------------GraphQl---------------------------
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

            // -----------------GraphQl---------------------------

            if ($decisionStatus === 'approved') {
                // triggerShopifyTimelineNote($orderId);
                $template = EmailTemplate::where('identifier', 'prescriber_approved')->first();
            }

            // Step 2: Take action based on decision
            if ($decisionStatus === 'on_hold') {
                markFulfillmentOnHold($orderId, $request->on_hold_reason);
                Order::where('order_number', $orderId)->update([
                    'fulfillment_status' => 'on_hold',
                ]);

                $template = EmailTemplate::where('identifier', 'prescriber_on_hold')->first();
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
                    'fulfillment_status' => null,
                    'order_data' => json_encode($orderData),
                    // 'cancelled_at' => $cancelTime,
                ]);

                $template = EmailTemplate::where('identifier', 'prescriber_rejected')->first();
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

            $reason = $request->clinical_reasoning
                ?: ($request->rejection_reason
                    ?: ($request->on_hold_reason
                        ?: 'N/A'));

            // Decide action text based on which field is filled
            if ($request->clinical_reasoning) {
                $actionText = 'Order Checked by ';
            } elseif ($request->rejection_reason) {
                $actionText = 'Order Rejected by ';
            } elseif ($request->on_hold_reason) {
                $actionText = 'Order Put On Hold by ';
            } else {
                $actionText = 'Order Updated by ';
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

            $order_detail = Order::where('order_number', $orderId)->first();
            $order_data = json_decode($order_detail->order_data, true) ?? [];

            if (isset($order_data['customer']) && !empty($order_data['customer'])) {
                $customerId = $order_data['customer']['id'] ?? null;
                $customerEmail = $order_data['customer']['email'] ?? null;
                $customerName =  (($order_data['customer']['first_name'] ?? null) . ' ' . ($order_data['customer']['last_name'] ?? null));
                $user = auth()->user();
                $prescriberData = $user->prescriber;

                $data = [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'signature_image' => asset('admin/signature-images/' . $prescriberData->signature_image),
                    'gphc_number' => $prescriberData->gphc_number,
                    'role' => $roleName ?? '',
                ];

                // Replace all {key} with actual values
                $parsedSubject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($data) {
                    return $data[$matches[1]] ?? ''; // Return empty string if key not found
                }, $template->subject ?? '');

                $parsedBody = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($data) {
                    return $data[$matches[1]] ?? ''; // Return empty string if key not found
                }, $template->body ?? '');

                $mail = new SendMail([
                    'subject' => $parsedSubject,
                    'body' => $parsedBody,
                ]);
                if ($customerEmail) {
                    try {
                        Mail::to('deepak.vaishnav@dotsquares.com')->queue($mail);
                        Log::info('Queue success for email to');
                    } catch (\Exception $e) {
                        Log::warning('Queue failed. Sending email synchronously.', [
                            'error' => $e->getMessage(),
                        ]);
                        Mail::to('deepak.vaishnav@dotsquares.com')->send($mail);
                    }
                }
            }

            return redirect()->route('prescriber_orders.index')->with('success', 'Order status changed successfully.');
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
        // $orderMetafields = getOrderMetafields($order->order_number);
        //  $orderMetafields = [];
        $user = auth()->user();
        $prescriber_s_name = $user->name ?? 'N/A';
        $approval = 'true';
        $prescriberData = $user->prescriber;

        $filePath = "signature-images/{$prescriberData->signature_image}";
        // $image_path = rtrim(config('app.url'), '/') . '/' . ltrim(Storage::url($filePath), '/');
        $image_path = public_path(Storage::url($filePath));


        $order_detail = Order::where('order_number', $orderId)->first();
        $order_data = json_decode($order_detail->order_data, true) ?? [];
        $dob = $order_data['customer']['dob'] ?? '';


        foreach ($orderData['line_items'] as $item) {
            // pr($item);die;
            if ($item['current_quantity'] > 0) {
                $productId = $item['product_id'];
                $title = $item['title'];
                $quantity = $item['current_quantity'];
                $directionOfUse = getProductMetafield($productId, $orderId);

                $items[] = [
                    'title' => $title,
                    'quantity' => $quantity,
                    'direction_of_use' => $directionOfUse,
                ];
            }
        }
        // return view('admin.orders.prescription_pdf', [
        //     'orderData' => $orderData,
        //     'items' => $items,
        //     // 'prescriber_name' => 'Abdullah Sabyah',
        //     'prescriber_reg' => '2224180',
        //     'order' => $order,
        //     'prescriber_s_name' => $prescriber_s_name,
        //     'gphc_number' => $prescriberData->gphc_number ?? 'N/A',
        //     'patient_s_dob' => $dob ?? 'N/A',
        //     'approval' => $approval,
        //     'prescriber_signature' => $image_path ?? null,
        // ]);die;
        $pdf = Pdf::loadView('admin.orders.prescription_pdf', [
            'orderData' => $orderData,
            'items' => $items,
            // 'prescriber_name' => 'Abdullah Sabyah',
            'prescriber_reg' => '2224180',
            'order' => $order,
            'prescriber_s_name' => $prescriber_s_name,
            'gphc_number' => $prescriberData->gphc_number ?? 'N/A',
            'patient_s_dob' => $dob ?? 'N/A',
            'approval' => $approval,
            'prescriber_signature' => $image_path ?? null,
        ]);

        $fileName = "Prescription-Order-{$order->order_number}.pdf";
        $filePath = "prescriptions/{$fileName}";

        Storage::disk('public')->put($filePath, $pdf->output());

        return Storage::url($filePath); // returns public path (requires `php artisan storage:link`)
    }

    public function overrideaction(Request $request, $orderId)
    {
        $request->validate([
            'release_hold_reason' => 'required_if:decision_status,release_hold',
        ]);
        $decisionStatus = $request->decision_status;

        [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));
        $roleName = auth()->user()->getRoleNames()->first(); // Returns string or null

        DB::beginTransaction();
        try {

            if ($decisionStatus === 'release_hold') {
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

            // Step 4: Log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $decisionStatus,
                'order_id' => $orderId,
                'details' => 'Order hold released by' . auth()->user()->name .
                    ' on ' . now()->format('d/m/Y') .
                    ' at ' . now()->format('H:i') . $request->release_hold_reason ?? '',
            ]);

            DB::commit();

            $template = EmailTemplate::where('identifier', 'release_hold')->first();
            $order_detail = Order::where('order_number', $orderId)->first();
            $order_data = json_decode($order_detail->order_data, true) ?? [];

            if (isset($order_data['customer']) && !empty($order_data['customer'])) {
                $customerId = $order_data['customer']['id'] ?? null;
                $customerEmail = $order_data['customer']['email'] ?? null;
                $customerName =  (($order_data['customer']['first_name'] ?? null) . ' ' . ($order_data['customer']['last_name'] ?? null));
                $user = auth()->user();
                $prescriberData = $user->prescriber;

                $data = [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'signature_image' => asset('admin/signature-images/' . $prescriberData->signature_image),
                    'gphc_number' => $prescriberData->gphc_number,
                    'role' => $roleName ?? '',
                ];

                // Replace all {key} with actual values
                $parsedSubject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($data) {
                    return $data[$matches[1]] ?? ''; // Return empty string if key not found
                }, $template->subject ?? '');

                $parsedBody = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($data) {
                    return $data[$matches[1]] ?? ''; // Return empty string if key not found
                }, $template->body ?? '');

                $mail = new SendMail([
                    'subject' => $parsedSubject,
                    'body' => $parsedBody,
                ]);
                if ($customerEmail) {
                    try {
                        Mail::to('deepak.vaishnav@dotsquares.com')->queue($mail);
                        Log::info('Queue success for email to');
                    } catch (\Exception $e) {
                        Log::warning('Queue failed. Sending email synchronously.', [
                            'error' => $e->getMessage(),
                        ]);
                        Mail::to('deepak.vaishnav@dotsquares.com')->send($mail);
                    }
                }
            }


            return back()->with('suceess', 'Order status changed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Failed to update order: ' . $e->getMessage());
        }
    }
}
