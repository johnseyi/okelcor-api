<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('vat_number', 30)->nullable()->after('company_name');
            $table->tinyInteger('vat_valid')->nullable()->after('vat_number');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('vat_number', 30)->nullable()->after('country');
            $table->tinyInteger('vat_valid')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['vat_number', 'vat_valid']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['vat_number', 'vat_valid']);
        });
    }
};
