<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->enum('locale', ['en', 'de', 'fr']);
            $table->string('category', 100);
            $table->string('title', 500);
            $table->string('read_time', 30)->default('');
            $table->text('summary');
            $table->longText('body');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['article_id', 'locale'], 'uq_article_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_translations');
    }
};
