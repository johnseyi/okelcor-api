<?php

namespace App\Http\Controllers;

use App\Models\FetEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FetEngineController extends Controller
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

        $perPage = min((int) $request->input('per_page', 50), 200);

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
        ]);
    }
}
