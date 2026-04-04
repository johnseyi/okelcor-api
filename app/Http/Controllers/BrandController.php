<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::whereNotNull('logo')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data'    => $brands->map(fn ($b) => [
                'id'       => $b->id,
                'name'     => $b->name,
                'logo_url' => url(Storage::url($b->logo)),
            ])->values(),
            'message' => 'success',
        ]);
    }
}
