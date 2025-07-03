<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class WebAppTimelineController extends Controller
{
  public function getPrescriberLogsByOrder(Request $request)
    {
        $orderId = $request->input('order_id');
 
        if (!$orderId) {
            return response()->json(['error' => 'Order ID is required.'], 400);
        }
 
        // Get all user IDs who have the "Prescriber" role (using Spatie)
        $prescriberUserIds = User::role('Prescriber')->pluck('id');
 
        // Filter audit logs for the given order and prescriber users
        $logs = AuditLog::where('order_id', $orderId)
            ->whereIn('user_id', $prescriberUserIds)
            ->orderBy('created_at', 'desc')
            ->get();
        Log::info('Audit Logs for Order ID ' . $orderId, [
            'logs' => $logs->toArray()
        ]);
 
        return response()->json($logs);
    }
}
