<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EbayService
{
    private function isSandbox(): bool
    {
        return config('services.ebay.environment') !== 'production';
    }

    private function oauthBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
    }

    private function browseBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/buy/browse/v1/item_summary/search'
            : 'https://api.ebay.com/buy/browse/v1/item_summary/search';
    }

    public function getAccessToken(): string
    {
        $cacheKey = 'ebay_access_token_' . config('services.ebay.environment');

        return Cache::remember($cacheKey, 7000, function () {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.ebay.client_id'),
                    config('services.ebay.client_secret')
                )
                ->post($this->oauthBaseUrl(), [
                    'grant_type' => 'client_credentials',
                    'scope'      => 'https://api.ebay.com/oauth/api_scope',
                ]);

            if (! $response->ok()) {
                throw new \RuntimeException('eBay OAuth failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    public function searchTyres(string $query, int $limit = 20): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_DE'])
            ->get($this->browseBaseUrl(), [
                'q'            => $query,
                'category_ids' => '66471',
                'limit'        => min($limit, 50),
            ]);

        if (! $response->ok()) {
            return [];
        }

        $items = $response->json('itemSummaries') ?? [];

        return array_map(fn ($item) => [
            'title'              => $item['title'] ?? null,
            'price'              => $item['price']['value'] ?? null,
            'currency'           => $item['price']['currency'] ?? null,
            'condition'          => $item['condition'] ?? null,
            'seller'             => $item['seller']['username'] ?? null,
            'url'                => $item['itemWebUrl'] ?? null,
            'image'              => $item['image']['imageUrl'] ?? null,
            'quantity_available' => $item['estimatedAvailabilities'][0]['estimatedAvailableQuantity'] ?? null,
        ], $items);
    }
}
