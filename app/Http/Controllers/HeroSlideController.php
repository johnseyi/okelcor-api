<?php

namespace App\Http\Controllers;

use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class HeroSlideController extends Controller
{
    public function index(): JsonResponse
    {
        $slides = HeroSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data'    => $slides->map(fn ($s) => [
                'id'                  => $s->id,
                'title'               => $s->title,
                'subtitle'            => $s->subtitle,
                'media_type'          => $s->media_type ?? 'image',
                'image_url'           => $s->image_url ? url(Storage::url($s->image_url)) : null,
                'video_url'           => $s->video_url ? url(Storage::url($s->video_url)) : null,
                'order'               => $s->sort_order,
                'cta_primary_label'   => $s->cta_primary_label,
                'cta_primary_href'    => $s->cta_primary_href,
                'cta_secondary_label' => $s->cta_secondary_label,
                'cta_secondary_href'  => $s->cta_secondary_href,
            ])->values(),
            'message' => 'success',
        ]);
    }
}
