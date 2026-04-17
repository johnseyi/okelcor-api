<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('container_number', 30)->nullable()->after('tracking_number');
            $table->string('tracking_status', 50)->nullable()->after('container_number');
            $table->date('eta')->nullable()->after('tracking_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['container_number', 'tracking_status', 'eta']);
        });
    }
};
