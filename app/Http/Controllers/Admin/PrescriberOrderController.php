<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use App\Models\AuditLog;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;



class PrescriberOrderController extends Controller
{

    public function index(Request $request)
    {
        $query = Order::query();

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
        return view('admin.prescriber.index', compact('orders', 'statuses'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderMetafields = getOrderMetafields($order->order_number) ?? null;
        // dd($orderMetafields);

        $orderData = json_decode($order->order_data, true);
        return view('admin.prescriber.view', compact('order', 'orderData', 'orderMetafields'));
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


 
    public function prescribe(Request $request, $orderId)
    {
        $request->validate([
            'decision_status' => 'required|in:approved,rejected,on_hold',
            'clinical_reasoning' => 'required_if:decision_status,approved',
            'rejection_reason' => 'required_if:decision_status,rejected',
            'on_hold_reason' => 'required_if:decision_status,on_hold',
            'patient_s_dob' => 'required_if:decision_status,approved|date',
            'gphc_number_' => 'required_if:decision_status,approved',
        ]);

        $decisionStatus = $request->decision_status;
        $metafields = $this->buildCommonMetafields($request, $decisionStatus);
        // dd($metafields);

        DB::beginTransaction();
        try {
            // Step 1: Push metafields to Shopify
            foreach ($metafields as $field) {
                Http::withHeaders([
                    'X-Shopify-Access-Token' => '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',
                    'Content-Type' => 'application/json',
                ])->post("https://ds-demo-testing.myshopify.com/admin/api/2023-10/orders/{$orderId}/metafields.json", [
                    'metafield' => $field
                ]);
            }

            // Step 2: Take action based on decision
            if ($decisionStatus === 'on_hold') {
                $this->markFulfillmentOnHold($orderId, $request->on_hold_reason);
                Order::where('order_number', $orderId)->update([
                    'fulfillment_status' => 'on_hold',
                ]);
            } elseif ($decisionStatus === 'rejected') {
                $this->cancelOrderWithRefund($orderId, $request->rejection_reason);
            }

            // Step 3: Save to DB
            Prescription::updateOrCreate(['order_id' => $orderId], [
                'prescriber_id' => auth()->id(),
                'gphc_number_' => $request->gphc_number_,
                'signature_image' => auth()->user()->signature_image ?? 'Signed by ' . auth()->user()->name,
                'clinical_reasoning' => $request->clinical_reasoning,
                'decision_status' => $decisionStatus,
                'rejection_reason' => $request->rejection_reason,
                'on_hold_reason' => $request->on_hold_reason,
                'decision_timestamp' => now(),
            ]);


            // Step 4: Log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'Prescription ' . $decisionStatus,
                'order_id' => $orderId,
                'details' => $request->clinical_reasoning ?? $request->rejection_reason ?? $request->on_hold_reason,
            ]);

            DB::commit();
            return back()->with('status', 'Order reviewed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Failed to update order: ' . $e->getMessage());
        }
    }


    public function buildCommonMetafields(Request $request, string $decisionStatus): array
    {
        $user = auth()->user();

        $metafields = [
            [
                'namespace' => 'custom',
                'key' => 'prescriber_id',
                'type' => 'number_integer',
                'value' => $user->id,
            ],
             [
                'namespace' => 'custom',
                'key' => 'prescriber_s_name',
                'type' => 'single_line_text_field',
                'value' => $user->name ?? 'admin_user',
            ],
            [
                'namespace' => 'custom',
                'key' => 'gphc_number_',
                'type' => 'single_line_text_field',
                'value' => $request->gphc_number_,
            ],
            [
                'namespace' => 'custom',
                'key' => 'prescriber_s_signature',
                'type' => 'single_line_text_field',
                'value' => $user->signature_image ?? 'Signed by ' . $user->name,
            ],
            [
                'namespace' => 'custom',
                'key' => 'decision_status',
                'type' => 'single_line_text_field',
                'value' => $decisionStatus,
            ],
            [
                'namespace' => 'custom',
                'key' => 'decision_timestamp',
                'type' => 'date_time',
                'value' => now()->toIso8601String(),
            ],

        ];

        if ($decisionStatus === 'approved') {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'clinical_reasoning',
                'type' => 'multi_line_text_field',
                'value' => $request->clinical_reasoning,
            ];
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'patient_s_dob',
                'type' => 'date',
                'value' => $request->patient_s_dob,
            ];
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'approval',
                'type' => 'boolean',
                'value' => true,
            ];
        } elseif ($decisionStatus === 'rejected') {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'rejection_reason',
                'type' => 'multi_line_text_field',
                'value' => $request->rejection_reason,
            ];
        } elseif ($decisionStatus === 'on_hold') {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'on_hold_reason',
                'type' => 'multi_line_text_field',
                'value' => $request->on_hold_reason,
            ];
        }

        return $metafields;
    }



    public function markFulfillmentOnHold($orderId, $reason)
    {
        $shopDomain = 'ds-demo-testing.myshopify.com';
        $accessToken = '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';
        // Step 1: Get the order to fetch fulfillment_order ID
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get("https://{$shopDomain}/admin/api/2023-10/orders/{$orderId}/fulfillment_orders.json");

        $fulfillmentOrders = $response->json('fulfillment_orders');

        if (empty($fulfillmentOrders)) {
            return response()->json(['error' => 'No fulfillment orders found.'], 404);
        }

        $fulfillmentOrderId = $fulfillmentOrders[0]['id'];


        // Step 2: Create fulfillment hold (mark as on-hold)
        $holdResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://{$shopDomain}/admin/api/2023-10/fulfillment_orders/{$fulfillmentOrderId}/hold.json", [
            'fulfillment_hold' => [
                'reason' => 'other', // ✅ valid reason
                'reason_notes' => $reason ?? 'Order placed on hold during review.',
            ],
        ]);
        if ($holdResponse->failed()) {
            return response()->json([
                'error' => 'Failed to put fulfillment on hold',
                'details' => $holdResponse->json()
            ], 500);
        }

        return true;
    }


    public function cancelOrderWithRefund($orderId, $reason)
    {
        $shopDomain = 'ds-demo-testing.myshopify.com';
        $accessToken = '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';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://{$shopDomain}/admin/api/2023-10/orders/{$orderId}/cancel.json", [
            'email' => true,
            'reason' => 'customer', // or 'other', 'fraud', 'inventory'
            'restock' => true,
            'note' => $reason ?? 'Order rejected by prescriber.',
            'refund' => [
                'notify' => true,
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception('Order cancellation failed: ' . json_encode($response->json()));
        }

        return true;
    }


}
