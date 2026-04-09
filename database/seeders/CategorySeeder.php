<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug'       => 'pcr',
                'sort_order' => 1,
                'translations' => [
                    'en' => [
                        'title'    => 'PCR Tyres',
                        'label'    => 'PCR',
                        'subtitle' => 'Passenger Car Radial tyres from leading global brands. Perfect for sedans, SUVs and hatchbacks.',
                    ],
                    'de' => [
                        'title'    => 'PKW-Reifen',
                        'label'    => 'PKW',
                        'subtitle' => 'Pkw-Radialreifen von führenden globalen Marken. Ideal für Limousinen, SUVs und Schrägheckfahrzeuge.',
                    ],
                    'fr' => [
                        'title'    => 'Pneus PCR',
                        'label'    => 'PCR',
                        'subtitle' => 'Pneus radiaux pour voitures particulières des meilleures marques mondiales. Idéaux pour berlines, SUV et citadines.',
                    ],
                    'es' => [
                        'title'    => 'Neumáticos PCR',
                        'label'    => 'PCR',
                        'subtitle' => 'Neumáticos radiales para turismos de las principales marcas mundiales. Perfectos para sedanes, SUV y utilitarios.',
                    ],
                ],
            ],
            [
                'slug'       => 'tbr',
                'sort_order' => 2,
                'translations' => [
                    'en' => [
                        'title'    => 'TBR Tyres',
                        'label'    => 'TBR',
                        'subtitle' => 'Truck and Bus Radial tyres engineered for long-haul transport, heavy loads and high mileage.',
                    ],
                    'de' => [
                        'title'    => 'LKW-Reifen',
                        'label'    => 'LKW',
                        'subtitle' => 'Radialreifen für Lkw und Busse, entwickelt für den Fernverkehr, schwere Lasten und hohe Laufleistungen.',
                    ],
                    'fr' => [
                        'title'    => 'Pneus TBR',
                        'label'    => 'TBR',
                        'subtitle' => 'Pneus radiaux pour camions et autobus conçus pour le transport longue distance, les charges lourdes et les grands kilométrages.',
                    ],
                    'es' => [
                        'title'    => 'Neumáticos TBR',
                        'label'    => 'TBR',
                        'subtitle' => 'Neumáticos radiales para camiones y autobuses diseñados para transporte de larga distancia, cargas pesadas y alto kilometraje.',
                    ],
                ],
            ],
            [
                'slug'       => 'used',
                'sort_order' => 3,
                'translations' => [
                    'en' => [
                        'title'    => 'Used Tyres',
                        'label'    => 'Used',
                        'subtitle' => 'Quality-inspected used tyres offering excellent value. Sourced from European markets with verified tread depth.',
                    ],
                    'de' => [
                        'title'    => 'Gebrauchtreifen',
                        'label'    => 'Gebraucht',
                        'subtitle' => 'Qualitätsgeprüfte Gebrauchreifen mit ausgezeichnetem Preis-Leistungs-Verhältnis. Aus europäischen Märkten mit verifizierten Profiltiefenwerten.',
                    ],
                    'fr' => [
                        'title'    => 'Pneus Occasion',
                        'label'    => 'Occasion',
                        'subtitle' => 'Pneus d\'occasion contrôlés offrant un excellent rapport qualité-prix. Provenant des marchés européens avec profondeur de sculpture vérifiée.',
                    ],
                    'es' => [
                        'title'    => 'Neumáticos de Ocasión',
                        'label'    => 'Ocasión',
                        'subtitle' => 'Neumáticos usados inspeccionados con calidad garantizada y excelente relación calidad-precio. Procedentes de mercados europeos con profundidad de dibujo verificada.',
                    ],
                ],
            ],
            [
                'slug'       => 'otr',
                'sort_order' => 4,
                'translations' => [
                    'en' => [
                        'title'    => 'OTR Tyres',
                        'label'    => 'OTR',
                        'subtitle' => 'Off-the-Road tyres for construction, mining and agricultural equipment. Built for extreme terrain and heavy duty use.',
                    ],
                    'de' => [
                        'title'    => 'OTR-Reifen',
                        'label'    => 'OTR',
                        'subtitle' => 'Geländereifen für Bau-, Bergbau- und Landwirtschaftsgeräte. Konzipiert für extremes Gelände und schwere Einsätze.',
                    ],
                    'fr' => [
                        'title'    => 'Pneus OTR',
                        'label'    => 'OTR',
                        'subtitle' => 'Pneus tout-terrain pour engins de construction, mines et agriculture. Conçus pour les terrains extrêmes et une utilisation intensive.',
                    ],
                    'es' => [
                        'title'    => 'Neumáticos OTR',
                        'label'    => 'OTR',
                        'subtitle' => 'Neumáticos todoterreno para maquinaria de construcción, minería y agricultura. Fabricados para terrenos extremos y uso de alta exigencia.',
                    ],
                ],
            ],
        ];

        foreach ($categories as $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['slug']],
                ['sort_order' => $data['sort_order'], 'is_active' => true]
            );

            foreach ($data['translations'] as $locale => $t) {
                CategoryTranslation::updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $locale],
                    $t
                );
            }
        }
    }
}
