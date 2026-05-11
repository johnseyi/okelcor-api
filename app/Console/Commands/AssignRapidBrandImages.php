<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssignRapidBrandImages extends Command
{
    protected $signature = 'products:assign-rapid-images
                            {--primary= : Path to primary image (default: "Image 1.png" in project root)}
                            {--secondary= : Path to secondary image (default: "Image 2.png" in project root)}
                            {--dry-run : Preview without writing anything}';

    protected $description = 'Copy Rapid brand images to storage and assign them to all Rapid products';

    // Stable storage paths — shared across all Rapid products (one file, many references)
    private const PRIMARY_DISK_PATH   = 'products/rapid-primary.png';
    private const SECONDARY_DISK_PATH = 'products/rapid-secondary.png';
    private const BRAND_LOGO_PATH     = 'brands/rapid-logo.png';

    public function handle(): int
    {
        $primarySrc   = $this->option('primary')   ?? base_path('Image 1.png');
        $secondarySrc = $this->option('secondary') ?? base_path('Image 2.png');
        $dryRun       = $this->option('dry-run');

        // --- Validate source files ---
        foreach ([$primarySrc => 'primary', $secondarySrc => 'secondary'] as $path => $label) {
            if (! file_exists($path)) {
                $this->error("Cannot find {$label} image: {$path}");
                return self::FAILURE;
            }
        }

        $this->info('Source files confirmed:');
        $this->line("  Primary   : {$primarySrc} (" . number_format(filesize($primarySrc)) . ' bytes)');
        $this->line("  Secondary : {$secondarySrc} (" . number_format(filesize($secondarySrc)) . ' bytes)');

        $rapidProducts = Product::where('brand', 'Rapid')->whereNull('deleted_at')->get();
        $this->line("  Rapid products: {$rapidProducts->count()}");

        if ($dryRun) {
            $this->warn('DRY-RUN — no changes written.');
            $this->table(['Action', 'Target'], [
                ['Copy file',  'storage/app/public/' . self::PRIMARY_DISK_PATH],
                ['Copy file',  'storage/app/public/' . self::SECONDARY_DISK_PATH],
                ['Copy file',  'storage/app/public/' . self::BRAND_LOGO_PATH],
                ['UPDATE products SET primary_image', 'all ' . $rapidProducts->count() . ' Rapid products'],
                ['DELETE + INSERT product_images',    'secondary image for all ' . $rapidProducts->count() . ' Rapid products'],
                ['UPSERT brands',                    'Rapid brand logo'],
            ]);
            return self::SUCCESS;
        }

        // --- 1. Copy images to public storage ---
        $this->line('');
        $this->info('Copying images to storage...');

        Storage::disk('public')->put(self::PRIMARY_DISK_PATH,   file_get_contents($primarySrc));
        Storage::disk('public')->put(self::SECONDARY_DISK_PATH, file_get_contents($secondarySrc));
        Storage::disk('public')->put(self::BRAND_LOGO_PATH,     file_get_contents($primarySrc));

        $this->line('  ✓ ' . self::PRIMARY_DISK_PATH);
        $this->line('  ✓ ' . self::SECONDARY_DISK_PATH);
        $this->line('  ✓ ' . self::BRAND_LOGO_PATH . ' (copy of primary for brand fallback)');

        // --- 2. Update primary_image on all Rapid products ---
        $this->line('');
        $this->info('Assigning primary_image to all Rapid products...');

        $updated = Product::where('brand', 'Rapid')
            ->whereNull('deleted_at')
            ->update(['primary_image' => self::PRIMARY_DISK_PATH]);

        $this->line("  ✓ {$updated} products updated");

        // --- 3. Replace secondary product_images for all Rapid products ---
        $this->line('');
        $this->info('Assigning secondary image to product_images...');

        DB::transaction(function () use ($rapidProducts) {
            $productIds = $rapidProducts->pluck('id')->all();

            // Remove any existing secondary image entries for Rapid products
            // (sort_order >= 1 — leave sort_order 0 entries from other sources intact)
            ProductImage::whereIn('product_id', $productIds)
                ->where('path', self::SECONDARY_DISK_PATH)
                ->delete();

            // Insert one secondary image record per product
            $now  = now();
            $rows = $rapidProducts->map(fn ($p) => [
                'product_id' => $p->id,
                'path'       => self::SECONDARY_DISK_PATH,
                'sort_order' => 1,
                'created_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 200) as $chunk) {
                ProductImage::insert($chunk);
            }
        });

        $this->line("  ✓ {$rapidProducts->count()} product_images records inserted");

        // --- 4. Create or update Rapid brand record ---
        $this->line('');
        $this->info('Upserting Rapid brand record...');

        $brand = Brand::firstOrNew(['name' => 'Rapid']);
        $brand->logo       = self::BRAND_LOGO_PATH;
        $brand->is_active  = true;
        $brand->sort_order = $brand->sort_order ?? 0;
        $brand->save();

        $this->line('  ✓ Rapid brand ' . ($brand->wasRecentlyCreated ? 'created' : 'updated'));

        // --- Summary ---
        $this->line('');
        $this->info('=== Done ===');
        $this->table(['Field', 'Value'], [
            ['Primary image URL',   url(Storage::url(self::PRIMARY_DISK_PATH))],
            ['Secondary image URL', url(Storage::url(self::SECONDARY_DISK_PATH))],
            ['Brand image URL',     url(Storage::url(self::BRAND_LOGO_PATH))],
            ['Products updated',    $updated],
            ['product_images rows', $rapidProducts->count()],
        ]);

        return self::SUCCESS;
    }
}
