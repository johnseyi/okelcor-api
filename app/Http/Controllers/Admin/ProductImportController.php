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
     * Accepts a CSV file upload and runs the Wix import logic.
     */
    public function import(Request $request): JsonResponse
    {
        // Allow up to 5 minutes and 256 MB for large CSV imports on shared hosting
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $request->validate([
            // mimes checks detected MIME type — CSVs commonly arrive as text/plain,
            // application/vnd.ms-excel, or application/octet-stream depending on OS/browser.
            // extensions:csv checks only the file extension, which is reliable enough here.
            'file' => ['required', 'file', 'extensions:csv', 'max:51200'], // 50 MB max
        ]);

        try {
            // Use PHP's temp file path directly — it is already on disk and valid
            // for the full lifetime of this request. Avoids any storage permission
            // issues that can occur when copying to storage/app on shared hosts.
            $fullPath = $request->file('file')->getRealPath();

            $exitCode = Artisan::call('import:wix-products', ['file' => $fullPath]);
            $output   = Artisan::output();

            if ($exitCode !== 0) {
                return response()->json([
                    'data'    => null,
                    'message' => 'Import failed.',
                    'error'   => trim($output),
                ], 422);
            }

            $counts = $this->parseOutputCounts($output);

            return response()->json([
                'data'    => [
                    'imported' => $counts['imported'],
                    'updated'  => $counts['updated'],
                    'skipped'  => $counts['skipped'],
                    'errors'   => [],
                ],
                'message' => 'Import completed successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data'    => null,
                'message' => 'Import failed.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/admin/products/export
     *
     * Streams all products as a CSV download in Wix-compatible format.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'okelcor_products_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // CSV header — Wix-compatible column names
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

            Product::withoutTrashed()
                ->orderBy('id')
                ->chunk(500, function ($products) use ($handle) {
                    foreach ($products as $p) {
                        fputcsv($handle, [
                            $p->sku,
                            $p->brand ? "{$p->brand} {$p->name}" : $p->name,
                            $p->brand,
                            $p->price,
                            $p->description,
                            $p->is_active ? 'true' : 'false',
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
     * Extract imported/updated/skipped counts from artisan command output.
     */
    private function parseOutputCounts(string $output): array
    {
        $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];

        // The table output from the command looks like: | 150 | 30 | 2 |
        if (preg_match('/\|\s*(\d+)\s*\|\s*(\d+)\s*\|\s*(\d+)\s*\|/', $output, $m)) {
            $counts['imported'] = (int) $m[1];
            $counts['updated']  = (int) $m[2];
            $counts['skipped']  = (int) $m[3];
        }

        return $counts;
    }
}
