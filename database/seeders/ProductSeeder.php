<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // PCR — Passenger Car Radial (4 products)
            [
                'sku'         => 'OKL-PCR-001',
                'brand'       => 'Michelin',
                'name'        => 'Energy Saver+',
                'size'        => '205/55R16',
                'spec'        => '91H',
                'season'      => 'Summer',
                'type'        => 'PCR',
                'price'       => 89.99,
                'description' => 'The Michelin Energy Saver+ is engineered for maximum fuel efficiency without compromising safety. Its innovative tread compound reduces rolling resistance, delivering up to 0.2 L/100km fuel savings compared to standard tyres. Excellent wet braking performance.',
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'sku'         => 'OKL-PCR-002',
                'brand'       => 'Bridgestone',
                'name'        => 'Turanza T005',
                'size'        => '225/45R17',
                'spec'        => '91W',
                'season'      => 'Summer',
                'type'        => 'PCR',
                'price'       => 124.50,
                'description' => 'The Bridgestone Turanza T005 delivers outstanding wet performance and precise handling for premium saloons and executive cars. Features NanoPro-Tech compound for long-lasting mileage and superior grip in all conditions.',
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'sku'         => 'OKL-PCR-003',
                'brand'       => 'Continental',
                'name'        => 'WinterContact TS 870',
                'size'        => '195/65R15',
                'spec'        => '91T',
                'season'      => 'Winter',
                'type'        => 'PCR',
                'price'       => 97.00,
                'description' => 'The Continental WinterContact TS 870 provides exceptional traction on snow and ice. Its evolved micro-pump sipe technology evacuates water and slush effectively, offering confident winter handling for compact and mid-size cars.',
                'is_active'   => true,
                'sort_order'  => 3,
            ],
            [
                'sku'         => 'OKL-PCR-004',
                'brand'       => 'Goodyear',
                'name'        => 'Vector 4Seasons Gen-3',
                'size'        => '215/60R16',
                'spec'        => '99H XL',
                'season'      => 'All Season',
                'type'        => 'PCR',
                'price'       => 108.75,
                'description' => 'The Goodyear Vector 4Seasons Gen-3 is a premium all-season tyre delivering year-round safety. Certified with the Three-Peak Mountain Snowflake symbol, it handles summer heat, autumn rain, and winter snow with equal confidence.',
                'is_active'   => true,
                'sort_order'  => 4,
            ],

            // TBR — Truck & Bus Radial (3 products)
            [
                'sku'         => 'OKL-TBR-001',
                'brand'       => 'Michelin',
                'name'        => 'X Line Energy D',
                'size'        => '315/70R22.5',
                'spec'        => '154/150L',
                'season'      => 'All Season',
                'type'        => 'TBR',
                'price'       => 389.00,
                'description' => 'The Michelin X Line Energy D is a fuel-efficient drive axle tyre designed for long-haul trucking. Its unique tread pattern and compound formulation reduce rolling resistance significantly, lowering fuel costs on motorway routes across Europe.',
                'is_active'   => true,
                'sort_order'  => 5,
            ],
            [
                'sku'         => 'OKL-TBR-002',
                'brand'       => 'Continental',
                'name'        => 'Conti EcoPlus HT3',
                'size'        => '385/65R22.5',
                'spec'        => '160K',
                'season'      => 'All Season',
                'type'        => 'TBR',
                'price'       => 465.00,
                'description' => 'The Continental Conti EcoPlus HT3 is an ultra-wide base trailer tyre offering fuel savings through reduced rolling resistance. Its robust construction handles heavy loads reliably, making it the choice for logistics fleets prioritising total cost of ownership.',
                'is_active'   => true,
                'sort_order'  => 6,
            ],
            [
                'sku'         => 'OKL-TBR-003',
                'brand'       => 'Bridgestone',
                'name'        => 'R168+ Steer',
                'size'        => '275/70R22.5',
                'spec'        => '148/145M',
                'season'      => 'All Season',
                'type'        => 'TBR',
                'price'       => 310.00,
                'description' => 'The Bridgestone R168+ is a highly durable steer axle tyre built for regional and long-haul operations. Its optimised tread design delivers even wear distribution, extended service life, and excellent retreadability for maximum fleet economy.',
                'is_active'   => true,
                'sort_order'  => 7,
            ],

            // Used (1 product)
            [
                'sku'         => 'OKL-USD-001',
                'brand'       => 'Pirelli',
                'name'        => 'P7 Cinturato (Used)',
                'size'        => '205/55R16',
                'spec'        => '91V',
                'season'      => 'Summer',
                'type'        => 'Used',
                'price'       => 34.99,
                'description' => 'Quality-inspected used Pirelli P7 Cinturato with 5–6mm tread depth remaining. Sourced from Western European fleets. Each tyre is individually checked for structural integrity, bead damage, and uniform wear before dispatch.',
                'is_active'   => true,
                'sort_order'  => 8,
            ],

            // OTR — Off the Road (2 products)
            [
                'sku'         => 'OKL-OTR-001',
                'brand'       => 'Michelin',
                'name'        => 'XHA2 Loader',
                'size'        => '17.5R25',
                'spec'        => '176B',
                'season'      => 'All-Terrain',
                'type'        => 'OTR',
                'price'       => 1249.00,
                'description' => 'The Michelin XHA2 is a radial loader tyre designed for wheel loaders and graders operating in quarry and mining environments. Its self-cleaning tread design resists stone entrapment, while the robust casing delivers exceptional puncture resistance.',
                'is_active'   => true,
                'sort_order'  => 9,
            ],
            [
                'sku'         => 'OKL-OTR-002',
                'brand'       => 'Goodyear',
                'name'        => 'GP-4D Agricultural',
                'size'        => '460/85R38',
                'spec'        => '158A8',
                'season'      => 'All-Terrain',
                'type'        => 'OTR',
                'price'       => 875.00,
                'description' => 'The Goodyear GP-4D is a high-flotation radial agricultural tyre built for tractors operating in challenging field conditions. Its aggressive lug pattern delivers maximum traction on wet and heavy soil, minimising compaction and improving crop yields.',
                'is_active'   => true,
                'sort_order'  => 10,
            ],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(['sku' => $data['sku']], $data);
        }
    }
}
