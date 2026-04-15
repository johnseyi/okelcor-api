<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('width', 10)->nullable()->after('spec');
            $table->string('height', 10)->nullable()->after('width');
            $table->string('rim', 10)->nullable()->after('height');
            $table->string('load_index', 10)->nullable()->after('rim');
            $table->string('speed_rating', 5)->nullable()->after('load_index');
            $table->integer('stock')->nullable()->after('speed_rating');
            $table->decimal('cost_price', 10, 2)->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'rim', 'load_index', 'speed_rating', 'stock', 'cost_price']);
        });
    }
};
