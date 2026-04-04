<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'slug'         => 'sourcing-tyres-at-scale',
                'published_at' => '2026-03-14',
                'is_published' => true,
                'sort_order'   => 1,
                'translations' => [
                    'en' => [
                        'category'  => 'Logistics',
                        'title'     => 'How to Source Tyres at Scale for International Markets',
                        'read_time' => '5 min read',
                        'summary'   => 'Scaling tyre procurement across multiple countries requires a reliable supply chain, consistent quality standards, and a partner who understands cross-border logistics. Here\'s what international buyers need to know.',
                        'body'      => [
                            'Sourcing tyres at scale is fundamentally different from buying locally. When you\'re supplying fleets, distributors, or retail chains across Europe or beyond, the logistics chain becomes a critical competitive advantage.',
                            'The first challenge is consistency. A buyer in Germany expects the same product specification as a buyer in Poland. This means working with a supplier who can guarantee uniform quality grading, standardised packaging, and reliable documentation — including ECE and EU tyre label compliance.',
                            'Lead times are the second major factor. Most international buyers operate on 30–60 day procurement cycles. A supplier who can confirm stock availability immediately and ship within 5–7 working days significantly reduces inventory pressure on the buyer\'s side.',
                            'At Okelcor, we maintain rolling stock of the most in-demand PCR and TBR sizes. Our logistics partners cover road freight to all major European hubs, with documentation support for customs clearance where required.',
                            'If you\'re looking to establish a reliable tyre supply chain for your business, contact our trade team for a tailored supply proposal.',
                        ],
                    ],
                    'de' => [
                        'category'  => 'Logistik',
                        'title'     => 'Wie Sie Reifen in großem Maßstab für internationale Märkte beziehen',
                        'read_time' => '5 Min. Lesezeit',
                        'summary'   => 'Die skalierte Reifenbeschaffung über mehrere Länder hinweg erfordert eine zuverlässige Lieferkette, konsistente Qualitätsstandards und einen Partner, der grenzüberschreitende Logistik versteht.',
                        'body'      => [
                            'Die Beschaffung von Reifen in großem Maßstab unterscheidet sich grundlegend vom lokalen Einkauf. Wenn Sie Fuhrparks, Distributoren oder Einzelhandelsketten in ganz Europa beliefern, wird die Lieferkette zu einem entscheidenden Wettbewerbsvorteil.',
                            'Die erste Herausforderung ist die Konsistenz. Ein Käufer in Deutschland erwartet dieselbe Produktspezifikation wie ein Käufer in Polen. Das bedeutet, mit einem Lieferanten zusammenzuarbeiten, der einheitliche Qualitätsbewertungen, standardisierte Verpackungen und zuverlässige Dokumentation — einschließlich ECE- und EU-Reifenetiketten-Konformität — garantieren kann.',
                            'Lieferzeiten sind der zweite wesentliche Faktor. Die meisten internationalen Käufer arbeiten mit Beschaffungszyklen von 30–60 Tagen. Ein Lieferant, der die Lagerverfügbarkeit sofort bestätigen und innerhalb von 5–7 Werktagen versenden kann, reduziert den Lagerdruck auf Käuferseite erheblich.',
                            'Bei Okelcor halten wir einen laufenden Bestand der gefragtesten PKW- und LKW-Größen bereit. Unsere Logistikpartner decken den Straßengüterverkehr zu allen wichtigen europäischen Drehkreuzen ab, mit Dokumentationsunterstützung für die Zollabfertigung.',
                            'Wenn Sie eine zuverlässige Reifen-Lieferkette für Ihr Unternehmen aufbauen möchten, kontaktieren Sie unser Handelsteam für ein maßgeschneidertes Versorgungsangebot.',
                        ],
                    ],
                    'fr' => [
                        'category'  => 'Logistique',
                        'title'     => 'Comment Approvisionner des Pneumatiques à Grande Échelle pour les Marchés Internationaux',
                        'read_time' => '5 min de lecture',
                        'summary'   => 'L\'approvisionnement en pneumatiques à grande échelle dans plusieurs pays nécessite une chaîne d\'approvisionnement fiable, des normes de qualité cohérentes et un partenaire qui comprend la logistique transfrontalière.',
                        'body'      => [
                            'L\'approvisionnement en pneumatiques à grande échelle est fondamentalement différent de l\'achat local. Lorsque vous approvisionnez des flottes, des distributeurs ou des chaînes de distribution à travers l\'Europe, la chaîne logistique devient un avantage concurrentiel décisif.',
                            'Le premier défi est la cohérence. Un acheteur en Allemagne attend la même spécification produit qu\'un acheteur en Pologne. Cela implique de travailler avec un fournisseur pouvant garantir une classification qualité uniforme, un emballage standardisé et une documentation fiable — y compris la conformité ECE et étiquetage UE.',
                            'Les délais de livraison constituent le deuxième facteur majeur. La plupart des acheteurs internationaux fonctionnent sur des cycles d\'approvisionnement de 30 à 60 jours. Un fournisseur capable de confirmer la disponibilité des stocks immédiatement et d\'expédier sous 5 à 7 jours ouvrables réduit considérablement la pression sur les stocks côté acheteur.',
                            'Chez Okelcor, nous maintenons un stock tournant des tailles PCR et TBR les plus demandées. Nos partenaires logistiques couvrent le fret routier vers tous les grands hubs européens, avec un accompagnement documentaire pour le dédouanement.',
                            'Si vous souhaitez établir une chaîne d\'approvisionnement fiable pour votre entreprise, contactez notre équipe commerciale pour une proposition d\'approvisionnement sur mesure.',
                        ],
                    ],
                ],
            ],
            [
                'slug'         => 'understanding-tyre-labels',
                'published_at' => '2026-02-28',
                'is_published' => true,
                'sort_order'   => 2,
                'translations' => [
                    'en' => [
                        'category'  => 'Industry',
                        'title'     => 'Understanding EU Tyre Labels: What Every B2B Buyer Should Know',
                        'read_time' => '4 min read',
                        'summary'   => 'The EU tyre label was updated in May 2021 and now includes snow and ice grip ratings. For fleet operators and wholesale buyers, understanding what each grade means is essential for procurement decisions.',
                        'body'      => [
                            'Since May 2021, all new tyres sold in the EU must carry the updated tyre label format. For B2B buyers, the label provides a fast, standardised way to compare tyres across brands and evaluate total cost of ownership.',
                            'The label covers three primary performance categories: fuel efficiency (A to E), wet grip (A to E), and external rolling noise (decibels and a wave indicator). Since the 2021 update, it also includes optional snow grip (Three-Peak Mountain Snowflake) and ice grip indicators.',
                            'For fleet operators, fuel efficiency grade has the most direct financial impact. The difference between an A-rated and a C-rated tyre on a long-haul truck can translate to several hundred euros in fuel savings per vehicle per year.',
                            'Wet grip is equally important from a liability and safety standpoint. Procurement policies at many large fleet operators now require a minimum wet grip grade of B or above for all front axle positions.',
                            'When requesting quotes from Okelcor, feel free to specify your minimum label requirements. Our product team can filter availability by label grade to match your fleet standards.',
                        ],
                    ],
                    'de' => [
                        'category'  => 'Branche',
                        'title'     => 'EU-Reifenlabel verstehen: Was jeder B2B-Käufer wissen sollte',
                        'read_time' => '4 Min. Lesezeit',
                        'summary'   => 'Das EU-Reifenlabel wurde im Mai 2021 aktualisiert und umfasst nun auch Schnee- und Eisgriffbewertungen. Für Flottenmanager und Großhandelskäufer ist das Verständnis der einzelnen Bewertungen für Beschaffungsentscheidungen unerlässlich.',
                        'body'      => [
                            'Seit Mai 2021 müssen alle in der EU verkauften neuen Reifen das aktualisierte Reifenlabel-Format tragen. Für B2B-Käufer bietet das Label eine schnelle, standardisierte Möglichkeit, Reifen markenübergreifend zu vergleichen und die Gesamtbetriebskosten zu bewerten.',
                            'Das Label deckt drei primäre Leistungskategorien ab: Kraftstoffeffizienz (A bis E), Nasshaftung (A bis E) und externes Rollgeräusch. Seit der Aktualisierung 2021 enthält es auch optionale Schneegriff- (Schneeflockensymbol) und Eisgriffanzeigen.',
                            'Für Flottenmanager hat die Kraftstoffeffizienzklasse die direkteste finanzielle Auswirkung. Der Unterschied zwischen einem A- und einem C-bewerteten Reifen auf einem Fernverkehrs-LKW kann sich in mehreren hundert Euro Kraftstoffeinsparung pro Fahrzeug und Jahr niederschlagen.',
                            'Nasshaftung ist aus Haftungs- und Sicherheitsgründen gleichermaßen wichtig. Beschaffungsrichtlinien vieler großer Flottenbetreiber fordern mittlerweile eine Mindestnasshaftungsklasse von B oder besser für alle Vorderachspositionen.',
                            'Geben Sie bei Angebotsanfragen bei Okelcor gerne Ihre Mindestlabelanforderungen an. Unser Produktteam kann die Verfügbarkeit nach Labelklasse filtern, um Ihren Flottenstandards zu entsprechen.',
                        ],
                    ],
                    'fr' => [
                        'category'  => 'Industrie',
                        'title'     => 'Comprendre l\'Étiquette Pneu UE : Ce que Tout Acheteur B2B Doit Savoir',
                        'read_time' => '4 min de lecture',
                        'summary'   => 'L\'étiquette pneu UE a été mise à jour en mai 2021 et comprend désormais des notes d\'adhérence sur neige et sur glace. Pour les gestionnaires de flottes et les acheteurs en gros, comprendre ce que signifie chaque note est essentiel.',
                        'body'      => [
                            'Depuis mai 2021, tous les nouveaux pneus vendus dans l\'UE doivent porter le format d\'étiquette pneu mis à jour. Pour les acheteurs B2B, l\'étiquette fournit un moyen rapide et standardisé de comparer les pneus entre marques et d\'évaluer le coût total de possession.',
                            'L\'étiquette couvre trois catégories de performance principales : efficacité énergétique (A à E), adhérence sur sol mouillé (A à E) et bruit de roulement extérieur. Depuis la mise à jour de 2021, elle inclut également des indicateurs optionnels d\'adhérence sur neige et sur glace.',
                            'Pour les gestionnaires de flottes, la classe d\'efficacité énergétique a l\'impact financier le plus direct. La différence entre un pneu classé A et un pneu classé C sur un camion longue distance peut représenter plusieurs centaines d\'euros d\'économies de carburant par véhicule et par an.',
                            'L\'adhérence sur sol mouillé est tout aussi importante du point de vue de la responsabilité et de la sécurité. Les politiques d\'approvisionnement de nombreux grands opérateurs de flottes exigent désormais une note minimale d\'adhérence sur sol mouillé de B ou supérieure pour toutes les positions d\'essieu avant.',
                            'Lors de vos demandes de devis à Okelcor, n\'hésitez pas à préciser vos exigences minimales en matière d\'étiquette. Notre équipe produit peut filtrer la disponibilité par classe d\'étiquette pour correspondre à vos standards de flotte.',
                        ],
                    ],
                ],
            ],
            [
                'slug'         => 'tbr-market-outlook-2026',
                'published_at' => '2026-01-15',
                'is_published' => true,
                'sort_order'   => 3,
                'translations' => [
                    'en' => [
                        'category'  => 'Market',
                        'title'     => 'TBR Tyre Market Outlook for Europe in 2026',
                        'read_time' => '6 min read',
                        'summary'   => 'European demand for Truck and Bus Radial tyres is expected to grow steadily through 2026, driven by e-commerce logistics and infrastructure investment. We break down what buyers should expect in terms of pricing, supply, and specification trends.',
                        'body'      => [
                            'The European TBR market enters 2026 with cautious optimism. After two years of supply chain disruption and price volatility, availability has stabilised and lead times from Asian manufacturers have returned to pre-2022 levels for most popular sizes.',
                            'Demand drivers remain strong. Cross-border e-commerce logistics continue to grow at double-digit rates, and the EU\'s infrastructure investment programmes are sustaining demand for construction equipment tyres — including OTR and heavy TBR sizes.',
                            'On the pricing side, raw material costs — particularly natural rubber and carbon black — have remained broadly stable through Q4 2025. This has allowed most manufacturers to hold prices or offer modest reductions on high-volume sizes in the 315/70R22.5 and 385/65R22.5 range.',
                            'The trend toward fuel-efficient drive axle tyres (A and B label ratings) continues to accelerate, particularly among large logistics operators managing fleet CO₂ reporting obligations under EU ESG frameworks.',
                            'For fleet procurement teams, 2026 represents a good window to lock in multi-year supply agreements at competitive rates. Okelcor\'s trade team can provide forward pricing on volume commitments for key TBR sizes — contact us to discuss your requirements.',
                        ],
                    ],
                    'de' => [
                        'category'  => 'Markt',
                        'title'     => 'LKW-Reifenmarkt Europa: Ausblick 2026',
                        'read_time' => '6 Min. Lesezeit',
                        'summary'   => 'Die europäische Nachfrage nach LKW-Radialreifen wird 2026 voraussichtlich stetig wachsen, angetrieben durch E-Commerce-Logistik und Infrastrukturinvestitionen. Wir analysieren, was Käufer bei Preisen, Angebot und Spezifikationstrends erwarten können.',
                        'body'      => [
                            'Der europäische LKW-Reifenmarkt startet mit vorsichtigem Optimismus ins Jahr 2026. Nach zwei Jahren Lieferkettenstörungen und Preisvolatilität hat sich die Verfügbarkeit stabilisiert und die Lieferzeiten asiatischer Hersteller haben für die meisten gängigen Größen das Niveau vor 2022 wieder erreicht.',
                            'Die Nachfragetreiber bleiben stark. Der grenzüberschreitende E-Commerce wächst weiterhin zweistellig, und die EU-Infrastrukturinvestitionsprogramme halten die Nachfrage nach Baumaschinenreifen — einschließlich OTR- und schwerer LKW-Größen — aufrecht.',
                            'Auf der Preisseite blieben die Rohstoffkosten — insbesondere Naturkautschuk und Ruß — im vierten Quartal 2025 weitgehend stabil. Dies hat es den meisten Herstellern ermöglicht, die Preise zu halten oder moderate Reduzierungen bei hohen Volumengrößen im Bereich 315/70R22.5 und 385/65R22.5 anzubieten.',
                            'Der Trend zu kraftstoffsparenden Antriebsachsreifen (Label-Bewertungen A und B) setzt sich weiter fort, insbesondere bei großen Logistikunternehmen, die im Rahmen der EU-ESG-Vorgaben CO₂-Flottenbericht-Pflichten erfüllen müssen.',
                            'Für Flottenmanager bietet 2026 ein gutes Fenster für den Abschluss mehrjähriger Liefervereinbarungen zu wettbewerbsfähigen Preisen. Unser Handelsteam kann für Volumenengagements in wichtigen LKW-Größen Vorwärtspreise anbieten — kontaktieren Sie uns.',
                        ],
                    ],
                    'fr' => [
                        'category'  => 'Marché',
                        'title'     => 'Perspectives du Marché des Pneus TBR en Europe pour 2026',
                        'read_time' => '6 min de lecture',
                        'summary'   => 'La demande européenne de pneus radiaux pour camions et autobus devrait croître régulièrement en 2026, portée par la logistique du commerce électronique et les investissements en infrastructure.',
                        'body'      => [
                            'Le marché européen des pneus TBR aborde 2026 avec un optimisme prudent. Après deux ans de perturbations de la chaîne d\'approvisionnement et de volatilité des prix, la disponibilité s\'est stabilisée et les délais de livraison des fabricants asiatiques sont revenus aux niveaux d\'avant 2022 pour la plupart des tailles courantes.',
                            'Les moteurs de la demande restent solides. Le commerce électronique transfrontalier continue de croître à des taux à deux chiffres, et les programmes d\'investissement en infrastructure de l\'UE soutiennent la demande de pneus pour engins de construction.',
                            'Du côté des prix, les coûts des matières premières — notamment le caoutchouc naturel et le noir de carbone — sont restés globalement stables au quatrième trimestre 2025. Cela a permis à la plupart des fabricants de maintenir leurs prix ou d\'offrir des réductions modestes sur les tailles à fort volume.',
                            'La tendance vers les pneus d\'essieu moteur économes en carburant (classements A et B) continue de s\'accélérer, en particulier chez les grands opérateurs logistiques soumis aux obligations de reporting CO₂ dans le cadre des réglementations ESG européennes.',
                            'Pour les équipes d\'achats de flottes, 2026 représente une bonne fenêtre pour conclure des accords d\'approvisionnement pluriannuels à des tarifs compétitifs. Contactez notre équipe commerciale pour discuter de vos besoins.',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($articles as $data) {
            $article = Article::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'published_at' => $data['published_at'],
                    'is_published' => $data['is_published'],
                    'sort_order'   => $data['sort_order'],
                ]
            );

            foreach ($data['translations'] as $locale => $t) {
                ArticleTranslation::updateOrCreate(
                    ['article_id' => $article->id, 'locale' => $locale],
                    [
                        'category'  => $t['category'],
                        'title'     => $t['title'],
                        'read_time' => $t['read_time'],
                        'summary'   => $t['summary'],
                        'body'      => $t['body'],
                    ]
                );
            }
        }
    }
}
