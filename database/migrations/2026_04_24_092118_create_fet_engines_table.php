<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fet_engines', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['cars_suv', 'commercial']);
            $table->string('manufacturer', 100);
            $table->string('model_series', 150);
            $table->string('engine_code', 50)->nullable();
            $table->string('displacement', 30)->nullable();
            $table->enum('fuel_type', ['diesel', 'petrol', 'both']);
            $table->string('fet_model', 100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('manufacturer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fet_engines');
    }
};
