<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Promotion ID 4 (shop_inline) was manually edited to 35 after the
     * discount migration ran. Correct it back to the stakeholder-confirmed 30.
     * Targets by ID so it is unaffected by any name/condition changes.
     */
    public function up(): void
    {
        DB::table('promotions')->where('id', 4)->update(['discount_pct' => 30]);
    }

    public function down(): void
    {
        DB::table('promotions')->where('id', 4)->update(['discount_pct' => 35]);
    }
};
