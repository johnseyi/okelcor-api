<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWixProducts extends Command
{
    protected $signature = 'import:wix-products
                            {file : Path to the CSV file}
                            {--segment= : Price segment to write: b2b or b2c. Omit for single-file import.}';

    protected $description = 'Import tyre products from a CSV export';

    private const TBR_KEYWORDS = ['truck', 'bus', 'tbr', 'heavy', 'commercial', 'lt ', ' lt', 'cargo'];

    public function handle(): int
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $filePath = $this->argument('file');
        $segment  = $this->option('segment'); // 'b2b', 'b2c', or null

        if ($segment !== null && ! in_array($segment, ['b2b', 'b2c'], true)) {
            $this->error("Invalid --segment value '{$segment}'. Must be 'b2b' or 'b2c'.");
            return self::FAILURE;
        }

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error("Cannot open file: {$filePath}");
            return self::FAILURE;
        }

        $rawHeaders    = fgetcsv($handle);
        $rawHeaders[0] = ltrim($rawHeaders[0], "\xEF\xBB\xBF");
        $headers       = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        $this->info("CSV columns detected: " . implode(', ', $headers));
        if ($segment) {
            $this->info("Segment mode: {$segment} — price column will be written to price_{$segment} only.");
        }

        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle); // skip header

        if ($totalRows === 0) {
            $this->warn('No data rows found in the CSV.');
            fclose($handle);
            return self::SUCCESS;
        }

        $this->info("Processing {$totalRows} rows…");
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        $skipped   = 0;
        $rowsBySku = []; // SKU => merged product row
        $imageMap  = []; // SKU => semicolon-separated image filename string

        // Phase 1 — read all rows into a SKU-keyed map
        while (($row = fgetcsv($handle)) !== false) {
            $bar->advance();

            // Safely map row to headers — tolerates rows with extra or missing
            // columns (e.g. unquoted commas in product names from proxy CSV re-serialisation)
            if (count($row) === 0) {
                $skipped++;
                continue;
            }
            $data = [];
            foreach ($headers as $i => $h) {
                $data[$h] = $row[$i] ?? '';
            }

            $sku         = trim($data['sku'] ?? $data['field:sku'] ?? '');
            $rawImageUrl = trim($data['productimageurl'] ?? '');

            if ($sku !== '' && $rawImageUrl !== '' && ! isset($imageMap[$sku])) {
                $imageMap[$sku] = $rawImageUrl;
            }

            $mapped = $this->mapRow($data);

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
                    // No-segment mode: within-file duplicate SKUs treated as B2B/B2C pair
                    $existingPrice = (float) $rowsBySku[$sku]['price'];
                    $newPrice      = (float) $mapped['price'];

                    $rowsBySku[$sku]['price_b2c'] = max($existingPrice, $newPrice);
                    $rowsBySku[$sku]['price_b2b'] = min($existingPrice, $newPrice);
                    $rowsBySku[$sku]['price']     = max($existingPrice, $newPrice);
                } else {
                    // Segment mode: files should have unique SKUs — last row wins
                    $rowsBySku[$sku] = $mapped;
                }
            } else {
                $rowsBySku[$sku] = $mapped;
            }
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        // Phase 2 — batch upsert and image downloads
        $imported   = 0;
        $updated    = 0;
        $imageCount = 0;
        $batchSize  = 200;
        $chunks     = array_chunk(array_values($rowsBySku), $batchSize);

        foreach ($chunks as $chunk) {
            [$imp, $upd] = $this->flushBatch($chunk, $segment);
            $imported   += $imp;
            $updated    += $upd;
            $imageCount += $this->downloadImagesForBatch($chunk, $imageMap);
        }

        $this->info("Import complete.");
        $this->table(
            ['Imported (new)', 'Updated (existing)', 'Skipped', 'Images downloaded'],
            [[$imported, $updated, $skipped, $imageCount]]
        );

        return self::SUCCESS;
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
                Log::info("import:wix-products downloaded images for {$count} products");
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

    private function mapRow(array $data): ?array
    {
        $sku = trim($data['sku'] ?? $data['field:sku'] ?? '');
        if ($sku === '') {
            return null;
        }

        $rawName    = trim($data['name'] ?? $data['product name'] ?? '');
        $rawBrand   = trim($data['brand'] ?? $data['field:brand'] ?? '');
        $rawPrice   = $this->parseDecimal($data['price'] ?? $data['field:price'] ?? '0');
        $rawDesc    = trim($data['description'] ?? $data['product description'] ?? '');
        // Support both original 'visible' and proxy-renamed 'is_active'; also handles '1'/'0' from proxy
        $rawVisible = strtolower(trim($data['visible'] ?? $data['is_active'] ?? $data['published'] ?? 'true'));
        $rawStock   = $this->parseInt($data['inventory'] ?? $data['field:stock'] ?? $data['stock'] ?? null);
        $rawCost    = $this->parseDecimal($data['cost'] ?? $data['cost price'] ?? $data['field:cost'] ?? null);

        $parsed = $this->parseTyreName($rawName);

        // Prefer regex-extracted values; fall back to dedicated CSV columns
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
            'price'        => $rawPrice ?? 0,
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
            if (str_contains($t, 'otr')) {
                return 'OTR';
            }
            if (str_contains($t, 'used')) {
                return 'Used';
            }
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
        if (str_contains($value, 'winter') || str_contains($value, 'snow')) {
            return 'Winter';
        }
        if (str_contains($value, 'all') && str_contains($value, 'terrain')) {
            return 'All-Terrain';
        }
        if (str_contains($value, 'all')) {
            return 'All Season';
        }
        if (str_contains($value, 'summer')) {
            return 'Summer';
        }
        return null;
    }

    private function flushBatch(array $batch, ?string $segment = null): array
    {
        $skus = array_column($batch, 'sku');

        $existingSkus = Product::withTrashed()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->flip()
            ->toArray();

        $newCount     = count(array_filter($batch, fn ($r) => ! isset($existingSkus[$r['sku']])));
        $updatedCount = count($batch) - $newCount;

        // Base columns updated on every import regardless of segment
        $updateCols = [
            'brand', 'name', 'size', 'spec', 'season', 'type',
            'price', 'description', 'is_active',
            'width', 'height', 'rim', 'load_index', 'speed_rating',
            'stock', 'cost_price', 'updated_at',
        ];

        // Only write the relevant price segment column — never overwrite the other
        if ($segment === 'b2b') {
            $updateCols[] = 'price_b2b';
        } elseif ($segment === 'b2c') {
            $updateCols[] = 'price_b2c';
        } else {
            $updateCols[] = 'price_b2b';
            $updateCols[] = 'price_b2c';
        }

        Product::upsert($batch, ['sku'], $updateCols);

        return [$newCount, $updatedCount];
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

    /** Converts a float string like "225.0" to an integer string "225" for varchar tyre fields. */
    private function parseFloatToString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $f = (float) $value;
        return $f > 0 ? (string) (int) $f : null;
    }
}
