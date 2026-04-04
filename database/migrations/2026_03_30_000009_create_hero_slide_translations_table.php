<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slide_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slide_id')->constrained('hero_slides')->cascadeOnDelete();
            $table->enum('locale', ['en', 'de', 'fr']);
            $table->string('title', 300);
            $table->string('subtitle', 500);
            $table->string('cta_primary', 100)->default('Shop Catalogue');
            $table->string('cta_secondary', 100)->default('Get a Quote');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['slide_id', 'locale'], 'uq_slide_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slide_translations');
    }
};
