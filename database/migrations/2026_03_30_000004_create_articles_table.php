<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 300)->unique();
            $table->string('image', 500)->nullable();
            $table->date('published_at')->nullable();
            $table->tinyInteger('is_published')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('slug', 'idx_slug');
            $table->index(['is_published', 'published_at'], 'idx_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
