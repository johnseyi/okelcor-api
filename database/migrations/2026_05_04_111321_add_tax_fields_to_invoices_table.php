<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal_net', 10, 2)->nullable()->after('amount');
            $table->string('tax_treatment', 30)->nullable()->after('subtotal_net');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_treatment');
            $table->decimal('tax_amount', 10, 2)->nullable()->after('tax_rate');
            $table->boolean('is_reverse_charge')->default(false)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['subtotal_net', 'tax_treatment', 'tax_rate', 'tax_amount', 'is_reverse_charge']);
        });
    }
};
