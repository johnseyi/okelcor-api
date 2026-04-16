# Session Handoff — Okelcor API
Last updated: 2026-04-16

## Project
Laravel 11 / PHP 8.3 REST API for Okelcor B2B tyre wholesale.
- Local: `http://localhost:8000`
- Production: `https://api.okelcor.de`
- DB: `okelcor_cms` on MySQL 8 via Laragon (root, no password)
- Auth: Laravel Sanctum token (Bearer) — admin routes only
- All responses: `application/json` (ForceJsonResponse middleware)
- GitHub: `https://github.com/johnseyi/okelcor-api.git` (branch: `main`)

---

## Current Route Count: 90+

### Public routes (no auth)
```
GET    /api/v1/products                  ← requires at least one filter param
GET    /api/v1/products/{id}
GET    /api/v1/products/brands           ← distinct brand list for filter dropdown
GET    /api/v1/products/specs            ← distinct widths/heights/rims/load_indexes/speed_ratings
GET    /api/v1/articles
GET    /api/v1/articles/{slug}
GET    /api/v1/categories
GET    /api/v1/hero-slides
GET    /api/v1/brands
GET    /api/v1/settings/public
GET    /api/v1/settings
GET    /api/v1/search
POST   /api/v1/vat/validate
POST   /api/v1/payments/create-intent
POST   /api/v1/payments/webhook          ← excluded from ForceJsonResponse (raw body for Stripe sig)
GET    /api/v1/orders                    ← requires ?email=
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
DELETE /admin/products/{id}             ← soft delete, 204
POST   /admin/products/{id}/restore
POST   /admin/products/{id}/images
DELETE /admin/products/{id}/images/{image}

# Product CSV import/export — super_admin, admin only
POST   /admin/products/import           ← upload CSV file (field: "file"), 50MB max
GET    /admin/products/export           ← streams CSV download

GET    /admin/articles
POST   /admin/articles
GET    /admin/articles/{id}
PUT    /admin/articles/{id}
DELETE /admin/articles/{id}             ← soft delete
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
PUT    /admin/orders/{id}               ← full update incl. shipment fields
PATCH  /admin/orders/{id}/status       ← lightweight status + shipment update

# Order CSV import/export — super_admin, admin, order_manager
POST   /admin/orders/import             ← upload Wix CSV (field: "file"), 50MB max
GET    /admin/orders/export             ← streams CSV download

GET    /admin/quote-requests
GET    /admin/quote-requests/{id}
PUT    /admin/quote-requests/{id}
PATCH  /admin/quote-requests/{id}/status

GET    /admin/contact-messages
GET    /admin/contact-messages/{id}
PATCH  /admin/contact-messages/{id}/status

GET    /admin/newsletter
DELETE /admin/newsletter/{email}
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
- Response: `{ data: { imported, updated, skipped, errors: [] } }`

### Order import (`POST /admin/orders/import`)
- Artisan command: `php artisan import:wix-orders {file}`
- Upserts on `order number` (Wix ref) — safe to re-run, items replaced each time
- Wix CSV column mapping (exact names Wix uses):
  - `Order number` → `ref`
  - `Contact email` → `customer_email`
  - `Billing name` → `customer_name`
  - `Billing phone` → `customer_phone`
  - `Billing address` → `address`
  - `Billing city` → `city`
  - `Billing zip/postal code` → `postal_code` (note: slash in column name)
  - `Billing country` → `country`
  - `Payment method` → `payment_method`
  - `Shipping rate` → `delivery_cost`
  - `Total` → `total` (subtotal = total - shipping_rate)
  - `Fulfillment status` → `status` (Fulfilled→delivered, Not fulfilled→pending, etc.)
  - `Payment status` → `payment_status`
  - `Tracking number` → `tracking_number`
  - `Delivery time` → `estimated_delivery`
  - `Note from customer` → `admin_notes`
  - `Item` → order item name
  - `SKU` → order item sku
  - `Qty` → order item quantity
  - `Price` → order item unit_price (line_total = price × qty)

### IMPORTANT — Upload directly to Laravel API (bypass Vercel)
Vercel has a hard 4.5 MB body size limit on serverless functions. Large CSV files (products: ~3MB+, orders: variable) must be uploaded directly to:
```
POST https://api.okelcor.de/api/v1/admin/products/import
POST https://api.okelcor.de/api/v1/admin/orders/import
```
NOT through the Vercel proxy (`okelcor-website.vercel.app/api/...`).

---

## Schema — Full Table Reference

### `products` (extended with tyre fields)
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
| `width` | varchar(10) | nullable, e.g. "205" |
| `height` | varchar(10) | nullable, e.g. "45" |
| `rim` | varchar(10) | nullable, e.g. "17" |
| `load_index` | varchar(10) | nullable, e.g. "88" |
| `speed_rating` | varchar(5) | nullable, e.g. "Y" |
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
| `payment_method` | varchar | |
| `subtotal` | decimal(10,2) | |
| `delivery_cost` | decimal(10,2) | default 0 |
| `total` | decimal(10,2) | |
| `status` | enum | pending / confirmed / processing / shipped / delivered / cancelled |
| `payment_status` | enum | unpaid / paid / refunded |
| `payment_intent_id` | varchar(100) | nullable — Stripe PI id |
| `mode` | enum | live / manual |
| `carrier` | varchar(100) | nullable |
| `tracking_number` | varchar(100) | nullable |
| `estimated_delivery` | date | nullable |
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

### Stripe Payment Gateway
- Package: `stripe/stripe-php` v20
- Config: `config/services.php` → `stripe.secret`, `stripe.webhook_secret`
- Env vars required on production: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`
- `StripeService::createPaymentIntent(int $cents, string $currency, array $metadata)`

**Flow:**
1. Frontend sends cart to `POST /api/v1/payments/create-intent`
2. Backend validates, looks up DB prices (prevents client-side price manipulation), saves a `pending` order with `mode=live`, creates Stripe PaymentIntent
3. Returns `{ "data": { "client_secret": "pi_xxx_secret_xxx" } }`
4. Frontend calls `stripe.confirmCardPayment(client_secret)` client-side
5. Stripe sends webhook to `POST /api/v1/payments/webhook`
6. Backend verifies `Stripe-Signature` header, updates order: `payment_intent.succeeded` → `status=processing, payment_status=paid`; `payment_intent.payment_failed` → `payment_status=failed`

**Webhook registration:** In Stripe Dashboard → Developers → Webhooks, add `https://api.okelcor.de/api/v1/payments/webhook` listening for `payment_intent.succeeded` and `payment_intent.payment_failed`.

### VAT Validation (EU VIES REST)
- No SOAP, no third-party package — direct HTTP via Laravel `Http` facade
- Endpoint: `POST /api/v1/vat/validate` body: `{ "vat_number": "DE123456789" }`
- Calls `https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{CC}/vat/{number}`
- Returns: `{ valid, name, address, country_code, vat_number, message }`
- VAT validation also runs automatically on `POST /orders` and `POST /quote-requests` when `vat_number` is provided

### Multilingual Content
- Locales: `en`, `de`, `fr`, `es`
- Pass `?locale=en|de|fr|es` on public content endpoints
- Articles: EN fallback — if requested locale has no translation, EN translation is returned
- Hero slides: locale-aware via `?locale=` param
- Categories: translation lookup via `?locale=`

### Role-Based Access Control
Middleware: `admin.role:{roles}` (comma-separated). Applied per route group.

| Role | Access |
|------|--------|
| `super_admin` | Everything including user management |
| `admin` | Content + operations + import/export (no user management) |
| `editor` | Content only (products, articles, categories, hero slides, brands, media, settings) |
| `order_manager` | Operations only (orders, quote requests, contacts, newsletter, order import/export) |

### Order Tracking (Public)
- `GET /api/v1/orders/{ref}` — full order detail by ref; used by customer tracking page with `cache: "no-store"`
- `GET /api/v1/orders?email={email}` — list all orders for an email; used for order history
- Both return identical field shapes including `status`, `carrier`, `tracking_number`, `estimated_delivery`
- Admin updates status via `PATCH /admin/orders/{id}/status` → customers see changes immediately on next page load

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
- `meta` — on paginated lists: `{ current_page, per_page, total, last_page }`; on non-paginated: `{ total }` or `{}`
- `message` — `"success"` on reads, descriptive string on writes
- Validation error (422): `{ "message": "...", "errors": { "field": ["..."] } }`
- Unauthenticated (401): `{ "message": "Unauthenticated." }`
- Forbidden (403): `{ "message": "Forbidden. Insufficient role." }`
- Import success: `{ "data": { "imported": N, "updated": N, "skipped": N, "errors": [] }, "message": "..." }`
- Import failure: `{ "data": null, "message": "Import failed.", "error": "..." }` — status 422

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
Video/image upload limit: 50 MB (`max:51200`)

---

## Soft Deletes

| Model | Soft delete? | Restore endpoint |
|-------|-------------|-----------------|
| `Product` | Yes | `POST /admin/products/{id}/restore` |
| `Article` | Yes | `POST /admin/articles/{id}/restore` |
| `Brand` | No (hard delete) | — |
| `HeroSlide` | No (hard delete) | — |

---

## Rate Limiting

| Limiter key | Limit | Applied to |
|-------------|-------|-----------|
| `search` | 30/min | `GET /search` |
| `vat` | 10/min | `POST /vat/validate` |
| `payments` | 20/min | `POST /payments/create-intent` |
| `public-form` | 10/hour | `POST /contact`, `POST /orders`, `GET /orders`, `GET /orders/{ref}`, `POST /newsletter/subscribe` |
| `quote-form` | 5/hour | `POST /quote-requests` |

---

## Pending / Not Yet Built

| Item | Notes |
|------|-------|
| Email notifications | Order/contact/quote receipts are `Log::info` only. Configure Resend/SMTP on Hostinger. |
| `GET /admin/products?trashed=only` | Restore works but no dedicated trashed product list endpoint |
| Stripe webhook registration | Must be done manually in Stripe Dashboard for production |

---

## Hostinger Deployment Checklist

After every `git push`, SSH into Hostinger and run:

```bash
cd ~/domains/takeovercreatives.com/public_html/okelcor-api
git pull origin main
php artisan migrate --force
php artisan route:clear
php artisan cache:clear
php artisan config:clear
```

**Migrations pending on Hostinger (as of 2026-04-16):**
```
2026_04_14_074810_add_payment_intent_fields_to_orders_table
2026_04_14_084610_make_order_items_sku_nullable
2026_04_14_113708_add_shipment_fields_to_orders_table
2026_04_15_000001_add_tyre_fields_to_products_table
```

**Required `.env` on Hostinger:**
```
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

---

## Environment

```
PHP:     8.3.30
Laravel: 11.x
MySQL:   8.0
DB:      okelcor_cms
Host:    127.0.0.1:3306
User:    root (no password, local) / Hostinger DB credentials (production)
upload_max_filesize: 2G (local) / Hostinger shared hosting limits apply
post_max_size:       2G
Web server: Apache
```
