<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('brand', 100);
            $table->string('name', 200);
            $table->string('size', 50);
            $table->string('spec', 50)->default('');
            $table->enum('season', ['Summer', 'Winter', 'All Season', 'All-Terrain']);
            $table->enum('type', ['PCR', 'TBR', 'Used', 'OTR']);
            $table->decimal('price', 10, 2);
            $table->text('description');
            $table->string('primary_image', 500)->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('type', 'idx_type');
            $table->index('brand', 'idx_brand');
            $table->index('season', 'idx_season');
            $table->index('is_active', 'idx_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
