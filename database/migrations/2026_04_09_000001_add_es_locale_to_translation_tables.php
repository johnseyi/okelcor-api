<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // article_translations
        DB::statement("ALTER TABLE article_translations MODIFY COLUMN locale ENUM('en','de','fr','es') NOT NULL");

        // category_translations
        DB::statement("ALTER TABLE category_translations MODIFY COLUMN locale ENUM('en','de','fr','es') NOT NULL");

        // hero_slide_translations
        DB::statement("ALTER TABLE hero_slide_translations MODIFY COLUMN locale ENUM('en','de','fr','es') NOT NULL");

        // newsletter_subscribers
        DB::statement("ALTER TABLE newsletter_subscribers MODIFY COLUMN locale ENUM('en','de','fr','es') NOT NULL DEFAULT 'en'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE article_translations MODIFY COLUMN locale ENUM('en','de','fr') NOT NULL");
        DB::statement("ALTER TABLE category_translations MODIFY COLUMN locale ENUM('en','de','fr') NOT NULL");
        DB::statement("ALTER TABLE hero_slide_translations MODIFY COLUMN locale ENUM('en','de','fr') NOT NULL");
        DB::statement("ALTER TABLE newsletter_subscribers MODIFY COLUMN locale ENUM('en','de','fr') NOT NULL DEFAULT 'en'");
    }
};
