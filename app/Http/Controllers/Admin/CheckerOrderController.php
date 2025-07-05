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
use Carbon\Carbon;
use App\Mail\SendMail;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;

class CheckerOrderController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Checker')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        })->except('index'); // <- This line skips index()


    }


    public function index(Request $request)
    {
        $totalPending = 0;
        $orderStatus = $request->input('order_status');

        if ($orderStatus && ($orderStatus === "approved" || $orderStatus === "on_hold")) {
            // Fetch only orders with latest action status = approved
            $approvedOrderIds = \App\Models\OrderAction::orderBy('created_at', 'desc')
                ->get()
                ->unique('order_id')
                ->filter(function ($action) use ($orderStatus) {
                    return $action->decision_status === $orderStatus;
                })
                ->pluck('order_id')
                ->toArray();

            $query = Order::query()
                ->where(function ($q) {
                    $q->whereNull('fulfillment_status')
                        ->orWhere('fulfillment_status', '!=', 'Fulfilled');
                })
                ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NOT NULL")
                ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NOT NULL")
                ->where(function ($q) {
                    $q->whereRaw("JSON_EXTRACT(order_data, '$.cancelled_at') IS NULL")
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
                })->whereIn('order_number', $approvedOrderIds);
        } else {
             $excludedStatuses = ['approved','on_hold' ,'accurately_checked', 'dispensed'];
            if($request->input('search')){
                $excludedStatuses = ['accurately_checked', 'dispensed'];
            }


            $excludedOrderIds = \App\Models\OrderAction::orderBy('created_at', 'desc')
                ->get()
                ->unique('order_id') // Keep only the latest action per order_id
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
                ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NOT NULL")
                ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NOT NULL")
                ->where(function ($q) {
                    $q->whereRaw("JSON_EXTRACT(order_data, '$.cancelled_at') IS NULL")
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
                })->whereNotIn('order_number', $excludedOrderIds);
        }

        $query = $this->filter_queries($request, $query);
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
        $totalPendingQuery = Order::with('orderaction')
            ->whereNull('fulfillment_status')
            // Add the B2B filter directly here
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NOT NULL")
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NOT NULL")
            ->where(function ($q) {
                $q->whereRaw("JSON_EXTRACT(order_data, '$.cancelled_at') IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
            })->whereDoesntHave('orderaction', function ($q) {
                $q->where('decision_status', 'approved');
            });

        $totalPendingQuery = $this->filter_queries($request, $totalPendingQuery, $isAction = false);

        if ($applyDateFilter) {
            $totalPendingQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $totalPending = $totalPendingQuery->count();

        // Shared query for actions
        $actionsQuery = OrderAction::join('orders', 'order_actions.order_id', '=', 'orders.order_number')
            ->where('order_actions.role', 'Checker')
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NOT NULL")
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NOT NULL")
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) = 'null'");
            });
        $actionsQuery = OrderAction::join('orders', 'order_actions.order_id', '=', 'orders.order_number')
            ->where('order_actions.role', 'Checker')
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.id') IS NOT NULL")
            ->whereRaw("JSON_EXTRACT(order_data, '$.company.location_id') IS NOT NULL")
            ->where(function ($q) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.order_data, '$.cancelled_at')) = 'null'");
            });
        // ->where(function ($q) {
        //     $q->whereNull('order_actions.decision_status')
        //     ->orWhere('order_actions.decision_status', '!=', 'approved');
        // });

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

        return view('admin.checker.index', compact('orders', 'statuses', 'counts'));
    }

    private function filter_queries($request, $query, $isAction = true)
    {
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
        return $query;
        return view('admin.checker.index', compact('orders', 'statuses', 'counts'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        // $orderMetafields = getOrderMetafields($order->order_number) ?? null;
        // dd($orderMetafields);

        $orderData = json_decode($order->order_data, true);
        $auditDetails = getAuditLogDetailsForOrder($order->order_number) ?? null;

        return view('admin.checker.view', compact('order', 'orderData', 'auditDetails'));
    }
    // public function view($id)
    // {
    //     $order = Order::findOrFail($id);
    //     $orderMetafields = getOrderMetafields($order->order_number) ?? null;
    //     $orderData = json_decode($order->order_data, true);

    //     $pdfAttachments = getShopifyTimelineAttachments($order->order_number);

    //     return view('admin.checker.view', compact('order', 'orderData', 'orderMetafields', 'pdfAttachments'));
    // }




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
            $directionOfUse = getProductMetafield($productId, $orderId); // Shopify API call

            $items[] = [
                'title' => $title,
                'quantity' => $quantity,
                'direction_of_use' => $directionOfUse,
            ];
        }

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



    public function check(Request $request, $orderId)
    {
        $request->validate([
            'decision_status' => 'required|in:approved,rejected,on_hold',
            'clinical_reasoning' => 'required_if:decision_status,approved',
            'rejection_reason' => 'required_if:decision_status,rejected',
            'on_hold_reason' => 'required_if:decision_status,on_hold',
        ]);

        $decisionStatus = $request->decision_status;

        // $metafieldsInput = buildCommonMetafieldsChecker($request, $decisionStatus);

        // [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

        $roleName = auth()->user()->getRoleNames()->first(); // Returns string or null

        // ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);

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
                // triggerShopifyTimelineNote($orderId);
                $template = EmailTemplate::where('identifier', 'checker_approved')->first();
            }

            // Step 2: Take action based on decision
            if ($decisionStatus === 'on_hold') {
                markFulfillmentOnHold($orderId, $request->on_hold_reason);
                Order::where('order_number', $orderId)->update([
                    'fulfillment_status' => 'on_hold',
                ]);

                $template = EmailTemplate::where('identifier', 'checker_on_hold')->first();
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
                    'cancelled_at' => $cancelTime,
                ]);

                $template = EmailTemplate::where('identifier', 'checker_rejected')->first();
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
                    'role' => $roleName
                ]
            );


            // Step 4: Log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $decisionStatus,
                'order_id' => $orderId,
                'details' =>  'Order checked by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y') . ' at ' . now()->format('H:i') . '. Reason: "' . $request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason . '"',
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
            // return back()->with('success', 'Order status changed successfully.');
            return back()->with('success', 'Order status changed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Failed to update order: ' . $e->getMessage());
        }
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
                'details' => $request->release_hold_reason ?? '',
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
