<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;


class OrderController extends Controller
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
        return view('orders.index', compact('orders', 'statuses'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderMetafields = getOrderMetafields($order->order_number) ?? null;

        $orderData = json_decode($order->order_data, true);

        return view('orders.view', compact('order', 'orderData','orderMetafields'));
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


        return Pdf::loadView('orders.prescription_pdf', [
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
}
