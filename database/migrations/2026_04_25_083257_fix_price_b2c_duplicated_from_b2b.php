<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear price_b2c on products where it was mistakenly set equal to
        // price_b2b during earlier B2B-segment imports. After this runs,
        // only products that had a genuine B2C price (different from B2B)
        // will keep their price_b2c value.
        DB::statement('UPDATE products SET price_b2c = NULL WHERE price_b2c IS NOT NULL AND price_b2c = price_b2b');
    }

    public function down(): void
    {
        // Not reversible — this is a data-cleanup migration.
    }
};
