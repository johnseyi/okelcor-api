<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Michelin',     'sort_order' => 1],
            ['name' => 'Bridgestone',  'sort_order' => 2],
            ['name' => 'Continental',  'sort_order' => 3],
            ['name' => 'Goodyear',     'sort_order' => 4],
            ['name' => 'Pirelli',      'sort_order' => 5],
            ['name' => 'Dunlop',       'sort_order' => 6],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['name' => $brand['name']],
                ['sort_order' => $brand['sort_order'], 'is_active' => true]
            );
        }
    }
}
