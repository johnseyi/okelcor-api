<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 1 — Backfill: the Excel import put supplier prices directly into
     * `price`. Store those as `cost_price` (permanent reference price) so the
     * system can recalculate `price` whenever the promotion discount changes.
     *
     * Phase 2 — Recalculate: apply the current active Rapid promotion
     * discount_pct so `price` immediately reflects the live campaign rate.
     */
    public function up(): void
    {
        // Backfill cost_price from price for any Rapid product that lacks it
        DB::statement("
            UPDATE products
            SET    cost_price = price
            WHERE  brand       = 'Rapid'
              AND  deleted_at IS NULL
              AND  cost_price IS NULL
        ");

        // Find the single authoritative Rapid discount (shop_inline placement
        // is the checkout-relevant one; fall back to any active Rapid promo)
        $promo = DB::table('promotions')
            ->where('brand_name', 'Rapid')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN placement = 'shop_inline' THEN 0 ELSE 1 END")
            ->first();

        if (! $promo || ! $promo->discount_pct) {
            return;
        }

        $factor = round(1 - ((float) $promo->discount_pct / 100), 10);

        DB::statement("
            UPDATE products
            SET    price      = ROUND(cost_price * {$factor}, 2),
                   updated_at = NOW()
            WHERE  brand       = 'Rapid'
              AND  deleted_at IS NULL
              AND  cost_price IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Restore: remove the discount — price returns to full supplier price
        DB::statement("
            UPDATE products
            SET    price      = cost_price,
                   updated_at = NOW()
            WHERE  brand       = 'Rapid'
              AND  deleted_at IS NULL
              AND  cost_price IS NOT NULL
        ");
    }
};
