<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hasFilter = $request->filled('search')
            || $request->filled('q')
            || $request->filled('type')
            || $request->filled('brand')
            || $request->filled('season')
            || $request->filled('size')
            || $request->filled('price_min')
            || $request->filled('price_max');

        if (! $hasFilter) {
            return response()->json([
                'data'    => [],
                'meta'    => [
                    'current_page' => 1,
                    'per_page'     => 50,
                    'total'        => 0,
                    'last_page'    => 1,
                ],
                'filters' => ['brands' => [], 'types' => [], 'seasons' => []],
                'message' => 'Please search or filter to find products.',
            ])->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
        }

        $query = Product::with('images')->where('is_active', true);

        if ($request->has('in_stock')) {
            $query->where('in_stock', (bool) $request->input('in_stock'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }
        if ($request->filled('season')) {
            $query->where('season', $request->season);
        }
        if ($request->filled('size')) {
            $query->where('size', 'like', '%' . $request->size . '%');
        }
        if ($request->filled('price_min')) {
            $query->where('price', '>=', (float) $request->price_min);
        }
        if ($request->filled('price_max')) {
            $query->where('price', '<=', (float) $request->price_max);
        }
        $searchTerm = $request->filled('q') ? $request->q : $request->input('search');
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('brand', 'like', "%{$searchTerm}%")
                  ->orWhere('name', 'like', "%{$searchTerm}%")
                  ->orWhere('size', 'like', "%{$searchTerm}%")
                  ->orWhere('sku', 'like', "%{$searchTerm}%");
            });
        }

        // Filters are derived from the current (pre-pagination) result set
        $filtersQuery = clone $query;
        $filters = [
            'brands'  => $filtersQuery->clone()->distinct()->orderBy('brand')->pluck('brand'),
            'types'   => $filtersQuery->clone()->distinct()->orderBy('type')->pluck('type'),
            'seasons' => $filtersQuery->clone()->distinct()->orderBy('season')->pluck('season'),
        ];

        match ($request->input('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default      => $query->orderByDesc('created_at'),
        };

        $perPage   = min((int) $request->input('per_page', 50), 50);
        $paginated = $query->paginate($perPage);

        $data = $paginated->map(fn ($p) => $this->formatProduct($p));

        return response()->json([
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'filters' => $filters,
            'message' => 'success',
        ])->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    public function specs(): JsonResponse
    {
        $base = Product::where('is_active', true);

        $pluck = function (string $column) use ($base) {
            return $base->clone()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderByRaw("CAST({$column} AS UNSIGNED)")
                ->pluck($column)
                ->values();
        };

        return response()->json([
            'data' => [
                'widths'       => $pluck('width'),
                'heights'      => $pluck('height'),
                'rims'         => $pluck('rim'),
                'load_indexes' => $pluck('load_index'),
                'speed_ratings' => $base->clone()
                    ->whereNotNull('speed_rating')
                    ->where('speed_rating', '!=', '')
                    ->distinct()
                    ->orderBy('speed_rating')
                    ->pluck('speed_rating')
                    ->values(),
            ],
        ])->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    public function brands(): JsonResponse
    {
        $brands = Product::where('is_active', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return response()->json(['data' => $brands])
            ->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with('images')->where('is_active', true)->findOrFail($id);

        $related = Product::where('type', $product->type)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(4)
            ->get(['id', 'brand', 'name', 'size', 'price', 'primary_image']);

        $data = $this->formatProduct($product);
        $data['related'] = $related->map(fn ($r) => [
            'id'            => $r->id,
            'brand'         => $r->brand,
            'name'          => $r->name,
            'size'          => $r->size,
            'price'         => $r->price,
            'primary_image' => $r->primary_image ? url('storage/' . $r->primary_image) : null,
        ]);

        return response()->json(['data' => $data])
            ->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    private function formatProduct(Product $p): array
    {
        return [
            'id'            => $p->id,
            'sku'           => $p->sku,
            'brand'         => $p->brand,
            'name'          => $p->name,
            'size'          => $p->size,
            'spec'          => $p->spec,
            'season'        => $p->season,
            'type'          => $p->type,
            'price'         => (float) $p->price,
            'description'   => $p->description,
            'primary_image' => $p->primary_image ? url('storage/' . $p->primary_image) : null,
            'images'        => $p->images->map(fn ($img) => url('storage/' . $img->path))->values(),
            'is_active'     => (bool) $p->is_active,
            'in_stock'      => (bool) $p->in_stock,
        ];
    }
}
