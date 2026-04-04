<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\HeroSlideTranslation;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            [
                'sort_order' => 1,
                'is_active'  => true,
                'translations' => [
                    'en' => [
                        'title'         => 'Your Global Tyre Partner',
                        'subtitle'      => 'PCR, TBR, OTR and Used tyres supplied to 40+ countries. Competitive pricing, reliable delivery.',
                        'cta_primary'   => 'Shop Catalogue',
                        'cta_secondary' => 'Get a Quote',
                    ],
                    'de' => [
                        'title'         => 'Ihr globaler Reifenpartner',
                        'subtitle'      => 'PKW-, LKW-, OTR- und Gebrauchreifen in über 40 Länder geliefert. Wettbewerbsfähige Preise, zuverlässige Lieferung.',
                        'cta_primary'   => 'Katalog ansehen',
                        'cta_secondary' => 'Angebot anfordern',
                    ],
                    'fr' => [
                        'title'         => 'Votre Partenaire Mondial en Pneumatiques',
                        'subtitle'      => 'Pneus PCR, TBR, OTR et d\'occasion livrés dans plus de 40 pays. Prix compétitifs, livraison fiable.',
                        'cta_primary'   => 'Voir le Catalogue',
                        'cta_secondary' => 'Obtenir un Devis',
                    ],
                ],
            ],
            [
                'sort_order' => 2,
                'is_active'  => true,
                'translations' => [
                    'en' => [
                        'title'         => 'Premium Brands, Wholesale Prices',
                        'subtitle'      => 'Michelin, Bridgestone, Continental and more — sourced direct, priced for B2B buyers.',
                        'cta_primary'   => 'Browse Products',
                        'cta_secondary' => 'Contact Us',
                    ],
                    'de' => [
                        'title'         => 'Premium-Marken, Großhandelspreise',
                        'subtitle'      => 'Michelin, Bridgestone, Continental und mehr – direkt bezogen, für B2B-Käufer kalkuliert.',
                        'cta_primary'   => 'Produkte entdecken',
                        'cta_secondary' => 'Kontakt aufnehmen',
                    ],
                    'fr' => [
                        'title'         => 'Marques Premium, Prix Grossiste',
                        'subtitle'      => 'Michelin, Bridgestone, Continental et plus encore — approvisionnement direct, tarifs B2B.',
                        'cta_primary'   => 'Parcourir les Produits',
                        'cta_secondary' => 'Nous Contacter',
                    ],
                ],
            ],
            [
                'sort_order' => 3,
                'is_active'  => true,
                'translations' => [
                    'en' => [
                        'title'         => 'Fast Quotes, Reliable Supply',
                        'subtitle'      => 'Submit your tyre requirements and receive a competitive quote within 1 business day.',
                        'cta_primary'   => 'Request a Quote',
                        'cta_secondary' => 'Learn More',
                    ],
                    'de' => [
                        'title'         => 'Schnelle Angebote, Verlässliche Versorgung',
                        'subtitle'      => 'Senden Sie Ihren Reifenbedarf und erhalten Sie innerhalb eines Werktages ein wettbewerbsfähiges Angebot.',
                        'cta_primary'   => 'Angebot anfordern',
                        'cta_secondary' => 'Mehr erfahren',
                    ],
                    'fr' => [
                        'title'         => 'Devis Rapides, Approvisionnement Fiable',
                        'subtitle'      => 'Soumettez vos besoins en pneumatiques et recevez un devis compétitif dans un délai d\'un jour ouvrable.',
                        'cta_primary'   => 'Demander un Devis',
                        'cta_secondary' => 'En Savoir Plus',
                    ],
                ],
            ],
        ];

        foreach ($slides as $data) {
            $slide = HeroSlide::create([
                'sort_order' => $data['sort_order'],
                'is_active'  => $data['is_active'],
            ]);

            foreach ($data['translations'] as $locale => $t) {
                HeroSlideTranslation::create(array_merge(
                    ['slide_id' => $slide->id, 'locale' => $locale],
                    $t
                ));
            }
        }
    }
}
