# Session Handoff — Okelcor API
Last updated: 2026-04-18

## Project
Laravel 11 / PHP 8.3 REST API for Okelcor B2B tyre wholesale.
- Local: `http://localhost:8000`
- Production: `https://api.okelcor.de`
- DB: `okelcor_cms` on MySQL 8 via Laragon (root, no password)
- Auth: Laravel Sanctum token (Bearer) — admin routes only
- All responses: `application/json` (ForceJsonResponse middleware)
- GitHub: `https://github.com/johnseyi/okelcor-api.git` (branch: `main`)

---

## Current Route Count: 95+

### Public routes (no auth)
```
GET    /api/v1/products
GET    /api/v1/products/{id}
GET    /api/v1/products/brands
GET    /api/v1/products/specs
GET    /api/v1/articles
GET    /api/v1/articles/{slug}
GET    /api/v1/categories
GET    /api/v1/hero-slides
GET    /api/v1/brands
GET    /api/v1/settings/public
GET    /api/v1/settings
GET    /api/v1/search
POST   /api/v1/vat/validate
POST   /api/v1/payments/create-session     ← was create-intent (now Adyen)
POST   /api/v1/payments/webhook            ← Adyen Standard Notification handler
GET    /api/v1/tracking/{container}        ← auto-detects DHL vs sea freight
GET    /api/v1/orders                      ← requires ?email=
GET    /api/v1/orders/{ref}
POST   /api/v1/orders
POST   /api/v1/contact
POST   /api/v1/newsletter/subscribe
GET    /api/v1/newsletter/confirm/{token}
POST   /api/v1/quote-requests
POST   /api/v1/admin/login
```

### Product filter query params (GET /api/v1/products)
At least ONE of these is required or endpoint returns empty with message:
| Param | Behaviour |
|-------|-----------|
| `q` or `search` | Full-text across brand, name, size, sku |
| `brand` | Exact match e.g. `?brand=PIRELLI` |
| `type` | PCR / TBR / OTR / Used |
| `season` | Summer / Winter / All Season / All-Terrain |
| `size` | Partial match e.g. `?size=205/45R17` |
| `price_min` | `WHERE price >= value` |
| `price_max` | `WHERE price <= value` |
| `sort` | `price_asc`, `price_desc`, `newest` (default) |
| `page` | Pagination |
Max 50 per page. All responses include `Cache-Control: no-store`.

### Admin routes (auth:sanctum)
All under `/api/v1/admin/` — require `Authorization: Bearer {token}`.

Role hierarchy: `super_admin` > `admin` > `editor` | `order_manager`

```
POST   /admin/logout
GET    /admin/me

# Own profile — all roles
GET    /admin/profile
PUT    /admin/profile
PUT    /admin/profile/password

# User management — super_admin only
GET    /admin/users
POST   /admin/users
GET    /admin/users/{id}
PUT    /admin/users/{id}
DELETE /admin/users/{id}

# Content — super_admin, admin, editor
GET    /admin/products
POST   /admin/products
GET    /admin/products/{id}
PUT    /admin/products/{id}
DELETE /admin/products/{id}
POST   /admin/products/{id}/restore
POST   /admin/products/{id}/images
DELETE /admin/products/{id}/images/{image}

# Product CSV import/export — super_admin, admin only
POST   /admin/products/import
GET    /admin/products/export

GET    /admin/articles
POST   /admin/articles
GET    /admin/articles/{id}
PUT    /admin/articles/{id}
DELETE /admin/articles/{id}
POST   /admin/articles/{id}/image
POST   /admin/articles/{id}/restore

GET    /admin/categories
PUT    /admin/categories/{id}

GET    /admin/hero-slides
POST   /admin/hero-slides
GET    /admin/hero-slides/{id}
PUT    /admin/hero-slides/{id}
POST   /admin/hero-slides/{id}/media
DELETE /admin/hero-slides/{id}

GET    /admin/brands
POST   /admin/brands
GET    /admin/brands/{id}
PUT    /admin/brands/{id}
POST   /admin/brands/{id}/logo
DELETE /admin/brands/{id}

GET    /admin/media
POST   /admin/media
DELETE /admin/media/{id}

GET    /admin/settings
PUT    /admin/settings

# Operations — super_admin, admin, order_manager
GET    /admin/orders
GET    /admin/orders/{id}
PUT    /admin/orders/{id}
PATCH  /admin/orders/{id}/status
DELETE /admin/orders/{id}               ← super_admin, admin only

# Order CSV import/export
POST   /admin/orders/import
GET    /admin/orders/export

GET    /admin/quote-requests
GET    /admin/quote-requests/{id}
PUT    /admin/quote-requests/{id}
PATCH  /admin/quote-requests/{id}/status

GET    /admin/contact-messages
GET    /admin/contact-messages/{id}
PATCH  /admin/contact-messages/{id}/status

GET    /admin/newsletter
DELETE /admin/newsletter/{email}

# Supplier intelligence — super_admin, admin, order_manager
GET    /admin/supplier/search?q={query}&limit={1-50}
GET    /admin/supplier/alibaba-link?q={query}
```

---

## Import/Export — Key Notes

### Product import (`POST /admin/products/import`)
- Artisan command: `php artisan import:wix-products {file}`
- Upserts on `sku` — safe to re-run
- Parses tyre dimensions (width/height/rim/load_index/speed_rating) from product name
- Pattern: `205/45R 17 88Y` (space between R and rim number)
- Detects season from name keywords (Winter, All Season, All-Terrain, Summer)
- Detects type: PCR (default) or TBR (keywords: Truck, Bus, TBR, Heavy, Commercial, LT, Cargo)
- **Image download:** reads `productimageurl` column (semicolon-separated filenames from Wix CDN)
  - Downloads image 1 → stores to `storage/app/public/products/{uuid}.jpg` → saves relative path to `primary_image`
  - Downloads image 2 → creates `ProductImage` gallery record
  - Skips silently on failure — product data still imports
  - `set_time_limit(600)` + `memory_limit 512M` applied for large runs
  - Logs every 100 image downloads; summary table includes "Images downloaded" column
- Response: `{ data: { imported, updated, skipped, errors: [] } }`

### Standalone image download command
```bash
php artisan import:product-images {file}
```
- Downloads missing images for products already in DB that have `primary_image IS NULL`
- Safe to re-run — only targets null `primary_image`
- Shows progress bar + downloaded/failed summary

### Order import (`POST /admin/orders/import`)
- Artisan command: `php artisan import:wix-orders {file}`
- Logic lives in `WixOrderImportService` — controller calls service directly (no Artisan::call)
- Upserts on `order number` (Wix ref) — safe to re-run, items replaced each time
- BOM stripping applied to CSV headers
- Wix CSV column mapping (exact names Wix uses):
  - `Order number` → `ref`
  - `Contact email` → `customer_email`
  - `Billing name` → `customer_name`
  - `Billing phone` → `customer_phone`
  - `Billing address` → `address`
  - `Billing city` → `city`
  - `Billing zip/postal code` → `postal_code`
  - `Billing country` → `country`
  - `Payment method` → `payment_method`
  - `Shipping rate` → `delivery_cost`
  - `Total` → `total`
  - `Fulfillment status` → `status`
  - `Payment status` → `payment_status`
  - `Tracking number` → `tracking_number`
  - `Delivery time` → `estimated_delivery`
  - `Note from customer` → `admin_notes`
  - `Item` / `SKU` / `Qty` / `Price` → order items

### IMPORTANT — Upload directly to Laravel API (bypass Vercel)
Vercel has a hard 4.5 MB body size limit. Large CSV files must be uploaded directly to:
```
POST https://api.okelcor.de/api/v1/admin/products/import
POST https://api.okelcor.de/api/v1/admin/orders/import
```
NOT through the Vercel proxy.

---

## Schema — Full Table Reference

### `products`
| Column | Type | Notes |
|--------|------|-------|
| `sku` | varchar(50) | unique |
| `brand` | varchar(100) | |
| `name` | varchar(200) | |
| `size` | varchar(50) | e.g. "205/45R17" |
| `spec` | varchar(50) | e.g. "88Y" |
| `season` | enum | Summer / Winter / All Season / All-Terrain |
| `type` | enum | PCR / TBR / Used / OTR |
| `price` | decimal(10,2) | |
| `primary_image` | varchar | nullable, relative path e.g. `products/uuid.jpg` |
| `width` | varchar(10) | nullable |
| `height` | varchar(10) | nullable |
| `rim` | varchar(10) | nullable |
| `load_index` | varchar(10) | nullable |
| `speed_rating` | varchar(5) | nullable |
| `stock` | int | nullable |
| `cost_price` | decimal(10,2) | nullable |
| `is_active` | tinyint | default 1 |
| `sort_order` | int | default 0 |

### `orders`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `ref` | varchar(30) | unique, Wix order number or `OKL-XXXXXX` |
| `customer_name` | varchar(200) | |
| `customer_email` | varchar(255) | indexed |
| `customer_phone` | varchar(50) | nullable |
| `address` | varchar(300) | |
| `city` | varchar(100) | |
| `postal_code` | varchar(20) | |
| `country` | varchar(100) | |
| `payment_method` | varchar(100) | nullable |
| `subtotal` | decimal(10,2) | |
| `delivery_cost` | decimal(10,2) | default 0 |
| `total` | decimal(10,2) | |
| `status` | enum | pending / confirmed / processing / shipped / delivered / cancelled |
| `payment_status` | enum | pending / paid / failed / refunded |
| `payment_session_id` | varchar(100) | nullable — Adyen session/PSP reference |
| `mode` | enum | live / manual |
| `carrier` | varchar(100) | nullable |
| `carrier_type` | enum | sea / air / dhl / road — nullable |
| `tracking_number` | varchar(100) | nullable |
| `container_number` | varchar(30) | nullable |
| `tracking_status` | varchar(50) | nullable |
| `estimated_delivery` | date | nullable |
| `eta` | date | nullable |
| `vat_number` | varchar(20) | nullable |
| `vat_valid` | tinyint | nullable |
| `admin_notes` | text | nullable |
| `ip_address` | varchar(45) | nullable, hidden from API |

### `order_items`
| Column | Type | Notes |
|--------|------|-------|
| `order_id` | bigint FK | |
| `product_id` | bigint | nullable FK |
| `sku` | varchar(50) | nullable |
| `brand` | varchar(100) | |
| `name` | varchar(200) | |
| `size` | varchar(50) | |
| `unit_price` | decimal(10,2) | |
| `quantity` | int | |
| `line_total` | decimal(10,2) | |

### `admin_users`
| Column | Type | Notes |
|--------|------|-------|
| `role` | enum | `super_admin`, `admin`, `editor`, `order_manager` |

### Translation tables (`article_translations`, `category_translations`, `hero_slide_translations`)
Locales ENUM: `en`, `de`, `fr`, `es`

---

## Features & Integrations

### Adyen Payment Gateway (replaced Stripe)
- Package: `adyen/php-api-library` v29
- Config: `config/services.php` → `adyen.api_key`, `adyen.merchant_account`, `adyen.environment`, `adyen.client_key`
- Env vars: `ADYEN_API_KEY`, `ADYEN_MERCHANT_ACCOUNT`, `ADYEN_ENVIRONMENT` (test/live), `ADYEN_CLIENT_KEY`
- `AdyenService::createPaymentSession(float $amount, string $currency, string $orderRef, string $customerEmail): array`

**Flow:**
1. Frontend sends cart to `POST /api/v1/payments/create-session`
2. Backend validates, looks up DB prices, saves `pending` order with `mode=live`, calls Adyen Sessions API
3. Returns `{ "data": { "session_id": "...", "session_data": "...", "client_key": "..." } }`
4. Frontend initialises Adyen Drop-in/Components with `session_id`, `session_data`, `client_key`
5. Adyen sends webhook to `POST /api/v1/payments/webhook`
6. Backend handles `AUTHORISATION` event → `status=processing, payment_status=paid`; also handles `CANCELLATION`/`CANCEL_OR_REFUND` → `payment_status=refunded`

**Webhook response:** must return plain text `[accepted]` — not JSON. Route is excluded from `ForceJsonResponse` middleware.

### Order Confirmation Emails
- `OrderConfirmation` mailable → sent to customer on `POST /api/v1/orders`
- `OrderReceived` mailable → sent to `ORDER_EMAIL` env var on `POST /api/v1/orders`
- Both views: `resources/views/emails/order-confirmation.blade.php` and `order-received.blade.php`
- Shipment fields (carrier, tracking_number, container_number, ETA) shown conditionally when set
- Tracking URL: `{FRONTEND_URL}/account/orders/{ref}`
- Env var required: `ORDER_EMAIL=orders@okelcor.de`

### Container Tracking (Public)
- `GET /api/v1/tracking/{container}` — auto-detects carrier by tracking number format
- **DHL** detected by regex: 10-12 digits, `JD…`, `1Z…`, `GM…` prefix → calls `DhlTrackingService`
- **Sea freight** (everything else) → calls `ShipsGoService`
- Response always includes `carrier` field: `"DHL"` or `"Sea Freight"`

**ShipsGo two-step flow:**
1. `POST /v2/ocean/shipments` — registers container for tracking (idempotent)
2. `GET /v2/ocean/shipments?filters[container_no]=eq:{container}` — fetches status
- Auth: `X-Shipsgo-User-Token` header
- First call may return null fields — ShipsGo takes minutes/hours to fetch live data from shipping line

**DHL:**
- Endpoint: `GET https://api-eu.dhl.com/track/shipments?trackingNumber={n}`
- Auth: `DHL-API-Key` header
- Returns: `{ status, location, eta, events[] }`

### Supplier Intelligence
- `GET /api/v1/admin/supplier/search?q={query}&limit={1-50}` — proxies eBay DE Browse API
- `GET /api/v1/admin/supplier/alibaba-link?q={query}` — returns Alibaba search URL (open in new tab)
- `EbayService`: client credentials OAuth token cached for ~2 hrs, searches category `66471` (tyres) on `EBAY_DE` marketplace
- Env vars: `EBAY_CLIENT_ID`, `EBAY_CLIENT_SECRET`, `EBAY_ENVIRONMENT` (sandbox/production)
- Sandbox returns dummy data — switch to `EBAY_ENVIRONMENT=production` with live credentials for real results

### VAT Validation (EU VIES REST)
- No SOAP, no third-party package — direct HTTP via Laravel `Http` facade
- Endpoint: `POST /api/v1/vat/validate` body: `{ "vat_number": "DE123456789" }`
- Calls `https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{CC}/vat/{number}`
- Returns: `{ valid, name, address, country_code, vat_number, message }`
- Also runs automatically on `POST /orders` and `POST /quote-requests` when `vat_number` is provided

### Multilingual Content
- Locales: `en`, `de`, `fr`, `es`
- Pass `?locale=en|de|fr|es` on public content endpoints
- Articles: EN fallback if requested locale has no translation
- Hero slides + categories: locale-aware via `?locale=` param

### Role-Based Access Control
Middleware: `admin.role:{roles}` (comma-separated).

| Role | Access |
|------|--------|
| `super_admin` | Everything including user management |
| `admin` | Content + operations + import/export (no user management) |
| `editor` | Content only (products, articles, categories, hero slides, brands, media, settings) |
| `order_manager` | Operations only (orders, quote requests, contacts, newsletter, import/export, supplier search) |

### Public Order API — fields returned
`GET /api/v1/orders/{ref}` and `GET /api/v1/orders?email=` both return:
```
ref, status, payment_status, payment_method, subtotal, delivery_cost, total,
carrier, tracking_number, container_number, estimated_delivery, eta, created_at, items[]
```

### CORS
Allowed origins:
- `http://localhost:3000`
- `https://okelcor-website.vercel.app`
- `https://okelcor.de`
- `https://www.okelcor.de`

---

## Response Envelope Reference

```json
{ "data": ..., "meta": { ... }, "message": "..." }
```

- `data` — always present (object or array)
- `meta` — on paginated lists: `{ current_page, per_page, total, last_page }`
- `message` — `"success"` on reads, descriptive string on writes
- Validation error (422): `{ "message": "...", "errors": { "field": ["..."] } }`
- Unauthenticated (401): `{ "message": "Unauthenticated." }`
- Forbidden (403): `{ "message": "Forbidden. Insufficient role." }`
- Import success: `{ "data": { "imported": N, "updated": N, "skipped": N, "errors": [] }, "message": "..." }`

---

## Image / Media Storage Rules

| Column | Stored in DB | Returned in API |
|--------|-------------|-----------------|
| `products.primary_image` | relative: `products/uuid.jpg` | absolute URL |
| `product_images.path` | relative: `products/uuid.jpg` | absolute URL |
| `articles.image` | relative: `articles/uuid.jpg` | absolute URL |
| `brands.logo` | relative: `brands/uuid.png` | absolute URL (`logo_url`) |
| `hero_slides.image_url` | relative: `hero/uuid.jpg` | absolute URL |
| `hero_slides.video_url` | relative: `hero/uuid.mp4` | absolute URL |

Storage disk: `public` → `storage/app/public/` → symlinked to `public/storage/`
Conversion: `url(Storage::url($relativePath))` in controller formatters.

---

## Soft Deletes

| Model | Soft delete? | Restore endpoint |
|-------|-------------|-----------------|
| `Product` | Yes | `POST /admin/products/{id}/restore` |
| `Article` | Yes | `POST /admin/articles/{id}/restore` |
| `Brand` | No (hard delete) | — |
| `HeroSlide` | No (hard delete) | — |
| `Order` | No (hard delete) | `DELETE /admin/orders/{id}` — super_admin, admin only |

---

## Rate Limiting

| Limiter key | Limit | Applied to |
|-------------|-------|-----------|
| `search` | 30/min | `GET /search` |
| `vat` | 10/min | `POST /vat/validate` |
| `payments` | 20/min | `POST /payments/create-session` |
| `public-form` | 10/hour | `POST /contact`, `POST /orders`, `GET /orders`, `GET /orders/{ref}`, `POST /newsletter/subscribe` |
| `quote-form` | 5/hour | `POST /quote-requests` |

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `import:wix-products {file}` | Import products from Wix CSV + download images |
| `import:product-images {file}` | Download missing images for already-imported products |
| `import:wix-orders {file}` | Import orders from Wix CSV |

---

## Pending / Not Yet Built

| Item | Notes |
|------|-------|
| eBay production credentials | Currently sandbox — set `EBAY_ENVIRONMENT=production` with live keys when ready |
| Adyen webhook HMAC verification | Currently accepts all POST to /payments/webhook — add HMAC check in production |
| `GET /admin/products?trashed=only` | Restore works but no dedicated trashed product list endpoint |

---

## Hostinger Deployment Checklist

After every `git push`, SSH into Hostinger and run:

```bash
cd ~/domains/takeovercreatives.com/public_html/okelcor-api
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

**All migrations are up to date as of 2026-04-18.**

**Required `.env` on Hostinger:**
```env
# Adyen (replaces Stripe)
ADYEN_API_KEY=
ADYEN_MERCHANT_ACCOUNT=
ADYEN_ENVIRONMENT=test
ADYEN_CLIENT_KEY=

# Order notifications
ORDER_EMAIL=orders@okelcor.de

# ShipsGo container tracking
SHIPSGO_API_KEY=

# DHL tracking
DHL_API_KEY=

# eBay supplier search
EBAY_CLIENT_ID=
EBAY_CLIENT_SECRET=
EBAY_ENVIRONMENT=sandbox

# Frontend URL (used in email tracking links)
FRONTEND_URL=https://okelcor.de
```

---

## Environment

```
PHP:     8.3.30
Laravel: 13.2.0
MySQL:   8.0
DB:      okelcor_cms
Host:    127.0.0.1:3306
User:    root (no password, local) / Hostinger DB credentials (production)
Web server: Apache
```
