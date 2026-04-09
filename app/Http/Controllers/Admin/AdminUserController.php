<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    // -------------------------------------------------------------------------
    // Own profile — available to all authenticated admin roles
    // -------------------------------------------------------------------------

    /**
     * GET /admin/profile
     * Returns the authenticated user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->formatUser($request->user())]);
    }

    /**
     * PUT /admin/profile
     * Update own name and/or email.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:200'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('admin_users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'data'    => $this->formatUser($user->fresh()),
            'message' => 'Profile updated.',
        ]);
    }

    /**
     * PUT /admin/profile/password
     * Change own password. Requires current password confirmation.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors'  => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => $request->password]);

        // Revoke all other tokens so any existing sessions must re-login
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // -------------------------------------------------------------------------
    // User management — super_admin only
    // -------------------------------------------------------------------------

    /**
     * GET /admin/users
     * List all admin users.
     */
    public function index(): JsonResponse
    {
        $users = AdminUser::orderBy('name')->get();

        return response()->json([
            'data'    => $users->map(fn ($u) => $this->formatUser($u)),
            'message' => 'success',
        ]);
    }

    /**
     * POST /admin/users
     * Create a new admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'max:255', 'unique:admin_users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role'     => ['required', Rule::in(['super_admin', 'admin', 'editor', 'order_manager'])],
        ]);

        $user = AdminUser::create($validated);

        return response()->json([
            'data'    => $this->formatUser($user),
            'message' => 'Admin user created.',
        ], 201);
    }

    /**
     * GET /admin/users/{id}
     * Get a single admin user.
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->formatUser(AdminUser::findOrFail($id))]);
    }

    /**
     * PUT /admin/users/{id}
     * Update a user's name, email, or role. Password reset also supported.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = AdminUser::findOrFail($id);

        $validated = $request->validate([
            'name'     => ['sometimes', 'string', 'max:200'],
            'email'    => ['sometimes', 'email', 'max:255', Rule::unique('admin_users', 'email')->ignore($id)],
            'role'     => ['sometimes', Rule::in(['super_admin', 'admin', 'editor', 'order_manager'])],
            'password' => ['sometimes', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update($validated);

        // If a super_admin resets someone's password, revoke that user's tokens
        if (isset($validated['password'])) {
            $user->tokens()->delete();
        }

        return response()->json([
            'data'    => $this->formatUser($user->fresh()),
            'message' => 'Admin user updated.',
        ]);
    }

    /**
     * DELETE /admin/users/{id}
     * Delete an admin user. Cannot delete yourself.
     */
    public function destroy(Request $request, int $id): Response
    {
        if ($request->user()->id === $id) {
            abort(422, 'You cannot delete your own account.');
        }

        $user = AdminUser::findOrFail($id);
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    // -------------------------------------------------------------------------

    private function formatUser(AdminUser $u): array
    {
        return [
            'id'            => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'role'          => $u->role,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'created_at'    => $u->created_at?->toIso8601String(),
        ];
    }
}
