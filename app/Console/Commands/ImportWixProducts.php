<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ImportWixProducts extends Command
{
    protected $signature = 'import:wix-products {file : Path to the Wix CSV export file}';

    protected $description = 'Import tyre products from a Wix CSV export';

    // Keywords in the product name that indicate TBR (truck/bus) tyres
    private const TBR_KEYWORDS = ['truck', 'bus', 'tbr', 'heavy', 'commercial', 'lt ', ' lt', 'cargo'];

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error("Cannot open file: {$filePath}");
            return self::FAILURE;
        }

        // Read header row and normalise column names
        $rawHeaders = fgetcsv($handle);
        $headers    = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        $this->info("CSV columns detected: " . implode(', ', $headers));

        // Count rows for progress bar
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle); // skip header again

        if ($totalRows === 0) {
            $this->warn('No data rows found in the CSV.');
            fclose($handle);
            return self::SUCCESS;
        }

        $this->info("Processing {$totalRows} rows…");
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        $upsertBatch = [];
        $batchSize   = 200;

        while (($row = fgetcsv($handle)) !== false) {
            $bar->advance();

            if (count($row) !== count($headers)) {
                $skipped++;
                continue;
            }

            $data = array_combine($headers, $row);
            $mapped = $this->mapRow($data);

            if ($mapped === null) {
                $skipped++;
                continue;
            }

            $upsertBatch[] = $mapped;

            if (count($upsertBatch) >= $batchSize) {
                [$imp, $upd] = $this->flushBatch($upsertBatch);
                $imported += $imp;
                $updated  += $upd;
                $upsertBatch = [];
            }
        }

        // Flush remaining rows
        if (! empty($upsertBatch)) {
            [$imp, $upd] = $this->flushBatch($upsertBatch);
            $imported += $imp;
            $updated  += $upd;
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->info("Import complete.");
        $this->table(
            ['Imported (new)', 'Updated (existing)', 'Skipped'],
            [[$imported, $updated, $skipped]]
        );

        return self::SUCCESS;
    }

    /**
     * Map a single CSV row to a Product attributes array.
     * Returns null if the row should be skipped.
     */
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
        $rawVisible = strtolower(trim($data['visible'] ?? $data['published'] ?? 'true'));
        $rawStock   = $this->parseInt($data['inventory'] ?? $data['field:stock'] ?? $data['stock'] ?? null);
        $rawCost    = $this->parseDecimal($data['cost'] ?? $data['cost price'] ?? $data['field:cost'] ?? null);

        // Extract tyre dimensions from the product name
        $parsed     = $this->parseTyreName($rawName);
        $width       = $parsed['width'];
        $height      = $parsed['height'];
        $rim         = $parsed['rim'];
        $loadIndex   = $parsed['load_index'];
        $speedRating = $parsed['speed_rating'];
        $season      = $parsed['season'];

        // Determine size string
        $size = ($width && $height && $rim)
            ? "{$width}/{$height}R{$rim}"
            : trim($data['size'] ?? $data['field:size'] ?? '');

        // Determine spec string
        $spec = ($loadIndex || $speedRating)
            ? trim($loadIndex . $speedRating)
            : trim($data['spec'] ?? $data['field:spec'] ?? '');

        // Determine product type
        $type = $this->detectType($rawName, $data['type'] ?? $data['field:type'] ?? '');

        // Use brand from dedicated column or fall back to name-parsed brand
        $brand = $rawBrand !== '' ? $rawBrand : ($parsed['brand'] ?? '');

        // Strip brand prefix from name to get clean product name
        $name = $this->stripBrandFromName($rawName, $brand);

        $isActive = ! in_array($rawVisible, ['false', '0', 'no', 'hidden', ''], true);

        // Season fallback: if not parsed from name, check dedicated column
        if ($season === null) {
            $rawSeason = strtolower(trim($data['season'] ?? $data['field:season'] ?? ''));
            $season    = $this->mapSeasonValue($rawSeason);
        }

        // Final fallback season
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

    /**
     * Parse tyre-specific data from a product name.
     * Handles patterns like: "Brand 205/45R17 88Y Summer"
     */
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

        // Combined pattern handles Wix name format: "205/45R 17 88Y"
        // Captures width, height, rim, load_index, speed_rating in one match.
        // \s* allows for optional space between R and rim (e.g. "R 17" or "R17").
        if (preg_match('/(\d{3})\/(\d{2})R\s*(\d{2})\s+(\d{2,3})([A-Z]{1,2})\b/', $name, $m)) {
            $result['width']        = $m[1];
            $result['height']       = $m[2];
            $result['rim']          = $m[3];
            $result['load_index']   = $m[4];
            $result['speed_rating'] = $m[5];
        }

        // Season from name keywords
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

        // Brand: first word before the tyre size (e.g. "Pirelli 205/45R 17 88Y" → "Pirelli")
        if ($result['width']) {
            $beforeSize = trim(preg_replace('/\d{3}\/\d{2}R\s*\d{2}.*/i', '', $name));
            $words      = explode(' ', $beforeSize);
            $result['brand'] = $words[0] ?? null;
        }

        return $result;
    }

    /**
     * Detect PCR vs TBR from name or an explicit type column.
     */
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

    /**
     * Remove the brand name prefix from the full product name.
     */
    private function stripBrandFromName(string $name, string $brand): string
    {
        if ($brand === '') {
            return $name;
        }
        $cleaned = preg_replace('/^' . preg_quote($brand, '/') . '\s*/i', '', $name);
        return trim($cleaned !== '' ? $cleaned : $name);
    }

    /**
     * Map a raw season string from a dedicated column.
     */
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

    /**
     * Upsert a batch of mapped rows. Returns [new_count, updated_count].
     */
    private function flushBatch(array $batch): array
    {
        $skus = array_column($batch, 'sku');

        // SKUs that already exist
        $existingSkus = Product::withTrashed()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->flip()
            ->toArray();

        $newCount     = count(array_filter($batch, fn ($r) => ! isset($existingSkus[$r['sku']])));
        $updatedCount = count($batch) - $newCount;

        Product::upsert(
            $batch,
            ['sku'],           // unique key
            [                  // columns to update on conflict
                'brand', 'name', 'size', 'spec', 'season', 'type',
                'price', 'description', 'is_active',
                'width', 'height', 'rim', 'load_index', 'speed_rating',
                'stock', 'cost_price', 'updated_at',
            ]
        );

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
}
