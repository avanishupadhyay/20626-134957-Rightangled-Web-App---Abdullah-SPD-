<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function admin_index(Request $request)
    {
        $queryObj   = Order::query();

        if($request->isMethod('get') && $request->input('search') == 'search')
        {
            if($request->filled('order_number')) {
                $queryObj->where('order_number', 'like', $request->input('order_number').'%');
            }

            if($request->filled('customer_id')) {
                $queryObj->where('customer_id', 'like', $request->input('customer_id').'%');
            }

            if($request->filled('customer_email')) {
                $queryObj->where('customer_email', 'like', '%'.$request->input('customer_email').'%');
            }
        }

        $queryObj->orderBy('id','desc');
        $orders  = $queryObj->paginate(config('Reading.nodes_per_page'));

        return view('admin.orders.index', compact('orders'));
    }
}
