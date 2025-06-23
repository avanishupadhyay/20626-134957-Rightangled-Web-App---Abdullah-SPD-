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
use App\Models\OrderDispense;

class AccuracyCheckerOrderController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Admin')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        })->except('index'); // <- This line skips index()
    }


    public function index(Request $request)
    {

        $dispensed = OrderDispense::pluck('order_id')->toArray();
        // Step 2: Get latest action per order, and filter if latest is 'dispensed'
        $latestApprovedOrderIds = OrderAction::latest('created_at')
            ->get()
            ->unique('order_id') // Keep only the latest action per order_id
            ->filter(function ($action) {
                return $action->decision_status === 'dispensed';
            })
            ->pluck('order_id')
            ->toArray();

        // Step 3: Final query
        $query = Order::whereIn('order_number', $dispensed)
            ->whereIn('id', $latestApprovedOrderIds)
            ->whereNull('fulfillment_status')
            ->where(function ($q) {
                $q->whereRaw("JSON_EXTRACT(order_data, '$.cancelled_at') IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.cancelled_at')) = 'null'");
            });

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
            // return $order;
            $orderDispense = \App\Models\OrderDispense::where('order_id', $order->order_number)->first();
            if ($orderDispense) {
                $order->dispense = $orderDispense;

                // Fetch batch number
                $batch = \App\Models\DispenseBatch::find($orderDispense->batch_id);
                $order->batch_number = $batch->batch_number ?? null;
                $order->dispensed_by = $batch->user->name ?? null;
            } else {
                $order->dispense = null;
                $order->batch_number = null;
                $order->dispensed_by = null;
            }

            return $order;
        });
        return view('admin.accuracy_checker.index', compact('orders'));
    }




    // public function view($id)
    // {
    //     $order = Order::where('order_number',$id)->get();
    //     $orderMetafields = getOrderMetafields($order->order_number) ?? null;
    //     // dd($orderMetafields);

    //     $orderData = json_decode($order->order_data, true);
    //     return view('admin.accuracy_checker.view', compact('order', 'orderData', 'orderMetafields'));
    // }

    public function ajaxView($id)
    {
        $order = Order::where('order_number', $id)->first();
        // dd( $order );

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found']);
        }

        $items = json_decode($order->order_data, true)['line_items'] ?? [];

        return response()->json([
            'status' => 'success',
            'order' => [
                'order_number' => $order->order_number,
                'email' => $order->email,
            ],
            'items' => array_map(function ($item) {
                return [
                    'name' => $item['title'] ?? 'N/A',
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => $item['price'] ?? '-',

                ];
            }, $items)
        ]);
    }



    // public function fulfill($id)
    // {
    //     $order = Order::where('order_number', $id)->first();
    //     if (!$order) {
    //         return response()->json(['status' => 'error', 'message' => 'Order not found']);
    //     }
    //     try {
    //         // 1. Fulfill via Shopify API (your logic here)
    //         fulfillShopifyOrder($order->order_number); // Use your method
    //         $order->fulfillment_status = 'fulfilled';
    //         $order->save();

    //         return response()->json(['status' => 'success']);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    //     }
    // }

    public function fulfill($id)
    {
        try {
            // Step 1: Find order by order_number
            $order = Order::where('order_number', $id)->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ]);
            }

            // Step 2: Fulfill via Shopify API
            fulfillShopifyOrder($order->order_number); // Your custom logic

            // Step 3: Get current user role (optional safety)
            $roleName = auth()->user()?->roles?->first()?->name ?? 'unknown';

            // Step 4: Log or update order decision
            \App\Models\OrderAction::updateOrCreate(
                [
                    'order_id' => $order->id, // Assuming this links to Order.id (not order_number)
                    'user_id' => auth()->id(),
                ],
                [
                    'decision_status' => 'dispensed',
                    'decision_timestamp' => now(),
                    'role' => $roleName,
                ]
            );

            // Step 5: Update fulfillment status
            $order->fulfillment_status = 'fulfilled';
            $order->save();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
