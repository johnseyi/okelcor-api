<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires MODIFY COLUMN to extend an existing enum
        DB::statement("
            ALTER TABLE promotions
            MODIFY COLUMN placement
                ENUM('announcement_bar', 'shop_inline', 'both', 'shop_hero')
                NOT NULL DEFAULT 'shop_inline'
        ");

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('brand_name', 100)->nullable()->after('placement');
            $table->string('customer_type_target', 10)->nullable()->after('brand_name');
            $table->decimal('discount_pct', 5, 2)->nullable()->after('customer_type_target');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'customer_type_target', 'discount_pct']);
        });

        // Restore original enum values
        DB::statement("
            ALTER TABLE promotions
            MODIFY COLUMN placement
                ENUM('announcement_bar', 'shop_inline', 'both')
                NOT NULL DEFAULT 'shop_inline'
        ");
    }
};
