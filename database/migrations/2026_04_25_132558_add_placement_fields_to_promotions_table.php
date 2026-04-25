<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('short_text', 255)->nullable()->after('subheadline');
            $table->string('emoji', 16)->nullable()->after('short_text');
            $table->enum('placement', ['announcement_bar', 'shop_inline', 'both'])
                  ->default('shop_inline')
                  ->after('emoji');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['short_text', 'emoji', 'placement']);
        });
    }
};
