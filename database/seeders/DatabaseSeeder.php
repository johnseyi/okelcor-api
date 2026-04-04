<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Auth — must run first (media table has FK to admin_users)
            AdminUserSeeder::class,

            // Global config
            SiteSettingsSeeder::class,

            // Reference data (no inter-dependencies)
            CategorySeeder::class,
            BrandSeeder::class,

            // Content with translations
            HeroSlideSeeder::class,
            ProductSeeder::class,
            ArticleSeeder::class,
        ]);
    }
}
