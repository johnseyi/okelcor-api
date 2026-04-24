<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FetEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFetEngineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FetEngine::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('manufacturer', 'like', "%{$search}%")
                  ->orWhere('model_series', 'like', "%{$search}%")
                  ->orWhere('engine_code', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 200), 200);

        $result = $query
            ->orderBy('category')
            ->orderBy('manufacturer')
            ->paginate($perPage);

        return response()->json([
            'data' => $result->items(),
            'meta' => [
                'total'        => $result->total(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
            ],
            'message' => 'success',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'     => 'required|in:cars_suv,commercial',
            'manufacturer' => 'required|string|max:100',
            'model_series' => 'required|string|max:150',
            'engine_code'  => 'nullable|string|max:50',
            'displacement' => 'nullable|string|max:30',
            'fuel_type'    => 'required|in:diesel,petrol,both',
            'fet_model'    => 'required|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $engine = FetEngine::create($data);

        return response()->json(['data' => $engine, 'message' => 'Engine created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $engine = FetEngine::findOrFail($id);

        $data = $request->validate([
            'category'     => 'sometimes|in:cars_suv,commercial',
            'manufacturer' => 'sometimes|string|max:100',
            'model_series' => 'sometimes|string|max:150',
            'engine_code'  => 'nullable|string|max:50',
            'displacement' => 'nullable|string|max:30',
            'fuel_type'    => 'sometimes|in:diesel,petrol,both',
            'fet_model'    => 'sometimes|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $engine->update($data);

        return response()->json(['data' => $engine->fresh(), 'message' => 'Engine updated.']);
    }

    public function destroy(int $id): \Illuminate\Http\Response
    {
        FetEngine::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'category'              => 'required|in:cars_suv,commercial',
            'rows'                  => 'required|array|min:1',
            'rows.*.manufacturer'   => 'required|string|max:100',
            'rows.*.model_series'   => 'required|string|max:150',
            'rows.*.engine_code'    => 'nullable|string|max:50',
            'rows.*.displacement'   => 'nullable|string|max:30',
            'rows.*.fuel_type'      => 'required|in:diesel,petrol,both',
            'rows.*.fet_model'      => 'required|string|max:100',
            'rows.*.notes'          => 'nullable|string',
        ]);

        $now      = now();
        $category = $request->category;

        $rows = collect($request->rows)->map(fn ($r) => array_merge($r, [
            'category'   => $category,
            'engine_code' => $r['engine_code'] ?? null,
            'displacement' => $r['displacement'] ?? null,
            'notes'      => $r['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]))->toArray();

        FetEngine::insert($rows);

        return response()->json([
            'data'    => ['imported' => count($rows)],
            'message' => count($rows) . ' engines imported.',
        ], 201);
    }
}
