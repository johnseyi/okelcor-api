<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage   = min((int) $request->input('per_page', 25), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ContactMessage::findOrFail($id)]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'      => ['required', Rule::in(['new', 'read', 'replied'])],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $message = ContactMessage::findOrFail($id);
        $message->update([
            'status'      => $request->status,
            'admin_notes' => $request->admin_notes ?? $message->admin_notes,
        ]);

        return response()->json(['data' => $message->fresh()]);
    }
}
