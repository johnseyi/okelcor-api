<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    /**
     * POST /api/v1/admin/products/import
     *
     * Accepts a CSV file and an optional segment (b2b|b2c).
     * When segment is supplied, the price column is written to price_b2b or price_b2c only —
     * the other price field on existing records is never overwritten.
     */
    public function import(Request $request): JsonResponse
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'max:51200'],
            'segment' => ['nullable', 'string', 'in:b2b,b2c'],
        ]);

        try {
            $fullPath = $request->file('file')->getRealPath();
            $segment  = $request->input('segment');

            $args = ['file' => $fullPath];
            if ($segment) {
                $args['--segment'] = $segment;
            }

            $exitCode = Artisan::call('import:wix-products', $args);
            $output   = Artisan::output();

            if ($exitCode !== 0) {
                return response()->json([
                    'data' => [
                        'imported' => 0,
                        'updated'  => 0,
                        'skipped'  => 0,
                        'errors'   => [['row' => null, 'message' => trim($output)]],
                    ],
                    'message' => 'Import failed.',
                ], 422);
            }

            $counts = $this->parseOutputCounts($output);

            return response()->json([
                'data' => [
                    'imported' => $counts['imported'],
                    'updated'  => $counts['updated'],
                    'skipped'  => $counts['skipped'],
                    'errors'   => [],
                ],
                'message' => 'Import completed successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [
                    'imported' => 0,
                    'updated'  => 0,
                    'skipped'  => 0,
                    'errors'   => [['row' => null, 'message' => $e->getMessage()]],
                ],
                'message' => 'Import failed.',
            ], 422);
        }
    }

    /**
     * GET /api/v1/admin/products/export
     *
     * Streams all products (or a segment) as a CSV download.
     * ?segment=b2b → products with price_b2b set, price column = price_b2b
     * ?segment=b2c → products with price_b2c set, price column = price_b2c
     * No segment    → full catalogue, price column = base price
     */
    public function export(Request $request): StreamedResponse
    {
        $segment  = $request->input('segment'); // 'b2b', 'b2c', or null
        $datePart = now()->format('Y-m-d_His');

        $filename = match ($segment) {
            'b2b'   => "products-b2b-{$datePart}.csv",
            'b2c'   => "products-b2c-{$datePart}.csv",
            default => "products-{$datePart}.csv",
        };

        return response()->streamDownload(function () use ($segment) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'sku',
                'name',
                'brand',
                'price',
                'description',
                'visible',
                'season',
                'type',
                'size',
                'spec',
                'width',
                'height',
                'rim',
                'load_index',
                'speed_rating',
                'inventory',
                'cost',
                'created_at',
            ]);

            $query = Product::withoutTrashed()->orderBy('id');

            if ($segment === 'b2b') {
                $query->whereNotNull('price_b2b');
            } elseif ($segment === 'b2c') {
                $query->whereNotNull('price_b2c');
            }

            $query->chunk(500, function ($products) use ($handle, $segment) {
                foreach ($products as $p) {
                    $price = match ($segment) {
                        'b2b'   => $p->price_b2b,
                        'b2c'   => $p->price_b2c,
                        default => $p->price,
                    };

                    fputcsv($handle, [
                        $p->sku,
                        $p->brand ? "{$p->brand} {$p->name}" : $p->name,
                        $p->brand,
                        $price,
                        $p->description,
                        $p->is_active ? 'True' : 'False',
                        $p->season,
                        $p->type,
                        $p->size,
                        $p->spec,
                        $p->width,
                        $p->height,
                        $p->rim,
                        $p->load_index,
                        $p->speed_rating,
                        $p->stock,
                        $p->cost_price,
                        $p->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Extract imported/updated/skipped counts from artisan command output table.
     */
    private function parseOutputCounts(string $output): array
    {
        $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];

        if (preg_match('/\|\s*(\d+)\s*\|\s*(\d+)\s*\|\s*(\d+)\s*\|/', $output, $m)) {
            $counts['imported'] = (int) $m[1];
            $counts['updated']  = (int) $m[2];
            $counts['skipped']  = (int) $m[3];
        }

        return $counts;
    }
}
