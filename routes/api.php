<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContainerTrackingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VatController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HeroSlideController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Admin\AdminArticleController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminContactController;
use App\Http\Controllers\Admin\AdminHeroSlideController;
use App\Http\Controllers\Admin\AdminNewsletterController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\OrderImportController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminQuoteRequestController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Public — no auth required
    // -------------------------------------------------------------------------

    // Products
    Route::get('products/brands', [ProductController::class, 'brands']);
    Route::get('products/specs', [ProductController::class, 'specs']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);

    // Articles
    Route::get('articles', [ArticleController::class, 'index']);
    Route::get('articles/{slug}', [ArticleController::class, 'show']);

    // Categories
    Route::get('categories', [CategoryController::class, 'index']);

    // Hero slides
    Route::get('hero-slides', [HeroSlideController::class, 'index']);

    // Brands
    Route::get('brands', [BrandController::class, 'index']);

    // Site settings (public read-only)
    Route::get('settings/public', [SettingController::class, 'public']);
    Route::get('settings', [SettingController::class, 'index']);

    // Container tracking — public, no auth
    Route::get('tracking/{container}', ContainerTrackingController::class);

    // Search — rate limited: 30/min
    Route::middleware('throttle:search')->group(function () {
        Route::get('search', SearchController::class);
    });

    // VAT validation — rate limited: 10/min
    Route::middleware('throttle:vat')->group(function () {
        Route::post('vat/validate', [VatController::class, 'validate']);
    });

    // Payments — rate limited: 20/min
    Route::middleware('throttle:payments')->group(function () {
        Route::post('payments/create-intent', [PaymentController::class, 'createIntent']);
    });

    // Stripe webhook — no rate limit, raw body required for signature verification
    Route::post('payments/webhook', [PaymentController::class, 'webhook'])
        ->withoutMiddleware([\App\Http\Middleware\ForceJsonResponse::class]);

    // Public forms — rate limited: 10/hour
    Route::middleware('throttle:public-form')->group(function () {
        Route::post('contact', [ContactController::class, 'store']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{ref}', [OrderController::class, 'show']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    });

    // Quote requests — rate limited: 5/hour (stricter per spec)
    Route::middleware('throttle:quote-form')->group(function () {
        Route::post('quote-requests', [QuoteRequestController::class, 'store']);
    });

    // Newsletter confirmation (GET — no rate limit needed)
    Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm']);

    // -------------------------------------------------------------------------
    // Admin auth (no Sanctum guard — these issue the token)
    // -------------------------------------------------------------------------
    Route::post('admin/login', [AuthController::class, 'login']);

    // -------------------------------------------------------------------------
    // Admin — protected by Sanctum token auth
    // Role hierarchy:
    //   super_admin  — full access
    //   admin        — full access
    //   editor       — content only (products, articles, categories, hero slides, brands, media, settings)
    //   order_manager — operations only (orders, quote requests, contacts, newsletter)
    // -------------------------------------------------------------------------
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

        // Auth — all authenticated admin users
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Own profile — all authenticated admin roles
        Route::get('profile', [AdminUserController::class, 'profile']);
        Route::put('profile', [AdminUserController::class, 'updateProfile']);
        Route::put('profile/password', [AdminUserController::class, 'changePassword']);

        // User management — super_admin only
        Route::middleware('admin.role:super_admin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::get('users/{id}', [AdminUserController::class, 'show']);
            Route::put('users/{id}', [AdminUserController::class, 'update']);
            Route::delete('users/{id}', [AdminUserController::class, 'destroy']);
        });

        // -----------------------------------------------------------------
        // Content routes — super_admin, admin, editor
        // -----------------------------------------------------------------
        // Import / export — super_admin and admin only
        Route::middleware('admin.role:super_admin,admin')->group(function () {
            Route::post('products/import', [ProductImportController::class, 'import']);
            Route::get('products/export', [ProductImportController::class, 'export']);
        });

        Route::middleware('admin.role:super_admin,admin,editor')->group(function () {

            // Products
            Route::post('products/{id}/restore', [AdminProductController::class, 'restore']);
            Route::get('products', [AdminProductController::class, 'index']);
            Route::post('products', [AdminProductController::class, 'store']);
            Route::get('products/{product}', [AdminProductController::class, 'show']);
            Route::put('products/{product}', [AdminProductController::class, 'update']);
            Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
            Route::post('products/{product}/images', [AdminProductController::class, 'uploadImages']);
            Route::delete('products/{product}/images/{image}', [AdminProductController::class, 'deleteImage']);

            // Articles
            Route::post('articles/{id}/restore', [AdminArticleController::class, 'restore']);
            Route::get('articles', [AdminArticleController::class, 'index']);
            Route::post('articles', [AdminArticleController::class, 'store']);
            Route::get('articles/{article}', [AdminArticleController::class, 'show']);
            Route::put('articles/{article}', [AdminArticleController::class, 'update']);
            Route::delete('articles/{article}', [AdminArticleController::class, 'destroy']);
            Route::post('articles/{id}/image', [AdminArticleController::class, 'uploadImage']);

            // Categories (fixed set — no create/delete)
            Route::get('categories', [AdminCategoryController::class, 'index']);
            Route::put('categories/{category}', [AdminCategoryController::class, 'update']);

            // Hero slides
            Route::get('hero-slides', [AdminHeroSlideController::class, 'index']);
            Route::post('hero-slides', [AdminHeroSlideController::class, 'store']);
            Route::get('hero-slides/{id}', [AdminHeroSlideController::class, 'show']);
            Route::put('hero-slides/{id}', [AdminHeroSlideController::class, 'update']);
            Route::post('hero-slides/{id}/media', [AdminHeroSlideController::class, 'uploadMedia']);
            Route::delete('hero-slides/{id}', [AdminHeroSlideController::class, 'destroy']);

            // Brands
            Route::get('brands', [AdminBrandController::class, 'index']);
            Route::post('brands', [AdminBrandController::class, 'store']);
            Route::get('brands/{id}', [AdminBrandController::class, 'show']);
            Route::put('brands/{id}', [AdminBrandController::class, 'update']);
            Route::post('brands/{id}/logo', [AdminBrandController::class, 'uploadLogo']);
            Route::delete('brands/{id}', [AdminBrandController::class, 'destroy']);

            // Media
            Route::get('media', [MediaController::class, 'index']);
            Route::post('media', [MediaController::class, 'store']);
            Route::delete('media/{id}', [MediaController::class, 'destroy']);

            // Site settings
            Route::get('settings', [AdminSettingController::class, 'index']);
            Route::put('settings', [AdminSettingController::class, 'update']);
        });

        // -----------------------------------------------------------------
        // Operations routes — super_admin, admin, order_manager
        // -----------------------------------------------------------------
        Route::middleware('admin.role:super_admin,admin,order_manager')->group(function () {

            // Quote requests
            Route::get('quote-requests', [AdminQuoteRequestController::class, 'index']);
            Route::get('quote-requests/{id}', [AdminQuoteRequestController::class, 'show']);
            Route::put('quote-requests/{id}', [AdminQuoteRequestController::class, 'update']);
            Route::patch('quote-requests/{id}/status', [AdminQuoteRequestController::class, 'updateStatus']);

            // Contact messages
            Route::get('contact-messages', [AdminContactController::class, 'index']);
            Route::get('contact-messages/{id}', [AdminContactController::class, 'show']);
            Route::patch('contact-messages/{id}/status', [AdminContactController::class, 'updateStatus']);

            // Orders
            Route::post('orders/import', [OrderImportController::class, 'import']);
            Route::get('orders/export', [OrderImportController::class, 'export']);
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{id}', [AdminOrderController::class, 'show']);
            Route::put('orders/{id}', [AdminOrderController::class, 'update']);
            Route::patch('orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
            Route::middleware('admin.role:super_admin,admin')->group(function () {
                Route::delete('orders/{id}', [AdminOrderController::class, 'destroy']);
            });

            // Newsletter subscribers
            Route::get('newsletter', [AdminNewsletterController::class, 'index']);
            Route::delete('newsletter/{email}', [AdminNewsletterController::class, 'destroy']);
        });
    });
});
