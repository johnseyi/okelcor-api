<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EbaySellingService
{
    // -------------------------------------------------------------------------
    // Environment helpers
    // -------------------------------------------------------------------------

    private function isSandbox(): bool
    {
        return config('services.ebay.environment') !== 'production';
    }

    private function oauthUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
    }

    private function inventoryBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/sell/inventory/v1'
            : 'https://api.ebay.com/sell/inventory/v1';
    }

    private function authBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://auth.sandbox.ebay.com/oauth2/authorize'
            : 'https://auth.ebay.com/oauth2/authorize';
    }

    // -------------------------------------------------------------------------
    // OAuth — user token via refresh_token
    // Different from EbayService which uses client_credentials (app token)
    // -------------------------------------------------------------------------

    public function getAccessToken(): string
    {
        $cacheKey = 'ebay_sell_user_token_' . config('services.ebay.environment');

        return Cache::remember($cacheKey, 7000, function () {
            $refreshToken = config('services.ebay_sell.refresh_token');

            if (empty($refreshToken)) {
                throw new \RuntimeException(
                    'EBAY_REFRESH_TOKEN is not set. Visit GET /admin/ebay/auth-url to authorise the seller account first.'
                );
            }

            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.ebay_sell.client_id'),
                    config('services.ebay_sell.client_secret')
                )
                ->post($this->oauthUrl(), [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'scope'         => implode(' ', [
                        'https://api.ebay.com/oauth/api_scope/sell.inventory',
                        'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
                        'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
                    ]),
                ]);

            if (! $response->ok()) {
                throw new \RuntimeException('eBay user token refresh failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    // -------------------------------------------------------------------------
    // OAuth consent URL — admin must visit this once to authorise the app
    // -------------------------------------------------------------------------

    public function getAuthUrl(): string
    {
        $scopes = implode(' ', [
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
        ]);

        return $this->authBaseUrl() . '?' . http_build_query([
            'client_id'     => config('services.ebay_sell.client_id'),
            'redirect_uri'  => config('services.ebay_sell.ru_name'),
            'response_type' => 'code',
            'scope'         => $scopes,
            'state'         => 'okelcor_ebay_auth',
        ]);
    }

    // -------------------------------------------------------------------------
    // Create or update a listing for a product
    // Returns the eBay listingId (item number shown in the URL)
    // -------------------------------------------------------------------------

    public function createOrUpdateListing(Product $product): string
    {
        $this->guardProduct($product);

        $token = $this->getAccessToken();
        $sku   = $product->sku;

        $this->upsertInventoryItem($product, $token);
        $offerId   = $this->upsertOffer($product, $token);
        $listingId = $this->publishOffer($offerId, $token);

        Log::info("eBay listing published: SKU {$sku} → listingId {$listingId}");

        return $listingId;
    }

    // -------------------------------------------------------------------------
    // Sync stock quantity only — no title/price changes
    // -------------------------------------------------------------------------

    public function syncInventory(Product $product): void
    {
        if (! $product->ebay_listed) {
            return;
        }

        $token    = $this->getAccessToken();
        $quantity = max(0, (int) $product->stock);

        $response = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->put("{$this->inventoryBaseUrl()}/inventory_item/{$product->sku}", [
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => $quantity,
                    ],
                ],
                // Reuse existing product data — partial updates require the full body in Inventory API
                'condition' => 'NEW',
                'product'   => [
                    'title'     => $this->buildTitle($product),
                    'imageUrls' => $this->imageUrls($product),
                ],
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException("eBay syncInventory failed for SKU {$product->sku}: " . $response->body());
        }
    }

    // -------------------------------------------------------------------------
    // Delete listing and inventory item
    // -------------------------------------------------------------------------

    public function deleteListing(string $sku): void
    {
        $token = $this->getAccessToken();

        // Withdraw any active offer for this SKU first
        $offersResponse = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->get("{$this->inventoryBaseUrl()}/offer", ['sku' => $sku]);

        if ($offersResponse->ok()) {
            foreach ($offersResponse->json('offers') ?? [] as $offer) {
                $offerId = $offer['offerId'];
                Http::withToken($token)
                    ->withHeaders($this->commonHeaders())
                    ->delete("{$this->inventoryBaseUrl()}/offer/{$offerId}");
            }
        }

        // Delete the inventory item
        $deleteResponse = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->delete("{$this->inventoryBaseUrl()}/inventory_item/{$sku}");

        if (! $deleteResponse->ok() && $deleteResponse->status() !== 404) {
            throw new \RuntimeException("eBay deleteListing failed for SKU {$sku}: " . $deleteResponse->body());
        }

        Log::info("eBay listing deleted: SKU {$sku}");
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function upsertInventoryItem(Product $product, string $token): void
    {
        $body = [
            'condition'    => 'NEW',
            'availability' => [
                'shipToLocationAvailability' => [
                    'quantity' => max(0, (int) $product->stock),
                ],
            ],
            'product' => [
                'title'       => $this->buildTitle($product),
                'description' => $this->buildDescription($product),
                'imageUrls'   => $this->imageUrls($product),
                'aspects'     => $this->buildAspects($product),
            ],
        ];

        $response = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->put("{$this->inventoryBaseUrl()}/inventory_item/{$product->sku}", $body);

        // 200 = updated, 201 = created — both are success
        if (! in_array($response->status(), [200, 204])) {
            throw new \RuntimeException("eBay inventory item upsert failed for SKU {$product->sku}: " . $response->body());
        }
    }

    private function upsertOffer(Product $product, string $token): string
    {
        $marketplaceId = config('services.ebay_sell.marketplace_id', 'EBAY_DE');
        $categoryId    = config('services.ebay_sell.category_id', '11755');

        $offerBody = [
            'sku'               => $product->sku,
            'marketplaceId'     => $marketplaceId,
            'format'            => 'FIXED_PRICE',
            'availableQuantity' => max(0, (int) $product->stock),
            'categoryId'        => $categoryId,
            'listingPolicies'   => [
                'fulfillmentPolicyId' => config('services.ebay_sell.fulfillment_policy_id'),
                'paymentPolicyId'     => config('services.ebay_sell.payment_policy_id'),
                'returnPolicyId'      => config('services.ebay_sell.return_policy_id'),
            ],
            'pricingSummary' => [
                'price' => [
                    'value'    => number_format((float) $product->price, 2, '.', ''),
                    'currency' => 'EUR',
                ],
            ],
            'listingDescription' => $this->buildDescription($product),
        ];

        // Check if offer already exists for this SKU
        $existing = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->get("{$this->inventoryBaseUrl()}/offer", ['sku' => $product->sku]);

        if ($existing->ok() && ! empty($existing->json('offers'))) {
            $offerId = $existing->json('offers.0.offerId');

            $response = Http::withToken($token)
                ->withHeaders($this->commonHeaders())
                ->put("{$this->inventoryBaseUrl()}/offer/{$offerId}", $offerBody);

            if (! $response->ok()) {
                throw new \RuntimeException("eBay offer update failed for SKU {$product->sku}: " . $response->body());
            }

            return $offerId;
        }

        // Create new offer
        $response = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->post("{$this->inventoryBaseUrl()}/offer", $offerBody);

        if (! $response->ok()) {
            throw new \RuntimeException("eBay offer create failed for SKU {$product->sku}: " . $response->body());
        }

        return $response->json('offerId');
    }

    private function publishOffer(string $offerId, string $token): string
    {
        $response = Http::withToken($token)
            ->withHeaders($this->commonHeaders())
            ->post("{$this->inventoryBaseUrl()}/offer/{$offerId}/publish");

        if (! $response->ok()) {
            throw new \RuntimeException("eBay offer publish failed for offerId {$offerId}: " . $response->body());
        }

        return (string) $response->json('listingId');
    }

    private function buildTitle(Product $product): string
    {
        // eBay title limit: 80 characters
        $parts = array_filter([
            $product->brand,
            $product->size,
            $product->spec,
            $product->season,
        ]);

        $title = implode(' ', $parts);

        return mb_substr($title, 0, 80);
    }

    private function buildDescription(Product $product): string
    {
        $lines = array_filter([
            $product->name,
            $product->brand  ? "Brand: {$product->brand}"   : null,
            $product->size   ? "Size: {$product->size}"     : null,
            $product->spec   ? "Spec: {$product->spec}"     : null,
            $product->season ? "Season: {$product->season}" : null,
            $product->type   ? "Type: {$product->type}"     : null,
        ]);

        return implode("\n", $lines);
    }

    private function buildAspects(Product $product): array
    {
        $aspects = [];

        if ($product->brand)       $aspects['Brand']        = [$product->brand];
        if ($product->width)       $aspects['Tyre Width']   = [$product->width];
        if ($product->height)      $aspects['Aspect Ratio'] = [$product->height];
        if ($product->rim)         $aspects['Rim Diameter'] = [$product->rim];
        if ($product->load_index)  $aspects['Load Rating']  = [$product->load_index];
        if ($product->speed_rating) $aspects['Speed Rating'] = [$product->speed_rating];
        if ($product->season)      $aspects['Season']       = [$product->season];

        return $aspects;
    }

    private function imageUrls(Product $product): array
    {
        $urls = [];

        if ($product->primary_image) {
            $urls[] = url(Storage::url($product->primary_image));
        }

        foreach ($product->images as $img) {
            $urls[] = url(Storage::url($img->path));
        }

        return array_values(array_unique($urls));
    }

    private function guardProduct(Product $product): void
    {
        if (empty($product->sku)) {
            throw new \InvalidArgumentException("Product ID {$product->id} has no SKU — cannot list on eBay.");
        }

        if (empty($product->price) || (float) $product->price <= 0) {
            throw new \InvalidArgumentException("Product SKU {$product->sku} has no price — cannot list on eBay.");
        }

        if (empty($this->imageUrls($product))) {
            throw new \InvalidArgumentException("Product SKU {$product->sku} has no images — eBay requires at least one image.");
        }

        if (
            empty(config('services.ebay_sell.fulfillment_policy_id')) ||
            empty(config('services.ebay_sell.payment_policy_id')) ||
            empty(config('services.ebay_sell.return_policy_id'))
        ) {
            throw new \RuntimeException(
                'eBay policy IDs are not configured. Set EBAY_FULFILLMENT_POLICY_ID, EBAY_PAYMENT_POLICY_ID, EBAY_RETURN_POLICY_ID in .env'
            );
        }
    }

    private function commonHeaders(): array
    {
        return [
            'Content-Language' => 'en-US',
        ];
    }
}
