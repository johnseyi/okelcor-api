<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When ADMIN_2FA_ENFORCED=true (and any grace period has passed), block admin
 * users who have not confirmed 2FA from reaching protected routes.
 *
 * Always allows: /admin/me, /admin/logout, /admin/2fa/*, /admin/security/*
 * so users can still check status, enable 2FA, and log out.
 *
 * To enable enforcement, set in .env:
 *   ADMIN_2FA_ENFORCED=true
 *   ADMIN_2FA_GRACE_UNTIL=2026-06-01   # optional — enforcement begins after this date
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
        if (! config('auth.admin_2fa_enforced', false)) {
            return $next($request);
        }

        $graceUntil = config('auth.admin_2fa_grace_until');
        if ($graceUntil && now()->lt(\Carbon\Carbon::parse($graceUntil)->endOfDay())) {
            return $next($request);
        }

        $user = $request->user();
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
            'message' => 'Two-factor authentication is required. Please enable 2FA before continuing.',
            'requires_2fa_setup' => true,
        ], 403);
    }
}
