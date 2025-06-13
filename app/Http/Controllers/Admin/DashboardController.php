<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\OrderAction;

class DashboardController extends Controller
{
    public function index(Request $request)
    {

        $months = collect(range(0, 5))->map(function ($i) {
            return Carbon::now()->subMonths($i)->format('Y-m');
        })->reverse();

        $data = $months->map(function ($month) {
            $year = substr($month, 0, 4);
            $monthNumber = substr($month, 5, 2);

            // Total orders in this month
            $total = DB::table('orders')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $monthNumber)
                ->count();

            // Approved logic
            $approved = DB::table('orders')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $monthNumber)
                ->where(function ($query) {
                    $query
                        // Case 1: Admin approved
                        ->whereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('order_actions')
                                ->whereRaw('order_actions.order_id = orders.order_number')
                                ->where('decision_status', 'approved')
                                ->where('role', 'Admin');
                        })
                        // OR Case 2: ACT approved AND NOT rejected/on-hold by Admin
                        ->orWhere(function ($subQuery) {
                            $subQuery
                                ->whereExists(function ($sub) {
                                    $sub->select(DB::raw(1))
                                        ->from('order_actions')
                                        ->whereRaw('order_actions.order_id = orders.order_number')
                                        ->where('decision_status', 'approved')
                                        ->where('role', 'ACT');
                                })
                                ->whereNotExists(function ($sub) {
                                    $sub->select(DB::raw(1))
                                        ->from('order_actions')
                                        ->whereRaw('order_actions.order_id = orders.order_number')
                                        ->whereIn('decision_status', ['on-hold', 'rejected'])
                                        ->where('role', 'Admin');
                                });
                        });
                })
                ->count();

            return [
                'month' => $month,
                'total' => $total,
                'approved' => $approved,
            ];
        })->values();

        $start = $request->start_date;
        $end = $request->end_date;

        // -----------Total Approved Orders------------------
        $approved_count = Order::where(function ($query) use ($start, $end) {
            if ($start && $end) {
                $query->whereBetween('orders.created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);
            }

            $query->where(function ($subQuery) {
                $subQuery
                    // Case 1: Admin approved
                    ->whereHas('orderAction', function ($q) {
                        $q->where('role', 'Admin')
                            ->where('decision_status', 'approved');
                    })
                    // OR: No Admin action AND ACT approved
                    ->orWhere(function ($q) {
                        $q->whereDoesntHave('orderAction', function ($sub) {
                            $sub->where('role', 'Admin');
                        })
                            ->whereHas('orderAction', function ($sub) {
                                $sub->where('role', 'ACT')
                                    ->where('decision_status', 'approved');
                            });
                    });
            });
        })->count();

        // -----------Total Orders------------------
        $query = DB::table('orders');

        if ($start && $end) {
            $query->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);
        }

        $current_month_order = $query->count();

        if (!$request) {
            $current_month_order = DB::table('orders')
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count();
        }

        return view('admin.dashboard', compact('data', 'current_month_order', 'approved_count'));
    }
}
