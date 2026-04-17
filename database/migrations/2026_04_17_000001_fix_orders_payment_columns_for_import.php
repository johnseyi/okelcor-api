<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The payment_intent migration changed payment_method to a restrictive
     * ENUM('stripe','revolut','bank_transfer') and dropped 'unpaid'/'refunded'
     * from payment_status. Both break Wix CSV imports which send arbitrary
     * payment method strings and use 'unpaid'/'refunded' status values.
     *
     * This migration:
     *  - Reverts payment_method to nullable VARCHAR(100) so any value is stored
     *  - Expands payment_status enum to include 'refunded' alongside the new values
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(100) NULL");

        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status
            ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method
            ENUM('stripe','revolut','bank_transfer') NULL DEFAULT NULL");

        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status
            ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending'");
    }
};
