<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Copy price_b2c from duplicate rows into the keeper ────────
        // The keeper is the lowest-id record per SKU — that is the B2B record
        // (imported first, has images). The duplicate row carries price_b2c.
        DB::statement("
            UPDATE products AS keeper
            INNER JOIN (
                SELECT sku, MIN(id) AS keep_id, MAX(price_b2c) AS best_b2c
                FROM products
                GROUP BY sku
                HAVING COUNT(*) > 1
            ) AS agg ON keeper.id = agg.keep_id
            SET keeper.price_b2c = COALESCE(keeper.price_b2c, agg.best_b2c)
        ");

        // ── Step 2: Remove gallery images attached to duplicate rows ──────────
        DB::statement("
            DELETE pi FROM product_images AS pi
            INNER JOIN (
                SELECT p.id
                FROM products AS p
                INNER JOIN (
                    SELECT sku, MIN(id) AS keep_id
                    FROM products
                    GROUP BY sku
                    HAVING COUNT(*) > 1
                ) AS keepers ON p.sku = keepers.sku
                WHERE p.id > keepers.keep_id
            ) AS dups ON pi.product_id = dups.id
        ");

        // ── Step 3: Delete the duplicate product rows ─────────────────────────
        DB::statement("
            DELETE p FROM products AS p
            INNER JOIN (
                SELECT sku, MIN(id) AS keep_id
                FROM products
                GROUP BY sku
                HAVING COUNT(*) > 1
            ) AS keepers ON p.sku = keepers.sku
            WHERE p.id > keepers.keep_id
        ");

        // ── Step 4: Add unique index on sku if it is missing ─────────────────
        $hasUnique = collect(
            DB::select("SHOW INDEX FROM products WHERE Column_name = 'sku' AND Non_unique = 0")
        )->isNotEmpty();

        if (! $hasUnique) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('sku', 'products_sku_unique');
            });
        }
    }

    public function down(): void
    {
        // Deduplication is not reversible.
    }
};
