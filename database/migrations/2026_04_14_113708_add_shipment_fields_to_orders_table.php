<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('carrier', 100)->nullable()->after('payment_intent_id');
            $table->string('tracking_number', 100)->nullable()->after('carrier');
            $table->date('estimated_delivery')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['carrier', 'tracking_number', 'estimated_delivery']);
        });
    }
};
