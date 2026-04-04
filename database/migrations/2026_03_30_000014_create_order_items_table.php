<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('sku', 50);
            $table->string('brand', 100);
            $table->string('name', 200);
            $table->string('size', 50);
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('line_total', 10, 2);

            $table->index('order_id', 'idx_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
