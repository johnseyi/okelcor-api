<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\HeroSlide;
use App\Models\HeroSlideTranslation;
use Illuminate\Database\Seeder;

/**
 * Inserts ES translations for all existing records.
 * Safe to re-run — uses updateOrCreate throughout.
 */
class SpanishTranslationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedHeroSlides();
        $this->seedArticles();
    }

    // -------------------------------------------------------------------------

    private function seedCategories(): void
    {
        $translations = [
            'pcr' => [
                'title'    => 'Neumáticos PCR',
                'label'    => 'PCR',
                'subtitle' => 'Neumáticos radiales para turismos de las principales marcas mundiales. Perfectos para sedanes, SUV y utilitarios.',
            ],
            'tbr' => [
                'title'    => 'Neumáticos TBR',
                'label'    => 'TBR',
                'subtitle' => 'Neumáticos radiales para camiones y autobuses diseñados para transporte de larga distancia, cargas pesadas y alto kilometraje.',
            ],
            'used' => [
                'title'    => 'Neumáticos de Ocasión',
                'label'    => 'Ocasión',
                'subtitle' => 'Neumáticos usados inspeccionados con calidad garantizada y excelente relación calidad-precio. Procedentes de mercados europeos con profundidad de dibujo verificada.',
            ],
            'otr' => [
                'title'    => 'Neumáticos OTR',
                'label'    => 'OTR',
                'subtitle' => 'Neumáticos todoterreno para maquinaria de construcción, minería y agricultura. Fabricados para terrenos extremos y uso de alta exigencia.',
            ],
        ];

        foreach ($translations as $slug => $t) {
            $category = Category::where('slug', $slug)->first();
            if (! $category) {
                continue;
            }

            CategoryTranslation::updateOrCreate(
                ['category_id' => $category->id, 'locale' => 'es'],
                $t
            );
        }

        $this->command->info('Category ES translations seeded.');
    }

    // -------------------------------------------------------------------------

    private function seedHeroSlides(): void
    {
        // Keyed by EN title so we can find the right slide_id regardless
        // of how many duplicate slide records exist in the DB.
        $translations = [
            'Your Global Tyre Partner' => [
                'title'         => 'Su Socio Global en Neumáticos',
                'subtitle'      => 'Neumáticos PCR, TBR, OTR y de ocasión suministrados a más de 40 países. Precios competitivos, entrega fiable.',
                'cta_primary'   => 'Ver Catálogo',
                'cta_secondary' => 'Solicitar Presupuesto',
            ],
            'Premium Brands, Wholesale Prices' => [
                'title'         => 'Marcas Premium, Precios de Mayorista',
                'subtitle'      => 'Michelin, Bridgestone, Continental y más — suministro directo, precios para compradores B2B.',
                'cta_primary'   => 'Ver Productos',
                'cta_secondary' => 'Contáctenos',
            ],
            'Fast Quotes, Reliable Supply' => [
                'title'         => 'Presupuestos Rápidos, Suministro Fiable',
                'subtitle'      => 'Envíe sus necesidades de neumáticos y reciba un presupuesto competitivo en 1 día hábil.',
                'cta_primary'   => 'Solicitar Presupuesto',
                'cta_secondary' => 'Saber Más',
            ],
        ];

        foreach ($translations as $enTitle => $es) {
            // Find every slide that has this EN title in hero_slide_translations
            $slideIds = HeroSlideTranslation::where('locale', 'en')
                ->where('title', $enTitle)
                ->pluck('slide_id');

            foreach ($slideIds as $slideId) {
                HeroSlideTranslation::updateOrCreate(
                    ['slide_id' => $slideId, 'locale' => 'es'],
                    $es
                );
            }
        }

        $this->command->info('Hero slide ES translations seeded.');
    }

    // -------------------------------------------------------------------------

    private function seedArticles(): void
    {
        $translations = [
            'sourcing-tyres-at-scale' => [
                'category'  => 'Logística',
                'title'     => 'Cómo Aprovisionar Neumáticos a Gran Escala para Mercados Internacionales',
                'read_time' => '5 min de lectura',
                'summary'   => 'El aprovisionamiento de neumáticos a gran escala en varios países requiere una cadena de suministro fiable, estándares de calidad consistentes y un socio que comprenda la logística transfronteriza.',
                'body'      => [
                    'Aprovisionar neumáticos a gran escala es fundamentalmente diferente a comprar localmente. Cuando se suministra a flotas, distribuidores o cadenas minoristas en toda Europa, la cadena logística se convierte en una ventaja competitiva clave.',
                    'El primer reto es la consistencia. Un comprador en Alemania espera la misma especificación de producto que uno en Polonia. Esto implica trabajar con un proveedor que garantice clasificación de calidad uniforme, embalaje estandarizado y documentación fiable, incluida la conformidad ECE y el etiquetado UE.',
                    'Los plazos de entrega son el segundo factor principal. La mayoría de los compradores internacionales operan con ciclos de aprovisionamiento de 30 a 60 días. Un proveedor que confirme disponibilidad de stock inmediatamente y envíe en 5-7 días hábiles reduce considerablemente la presión sobre el inventario del comprador.',
                    'En Okelcor, mantenemos stock rotativo de las tallas PCR y TBR más demandadas. Nuestros socios logísticos cubren transporte por carretera hacia todos los principales hubs europeos, con soporte documental para el despacho aduanero.',
                    'Si desea establecer una cadena de suministro de neumáticos fiable para su empresa, contacte a nuestro equipo comercial para una propuesta de suministro personalizada.',
                ],
            ],
            'understanding-tyre-labels' => [
                'category'  => 'Industria',
                'title'     => 'Entender la Etiqueta de Neumáticos UE: Lo que Todo Comprador B2B Debe Saber',
                'read_time' => '4 min de lectura',
                'summary'   => 'La etiqueta de neumáticos UE fue actualizada en mayo de 2021 e incluye ahora valoraciones de adherencia en nieve y hielo. Para operadores de flotas y compradores al por mayor, entender cada clasificación es esencial para las decisiones de compra.',
                'body'      => [
                    'Desde mayo de 2021, todos los neumáticos nuevos vendidos en la UE deben llevar el formato de etiqueta actualizado. Para compradores B2B, la etiqueta ofrece una forma rápida y estandarizada de comparar neumáticos entre marcas y evaluar el coste total de propiedad.',
                    'La etiqueta cubre tres categorías de rendimiento principales: eficiencia de combustible (A a E), adherencia en mojado (A a E) y ruido de rodadura exterior. Desde la actualización de 2021, también incluye indicadores opcionales de adherencia en nieve y en hielo.',
                    'Para gestores de flotas, la clase de eficiencia de combustible tiene el impacto financiero más directo. La diferencia entre un neumático con clasificación A y uno con clasificación C en un camión de larga distancia puede suponer varios cientos de euros en ahorro de combustible por vehículo y año.',
                    'La adherencia en mojado es igualmente importante desde el punto de vista de la responsabilidad y la seguridad. Las políticas de aprovisionamiento de muchos grandes operadores de flotas exigen ahora una nota mínima de adherencia en mojado de B o superior para todas las posiciones del eje delantero.',
                    'Al solicitar presupuestos a Okelcor, no dude en especificar sus requisitos mínimos de etiqueta. Nuestro equipo de producto puede filtrar la disponibilidad por clase de etiqueta para adaptarse a los estándares de su flota.',
                ],
            ],
            'tbr-market-outlook-2026' => [
                'category'  => 'Mercado',
                'title'     => 'Perspectivas del Mercado de Neumáticos TBR en Europa para 2026',
                'read_time' => '6 min de lectura',
                'summary'   => 'Se espera que la demanda europea de neumáticos radiales para camiones y autobuses crezca de forma constante en 2026, impulsada por la logística del comercio electrónico y la inversión en infraestructura.',
                'body'      => [
                    'El mercado europeo de neumáticos TBR afronta 2026 con optimismo cauteloso. Tras dos años de disrupciones en la cadena de suministro y volatilidad de precios, la disponibilidad se ha estabilizado y los plazos de entrega de fabricantes asiáticos han vuelto a los niveles previos a 2022 para la mayoría de tallas populares.',
                    'Los motores de demanda siguen siendo sólidos. El comercio electrónico transfronterizo continúa creciendo a tasas de dos dígitos, y los programas de inversión en infraestructura de la UE sostienen la demanda de neumáticos para maquinaria de construcción.',
                    'En cuanto a precios, los costes de materias primas —especialmente caucho natural y negro de humo— se mantuvieron ampliamente estables en el cuarto trimestre de 2025, permitiendo a la mayoría de fabricantes mantener precios u ofrecer reducciones moderadas en tallas de alto volumen.',
                    'La tendencia hacia neumáticos de eje motriz eficientes en combustible (clasificaciones A y B) continúa acelerándose, especialmente entre grandes operadores logísticos con obligaciones de reporte de CO₂ bajo marcos ESG europeos.',
                    'Para los equipos de compras de flotas, 2026 representa una buena ventana para cerrar acuerdos de suministro plurianuales a precios competitivos. Contacte a nuestro equipo comercial para analizar sus necesidades.',
                ],
            ],
        ];

        foreach ($translations as $slug => $t) {
            $article = Article::where('slug', $slug)->first();
            if (! $article) {
                continue;
            }

            ArticleTranslation::updateOrCreate(
                ['article_id' => $article->id, 'locale' => 'es'],
                $t
            );
        }

        $this->command->info('Article ES translations seeded.');
    }
}
