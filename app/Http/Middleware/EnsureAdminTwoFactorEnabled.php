<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block admin users who have not confirmed 2FA from reaching protected routes.
 *
 * 2FA is mandatory for all admin users. Admins who log in without 2FA configured
 * receive a temp_token and must complete the setup flow before a session is issued.
 * This middleware is the belt-and-suspenders check in case any legacy tokens exist.
 *
 * Always allows:
 *   - /admin/me, /admin/logout, /admin/profile
 *   - /admin/2fa/*  (enable, confirm, disable, setup/*)
 *   - /admin/security/*
 *
 * Optional grace period: set ADMIN_2FA_GRACE_UNTIL=YYYY-MM-DD in .env to allow
 * a transitional window during staged rollouts. Leave unset for immediate enforcement.
 */
class EnsureAdminTwoFactorEnabled
{
    private const ALLOWED_PATHS = [
        'api/v1/admin/me',
        'api/v1/admin/logout',
        'api/v1/admin/profile',
    ];

    private const ALLOWED_PREFIXES = [
        'api/v1/admin/2fa',
        'api/v1/admin/security',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Grace period — bypass enforcement until the configured date (staged rollouts)
        $graceUntil = config('auth.admin_2fa_grace_until');
        if ($graceUntil && now()->lt(Carbon::parse($graceUntil)->endOfDay())) {
            return $next($request);
        }

        $user = $request->user();

        // No user (unauthenticated) or user already has 2FA — let through
        if (! $user || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        if (in_array($path, self::ALLOWED_PATHS, true)) {
            return $next($request);
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Two-factor authentication is required before accessing the admin panel. Please enable 2FA to continue.',
            'code'    => 'two_factor_required',
        ], 428);
    }
}
