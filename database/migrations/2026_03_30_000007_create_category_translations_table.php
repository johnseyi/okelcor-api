<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->enum('locale', ['en', 'de', 'fr']);
            $table->string('title', 200);
            $table->string('label', 100);
            $table->string('subtitle', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['category_id', 'locale'], 'uq_cat_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_translations');
    }
};
