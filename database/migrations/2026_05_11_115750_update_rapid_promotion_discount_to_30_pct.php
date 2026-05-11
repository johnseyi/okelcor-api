<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stakeholder-confirmed correction: Rapid campaign discount is 30%, not 5%.
     * Updates both active Rapid promotion records (shop_inline + announcement_bar)
     * and corrects the subheadline/short_text UI strings that still say "5%".
     *
     * Note on pricing: prices in the products table are already final selling
     * prices imported directly from the supplier Excel sheet. discount_pct is
     * promotional messaging only — the frontend must NOT apply it as a price
     * calculation (no price * 0.70).
     */
    public function up(): void
    {
        DB::table('promotions')
            ->where('brand_name', 'Rapid')
            ->where('is_active', true)
            ->update([
                'discount_pct' => 30,
                'subheadline'  => DB::raw("REPLACE(subheadline, '5%', '30%')"),
                'short_text'   => DB::raw("REPLACE(short_text, '5%', '30%')"),
            ]);
    }

    public function down(): void
    {
        DB::table('promotions')
            ->where('brand_name', 'Rapid')
            ->where('is_active', true)
            ->update([
                'discount_pct' => 5,
                'subheadline'  => DB::raw("REPLACE(subheadline, '30%', '5%')"),
                'short_text'   => DB::raw("REPLACE(short_text, '30%', '5%')"),
            ]);
    }
};
