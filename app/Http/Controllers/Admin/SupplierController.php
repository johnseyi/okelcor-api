<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EbayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(private EbayService $ebay) {}

    /**
     * GET /api/v1/admin/supplier/search?q={query}&limit={limit}
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'     => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $results = $this->ebay->searchTyres(
                $request->q,
                (int) $request->input('limit', 20)
            );

            return response()->json([
                'data'    => $results,
                'meta'    => ['total' => count($results)],
                'message' => 'success',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data'    => [],
                'message' => 'eBay search unavailable: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * GET /api/v1/admin/supplier/alibaba-link?q={query}
     */
    public function alibabaLink(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $url = 'https://www.alibaba.com/trade/search?SearchText=' . urlencode($request->q);

        return response()->json([
            'data'    => ['url' => $url],
            'message' => 'success',
        ]);
    }
}
