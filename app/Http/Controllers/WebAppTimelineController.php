<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class WebAppTimelineController extends Controller
{

    //  old workong code
    // public function getPrescriberLogsByOrder(Request $request)
    // {
    //     $orderId = $request->input('order_id');

    //     if (!$orderId) {
    //         return response()->json(['error' => 'Order ID is required.'], 400);
    //     }

    //     $userRoles = ['Prescriber', 'Checker', 'Admin'];
    //     $logsByRole = [];

    //     foreach ($userRoles as $roleName) {
    //         $logs = DB::table('audit_logs')
    //             ->join('users', 'audit_logs.user_id', '=', 'users.id')
    //             ->join('model_has_roles', function ($join) {
    //                 $join->on('users.id', '=', 'model_has_roles.model_id')
    //                     ->where('model_has_roles.model_type', '=', \App\Models\User::class);
    //             })
    //             ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    //             ->where('roles.name', $roleName)
    //             ->where('audit_logs.order_id', $orderId)
    //             ->orderBy('audit_logs.created_at', 'desc')
    //             ->select('audit_logs.*', 'users.name as user_name', 'roles.name as role_name')
    //             ->get()
    //             ->map(function ($log) {
    //                 if ($log->checker_prescription_file) {
    //                     $log->checker_pdf_url = asset('storage/' . ltrim($log->checker_prescription_file, '/'));
    //                 }
    //                 return $log;
    //             });

    //         $logsByRole[strtolower($roleName) . '_logs'] = $logs;
    //     }

    //     $pdfRecord = DB::table('order_actions')
    //         ->where('order_id', $orderId)
    //         ->where('decision_status', 'approved')
    //         ->orderBy('created_at', 'desc')
    //         ->first();

    //     $prescribedPdfUrl = $pdfRecord && $pdfRecord->prescribed_pdf
    //         ? config('app.url') . '/' . ltrim($pdfRecord->prescribed_pdf, '/')
    //         : null;

    //     return response()->json([
    //         'prescriber_logs' => $logsByRole['prescriber_logs'],
    //         'checker_logs' => $logsByRole['checker_logs'],
    //         'admin_logs' => $logsByRole['admin_logs'],
    //         'prescribed_pdf' => $prescribedPdfUrl
    //     ]);
    // }

   

    public function getPrescriberLogsByOrder(Request $request)
    {
        $orderId = $request->input('order_id');

        if (!$orderId) {
            return response()->json(['error' => 'Order ID is required.'], 400);
        }

        // Subquery: Get the latest approved order_action per order_id with updated_at
        $latestApprovedActions = DB::table('order_actions as oa1')
            ->select('oa1.order_id', 'oa1.prescribed_pdf', 'oa1.updated_at')
            ->where('oa1.decision_status', 'approved')
            ->whereRaw('oa1.updated_at = (
        SELECT MAX(oa2.updated_at)
        FROM order_actions oa2
        WHERE oa2.order_id = oa1.order_id
          AND oa2.decision_status = "approved"
    )');

        // Main logs query
        $logs = DB::table('audit_logs')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->leftJoinSub($latestApprovedActions, 'latest_actions', function ($join) {
                $join->on('audit_logs.order_id', '=', 'latest_actions.order_id');
            })
            ->where('audit_logs.order_id', $orderId)
            ->orderBy('audit_logs.created_at', 'desc')
            ->select(
                'audit_logs.*',
                'users.name as user_name',
                'roles.name as role_name',
                'latest_actions.prescribed_pdf',
                'latest_actions.updated_at as prescribed_pdf_updated_at'
            )
            ->get()
            ->map(function ($log) {
                if ($log->checker_prescription_file) {
                    $log->checker_pdf_url = asset('storage/' . ltrim($log->checker_prescription_file, '/'));
                }

                if ($log->action === 'approved' && $log->prescribed_pdf) {
                    $log->prescribed_pdf_url = config('app.url') . '/' . ltrim($log->prescribed_pdf, '/');
                }

                return $log;
            });

        return response()->json([
            'logs' => $logs,
        ]);
    }
}
