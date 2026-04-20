<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\EbaySellingService;
use Illuminate\Http\JsonResponse;

class EbayListingController extends Controller
{
    public function __construct(private EbaySellingService $ebay) {}

    // GET /admin/ebay/auth-url
    public function authUrl(): JsonResponse
    {
        return response()->json([
            'data'    => ['auth_url' => $this->ebay->getAuthUrl()],
            'message' => 'Visit this URL in a browser while logged in as the eBay seller to authorise the app.',
        ]);
    }

    // GET /admin/ebay/listings
    public function listings(): JsonResponse
    {
        $products = Product::where('ebay_listed', true)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'sku', 'name', 'brand', 'price', 'stock', 'ebay_listed', 'ebay_listing_id']);

        return response()->json([
            'data' => $products,
            'meta' => ['total' => $products->count()],
        ]);
    }

    // POST /admin/products/{id}/list-on-ebay
    public function listProduct(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $listingId = $this->ebay->createOrUpdateListing($product);

        $product->update([
            'ebay_listed'     => true,
            'ebay_listing_id' => $listingId,
        ]);

        return response()->json([
            'data'    => [
                'listing_id' => $listingId,
                'sku'        => $product->sku,
            ],
            'message' => "Product SKU {$product->sku} listed on eBay (listing #{$listingId}).",
        ]);
    }

    // DELETE /admin/products/{id}/ebay-listing
    public function removeListing(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $this->ebay->deleteListing($product->sku);

        $product->update([
            'ebay_listed'     => false,
            'ebay_listing_id' => null,
        ]);

        return response()->json([
            'message' => "eBay listing removed for SKU {$product->sku}.",
        ]);
    }

    // POST /admin/ebay/sync-all
    public function syncAll(): JsonResponse
    {
        $products = Product::where('ebay_listed', true)->get();

        $synced = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                $this->ebay->syncInventory($product);
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = "SKU {$product->sku}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'data'    => ['synced' => $synced, 'errors' => $errors],
            'message' => "Synced {$synced} of {$products->count()} listings.",
        ]);
    }
}
