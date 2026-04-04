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
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('title')->nullable()->after('id');
            $table->string('subtitle', 1000)->nullable()->after('title');
            $table->string('media_type', 10)->default('image')->after('subtitle');
            $table->string('image_url', 500)->nullable()->after('media_type');
            $table->string('video_url', 500)->nullable()->after('image_url');
            $table->string('cta_primary_label')->nullable()->after('video_url');
            $table->string('cta_primary_href')->nullable()->after('cta_primary_label');
            $table->string('cta_secondary_label')->nullable()->after('cta_primary_href');
            $table->string('cta_secondary_href')->nullable()->after('cta_secondary_label');
            // migrate existing image paths to image_url
            // (handled by controller going forward; old `image` column left intact for safety)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'subtitle', 'media_type',
                'image_url', 'video_url',
                'cta_primary_label', 'cta_primary_href',
                'cta_secondary_label', 'cta_secondary_href',
            ]);
        });
    }
};
