<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WixProductImportService
{
    private const TBR_KEYWORDS = ['truck', 'bus', 'tbr', 'heavy', 'commercial', 'lt ', ' lt', 'cargo'];

    /**
     * Import products from a CSV file path.
     *
     * @param  string       $filePath  Absolute path to the CSV file
     * @param  string|null  $segment   'b2b', 'b2c', or null (no-segment / within-file duplicate detection)
     * @param  callable|null $progress  Optional progress callback(int $current, int $total)
     * @return array{ imported: int, updated: int, skipped: int, images: int, errors: list<array{row:int,message:string}> }
     */
    public function import(string $filePath, ?string $segment = null, ?callable $progress = null): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return $this->result(0, 0, 0, 0, [['row' => null, 'message' => "Cannot open file: {$filePath}"]]);
        }

        // Strip UTF-8 BOM from first header cell
        $rawHeaders    = fgetcsv($handle);
        $rawHeaders[0] = ltrim($rawHeaders[0], "\xEF\xBB\xBF");
        $headers       = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        $skipped   = 0;
        $rowsBySku = [];
        $imageMap  = [];
        $rowNum    = 1;
        $errors    = [];

        // ── Phase 1: read entire file into a SKU-keyed map ──────────────────
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count($row) === 0) {
                $skipped++;
                continue;
            }

            // Map row to headers by position — tolerates rows with extra or
            // missing columns (e.g. unquoted commas in re-serialised CSVs)
            $data = [];
            foreach ($headers as $i => $h) {
                $data[$h] = $row[$i] ?? '';
            }

            $sku         = trim($data['sku'] ?? $data['field:sku'] ?? '');
            $rawImageUrl = trim($data['productimageurl'] ?? '');

            if ($sku !== '' && $rawImageUrl !== '' && ! isset($imageMap[$sku])) {
                $imageMap[$sku] = $rawImageUrl;
            }

            $mapped = $this->mapRow($data, $rowNum);

            if ($mapped === null) {
                $skipped++;
                continue;
            }

            // Assign price to the correct segment field
            if ($segment === 'b2b') {
                $mapped['price_b2b'] = $mapped['price'];
                $mapped['price_b2c'] = null;
            } elseif ($segment === 'b2c') {
                $mapped['price_b2c'] = $mapped['price'];
                $mapped['price_b2b'] = null;
            }

            if (isset($rowsBySku[$sku])) {
                if ($segment === null) {
                    // Within-file duplicate: higher price = B2C (retail), lower = B2B (wholesale)
                    $existingPrice = (float) $rowsBySku[$sku]['price'];
                    $newPrice      = (float) $mapped['price'];

                    $rowsBySku[$sku]['price_b2c'] = max($existingPrice, $newPrice);
                    $rowsBySku[$sku]['price_b2b'] = min($existingPrice, $newPrice);
                    $rowsBySku[$sku]['price']     = max($existingPrice, $newPrice);
                } else {
                    // Segment mode: same file should have unique SKUs — last row wins
                    $rowsBySku[$sku] = $mapped;
                }
            } else {
                $rowsBySku[$sku] = $mapped;
            }

            if ($progress) {
                ($progress)($rowNum, 0);
            }
        }

        fclose($handle);

        // ── Phase 2: batch upsert ─────────────────────────────────────────
        $imported   = 0;
        $updated    = 0;
        $imageCount = 0;
        $batchSize  = 200;
        $allRows    = array_values($rowsBySku);
        $total      = count($allRows);
        $done       = 0;

        foreach (array_chunk($allRows, $batchSize) as $chunk) {
            try {
                [$imp, $upd] = $this->flushBatch($chunk, $segment);
                $imported   += $imp;
                $updated    += $upd;
                $imageCount += $this->downloadImagesForBatch($chunk, $imageMap);
            } catch (\Throwable $e) {
                $errors[] = ['row' => null, 'message' => 'Batch upsert failed: ' . $e->getMessage()];
                Log::error('WixProductImportService batch error: ' . $e->getMessage());
            }

            $done += count($chunk);
            if ($progress) {
                ($progress)($done, $total);
            }
        }

        return $this->result($imported, $updated, $skipped, $imageCount, $errors);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function result(int $imported, int $updated, int $skipped, int $images, array $errors): array
    {
        return compact('imported', 'updated', 'skipped', 'images', 'errors');
    }

    private function flushBatch(array $batch, ?string $segment): array
    {
        $skus = array_column($batch, 'sku');

        $existingSkus = Product::withTrashed()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->flip()
            ->toArray();

        $newCount     = count(array_filter($batch, fn ($r) => ! isset($existingSkus[$r['sku']])));
        $updatedCount = count($batch) - $newCount;

        $updateCols = [
            'brand', 'name', 'size', 'spec', 'season', 'type',
            'price', 'description', 'is_active',
            'width', 'height', 'rim', 'load_index', 'speed_rating',
            'stock', 'cost_price', 'updated_at',
        ];

        // Only write the relevant price column — preserve the other tier on existing rows
        if ($segment === 'b2b') {
            $updateCols[] = 'price_b2b';
        } elseif ($segment === 'b2c') {
            $updateCols[] = 'price_b2c';
        } else {
            $updateCols[] = 'price_b2b';
            $updateCols[] = 'price_b2c';
        }

        Product::upsert($batch, ['sku'], $updateCols);

        // Restore any previously soft-deleted products that were just upserted
        Product::onlyTrashed()->whereIn('sku', $skus)->restore();

        return [$newCount, $updatedCount];
    }

    private function downloadImagesForBatch(array $batch, array $imageMap): int
    {
        $skus = array_column($batch, 'sku');

        $products = Product::whereIn('sku', $skus)
            ->whereNull('primary_image')
            ->get()
            ->keyBy('sku');

        $count = 0;

        foreach ($products as $sku => $product) {
            $rawUrls = $imageMap[$sku] ?? null;
            if (! $rawUrls) {
                continue;
            }

            $filenames = array_values(array_filter(array_map('trim', explode(';', $rawUrls))));
            if (empty($filenames)) {
                continue;
            }

            $primaryPath = $this->downloadWixImage($filenames[0]);
            if (! $primaryPath) {
                continue;
            }

            $product->update(['primary_image' => $primaryPath]);
            $count++;

            if ($count % 100 === 0) {
                Log::info("WixProductImportService: downloaded images for {$count} products");
            }

            if (isset($filenames[1])) {
                $secondPath = $this->downloadWixImage($filenames[1]);
                if ($secondPath) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => $secondPath,
                        'sort_order' => 1,
                    ]);
                }
            }
        }

        return $count;
    }

    private function downloadWixImage(string $filename): ?string
    {
        try {
            $url      = 'https://static.wixstatic.com/media/' . ltrim($filename, '/');
            $response = Http::timeout(30)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $path = 'products/' . Str::uuid() . '.jpg';
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapRow(array $data, int $rowNum): ?array
    {
        $sku = trim($data['sku'] ?? $data['field:sku'] ?? '');
        if ($sku === '') {
            return null;
        }

        $rawName    = trim($data['name'] ?? $data['product name'] ?? '');
        $rawBrand   = trim($data['brand'] ?? $data['field:brand'] ?? '');
        $rawPrice   = $this->parseDecimal($data['price'] ?? $data['field:price'] ?? '0') ?? 0;
        $rawDesc    = trim($data['description'] ?? $data['product description'] ?? '');
        // Support both original 'visible' column and proxy-renamed 'is_active'; handles "True"/"False" and "1"/"0"
        $rawVisible = strtolower(trim($data['visible'] ?? $data['is_active'] ?? $data['published'] ?? 'true'));
        $rawStock   = $this->parseInt($data['inventory'] ?? $data['field:stock'] ?? $data['stock'] ?? null);
        $rawCost    = $this->parseDecimal($data['cost'] ?? $data['cost price'] ?? $data['field:cost'] ?? null);

        $parsed = $this->parseTyreName($rawName);

        // Prefer regex-extracted values; fall back to dedicated CSV columns when regex fails
        $width       = $parsed['width']        ?? $this->parseFloatToString($data['width'] ?? null);
        $height      = $parsed['height']       ?? $this->parseFloatToString($data['height'] ?? null);
        $rim         = $parsed['rim']          ?? $this->parseFloatToString($data['rim'] ?? null);
        $loadIndex   = $parsed['load_index']   ?? $this->parseFloatToString($data['load_index'] ?? null);
        $speedRating = $parsed['speed_rating'] ?? (trim($data['speed_rating'] ?? '') ?: null);
        $season      = $parsed['season'];

        $size = ($width && $height && $rim)
            ? "{$width}/{$height}R{$rim}"
            : trim($data['size'] ?? $data['field:size'] ?? '');

        $spec = ($loadIndex || $speedRating)
            ? trim($loadIndex . $speedRating)
            : trim($data['spec'] ?? $data['field:spec'] ?? '');

        $type  = $this->detectType($rawName, $data['type'] ?? $data['field:type'] ?? '');
        $brand = $rawBrand !== '' ? $rawBrand : ($parsed['brand'] ?? '');
        $name  = $this->stripBrandFromName($rawName, $brand);

        $isActive = ! in_array($rawVisible, ['false', '0', 'no', 'hidden', ''], true);

        if ($season === null) {
            $rawSeason = strtolower(trim($data['season'] ?? $data['field:season'] ?? ''));
            $season    = $this->mapSeasonValue($rawSeason);
        }

        $season ??= 'Summer';

        return [
            'sku'          => $sku,
            'brand'        => $brand,
            'name'         => $name,
            'size'         => $size,
            'spec'         => $spec,
            'season'       => $season,
            'type'         => $type,
            'price'        => $rawPrice,
            'price_b2b'    => null,
            'price_b2c'    => null,
            'description'  => $rawDesc,
            'is_active'    => $isActive,
            'width'        => $width,
            'height'       => $height,
            'rim'          => $rim,
            'load_index'   => $loadIndex,
            'speed_rating' => $speedRating,
            'stock'        => $rawStock,
            'cost_price'   => $rawCost,
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

    private function parseTyreName(string $name): array
    {
        $result = [
            'brand'        => null,
            'width'        => null,
            'height'       => null,
            'rim'          => null,
            'load_index'   => null,
            'speed_rating' => null,
            'season'       => null,
        ];

        if (preg_match('/(\d{3})\/(\d{2})R\s*(\d{2})\s+(\d{2,3})([A-Z]{1,2})\b/', $name, $m)) {
            $result['width']        = $m[1];
            $result['height']       = $m[2];
            $result['rim']          = $m[3];
            $result['load_index']   = $m[4];
            $result['speed_rating'] = $m[5];
        }

        $lower = strtolower($name);
        if (str_contains($lower, 'winter') || str_contains($lower, 'snow') || str_contains($lower, 'nordic')) {
            $result['season'] = 'Winter';
        } elseif (str_contains($lower, 'all season') || str_contains($lower, 'allseason') || str_contains($lower, 'all-season') || str_contains($lower, 'four season')) {
            $result['season'] = 'All Season';
        } elseif (str_contains($lower, 'all terrain') || str_contains($lower, 'all-terrain') || str_contains($lower, 'at ') || str_contains($lower, 'a/t')) {
            $result['season'] = 'All-Terrain';
        } elseif (str_contains($lower, 'summer')) {
            $result['season'] = 'Summer';
        }

        if ($result['width']) {
            $beforeSize      = trim(preg_replace('/\d{3}\/\d{2}R\s*\d{2}.*/i', '', $name));
            $words           = explode(' ', $beforeSize);
            $result['brand'] = $words[0] ?? null;
        }

        return $result;
    }

    private function detectType(string $name, string $typeColumn): string
    {
        if ($typeColumn !== '') {
            $t = strtolower($typeColumn);
            if (str_contains($t, 'tbr') || str_contains($t, 'truck') || str_contains($t, 'bus') || str_contains($t, 'commercial')) {
                return 'TBR';
            }
            if (str_contains($t, 'otr')) return 'OTR';
            if (str_contains($t, 'used')) return 'Used';
        }

        $lower = strtolower($name);
        foreach (self::TBR_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return 'TBR';
            }
        }

        return 'PCR';
    }

    private function stripBrandFromName(string $name, string $brand): string
    {
        if ($brand === '') {
            return $name;
        }
        $cleaned = preg_replace('/^' . preg_quote($brand, '/') . '\s*/i', '', $name);
        return trim($cleaned !== '' ? $cleaned : $name);
    }

    private function mapSeasonValue(string $value): ?string
    {
        if (str_contains($value, 'winter') || str_contains($value, 'snow')) return 'Winter';
        if (str_contains($value, 'all') && str_contains($value, 'terrain')) return 'All-Terrain';
        if (str_contains($value, 'all')) return 'All Season';
        if (str_contains($value, 'summer')) return 'Summer';
        return null;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $clean = preg_replace('/[^\d.]/', '', $value);
        return $clean !== '' ? (float) $clean : null;
    }

    private function parseInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $clean = preg_replace('/\D/', '', $value);
        return $clean !== '' ? (int) $clean : null;
    }

    private function parseFloatToString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $f = (float) $value;
        return $f > 0 ? (string) (int) $f : null;
    }
}
