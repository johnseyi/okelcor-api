<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipsGoService
{
    public function trackContainer(string $containerNumber): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'    => config('services.shipsgo.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.shipsgo.com/v2/shipmentlist', [
                'ContainerNo'      => $containerNumber,
                'ShippingLineCode' => null,
            ]);

            Log::info('ShipsGo response', [
                'container' => $containerNumber,
                'status'    => $response->status(),
                'body'      => $response->json(),
            ]);

            if (! $response->successful()) {
                return ['error' => 'Tracking unavailable'];
            }

            $data = $response->json();

            return [
                'status'   => $data['status']         ?? null,
                'vessel'   => $data['vessel']          ?? null,
                'location' => $data['location']        ?? null,
                'eta'      => $data['eta']              ?? null,
                'events'   => $data['events']           ?? [],
            ];
        } catch (\Throwable) {
            return ['error' => 'Tracking unavailable'];
        }
    }
}
