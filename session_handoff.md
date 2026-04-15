# Session Handoff — Okelcor API
Last updated: 2026-04-15

## Project
Laravel 11 / PHP 8.3 REST API for Okelcor B2B tyre wholesale.
- Local: `http://localhost:8000`
- Production: `https://api.okelcor.de`
- DB: `okelcor_cms` on MySQL 8 via Laragon (root, no password)
- Auth: Laravel Sanctum token (Bearer) — admin routes only
- All responses: `application/json` (ForceJsonResponse middleware)
- GitHub: `https://github.com/johnseyi/okelcor-api.git` (branch: `main`)

---

## Current Route Count: 80

### Public routes (no auth)
```
GET    /api/v1/products
GET    /api/v1/products/{id}
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

## Schema — Full Table Reference

### `orders`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `ref` | varchar(30) | unique, e.g. `OKL-AB1CD2` |
| `customer_name` | varchar(200) | |
| `customer_email` | varchar(255) | indexed |
| `customer_phone` | varchar(50) | nullable |
| `address` | varchar(300) | |
| `city` | varchar(100) | |
| `postal_code` | varchar(20) | |
| `country` | varchar(100) | |
| `payment_method` | enum | `stripe`, `revolut`, `bank_transfer` — nullable |
| `subtotal` | decimal(10,2) | |
| `delivery_cost` | decimal(10,2) | default 0 |
| `total` | decimal(10,2) | |
| `status` | enum | `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled` |
| `payment_status` | enum | `pending`, `paid`, `failed` |
| `payment_intent_id` | varchar(100) | nullable — Stripe PI id |
| `mode` | enum | `live`, `manual` |
| `carrier` | varchar(100) | nullable |
| `tracking_number` | varchar(100) | nullable |
| `estimated_delivery` | date | nullable |
| `vat_number` | varchar(20) | nullable |
| `vat_valid` | tinyint | nullable — 1=valid, 0=invalid |
| `admin_notes` | text | nullable |
| `ip_address` | varchar(45) | nullable, hidden from API |

### `order_items`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_id` | bigint FK | |
| `product_id` | bigint | nullable FK |
| `sku` | varchar(50) | **nullable** |
| `brand` | varchar | |
| `name` | varchar | |
| `size` | varchar(50) | |
| `unit_price` | decimal | |
| `quantity` | int | |
| `line_total` | decimal | |

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
| `admin` | Content + operations (no user management) |
| `editor` | Content only (products, articles, categories, hero slides, brands, media, settings) |
| `order_manager` | Operations only (orders, quote requests, contacts, newsletter) |

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
| Email notifications | Order/contact/quote receipts are `Log::info` only. `ORDER_EMAIL` env var is a placeholder. Configure Resend/SMTP on Hostinger. |
| `GET /admin/products?trashed=only` | Restore works but no dedicated trashed product list endpoint |
| Stripe webhook registration | Must be done manually in Stripe Dashboard for production |
| Quote request `PUT` update | Route exists but only `updateStatus` (PATCH) was spec'd — confirm if full update is needed |

---

## Pending Hostinger Migrations

Every time new migrations are pushed, run on Hostinger via SSH:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
```

**Migrations not yet run on Hostinger (as of 2026-04-15):**
```
2026_04_14_074810_add_payment_intent_fields_to_orders_table
2026_04_14_084610_make_order_items_sku_nullable
2026_04_14_113708_add_shipment_fields_to_orders_table
```

**Required `.env` additions on Hostinger:**
```
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

---

## Environment

```
PHP:     8.3.30
Laravel: 11.x (Laragon local)
MySQL:   8.0
DB:      okelcor_cms
Host:    127.0.0.1:3306
User:    root (no password)
upload_max_filesize: 2G
post_max_size:       2G
Web server: Apache (Laragon) — not nginx
```
