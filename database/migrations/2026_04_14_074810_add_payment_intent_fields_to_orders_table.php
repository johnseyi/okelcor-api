<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add payment_intent_id column
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_intent_id', 100)->nullable()->after('vat_valid');
        });

        // Map existing 'unpaid' → 'pending' before altering enum
        DB::statement("UPDATE orders SET payment_status = 'pending' WHERE payment_status = 'unpaid'");

        // Modify payment_status enum: (unpaid, paid, refunded) → (pending, paid, failed)
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending'");

        // Change payment_method from varchar(50) to nullable enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('stripe','revolut','bank_transfer') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(50) NOT NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_intent_id');
        });
    }
};
