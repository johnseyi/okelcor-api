<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    // ── GET /admin/security/summary ───────────────────────────────────────────

    public function summary(): JsonResponse
    {
        $today = now()->startOfDay();

        $lockedToday = SecurityEvent::where('type', 'account_lockout')
            ->where('created_at', '>=', $today)
            ->count();

        $failedAttemptsToday = LoginHistory::where('success', false)
            ->where('created_at', '>=', $today)
            ->count();

        $newRegistrationsToday = Customer::where('created_at', '>=', $today)->count();

        $suspiciousAccounts = Customer::where('status', 'suspended')->count();

        $suspendedToday = SecurityEvent::where('type', 'account_suspend')
            ->where('created_at', '>=', $today)
            ->count();

        $bannedToday = SecurityEvent::where('type', 'account_ban')
            ->where('created_at', '>=', $today)
            ->count();

        return response()->json([
            'data' => [
                'locked_today'           => $lockedToday,
                'failed_attempts_today'  => $failedAttemptsToday,
                'new_registrations_today' => $newRegistrationsToday,
                'suspicious_accounts'    => $suspiciousAccounts,
                'suspended_today'        => $suspendedToday,
                'banned_today'           => $bannedToday,
            ],
        ]);
    }

    // ── GET /admin/security/events ────────────────────────────────────────────

    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'type'        => ['nullable', 'in:failed_login,suspicious_activity,new_registration,password_reset,account_changes,account_lockout,account_unlock,account_suspend,account_ban'],
            'customer_id' => ['nullable', 'integer'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = SecurityEvent::with('customer:id,email')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        $paginated = $query->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $paginated->map(fn ($e) => [
                'id'             => $e->id,
                'type'           => $e->type,
                'severity'       => $e->severity,
                'description'    => $e->description,
                'customer_id'    => $e->customer_id,
                'customer_email' => $e->customer?->email,
                'ip_address'     => $e->ip_address,
                'user_agent'     => $e->user_agent,
                'location'       => $e->location,
                'created_at'     => $e->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }
}
