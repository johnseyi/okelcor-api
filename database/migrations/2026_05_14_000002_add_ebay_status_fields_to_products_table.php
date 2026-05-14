<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ebay_offer_id')->nullable()->after('ebay_item_id');
            $table->string('ebay_status')->nullable()->after('ebay_offer_id');
            $table->timestamp('ebay_last_synced_at')->nullable()->after('ebay_status');
            $table->text('ebay_sync_error')->nullable()->after('ebay_last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ebay_offer_id', 'ebay_status', 'ebay_last_synced_at', 'ebay_sync_error']);
        });
    }
};
