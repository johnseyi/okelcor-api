<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_listing_logs', function (Blueprint $table) {
            $table->id();

            // References — nullable so logs survive product/user deletion
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->string('sku')->nullable()->index();

            // Action enum
            $table->string('action')->index();
            // publish | publish_failed | remove | remove_failed
            // sync | sync_failed | refresh_status | refresh_status_failed

            $table->string('ebay_item_id')->nullable();
            $table->string('ebay_offer_id')->nullable();
            $table->string('status')->nullable();     // active | draft | error | ended | withdrawn | unknown
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('payload_summary')->nullable();

            // Append-only — no updated_at
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_listing_logs');
    }
};
