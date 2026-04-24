<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    public function active(): JsonResponse
    {
        $promotion = Promotion::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->first();

        return response()->json([
            'data' => $promotion ? $this->format($promotion) : null,
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
