<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 300);
            $table->string('original_name', 300);
            $table->string('path', 500);
            $table->string('url', 500);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('alt_text', 300)->nullable();
            $table->string('collection', 100)->nullable()->default('general');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('uploaded_by')->references('id')->on('admin_users')->nullOnDelete();
            $table->index('collection', 'idx_collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
