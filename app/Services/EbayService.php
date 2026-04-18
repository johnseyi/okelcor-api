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

        // Strip overly specific tyre spec suffixes — keep brand + size only
        $searchQuery = $this->simplifyTyreQuery($query);

        $response = Http::withToken($token)
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_DE'])
            ->get($this->browseBaseUrl(), [
                'q'     => $searchQuery,
                'limit' => min($limit, 50),
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException(
                'eBay Browse API error ' . $response->status() . ': ' . $response->body()
            );
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

    // Extract "BRAND SIZE" from a full product name like
    // "YOKOHAMA 225/45R 18 95Y Tl Ad.Sp.V-105 Mo Summer"
    private function simplifyTyreQuery(string $query): string
    {
        // Normalise size: collapse "225/45R 18" → "225/45R18"
        $query = preg_replace('/(\d{3}\/\d{2,3}R)\s+(\d{2})/', '$1$2', $query);

        // Extract size
        preg_match('/\d{3}\/\d{2,3}R\d{2}/', $query, $sizeMatch);
        $size = $sizeMatch[0] ?? '';

        // Extract brand — first ALL-CAPS word (2+ chars) in the string
        preg_match('/\b([A-Z]{2,}[A-Z0-9\-]*)\b/', $query, $brandMatch);
        $brand = $brandMatch[1] ?? '';

        if ($brand && $size) {
            return "$brand $size";
        }

        return mb_substr($query, 0, 60);
    }
}
