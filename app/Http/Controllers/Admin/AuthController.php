<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $ip  = $request->ip();
        $key = 'admin-login:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Admin login blocked — rate limit exceeded', [
                'ip'    => $ip,
                'email' => $request->email,
            ]);
            return response()->json([
                'message' => "Too many failed login attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        $admin = AdminUser::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            RateLimiter::hit($key, 60);
            Log::warning('Admin login failed', [
                'email' => $request->email,
                'ip'    => $ip,
            ]);
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors'  => ['email' => ['The provided credentials are incorrect.']],
            ], 422);
        }

        if (! $admin->is_active) {
            RateLimiter::hit($key, 60);
            Log::warning('Admin login attempt on inactive account', [
                'email' => $request->email,
                'ip'    => $ip,
            ]);
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        RateLimiter::clear($key);

        $admin->tokens()->delete();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        Log::info("Admin login: {$admin->email} from IP {$ip}");

        $response = [
            'token' => $token,
            'user'  => $this->formatUser($admin),
        ];

        if ($admin->must_change_password) {
            $response['must_change_password'] = true;
        }

        return response()->json(['data' => $response]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->formatUser($request->user())]);
    }

    private function formatUser(AdminUser $u): array
    {
        return [
            'id'                  => $u->id,
            'name'                => $u->name,
            'first_name'          => $u->first_name,
            'last_name'           => $u->last_name,
            'display_name'        => $u->display_name,
            'email'               => $u->email,
            'role'                => $u->role,
            'last_login_at'       => $u->last_login_at?->toIso8601String(),
            'must_change_password' => (bool) $u->must_change_password,
        ];
    }
}
