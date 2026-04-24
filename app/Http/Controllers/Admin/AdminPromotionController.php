<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPromotionController extends Controller
{
    public function index(): JsonResponse
    {
        $promotions = Promotion::orderByDesc('created_at')->get();

        return response()->json([
            'data'    => $promotions->map(fn ($p) => $this->format($p)),
            'message' => 'success',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'subheadline'  => ['nullable', 'string', 'max:300'],
            'button_text'  => ['nullable', 'string', 'max:100'],
            'button_link'  => ['nullable', 'string', 'max:300'],
            'is_active'    => ['nullable', 'boolean'],
            'start_date'   => ['nullable', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if (! empty($data['is_active'])) {
            Promotion::query()->update(['is_active' => false]);
        }

        $promotion = Promotion::create($data);

        return response()->json([
            'data'    => $this->format($promotion),
            'message' => 'Promotion created.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        $data = $request->validate([
            'title'        => ['sometimes', 'string', 'max:200'],
            'subheadline'  => ['nullable', 'string', 'max:300'],
            'button_text'  => ['nullable', 'string', 'max:100'],
            'button_link'  => ['nullable', 'string', 'max:300'],
            'is_active'    => ['nullable', 'boolean'],
            'start_date'   => ['nullable', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Activating this promotion deactivates all others
        if (isset($data['is_active']) && $data['is_active']) {
            Promotion::where('id', '!=', $id)->update(['is_active' => false]);
        }

        $promotion->update($data);

        return response()->json([
            'data'    => $this->format($promotion->fresh()),
            'message' => 'Promotion updated.',
        ]);
    }

    public function toggle(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        if (! $promotion->is_active) {
            // Activating — deactivate all others first
            Promotion::where('id', '!=', $id)->update(['is_active' => false]);
            $promotion->update(['is_active' => true]);
        } else {
            $promotion->update(['is_active' => false]);
        }

        return response()->json([
            'data'    => $this->format($promotion->fresh()),
            'message' => 'Promotion ' . ($promotion->is_active ? 'activated' : 'deactivated') . '.',
        ]);
    }

    public function destroy(int $id): \Illuminate\Http\Response
    {
        $promotion = Promotion::findOrFail($id);

        if ($promotion->image_url) {
            Storage::disk('public')->delete($promotion->image_url);
        }

        $promotion->delete();

        return response()->noContent();
    }

    public function uploadMedia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        $promotion = Promotion::findOrFail($id);

        // Remove old banner
        if ($promotion->image_url) {
            Storage::disk('public')->delete($promotion->image_url);
        }

        $ext      = $request->file('image')->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $ext;
        $path     = Storage::disk('public')->putFileAs('promotions', $request->file('image'), $filename);

        $promotion->update(['image_url' => $path]);

        return response()->json([
            'data'    => ['image_url' => url(Storage::url($path))],
            'message' => 'Banner uploaded.',
        ]);
    }

    private function format(Promotion $p): array
    {
        return [
            'id'           => $p->id,
            'title'        => $p->title,
            'subheadline'  => $p->subheadline,
            'button_text'  => $p->button_text,
            'button_link'  => $p->button_link,
            'image_url'    => $p->image_url ? url(Storage::url($p->image_url)) : null,
            'is_active'    => $p->is_active,
            'start_date'   => $p->start_date?->toDateString(),
            'end_date'     => $p->end_date?->toDateString(),
            'created_at'   => $p->created_at?->toIso8601String(),
        ];
    }
}
