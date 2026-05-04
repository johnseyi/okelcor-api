# Session Handoff — Okelcor API
Last updated: 2026-05-04

## Project
Laravel 13.2 / PHP 8.3 REST API for Okelcor B2B tyre wholesale.

- Local: `http://localhost:8000`
- Production API: `https://api.okelcor.com`
- Frontend production: `https://okelcor.com`
- DB: `okelcor_cms` on MySQL 8
- Auth: Laravel Sanctum token (Bearer) — admin routes and customer routes
- All responses: `application/json` via ForceJsonResponse middleware
- GitHub: `https://github.com/johnseyi/okelcor-api.git`
- Active deploy branch: `main`

Important:
- `okelcor.com` is the canonical frontend domain.
- `api.okelcor.com` is the canonical API domain.
- Old `.de` references are legacy — do not use.

---

## Namecheap Deploy Command

Run after every backend deployment:

```bash
cd /home/u978121777/domains/okelcor.com/public_html/okelcor-api
git fetch origin
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan config:clear
/opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

## Current Route Count: 125+

### Customer Auth routes (public — no token)
```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/resend-verification
GET    /api/v1/auth/verify-email/{id}/{hash}    ← signed URL, redirects to frontend
```

### Customer routes (auth.customer middleware — Bearer token)
```
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
PUT    /api/v1/auth/profile
PUT    /api/v1/auth/change-password

GET    /api/v1/auth/quotes       ← customer's own quote requests
GET    /api/v1/auth/invoices     ← customer's own invoices
GET    /api/v1/invoices/{id}/download  ← download invoice PDF (auth.customer Bearer token)

GET    /api/v1/auth/addresses
POST   /api/v1/auth/addresses
PUT    /api/v1/auth/addresses/{id}
DELETE /api/v1/auth/addresses/{id}
```

#### GET /api/v1/auth/quotes — response shape
```json
{
  "data": [
    {
      "id": 1,
      "ref": "QT-2024-001",
      "created_at": "2024-04-01T10:00:00+00:00",
      "status": "pending",
      "product_details": "Michelin — 205/55R16",
      "quantity": "200",
      "notes": "Urgent delivery needed"
    }
  ]
}
```
Status mapping (internal → customer-facing):
`new` → `pending` | `reviewed` → `reviewed` | `quoted` → `approved` | `closed` → `rejected`

#### GET /api/v1/auth/invoices — response shape
```json
{
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-2024-0042",
      "issued_at": "2024-04-01T00:00:00+00:00",
      "due_at": "2024-04-30T00:00:00+00:00",
      "amount": 4850.00,
      "status": "paid",
      "pdf_url": "https://api.okelcor.com/api/v1/invoices/1/download",
      "order_ref": "OKL-AB123"
    }
  ]
}
```
Status values: `paid` | `unpaid` | `overdue`

#### GET /api/v1/invoices/{id}/download
- Middleware: `auth.customer` — requires `Authorization: Bearer {customer_token}`
- Verifies `invoice.customer_id === authenticated customer.id` — 403 if mismatch
- Returns file as `Content-Disposition: attachment` with filename `INV-YYYY-NNNN.pdf`
- Error responses (all JSON):
  - 401 `"Unauthenticated."` — no/invalid token
  - 403 `"You do not have access to this invoice."` — wrong customer
  - 404 `"Invoice PDF is not available yet."` — `pdf_url` is null
  - 404 `"Invoice PDF file was not found."` — file missing on disk
- Controller: `InvoiceDownloadController@download`
- All 403/404 cases write `Log::warning` with `invoice_id`, `invoice_customer_id`, `auth_customer_id`, `pdf_url`

### Public routes (no auth)
```
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
POST   /api/v1/payments/create-session
POST   /api/v1/payments/webhook            ← Stripe webhook handler
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

### Product catalogue — requires auth.customer
```
GET    /api/v1/products                    ← requires customer Bearer token
GET    /api/v1/products/{id}               ← requires customer Bearer token
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
POST   /admin/login                         ← public, issues token

GET    /admin/dashboard                     ← all roles — metrics endpoint

POST   /admin/logout                        ← all roles
GET    /admin/me                            ← all roles
GET    /admin/profile                       ← all roles
PUT    /admin/profile                       ← all roles (first_name, last_name, display_name, name, email)
PUT    /admin/profile/password              ← all roles (change password)
PUT    /admin/change-password               ← all roles (alias for profile/password — same method)

# User management — super_admin, admin
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
DELETE /admin/orders/{id}               ← super_admin only

# Order CSV import/export
POST   /admin/orders/import
GET    /admin/orders/export

GET    /admin/quote-requests
GET    /admin/quote-requests/{id}
PUT    /admin/quote-requests/{id}
PATCH  /admin/quote-requests/{id}/status
POST   /admin/quote-requests/{id}/convert-to-order   ← converts quoted quote to order

GET    /admin/contact-messages
GET    /admin/contact-messages/{id}
PATCH  /admin/contact-messages/{id}/status

GET    /admin/newsletter
DELETE /admin/newsletter/{email}

# Supplier intelligence — super_admin, admin, order_manager
GET    /admin/supplier/search?q={query}&limit={1-50}
GET    /admin/supplier/alibaba-link?q={query}
```

#### Admin login response shape
```json
{
  "data": {
    "token": "...",
    "user": {
      "id": 1,
      "name": "John Doe",
      "first_name": "John",
      "last_name": "Doe",
      "display_name": "John",
      "email": "john@okelcor.com",
      "role": "super_admin",
      "role_label": "Super Admin",
      "last_login_at": "2026-05-02T10:00:00+00:00",
      "must_change_password": false
    }
  }
}
```
- `role` — raw DB string, always one of: `super_admin` | `admin` | `editor` | `order_manager`
- `role_label` — human-readable: `Super Admin` | `Admin` | `Editor` | `Order Manager`
- `must_change_password` — `true` on first login after account creation; cleared to `false` after `PUT /admin/change-password`
- **Key name is `user`, NOT `admin`** — frontend must read `data.user.role`

#### GET /admin/me and GET /admin/profile — same user shape as above, directly under `data`
```json
{ "data": { "id": 1, "role": "editor", "role_label": "Editor", ... } }
```
Frontend reads: `response.data.data.role` (axios) or `response.data.role` (fetch after `.json()`)

#### PUT /admin/change-password — response includes updated user
```json
{
  "data": { ...user object with must_change_password: false... },
  "message": "Password changed successfully."
}
```
Frontend must update its auth store from this response to clear the "change password" banner.

#### POST /admin/users (super_admin only)
- No password field in request — backend generates a 16-char secure temp password
- Sets `must_change_password = true`
- Sends `AdminWelcome` email to new user with temp password + login URL
- Login URL comes from `FRONTEND_URL` env var
- Plain text password is never stored or returned after the email is sent

#### GET /admin/dashboard — response shape
All roles. Excludes Stripe test sessions (`cs_test_%`). Currency fields in EUR, rounded to 2 decimals.
```json
{
  "data": {
    "revenue_today": 0.00,
    "orders_today_total": 0,
    "orders_today_paid": 0,
    "conversion_rate": 0.0,
    "average_order_value": 0.00,
    "aov_period_label": "last 30 days",
    "aov_paid_orders_count": 0,
    "aov_stripe_orders_count": 0,
    "aov_manual_orders_count": 0,
    "new_customers_today": 0,
    "pending_orders": 0,
    "confirmed_revenue_month": 0.00,
    "pending_revenue": 0.00,
    "revenue_last_7_days": [
      { "date": "2026-04-28", "revenue": 0.00 },
      { "date": "2026-04-29", "revenue": 0.00 },
      { "date": "2026-04-30", "revenue": 0.00 },
      { "date": "2026-05-01", "revenue": 0.00 },
      { "date": "2026-05-02", "revenue": 0.00 },
      { "date": "2026-05-03", "revenue": 0.00 },
      { "date": "2026-05-04", "revenue": 0.00 }
    ]
  }
}
```
**Metric rules:**
- `revenue_today` — `SUM(total)` where `DATE(created_at)=today AND payment_status='paid' AND status!='cancelled'`
- `orders_today_total` — all orders today (test sessions excluded)
- `orders_today_paid` — paid non-cancelled orders today
- `conversion_rate` — `orders_today_paid / orders_today_total * 100`
- `average_order_value` — `SUM/COUNT` of paid non-cancelled orders in last 30 days
- `aov_manual_orders_count` — covers both Wix-imported and organic manual orders (`mode='manual'`)
- `new_customers_today` — `email_verified_at IS NOT NULL AND imported_from_wix=0`
- `pending_orders` — `status IN ('pending','confirmed')`
- `confirmed_revenue_month` — paid, not cancelled/refunded, current month
- `pending_revenue` — `payment_status='pending'` and not cancelled/failed
- `revenue_last_7_days` — always 7 elements, zero-filled for days with no revenue
- Controller: `AdminDashboardController@stats`

#### POST /admin/quote-requests/{id}/convert-to-order
Roles: `super_admin`, `admin`, `order_manager`

Guards:
- 422 if quote `status !== 'quoted'`
- 409 if `quote.order_id` is already set (duplicate prevention)

Request body:
```json
{
  "delivery": {
    "address": "Musterstraße 1",
    "city": "Hamburg",
    "postal_code": "20095",
    "country": "Germany",
    "phone": "+49 170 1234567"
  },
  "items": [
    {
      "name": "Michelin Pilot Sport 4",
      "brand": "Michelin",
      "size": "205/55R16",
      "sku": null,
      "unit_price": 89.50,
      "quantity": 200
    }
  ],
  "delivery_cost": 0,
  "payment_method": "bank_transfer",
  "admin_notes": "Converted from quote OKL-QR-XXXXXX."
}
```
Response (201):
```json
{
  "data": {
    "order_ref": "OKL-XXXXXX",
    "quote_ref": "OKL-QR-XXXXXX",
    "status": "confirmed",
    "payment_status": "pending",
    "total": 17900.00
  },
  "message": "Quote converted to order successfully."
}
```
- Creates order with `mode='manual'`, `status='confirmed'`, `payment_status='pending'`
- `payment_method` defaults to `bank_transfer` if not provided
- Sets `quote_requests.order_id` = new order ID to prevent re-conversion
- Writes `OrderLog` entry with `action='status_changed'`, `new_value='confirmed'`, notes referencing quote ref
- Invoice is NOT auto-created — admin manually sets `payment_status=paid` later, which should trigger invoice creation if needed
- FormRequest: `ConvertQuoteToOrderRequest`

#### GET /admin/quote-requests/{id} — detail response fields
```json
{
  "data": {
    "id": 1,
    "ref_number": "OKL-QR-877755-MWM",
    "full_name": "...",
    "email": "...",
    "phone": "...",
    "tyre_category": "PCR",
    "country": "Germany",
    "quantity": "200",
    "delivery_location": "Hamburg",
    "notes": "...",
    "status": "quoted",
    "admin_notes": "...",
    "created_at": "...",
    "updated_at": "...",
    "order_id": 42,
    "order_ref": "OKL-XXXXXX",
    "has_attachment": true,
    "attachment_url": "https://api.okelcor.com/storage/quote-attachments/uuid.pdf",
    "attachment_name": "Invoice-KDQWK0JJ-0001.pdf",
    "attachment_original_name": "Invoice-KDQWK0JJ-0001.pdf",
    "attachment_size": 116643,
    "attachment_mime": "application/pdf"
  }
}
```
- `order_id` / `order_ref` — null until converted; non-null means already converted
- `attachment_name` is an alias of `attachment_original_name` (both present for frontend compatibility)
- List response also includes `order_id` and `has_attachment`

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
POST https://api.okelcor.com/api/v1/admin/products/import
POST https://api.okelcor.com/api/v1/admin/orders/import
```
NOT through the Vercel proxy.

---

## Schema — Full Table Reference

### `customers`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `customer_type` | enum | `b2c`, `b2b` — default `b2c` |
| `first_name` | varchar | |
| `last_name` | varchar | |
| `email` | varchar(255) | unique |
| `password` | varchar | hashed, hidden from API |
| `phone` | varchar(50) | nullable |
| `country` | varchar(100) | nullable |
| `company_name` | varchar(200) | nullable; required if b2b on register |
| `vat_number` | varchar(20) | nullable |
| `vat_verified` | tinyint | auto-set via VIES on register/profile update |
| `industry` | varchar(100) | nullable |
| `email_verified_at` | timestamp | nullable — must be set before login allowed |
| `must_reset_password` | tinyint | default 0 — blocks login until reset |
| `is_active` | tinyint | default 1 |
| `imported_from_wix` | tinyint | default 0 |

### `customer_addresses`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `customer_id` | bigint FK | cascade delete |
| `full_name` | varchar | |
| `address_line_1` | varchar | |
| `address_line_2` | varchar | nullable |
| `city` | varchar | |
| `postcode` | varchar(20) | |
| `country` | varchar | |
| `phone` | varchar(50) | nullable |
| `is_default` | tinyint | default 0 — only one default per customer |

### `invoices`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `customer_id` | bigint FK | cascade delete |
| `invoice_number` | varchar(50) | unique — format `INV-YYYY-NNNN` |
| `issued_at` | timestamp | |
| `due_at` | timestamp | nullable |
| `amount` | decimal(10,2) | |
| `status` | enum | `paid`, `unpaid`, `overdue` — default `unpaid` |
| `pdf_url` | varchar(500) | nullable — relative path e.g. `invoices/INV-2026-0001.pdf`; returned as absolute URL |
| `order_ref` | varchar(30) | nullable — unique per invoice |
| `created_at` / `updated_at` | timestamp | |

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

### `quote_requests`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `customer_id` | bigint FK | nullable, nullifies on customer delete — links to `customers` table |
| `order_id` | bigint FK | nullable, nullifies on order delete — set when quote is converted to order |
| `ref_number` | varchar(30) | unique |
| `full_name` | varchar(200) | |
| `company_name` | varchar(200) | nullable |
| `email` | varchar(255) | indexed |
| `phone` | varchar(50) | nullable |
| `country` | varchar(100) | |
| `tyre_category` | varchar(100) | |
| `brand_preference` | varchar(200) | nullable |
| `tyre_size` | varchar(100) | nullable |
| `quantity` | varchar(100) | free text — not an integer |
| `notes` | text | |
| `status` | enum | `new`, `reviewed`, `quoted`, `closed` — internal values |
| `admin_notes` | text | nullable |
| `ip_address` | varchar(45) | nullable, hidden from API |
| `vat_number` | varchar(30) | nullable |
| `vat_valid` | tinyint | nullable |
| `delivery_address` | varchar(300) | nullable — structured delivery street address |
| `delivery_city` | varchar(100) | nullable — structured delivery city |
| `delivery_postal_code` | varchar(30) | nullable — structured delivery postal code |
| `attachment_path` | varchar(500) | nullable — relative path e.g. `quote-attachments/uuid.pdf` |
| `attachment_original_name` | varchar(255) | nullable — original filename from customer |
| `attachment_mime` | varchar(100) | nullable — MIME type of uploaded file |
| `attachment_size` | unsigned int | nullable — file size in bytes |

**Quote attachment upload:**
- Field: `attachment` (multipart/form-data), optional
- Accepted types: `pdf`, `csv`, `xls`, `xlsx` — max 10 MB
- Stored to `storage/app/public/quote-attachments/{uuid}.ext`
- Admin list + detail responses include: `attachment_url` (absolute), `attachment_name`, `attachment_original_name`, `attachment_mime`, `attachment_size`, `has_attachment` — all null/false when no file attached

**Quote status enum (corrected):**
`new` → `reviewed` → `quoted` → `closed`
Only quotes with `status='quoted'` can be converted to an order.

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
| `payment_session_id` | varchar(100) | nullable — Stripe Checkout Session ID |
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

**Order mode values:**
- `live` — Stripe checkout orders
- `manual` — Wix imported orders, organic manual orders, and quote-converted orders

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

### `order_logs`
Append-only audit trail — no `updated_at`, never mutated after insert.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_id` | bigint FK | nullable → nullOnDelete — log survives order deletion |
| `order_ref` | varchar(30) | denormalized — readable even after order hard-deleted |
| `admin_user_id` | bigint FK | nullable → nullOnDelete — log survives user deletion |
| `admin_user_email` | varchar(255) | nullable — denormalized |
| `action` | enum | `status_changed`, `cancelled`, `deleted`, `tracking_updated`, `payment_status_changed` |
| `old_value` | varchar(100) | nullable — previous status/value |
| `new_value` | varchar(100) | nullable — new status/value |
| `notes` | text | nullable — optional context |
| `ip_address` | varchar(45) | nullable |
| `created_at` | timestamp | auto-set, no `updated_at` |

Indexes: `(order_id, created_at)`, `order_ref`.

### `admin_users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `name` | varchar(200) | full name (legacy field — kept for compatibility) |
| `first_name` | varchar | nullable |
| `last_name` | varchar | nullable |
| `display_name` | varchar | nullable — shown in admin UI |
| `email` | varchar(255) | unique |
| `password` | varchar | hashed, hidden from API |
| `role` | enum | `super_admin`, `admin`, `editor`, `order_manager` — default `editor` |
| `last_login_at` | timestamp | nullable — updated on every successful login |
| `last_login_ip` | varchar(45) | nullable — updated on every successful login |
| `must_change_password` | tinyint | default 0 — set to 1 when super_admin creates account; cleared on first password change |
| `is_active` | tinyint | default 1 — inactive accounts cannot log in |
| `created_at` / `updated_at` | timestamp | |

### Translation tables (`article_translations`, `category_translations`, `hero_slide_translations`)
Locales ENUM: `en`, `de`, `fr`, `es`

---

## Features & Integrations

### Customer Authentication (Laravel Sanctum)
- **Separate from admin auth** — uses `auth.customer` middleware (`CustomerAuth.php`)
- Token resolved via `PersonalAccessToken::findToken()` scoped to `Customer` model
- Middleware alias: `auth.customer` registered in `bootstrap/app.php`

**Register flow:**
- Validates all fields; `company_name` required if `customer_type=b2b`
- If `vat_number` provided → validated against VIES API, sets `vat_verified`
- Sends verification email via `CustomerEmailVerification` mailable
- Customer cannot log in until `email_verified_at` is set

**Login flow:**
- Checks `is_active`, `email_verified_at`, `must_reset_password` before issuing token
- Returns `{ token, customer: { id, first_name, last_name, email, customer_type, company_name, vat_verified, country } }`

**Email verification:**
- Signed URL: `GET /api/v1/auth/verify-email/{id}/{hash}` (24hr expiry)
- On success: redirects to `{FRONTEND_URL}/login?verified=true`
- Route name: `verification.verify`

**Password reset:**
- Token stored in `password_reset_tokens` table (60 min expiry), hashed with `Hash::make`
- Reset link: `{FRONTEND_URL}/reset-password?token={token}&email={email}`

**Customer address management:**
- All 4 CRUD endpoints under `/api/v1/auth/addresses`
- Setting `is_default: true` on create/update automatically clears all other defaults
- Address queries scoped to authenticated customer — no cross-customer access possible

**Mailables:**
- `CustomerEmailVerification` → view: `emails.customer-verify-email`
- `CustomerPasswordReset` → view: `emails.customer-reset-password`

### Admin Authentication (Laravel Sanctum)
- Guard: `auth:sanctum` on all admin routes
- Model: `AdminUser` (separate from customer `User` model)
- Login: `POST /api/v1/admin/login` — public, no token required

**Login security:**
- Rate limited: 5 failed attempts per IP per minute via `RateLimiter` facade
- Failed attempts logged to `laravel.log`: email + IP (password never logged)
- Successful login logged: `Admin login: {email} from IP {ip}`
- Checks `is_active` — inactive accounts get 403 before token is issued
- Revokes all existing tokens before issuing new one

**Account creation (super_admin only):**
- No password field required — backend generates 16-char secure temp password via `Str::password(16)`
- Sets `must_change_password = true` on new account
- Sends `AdminWelcome` email with temp password and login URL
- Login URL uses `FRONTEND_URL` env var (not hardcoded) — update env when domain changes
- Plain text password is discarded after email is sent; never stored or returned

**Password change:**
- `PUT /admin/change-password` or `PUT /admin/profile/password` (same method, two paths)
- Validates current password, sets new password, sets `must_change_password = false`
- Revokes all other active sessions
- Returns updated user object — frontend should update auth store from this response to clear any "change password" banner

**Mailables:**
- `AdminWelcome` → view: `emails.admin-welcome`

### Stripe Checkout Payment Gateway
- Active gateway: Stripe Checkout.
- Package: `stripe/stripe-php`.
- Config: `config/services.php` → `stripe.secret`, `stripe.webhook_secret`, `stripe.currency`.
- Env vars: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY`.
- `StripeService::createCheckoutSession(array $orderData): array`.

**Flow:**
1. Frontend sends cart to `POST /api/v1/payments/create-session`.
2. `payment_method` field is **optional** in the request — defaults to `stripe` server-side. If provided, only `"stripe"` is accepted.
3. Backend validates, looks up DB prices, saves a `pending` order with `mode=live` and `payment_method=stripe`.
4. Backend calls Stripe Checkout Session API and stores the Checkout Session ID in `orders.payment_session_id`.
5. Returns `{ "data": { "provider": "stripe", "order_ref": "...", "checkout_session_id": "...", "checkout_url": "https://checkout.stripe.com/..." } }`.
6. Frontend redirects customer to `checkout_url`.
7. Stripe redirects customer to: `{FRONTEND_URL}/checkout/return?session_id={cs_xxx}&order_ref={OKL-xxx}`
8. Stripe sends webhook to `POST /api/v1/payments/webhook`.
9. Backend verifies `Stripe-Signature` header using `STRIPE_WEBHOOK_SECRET`.
10. Handled events:
    - `checkout.session.completed` → `payment_status=paid`, `status=confirmed`, creates invoice record + generates PDF, sends `OrderConfirmation` (with invoice number) to customer + `OrderReceived` to `ORDER_EMAIL`
    - `payment_intent.payment_failed` → `payment_status=failed`, `status=cancelled`
    - `charge.refunded` → `payment_status=refunded`

**Invoice auto-creation (Stripe path):**
- Triggered inside `markOrderPaid()` after the order is confirmed
- Only created if a `Customer` account exists for `order.customer_email` — guest checkouts produce no invoice
- Invoice number: `INV-YYYY-NNNN` — sequence generated inside `DB::transaction` with `lockForUpdate()` on all same-year rows to prevent race conditions on concurrent webhook retries
- PDF generated immediately via `barryvdh/laravel-dompdf` (v3.1.2) and stored to `storage/app/public/invoices/{invoice_number}.pdf`
- `invoices.pdf_url` stores the **relative** path; `GET /api/v1/auth/invoices` returns an **absolute URL** via `url(Storage::url($path))`
- Idempotency: if invoice record already exists with `pdf_url` set → return early; if `pdf_url` is null → skip record creation, re-run PDF generation only
- PDF failure is non-blocking — logged as warning; invoice record is still returned and email still sent
- Recovery: run `php artisan invoices:generate-missing-pdfs` to backfill any invoices where PDF generation failed

**Order status flow (Stripe path):**
```
pending → confirmed (Stripe webhook) → processing (admin sets manually) → shipped → delivered
```

**Webhook idempotency:**
- If `payment_status` is already `paid` when webhook fires, the handler skips DB update and email sending (Stripe retry protection).

**Debug log:**
- `StripeService` logs `Stripe checkout success_url` with `success_url` and `order_ref` immediately before calling the Stripe API. Grep: `tail -n 50 storage/logs/laravel.log | grep "Stripe checkout success_url"`

**Stripe test order identification:**
- Test sessions have `payment_session_id LIKE 'cs_test_%'`
- Dashboard metrics exclude them via `(payment_session_id IS NULL OR payment_session_id NOT LIKE 'cs_test_%')`
- To delete test orders manually: use `orders:delete-specific` or `orders:cleanup-test-stripe` Artisan commands

**Frontend return pages required:**
- `/checkout/return?session_id=...&order_ref=OKL-...` — success page, show order ref, tell customer to check email
- `/checkout/cancel` — payment cancelled page
- Note: webhook may fire slightly after the customer lands on `/checkout/return`. Do not rely on `payment_status=paid` being set immediately on page load.

**Legacy gateways:**
- Adyen code/package/config remain present but inactive until business account/API credentials are approved.
- Mollie code/config remain present, but `POST /api/v1/orders/mollie-webhook` returns HTTP 410 with `{ "message": "Mollie payments are currently disabled." }`.
- Do not use Adyen or Mollie unless explicitly re-enabled later.

### Order Confirmation Emails
- `OrderConfirmation` mailable → sent to customer **after Stripe webhook confirms payment** (`checkout.session.completed`)
- `OrderReceived` mailable → sent to `ORDER_EMAIL` env var **after Stripe webhook confirms payment**
- Manual order flow (`POST /api/v1/orders`) sends `OrderReceived` to admin only — no customer email on manual orders
- Quote-converted orders (`POST /admin/quote-requests/{id}/convert-to-order`) do NOT auto-send emails — admin contacts customer separately
- Both views: `resources/views/emails/order-confirmation.blade.php` and `order-received.blade.php`
- Templates are plain transactional HTML — white background, minimal color (3px orange top border only), no image assets
- Contact email in templates: `support@okelcor.com`
- Shipment fields (carrier, tracking_number, container_number, ETA) shown conditionally when set
- Tracking URL: `{FRONTEND_URL}/account/orders/{ref}`
- Env var required: `ORDER_EMAIL=support@okelcor.com`
- `OrderConfirmation` accepts an optional `?Invoice $invoice` — when set, adds an invoice block to the email showing invoice number and a link to `/account/invoices` on the frontend. Block is omitted if invoice is null (guest checkout).

### Order Security & Audit Logging

**Order deletion (super_admin only):**
- Route middleware: `admin.role:super_admin` — admin role cannot delete
- Request body must include `confirm_ref` matching `order.ref` exactly — 422 if mismatch
- Orders with `payment_status=paid` cannot be deleted — returns 409 Conflict
- `deleted` log entry is written **before** `$order->items()->delete()` and `$order->delete()` to capture data while record exists

**Cancel transition guard:**
- `PUT /admin/orders/{id}` and `PATCH /admin/orders/{id}/status` reject cancellation if current status is already `cancelled` or `delivered` — returns 409 Conflict

**Audit log (order_logs):**
- Written by `AdminOrderController::writeLog()` — wrapped in try/catch so log failure never blocks primary action
- Also written by `AdminQuoteRequestController::writeConversionLog()` when a quote is converted to order
- `GET /admin/orders/{id}` response includes a `logs` array:
```json
{
  "logs": [
    {
      "id": 1,
      "action": "status_changed",
      "old_value": "pending",
      "new_value": "confirmed",
      "notes": null,
      "admin_user_id": 3,
      "admin_user_email": "admin@okelcor.com",
      "ip_address": "1.2.3.4",
      "created_at": "2026-05-02T12:00:00+00:00"
    }
  ]
}
```
- Actions logged: `status_changed` (any status transition including quote conversion), `cancelled` (status set to cancelled), `tracking_updated` (any of carrier/tracking_number/container_number/estimated_delivery/eta), `deleted` (hard delete before records removed)

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
- `EbayService`: client credentials OAuth token cached for ~2 hrs
- Query is auto-simplified before sending to eBay — extracts `BRAND SIZE` (e.g. `"YOKOHAMA 225/45R18"`) from full product name
- No category filter applied — removed to avoid EBAY_DE category ID mismatch
- Env vars: `EBAY_CLIENT_ID`, `EBAY_CLIENT_SECRET`, `EBAY_ENVIRONMENT=production`
- eBay errors now throw (visible 502 with message) instead of silently returning empty

### VAT Validation (EU VIES REST)
- No SOAP, no third-party package — direct HTTP via Laravel `Http` facade
- Endpoint: `POST /api/v1/vat/validate` body: `{ "vat_number": "DE123456789" }`
- Calls `https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{CC}/vat/{number}`
- Returns: `{ valid, name, address, country_code, vat_number, message }`
- Also runs automatically on `POST /orders`, `POST /quote-requests`, and customer register/profile update when `vat_number` is provided

### Multilingual Content
- Locales: `en`, `de`, `fr`, `es`
- Pass `?locale=en|de|fr|es` on public content endpoints
- Articles: EN fallback if requested locale has no translation
- Hero slides + categories: locale-aware via `?locale=` param

### Role-Based Access Control
Middleware: `admin.role:{roles}` (comma-separated) — enforced at route level.

| Role | `role` string | `role_label` | Access |
|------|--------------|-------------|--------|
| Super Admin | `super_admin` | Super Admin | Full access — everything |
| Admin | `admin` | Admin | Full access — everything including user management |
| Editor | `editor` | Editor | Content only (products, articles, categories, hero slides, brands, media, settings) |
| Order Manager | `order_manager` | Order Manager | Operations only (orders, quote requests, contacts, newsletter, supplier search) |

**Frontend nav filtering** — use `user.role` from auth store:
```js
const ROLE_ACCESS = {
  super_admin:   ['dashboard','products','orders','quotes','articles','hero_slides','brands','categories','media','settings','users','supplier'],
  admin:         ['dashboard','products','orders','quotes','articles','hero_slides','brands','categories','media','settings','users','supplier'],
  editor:        ['dashboard','articles','hero_slides'],
  order_manager: ['dashboard','orders','quotes','supplier'],
}
```

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
- `https://okelcor.com`
- `https://www.okelcor.com`

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
- Rate limited (429): `{ "message": "Too many failed login attempts. Try again in N seconds." }`
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
| `invoices.pdf_url` | relative: `invoices/INV-YYYY-NNNN.pdf` | served via `/api/v1/invoices/{id}/download` (auth.customer) |
| `quote_requests.attachment_path` | relative: `quote-attachments/uuid.ext` | absolute URL — admin only |

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
| `Order` | No (hard delete) | `DELETE /admin/orders/{id}` — super_admin only; requires `confirm_ref` body param matching order.ref; blocked if `payment_status=paid` |

---

## Rate Limiting

| Limiter key | Limit | Applied to |
|-------------|-------|-----------|
| `admin-login:{ip}` | 5 failed attempts/min | `POST /admin/login` — via RateLimiter in controller |
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
| `import:wix-customers {file}` | Import customers from Wix contacts CSV |
| `import:wix-customers {file} --no-email` | Import customers without sending welcome emails |
| `orders:cleanup-test-stripe --date=YYYY-MM-DD --email=EMAIL --dry-run` | Delete test Stripe orders by date + email (always dry-run first) |
| `orders:delete-specific [--dry-run]` | Delete 9 specific hard-coded test order refs — dry-run first, then run without flag |
| `invoices:generate-missing-pdfs [--dry-run] [--invoice=INV-YYYY-NNNN]` | Generate PDF files for invoices where `pdf_url IS NULL`; dry-run lists affected rows without writing |

---

## Pending / Not Yet Built

| Item | Notes |
|------|-------|
| eBay production credentials | Live keys set in Namecheap .env — `EBAY_ENVIRONMENT=production` |
| Adyen approval | Legacy/inactive until business account/API credentials are approved |
| `GET /admin/products?trashed=only` | Restore works but no dedicated trashed product list endpoint |
| Admin customer edit/deactivate | GET /admin/customers list exists; no PUT/DELETE per customer yet |
| Quote-converted order invoice | Invoice not auto-created on conversion — admin must manually set `payment_status=paid` to trigger invoice later |

---

## Environment

```
PHP:     8.3.30
Laravel: 13.2.0
MySQL:   8.0
DB:      okelcor_cms
Host:    127.0.0.1:3306 (local) / Namecheap DB credentials (production)
Web server: Apache
```

## Required `.env` on Namecheap
```env
# Stripe Checkout (active)
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=eur

# Adyen (legacy/inactive)
ADYEN_API_KEY=
ADYEN_MERCHANT_ACCOUNT=
ADYEN_ENVIRONMENT=test
ADYEN_CLIENT_KEY=

# Mollie (legacy/inactive; public webhook returns HTTP 410)
MOLLIE_WEBHOOK_SECRET=

# Order notifications — admin receives email here after every confirmed Stripe payment
ORDER_EMAIL=support@okelcor.com

# ShipsGo container tracking
SHIPSGO_API_KEY=

# DHL tracking
DHL_API_KEY=

# eBay supplier search
EBAY_CLIENT_ID=
EBAY_CLIENT_SECRET=
EBAY_ENVIRONMENT=production

# Frontend URL — used in ALL email links and redirects (verify email, password reset, admin welcome, order tracking)
FRONTEND_URL=https://okelcor.com
```
