<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename payment_intent_id → payment_session_id
        if (Schema::hasColumn('orders', 'payment_intent_id')) {
            Schema::table('orders', function ($table) {
                $table->renameColumn('payment_intent_id', 'payment_session_id');
            });
        }

        // Add 'adyen' to payment_method enum (VARCHAR since previous migration already made it VARCHAR)
        // No change needed — payment_method is already VARCHAR(100) NULL after migration 000001
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_session_id')) {
            Schema::table('orders', function ($table) {
                $table->renameColumn('payment_session_id', 'payment_intent_id');
            });
        }
    }
};
