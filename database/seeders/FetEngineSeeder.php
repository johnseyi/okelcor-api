<?php

namespace Database\Seeders;

use App\Models\FetEngine;
use Illuminate\Database\Seeder;

class FetEngineSeeder extends Seeder
{
    public function run(): void
    {
        FetEngine::truncate();

        // ---------------------------------------------------------------
        // Source: 2026-02-05_Engine overview_Cars_SUV_Sportcars.pdf
        // Each row = one vehicle class band from the application overview.
        // manufacturer  = vehicle class grouping (no per-brand data in PDF)
        // model_series  = typical vehicle examples from the PDF
        // displacement  = litres (range as string)
        // fuel_type     = petrol (passenger cars); both for SUVs (diesel/petrol)
        // fet_model     = FET size/designation from the PDF
        // notes         = cylinder layout, forced induction, power & torque
        // ---------------------------------------------------------------
        $carsSuv = [
            [
                'manufacturer' => 'Small cars',
                'model_series' => 'Polo, Fiesta',
                'engine_code'  => null,
                'displacement' => '1.0',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FI (SAE 5/16")',
                'notes'        => 'R3, Turbo, 90–120 HP, 160–200 Nm. Downsizing / EU-Standard class.',
            ],
            [
                'manufacturer' => 'Compact class',
                'model_series' => 'Golf, A3',
                'engine_code'  => null,
                'displacement' => '1.4–1.5',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FI (SAE 5/16")',
                'notes'        => 'R4, Turbo, 120–170 HP, 200–250 Nm. EU-Standard class.',
            ],
            [
                'manufacturer' => 'Compact class',
                'model_series' => 'GTI, i30N',
                'engine_code'  => null,
                'displacement' => '1.8–2.0',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FI (SAE 5/16" or SAE 1/2")',
                'notes'        => 'R4, Turbo, 180–300 HP, 300–400 Nm. Sporty sweet-spot class.',
            ],
            [
                'manufacturer' => 'Mid-range',
                'model_series' => 'Passat, 3er',
                'engine_code'  => null,
                'displacement' => '2.0',
                'fuel_type'    => 'both',
                'fet_model'    => 'FET-PRO-FI (SAE 5/16" or SAE 1/2")',
                'notes'        => 'R4, Turbo, 190–320 HP, 320–450 Nm. Sweet-spot / performance class.',
            ],
            [
                'manufacturer' => 'Mid-range',
                'model_series' => 'RS3',
                'engine_code'  => null,
                'displacement' => '2.5',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FII (SAE 5/16" or SAE 1/2")',
                'notes'        => 'R5, Turbo, 350–400 HP, 420–480 Nm. Performance class.',
            ],
            [
                'manufacturer' => 'Upper class',
                'model_series' => '5er, E-Klasse',
                'engine_code'  => null,
                'displacement' => '3.0',
                'fuel_type'    => 'both',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'R6/V6, Turbo, 280–450 HP, 400–600 Nm. Premium class.',
            ],
            [
                'manufacturer' => 'Upper class',
                'model_series' => 'S-Klasse',
                'engine_code'  => null,
                'displacement' => '4.0',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2")',
                'notes'        => 'V8, Biturbo, 450–650 HP, 600–850 Nm. Luxury class.',
            ],
            [
                'manufacturer' => 'SUV compact',
                'model_series' => 'Tiguan, Q3',
                'engine_code'  => null,
                'displacement' => '1.5–2.0',
                'fuel_type'    => 'both',
                'fet_model'    => 'FET-PRO-FII (SAE 5/16" or SAE 1/2")',
                'notes'        => 'R4, Turbo, 150–250 HP, 250–400 Nm. Mass-market long-distance class.',
            ],
            [
                'manufacturer' => 'Large SUV',
                'model_series' => 'X5, GLE',
                'engine_code'  => null,
                'displacement' => '3.0',
                'fuel_type'    => 'both',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'R6/V6, Turbo, 300–450 HP, 500–650 Nm. Long-distance class.',
            ],
            [
                'manufacturer' => 'SUV Performance',
                'model_series' => 'X5M, G63',
                'engine_code'  => null,
                'displacement' => '4.0',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2")',
                'notes'        => 'V8, Biturbo, 500–650 HP, 700–900 Nm. High-performance SUV class.',
            ],
            [
                'manufacturer' => 'Sports cars',
                'model_series' => 'Supra 2.0',
                'engine_code'  => null,
                'displacement' => '2.0',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'R4, Turbo, 250–320 HP, 350–420 Nm. Lightweight-construction performance.',
            ],
            [
                'manufacturer' => 'Sports cars',
                'model_series' => 'Supra, 911',
                'engine_code'  => null,
                'displacement' => '3.0',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'R6, Turbo, 350–510 HP, 500–650 Nm. Performance / emotion class.',
            ],
            [
                'manufacturer' => 'Supersport cars',
                'model_series' => 'Ferrari, Lamborghini',
                'engine_code'  => null,
                'displacement' => '4.0–6.5',
                'fuel_type'    => 'petrol',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2")',
                'notes'        => 'V8–V12, NA/Turbo, 600–1000+ HP, 700–1000+ Nm. Emotion / prestige class.',
            ],
        ];

        // ---------------------------------------------------------------
        // Source: 2026-02-05_Engine overview_Commercial Vehicle_til_40t.pdf
        // manufacturer  = vehicle weight class
        // model_series  = typical application / usage description
        // displacement  = litres (range)
        // fuel_type     = diesel (all commercial)
        // fet_model     = FET size/designation from the PDF
        // notes         = cylinder layout, power, torque, weight class
        // ---------------------------------------------------------------
        $commercial = [
            [
                'manufacturer' => 'Mini bus / Van',
                'model_series' => 'Shuttle, Taxi',
                'engine_code'  => null,
                'displacement' => '1.6',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FI (SAE 5/16")',
                'notes'        => 'R4, 95–135 PS, up to 3.5 t, 260–320 Nm.',
            ],
            [
                'manufacturer' => 'Mini bus / Van',
                'model_series' => 'Standard Transport',
                'engine_code'  => null,
                'displacement' => '2.0',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FI (SAE 5/16" or SAE 1/2")',
                'notes'        => 'R4, 110–190 PS, up to 3.5 t, 300–450 Nm.',
            ],
            [
                'manufacturer' => 'Mini bus / Van',
                'model_series' => 'Payload-oriented',
                'engine_code'  => null,
                'displacement' => '2.2–2.3',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'R4, 120–180 PS, up to 3.5 t, 320–450 Nm.',
            ],
            [
                'manufacturer' => 'Mini bus / Van',
                'model_series' => 'Trailer / Mountains',
                'engine_code'  => null,
                'displacement' => '3.0',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'V6, 190–258 PS, up to 3.5 t, 440–600 Nm.',
            ],
            [
                'manufacturer' => 'Transporter / Sprinter',
                'model_series' => 'Local transport',
                'engine_code'  => null,
                'displacement' => '2.0–2.3',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'R4, 130–180 PS, 3.5–5.0 t, 350–450 Nm.',
            ],
            [
                'manufacturer' => 'Transporter / Sprinter',
                'model_series' => '5t-class',
                'engine_code'  => null,
                'displacement' => '3.0',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FII (SAE 1/2")',
                'notes'        => 'V6, 190–210 PS, 3.5–5.0 t, 450–600 Nm.',
            ],
            [
                'manufacturer' => 'Light trucks',
                'model_series' => 'City / Distribution',
                'engine_code'  => null,
                'displacement' => '3.0–4.5',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2")',
                'notes'        => 'R4, 160–210 PS, 5.0–12 t, 600–800 Nm.',
            ],
            [
                'manufacturer' => 'Light trucks',
                'model_series' => 'Distribution traffic',
                'engine_code'  => null,
                'displacement' => '6.0–6.7',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2" or 5/8")',
                'notes'        => 'R6, 220–320 PS, 5.0–12 t, 800–1,200 Nm.',
            ],
            [
                'manufacturer' => 'Medium trucks',
                'model_series' => 'Regional / Long-distance',
                'engine_code'  => null,
                'displacement' => '7.7–8.0',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2" or 5/8")',
                'notes'        => 'R6, 250–350 PS, 12–18 t, 1,100–1,400 Nm.',
            ],
            [
                'manufacturer' => 'Medium trucks',
                'model_series' => 'Heavy distributor',
                'engine_code'  => null,
                'displacement' => '9.0',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIII (SAE 1/2" or 5/8")',
                'notes'        => 'R6, 300–430 PS, 12–18 t, 1,400–1,700 Nm.',
            ],
            [
                'manufacturer' => 'Heavy Trucks',
                'model_series' => 'Long-distance traffic',
                'engine_code'  => null,
                'displacement' => '10–12',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIV (SAE 5/8" or 3/4")',
                'notes'        => 'R6, 350–430 PS, up to 40 t, 1,800–2,100 Nm.',
            ],
            [
                'manufacturer' => 'Heavy Trucks',
                'model_series' => 'Long-distance traffic (high torque)',
                'engine_code'  => null,
                'displacement' => '12–13',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIV (SAE 5/8" or 3/4")',
                'notes'        => 'R6, 420–540 PS, up to 40 t, 2,100–2,600 Nm.',
            ],
            [
                'manufacturer' => 'Heavy Trucks',
                'model_series' => 'Heavy load',
                'engine_code'  => null,
                'displacement' => '15–16',
                'fuel_type'    => 'diesel',
                'fet_model'    => 'FET-PRO-FIV (SAE 5/8" or 3/4")',
                'notes'        => 'R6/V8, 520–660 PS, up to 40 t, 2,700–3,300 Nm. Metal supply lines may be required.',
            ],
        ];

        $now = now();

        $toInsert = [];

        foreach ($carsSuv as $row) {
            $toInsert[] = array_merge($row, ['category' => 'cars_suv', 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ($commercial as $row) {
            $toInsert[] = array_merge($row, ['category' => 'commercial', 'created_at' => $now, 'updated_at' => $now]);
        }

        FetEngine::insert($toInsert);

        $this->command->info('FET engines seeded: ' . count($toInsert) . ' rows (13 cars/SUV + 13 commercial).');
    }
}
