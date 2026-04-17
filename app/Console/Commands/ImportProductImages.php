<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProductImages extends Command
{
    protected $signature = 'import:product-images {file : Path to the Wix CSV export file}';

    protected $description = 'Download Wix CDN images for existing products that are missing a primary_image';

    public function handle(): int
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

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

        $rawHeaders    = fgetcsv($handle);
        $rawHeaders[0] = ltrim($rawHeaders[0], "\xEF\xBB\xBF");
        $headers       = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        // Build sku → image filename string map from the CSV
        $imageMap = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $data        = array_combine($headers, $row);
            $sku         = trim($data['sku'] ?? $data['field:sku'] ?? '');
            $rawImageUrl = trim($data['productimageurl'] ?? '');
            if ($sku !== '' && $rawImageUrl !== '') {
                $imageMap[$sku] = $rawImageUrl;
            }
        }
        fclose($handle);

        $this->info("Found " . count($imageMap) . " SKUs with image URLs in CSV.");

        // Only process products that are missing a primary image
        $products = Product::whereIn('sku', array_keys($imageMap))
            ->whereNull('primary_image')
            ->get();

        $total = $products->count();

        if ($total === 0) {
            $this->info('All products already have a primary image. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Downloading images for {$total} products…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $downloaded = 0;
        $failed     = 0;

        foreach ($products as $product) {
            $bar->advance();

            $rawUrls   = $imageMap[$product->sku];
            $filenames = array_values(array_filter(array_map('trim', explode(';', $rawUrls))));

            if (empty($filenames)) {
                $failed++;
                continue;
            }

            $primaryPath = $this->downloadWixImage($filenames[0]);

            if (! $primaryPath) {
                $failed++;
                continue;
            }

            $product->update(['primary_image' => $primaryPath]);
            $downloaded++;

            if ($downloaded % 100 === 0) {
                Log::info("import:product-images downloaded {$downloaded} images");
            }

            // Second image → gallery record (only if none already exist)
            if (isset($filenames[1]) && ! $product->images()->exists()) {
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

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Downloaded', 'Failed'],
            [[$downloaded, $failed]]
        );

        return self::SUCCESS;
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
}
