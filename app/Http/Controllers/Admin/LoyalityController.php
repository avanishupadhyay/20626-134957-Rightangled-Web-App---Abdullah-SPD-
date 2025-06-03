<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loyality;
use Illuminate\Http\Request;

class LoyalityController extends Controller
{
    public function admin_index(Request $request)
    {
        $queryObj   = Loyality::query();

        if($request->isMethod('get') && $request->input('search') == 'search')
        {
            if($request->filled('order_number')) {
                $queryObj->where('order_number', 'like', $request->input('order_number').'%');
            }

            if($request->filled('customer_email')) {
                $queryObj->where('customer_email', 'like', "%{$request->input('customer_email')}%");
            }

            if($request->filled('status')) {
                $queryObj->where('status', $request->input('status'));
            }
        }

        $queryObj->orderBy('id','desc');
        $loyalities  = $queryObj->paginate(config('Reading.nodes_per_page'));

        return view('admin.loyalities.index', compact('loyalities'));
    }
}
