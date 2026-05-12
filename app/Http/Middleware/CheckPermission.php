<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes:
 *   ->middleware('permission:orders.update')
 *
 * Derives access from AdminPermissions::MAP — never from raw role strings.
 * Logs all denied attempts on sensitive endpoints for audit trail.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user && AdminPermissions::can($user->role, $permission)) {
            return $next($request);
        }

        Log::warning('Admin permission denied', [
            'admin_id'             => $user?->id,
            'role'                 => $user?->role,
            'permission_attempted' => $permission,
            'route'                => $request->path(),
            'method'               => $request->method(),
            'ip'                   => $request->ip(),
            'timestamp'            => now()->toIso8601String(),
        ]);

        return response()->json([
            'message'    => 'Forbidden. You do not have permission to perform this action.',
            'permission' => $permission,
        ], 403);
    }
}
