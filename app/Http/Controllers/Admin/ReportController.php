<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderAction;
use App\Models\Store;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Response;
use App\Models\User;


class ReportController extends Controller
{
    //
    function __construct() {}

    public function index(Request $request)
    {
        $query = OrderAction::select('order_actions.*', 'orders.store_id', 'orders.order_data')
            ->join('orders', 'order_actions.order_id', '=', 'orders.order_number');

        if ($request->filled('store')) {
            $query->where('store_id', $request->store);
        }

        if ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('metrics')) {
            $query->where('decision_status', $request->metrics);
        }

        if ($request->filled('sku')) {
            $query->whereJsonContains('order_data->line_items', [['sku' => $request->sku]]);
        }

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->from . 'T00:00:00+01:00';
            $to = $request->to . 'T23:59:59+01:00';
            $query->whereRaw("
                JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.created_at')) BETWEEN ? AND ?
            ", [$from, $to]);
        }

        $orders = $query->paginate(config('Reading.nodes_per_page'));
       
        $orderActions = OrderAction::with('user.roles')
            ->when(!empty($request->metrics), function ($query) use ($request) {
                $query->where('decision_status', $request->metrics);
            })->get();

        $roleWiseCounts = [];

        foreach ($orderActions as $orderAction) {
            $user = $orderAction->user;

            if ($user) {
                $roles = $user->getRoleNames();

                foreach ($roles as $role) {
                    if (!isset($roleWiseCounts[$role])) {
                        $roleWiseCounts[$role] = 0;
                    }
                    $roleWiseCounts[$role]++;
                }
            }
        }
        // pr($roleWiseCounts);die;


        // Filter: SKU
        // if ($request->filled('sku')) {
        //     $query->whereJsonContains('order_data->line_items', [['sku' => $request->sku]]);
        // }

        // Filter: User (customer first name)
        // if ($request->filled('user')) {
        //     $query->where('order_data->customer->first_name', $request->user);
        // }

        // Filter: Role – assuming orders are linked to users who have roles
        // if ($request->filled('role')) {
        //     $query->whereHas('user.roles', function ($q) use ($request) {
        //         $q->where('name', $request->role);
        //     });
        // }

        // Filter: Date range (example)
        //  if ($request->filled('from') && $request->filled('to')) {
        //     $from = $request->from . 'T00:00:00+01:00';
        //     $to = $request->to . 'T23:59:59+01:00';

        //     $query->whereRaw("
        //         JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.created_at')) BETWEEN ? AND ?
        //     ", [$from, $to]);
        // }
        // You can handle metrics/report_type filters similarly...

        // Get paginated filtered results
        // $orders = $query->paginate(config('Reading.nodes_per_page'))
        //                 ->appends($request->except('page')); // preserve filters in pagination

        // Get roles, stores, and users for filters
        $roles = \Spatie\Permission\Models\Role::pluck('name', 'name');
        $store = Store::select('id', 'name')->get()->toArray();

        // Get unique users from order_data
        $users = [];
        $users = User::select('id', 'name')->get()->toArray();

        return view('admin.report.index', compact('orders', 'roles', 'store', 'users', 'roleWiseCounts'));
    }

    // function index()
    // {

    //     $orders = Order::paginate(config('Reading.nodes_per_page'));
    //     $roles = \Spatie\Permission\Models\Role::pluck('name', 'name');
    //     $store = Store::select('id', 'name')->get()->toArray();

    //     $users = [];

    //       foreach($orders as $key=>$value){
    //         $order_data = json_decode($value['order_data'],true);
    //         $users[] = isset($order_data['customer']['first_name']) ? $order_data['customer']['first_name'] : '';
    //       }

    //     return view('admin.report.index', compact('orders', 'roles', 'store', 'users'));
    // }

    public function export(Request $request)
    {
        $query = OrderAction::select('order_actions.*', 'orders.store_id', 'orders.order_data')
            ->join('orders', 'order_actions.order_id', '=', 'orders.order_number');

        if ($request->filled('store')) {
            $query->where('store_id', $request->store);
        }

        if ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('metrics')) {
            $query->where('decision_status', $request->metrics);
        }

        if ($request->filled('sku')) {
            $query->whereJsonContains('order_data->line_items', [['sku' => $request->sku]]);
        }

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->from . 'T00:00:00+01:00';
            $to = $request->to . 'T23:59:59+01:00';
            $query->whereRaw("
                JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.created_at')) BETWEEN ? AND ?
            ", [$from, $to]);
        }

        $orders = $query->get();

        // Create CSV content
        $headers = [
            'Order ID',
            'Action',
            'Role',
            'SKU',
            'Clinical Notes Snippet',
            'Rejection Reason',
            'On Hold Reason',
        ];

        $rows = [];

        foreach ($orders as $key => $value) {

            $order_values = getOrderData($value['order_id']);

            $sku_txt = '';
            $sku = [];

            $order_data = json_decode($order_values['order_data'], true);
            if (isset($order_data['line_items']) && is_array($order_data['line_items'])) {
                foreach ($order_data['line_items'] as $skey => $svalue) {
                    if (isset($svalue['sku'])) {
                        $sku[] = $svalue['sku'];
                    }
                }
                $sku_txt = implode(',', $sku);
            }

            $status = '';
            if ($value['decision_status'] == "on_hold") {
                $status = 'On Hold';
            } else {
                $status = ucfirst($value['decision_status']);
            }
            $rows[] = [
                $value['order_id'],
                $status,
                $value['role'],
                $sku_txt,
                ($value['clinical_reasoning'] ?? '-'),
                ($value['rejection_reason '] ?? '-'),
                ($value['on_hold_reason'] ?? '-'),
            ];
        }

        // Build CSV content
        $filename = 'filtered_orders_export_' . now()->format('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}
