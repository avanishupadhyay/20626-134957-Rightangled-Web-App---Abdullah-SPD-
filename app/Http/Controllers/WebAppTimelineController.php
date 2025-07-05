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

    // public function getPrescriberLogsByOrder(Request $request)
    // {
    //     $orderId = $request->input('order_id');

    //     if (!$orderId) {
    //         return response()->json(['error' => 'Order ID is required.'], 400);
    //     }

    //     // Get prescriber logs by joining users table with audit_logs
    //     $logs = DB::table('audit_logs')
    //         ->join('users', 'audit_logs.user_id', '=', 'users.id')
    //         ->join('model_has_roles', function ($join) {
    //             $join->on('users.id', '=', 'model_has_roles.model_id')
    //                 ->where('model_has_roles.model_type', '=', \App\Models\User::class);
    //         })
    //         ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    //         ->where('roles.name', 'Prescriber')
    //         ->where('audit_logs.order_id', $orderId)
    //         ->orderBy('audit_logs.created_at', 'desc')
    //         ->select('audit_logs.*', 'users.name as user_name', 'roles.name as role_name')
    //         ->get();

    //     // Get prescribed PDF from order_actions table where decision_status is 'approved'
    //     $pdfRecord = DB::table('order_actions')
    //         ->where('order_id', $orderId)
    //         ->where('decision_status', 'approved')
    //         ->orderBy('created_at', 'desc')
    //         ->first();

    //     $prescribedPdfUrl = null;
    //     if ($pdfRecord && $pdfRecord->prescribed_pdf) {
    //         $prescribedPdfUrl = config('app.url') . '/' . ltrim($pdfRecord->prescribed_pdf, '/');
    //     }

    //     Log::info('Audit Logs for Order ID ' . $orderId, [
    //         'logs' => $logs,
    //         'prescribed_pdf' => $prescribedPdfUrl
    //     ]);

    //     return response()->json([
    //         'logs' => $logs,
    //         'prescribed_pdf' => $prescribedPdfUrl
    //     ]);
    // }

    //     public function getPrescriberLogsByOrder(Request $request)
    // {
    //     $orderId = $request->input('order_id');

    //     if (!$orderId) {
    //         return response()->json(['error' => 'Order ID is required.'], 400);
    //     }

    //     $userRoles = ['Prescriber', 'Checker'];
    //     $logsByRole = [];

    //     foreach ($userRoles as $roleName) {
    //         $logsByRole[strtolower($roleName) . '_logs'] = DB::table('audit_logs')
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
    //             ->get();
    //     }

    //     // Only fetch PDF if Prescriber log exists
    //     $pdfRecord = DB::table('order_actions')
    //         ->where('order_id', $orderId)
    //         ->where('decision_status', 'approved')
    //         ->orderBy('created_at', 'desc')
    //         ->first();

    //     $prescribedPdfUrl = null;
    //     if ($pdfRecord && $pdfRecord->prescribed_pdf) {
    //         $prescribedPdfUrl = config('app.url') . '/' . ltrim($pdfRecord->prescribed_pdf, '/');
    //     }

    //     Log::info("Audit Logs for Order ID {$orderId}", [
    //         'prescriber_logs' => $logsByRole['prescriber_logs'],
    //         'checker_logs' => $logsByRole['checker_logs'],
    //         'prescribed_pdf' => $prescribedPdfUrl
    //     ]);

    //     return response()->json([
    //         'prescriber_logs' => $logsByRole['prescriber_logs'],
    //         'checker_logs' => $logsByRole['checker_logs'],
    //         'prescribed_pdf' => $prescribedPdfUrl
    //     ]);
    // }

    // public function getPrescriberLogsByOrder(Request $request)
    // {
    //     $orderId = $request->input('order_id');

    //     if (!$orderId) {
    //         return response()->json(['error' => 'Order ID is required.'], 400);
    //     }

    //     $userRoles = ['Prescriber', 'Checker'];
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
    //             ->select(
    //                 'audit_logs.*',
    //                 'users.name as user_name',
    //                 'roles.name as role_name'
    //             )
    //             ->get()
    //             ->map(function ($log) {
    //                 if ($log->checker_prescription_file) {
    //                     $log->checker_pdf_url = asset('storage/' . ltrim($log->checker_prescription_file, '/'));
    //                 }
    //                 return $log;
    //             });

    //         $logsByRole[strtolower($roleName) . '_logs'] = $logs;
    //     }

    //     // Only fetch PDF if Prescriber log exists
    //     $pdfRecord = DB::table('order_actions')
    //         ->where('order_id', $orderId)
    //         ->where('decision_status', 'approved')
    //         ->orderBy('created_at', 'desc')
    //         ->first();

    //     $prescribedPdfUrl = null;
    //     if ($pdfRecord && $pdfRecord->prescribed_pdf) {
    //         $prescribedPdfUrl = config('app.url') . '/' . ltrim($pdfRecord->prescribed_pdf, '/');
    //     }

    //     return response()->json([
    //         'prescriber_logs' => $logsByRole['prescriber_logs'],
    //         'checker_logs' => $logsByRole['checker_logs'],
    //         'prescribed_pdf' => $prescribedPdfUrl
    //     ]);
    // }
    public function getPrescriberLogsByOrder(Request $request)
    {
        $orderId = $request->input('order_id');

        if (!$orderId) {
            return response()->json(['error' => 'Order ID is required.'], 400);
        }

        $userRoles = ['Prescriber', 'Checker', 'Admin'];
        $logsByRole = [];

        foreach ($userRoles as $roleName) {
            $logs = DB::table('audit_logs')
                ->join('users', 'audit_logs.user_id', '=', 'users.id')
                ->join('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', \App\Models\User::class);
                })
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', $roleName)
                ->where('audit_logs.order_id', $orderId)
                ->orderBy('audit_logs.created_at', 'desc')
                ->select('audit_logs.*', 'users.name as user_name', 'roles.name as role_name')
                ->get()
                ->map(function ($log) {
                    if ($log->checker_prescription_file) {
                        $log->checker_pdf_url = asset('storage/' . ltrim($log->checker_prescription_file, '/'));
                    }
                    return $log;
                });

            $logsByRole[strtolower($roleName) . '_logs'] = $logs;
        }

        $pdfRecord = DB::table('order_actions')
            ->where('order_id', $orderId)
            ->where('decision_status', 'approved')
            ->orderBy('created_at', 'desc')
            ->first();

        $prescribedPdfUrl = $pdfRecord && $pdfRecord->prescribed_pdf
            ? config('app.url') . '/' . ltrim($pdfRecord->prescribed_pdf, '/')
            : null;

        return response()->json([
            'prescriber_logs' => $logsByRole['prescriber_logs'],
            'checker_logs' => $logsByRole['checker_logs'],
            'admin_logs' => $logsByRole['admin_logs'],
            'prescribed_pdf' => $prescribedPdfUrl
        ]);
    }
}
