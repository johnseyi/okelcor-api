<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('delivery_address', 300)->nullable()->after('delivery_location');
            $table->string('delivery_city', 100)->nullable()->after('delivery_address');
            $table->string('delivery_postal_code', 30)->nullable()->after('delivery_city');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['delivery_address', 'delivery_city', 'delivery_postal_code']);
        });
    }
};
