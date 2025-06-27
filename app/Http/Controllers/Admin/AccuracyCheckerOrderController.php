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
use App\Models\User;
use Milon\Barcode\DNS1D;

class AccuracyCheckerOrderController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('ACT')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        })->except('index'); // <- This line skips index()
    }


    public function index(Request $request)
    {

        $dispensed = OrderDispense::pluck('order_id')->toArray();
        // Step 2: Get latest action per order, and filter if latest is 'dispensed'
        $latestApprovedOrderIds = OrderAction::latest('updated_at')
            ->get()
            ->unique('order_id') // Keep only the latest action per order_id
            ->filter(function ($action) {
                return $action->decision_status === 'dispensed';
            })
            ->pluck('order_id')
            ->toArray();
        // dd($latestApprovedOrderIds);

        // Step 3: Final query
        $query = Order::whereIn('order_number', $dispensed)
            ->whereIn('order_number', $latestApprovedOrderIds)
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



    public function ajaxView($id)
    {
        $order = Order::where('order_number', $id)->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found']);
        }

        if ($order->fulfillment_status === 'fulfilled') {
            $dispensedAction = OrderAction::where('order_id', $id)
                ->where('decision_status', 'dispensed')
                ->first();

            $checkedAction = OrderAction::where('order_id', $id)
                ->where('decision_status', 'accurately_checked')
                ->first();

            $messageParts = [];

            if ($dispensedAction) {
                $dispensedUser = User::find($dispensedAction->user_id);
                $messageParts[] = "Dispensed on {$dispensedAction->created_at} by " . ($dispensedUser->name ?? 'Unknown');
            }

            if ($checkedAction) {
                $checkedUser = User::find($checkedAction->user_id);
                $messageParts[] = "Checked on {$checkedAction->created_at} by " . ($checkedUser->name ?? 'Unknown');
            }

            return response()->json([
                'status' => 'error',
                'message' => implode(' | ', $messageParts),
            ]);
        }

        $orderData = json_decode($order->order_data, true);
        $items = $orderData['line_items'] ?? [];
        $shipping = $orderData['shipping_address'] ?? [];

        $dns = new DNS1D();

        $filteredItems = array_filter($items, function ($item) {
            return isset($item['current_quantity']) && $item['current_quantity'] > 0;
        });

        return response()->json([
            'status' => 'success',
            'order' => [
                'order_number' => $order->order_number,
                'email' => $order->email,
            ],
            'shipping_address' => [
                'name' => $shipping['name'] ?? 'N/A',
                'address1' => $shipping['address1'] ?? '',
                'address2' => $shipping['address2'] ?? '',
                'city' => $shipping['city'] ?? '',
                'zip' => $shipping['zip'] ?? '',
                'country' => $shipping['country'] ?? '',
                'phone' => $shipping['phone'] ?? '',
            ],
            'items' => array_map(function ($item) use ($dns) {
                if (!is_array($item)) {
                    return null;
                }

                $sku = $item['product_id'] ?? 'UNKNOWN_SKU';

                return [
                    'name' => $item['title'] ?? 'N/A',
                    'quantity' => $item['current_quantity'] ?? 0,
                    'price' => $item['price'] ?? '-',
                    'sku' => $sku,
                    'barcode_base64' => 'data:image/png;base64,' . $dns->getBarcodePNG($sku, 'C128', 2, 60),
                    'product_id' => $item['product_id'] ?? null,
                ];
            }, $filteredItems),
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

            if ($order->fulfillment_status === 'fulfilled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order has already been fulfilled.',
                ]);
            }

            if ($order->fulfillment_status === 'cancelled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot fulfill a cancelled order.',
                ]);
            }
            // dd("stop");

            // Step 2: Fulfill via Shopify API
            fulfillShopifyOrder($order->order_number); // Your custom logic
            triggerShopifyTimelineNote($order->order_number);

            // Step 3: Get current user role (optional safety)
            $roleName = auth()->user()?->roles?->first()?->name ?? 'unknown';

            // Step 4: Log or update order decision
            \App\Models\OrderAction::updateOrCreate(
                [
                    'order_id' => $order->order_number, // Assuming this links to Order.id (not order_number)
                    'user_id' => auth()->id(),
                ],
                [
                    'decision_status' => 'accurately_checked',
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


    // public function getProductStock($productId, $orderid)
    // {
    //     [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderid));


    //     $response = Http::withHeaders([
    //         'X-Shopify-Access-Token' => $accessToken,
    //     ])->get("{$shopDomain}/admin/api/2023-04/products/{$productId}.json");

    //     if ($response->successful()) {
    //         $product = $response->json()['product'];

    //         // Assume you're using the first variant
    //         $variant = $product['variants'][0] ?? null;
    //         $stock = $variant['inventory_quantity'] ?? 'N/A';

    //         return response()->json([
    //             'status' => 'success',
    //             'stock' => $stock,
    //             'product_title' => $product['title'] ?? '',
    //         ]);
    //     }

    //     return response()->json([
    //         'status' => 'error',
    //         'message' => 'Failed to fetch product stock from Shopify.'
    //     ]);
    // }

    public function getProductStock($productId, $orderid)
    {
        [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderid));

        $query = <<<GQL
    {
      product(id: "gid://shopify/Product/{$productId}") {
        title
        variants(first: 1) {
          edges {
            node {
              inventoryQuantity
            }
          }
        }
      }
    }
    GQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$shopDomain}/admin/api/2023-04/graphql.json", [
            'query' => $query
        ]);

        if ($response->successful()) {
            $body = $response->json();
            $product = $body['data']['product'] ?? null;

            if ($product) {
                $stock = $product['variants']['edges'][0]['node']['inventoryQuantity'] ?? 'N/A';
                return response()->json([
                    'status' => 'success',
                    'stock' => $stock,
                    'product_title' => $product['title'] ?? '',
                ]);
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch product stock from Shopify.'
        ]);
    }
}
