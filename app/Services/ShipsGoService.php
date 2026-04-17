<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShipsGoService
{
    public function trackContainer(string $containerNumber): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.shipsgo.key'),
            ])->get('https://api.shipsgo.com/v2/tracking', [
                'container_number' => $containerNumber,
            ]);

            if (! $response->successful()) {
                return ['error' => 'Tracking unavailable'];
            }

            $data = $response->json();

            return [
                'status'   => $data['status']   ?? null,
                'vessel'   => $data['vessel']    ?? null,
                'location' => $data['location']  ?? null,
                'eta'      => $data['eta']        ?? null,
                'events'   => $data['events']     ?? [],
            ];
        } catch (\Throwable) {
            return ['error' => 'Tracking unavailable'];
        }
    }
}
