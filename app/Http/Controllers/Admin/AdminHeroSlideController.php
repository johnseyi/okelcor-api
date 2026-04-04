<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminHeroSlideController extends Controller
{
    public function index(): JsonResponse
    {
        $slides = HeroSlide::orderBy('sort_order')->get();

        return response()->json([
            'data'    => $slides->map(fn ($s) => $this->formatSlide($s))->values(),
            'message' => 'success',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->textRules());

        $slide = HeroSlide::create([
            'title'               => $request->title,
            'subtitle'            => $request->subtitle,
            'media_type'          => $request->input('media_type', 'image'),
            'sort_order'          => $request->input('order', 1),
            'cta_primary_label'   => $request->cta_primary_label,
            'cta_primary_href'    => $request->cta_primary_href,
            'cta_secondary_label' => $request->cta_secondary_label,
            'cta_secondary_href'  => $request->cta_secondary_href,
            'is_active'           => true,
        ]);

        return response()->json([
            'data'    => $this->formatSlide($slide),
            'message' => 'Hero slide created.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->formatSlide(HeroSlide::findOrFail($id))]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate($this->textRules());

        $slide = HeroSlide::findOrFail($id);
        $slide->update([
            'title'               => $request->title,
            'subtitle'            => $request->subtitle,
            'media_type'          => $request->input('media_type', $slide->media_type),
            'sort_order'          => $request->input('order', $slide->sort_order),
            'cta_primary_label'   => $request->cta_primary_label,
            'cta_primary_href'    => $request->cta_primary_href,
            'cta_secondary_label' => $request->cta_secondary_label,
            'cta_secondary_href'  => $request->cta_secondary_href,
        ]);

        return response()->json([
            'data'    => $this->formatSlide($slide->fresh()),
            'message' => 'Hero slide updated.',
        ]);
    }

    public function uploadMedia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'media'      => ['required', 'file', 'max:51200'],
            'media_type' => ['required', Rule::in(['image', 'video'])],
        ]);

        $slide = HeroSlide::findOrFail($id);

        // Delete old file for whichever type we're replacing
        if ($request->media_type === 'image' && $slide->image_url) {
            Storage::disk('public')->delete($slide->image_url);
        }
        if ($request->media_type === 'video' && $slide->video_url) {
            Storage::disk('public')->delete($slide->video_url);
        }

        $file     = $request->file('media');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = Storage::disk('public')->putFileAs('hero', $file, $filename);

        if ($request->media_type === 'image') {
            $slide->update([
                'media_type' => 'image',
                'image_url'  => $path,
                'video_url'  => null,
            ]);
        } else {
            $slide->update([
                'media_type' => 'video',
                'video_url'  => $path,
                'image_url'  => null,
            ]);
        }

        return response()->json([
            'data'    => $this->formatSlide($slide->fresh()),
            'message' => 'Media uploaded.',
        ]);
    }

    public function destroy(int $id): Response
    {
        $slide = HeroSlide::findOrFail($id);

        if ($slide->image_url) {
            Storage::disk('public')->delete($slide->image_url);
        }
        if ($slide->video_url) {
            Storage::disk('public')->delete($slide->video_url);
        }

        $slide->delete();

        return response()->noContent();
    }

    private function textRules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:300'],
            'subtitle'            => ['nullable', 'string', 'max:1000'],
            'media_type'          => ['nullable', Rule::in(['image', 'video'])],
            'order'               => ['nullable', 'integer'],
            'cta_primary_label'   => ['nullable', 'string', 'max:100'],
            'cta_primary_href'    => ['nullable', 'string', 'max:500'],
            'cta_secondary_label' => ['nullable', 'string', 'max:100'],
            'cta_secondary_href'  => ['nullable', 'string', 'max:500'],
        ];
    }

    private function formatSlide(HeroSlide $s): array
    {
        return [
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
        ];
    }
}
