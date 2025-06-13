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
        $query = Order::with('orderaction')
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

        // ->whereRaw("JSON_EXTRACT(order_data, '$.cancelled_at') IS NULL");
        // only non-cancelled, unfulfilled, and B2B orders

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

        // Get paginated result
        $orders = $query->latest()->paginate(config('Reading.nodes_per_page'));

        // Get distinct statuses
        $statuses = Order::select('financial_status')->distinct()->pluck('financial_status');

        return view('admin.checker.index', compact('orders', 'statuses'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderMetafields = getOrderMetafields($order->order_number) ?? null;
        // dd($orderMetafields);

        $orderData = json_decode($order->order_data, true);
        return view('admin.checker.view', compact('order', 'orderData', 'orderMetafields'));
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

            $directionOfUse = getProductMetafield($productId); // Shopify API call

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

        $metafields = buildCommonMetafieldsChecker($request, $decisionStatus);
        // dd($metafields);

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
                    'cancelled_at' => $cancelTime,
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
                ]
            );


            // Step 4: Log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $decisionStatus,
                'order_id' => $orderId,
                'details' => $request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason,
            ]);

            DB::commit();
            return back()->with('success', 'Order status changed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Failed to update order: ' . $e->getMessage());
        }
    }
}
