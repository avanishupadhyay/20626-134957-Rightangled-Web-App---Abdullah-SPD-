<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use Illuminate\Http\Request;

class UserLogsController extends Controller
{
    public function admin_index(Request $request)
    {
        // Fetch all user logs with pagination
        $queryObj   = UserLog::query();

        if($request->isMethod('get') && $request->input('search') == 'search')
        {
            if($request->filled('email')) {
                $queryObj->where('email', 'like', "%{$request->input('email')}%");
            }
            if($request->filled('customer_id')) {
                $queryObj->where('customer_id', 'like', "{$request->input('customer_id')}%");
            }
        }

        $queryObj->orderBy('id','desc');
        $user_logs  = $queryObj->paginate(config('Reading.nodes_per_page'));

        // Return view with data
        return view('admin.user_logs.index', compact('user_logs'));
    }
}
