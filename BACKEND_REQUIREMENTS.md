# Okelcor — Laravel CMS API: Backend Requirements

**Document version:** 1.0 — 2026-03-29
**Prepared for:** Laravel backend developer
**Frontend project:** Next.js 16 / React 19 (App Router)
**Hosting target:** Laravel on Hostinger Business Hosting (PHP 8.2+, MySQL 8.0)
**API base URL:** `https://api.okelcor.de` (subdomain on same Hostinger account)

---

## 1. Overview

The Next.js frontend currently renders all content from static TypeScript data files.
The goal is to replace those static files with a **live Laravel JSON API**, turning the site
into a fully dynamic CMS-driven platform.

### Responsibility split

| Layer | Responsibility |
|---|---|
| **Laravel (this spec)** | CRUD for all content, media storage, MySQL, JSON responses, admin auth tokens |
| **Next.js (frontend)** | UI rendering, routing, customer-facing NextAuth sessions, fetch calls, form submissions |

The frontend is a **consumer only** — it never writes to the database directly. All mutations
go through API requests to Laravel.

---

## 2. What the CMS Manages

| Resource | Description |
|---|---|
| **Products** | Tyre catalogue — PCR, TBR, OTR, Used; brand, size, spec, price, SKU, images |
| **Articles** | News & insights blog — multilingual (EN / DE / FR) |
| **Categories** | The 4 main tyre categories shown in the homepage carousel |
| **Hero Slides** | Homepage hero slider — title, subtitle, background image per slide |
| **Brands** | Tyre brand logos and names shown in the Trusted Brands section |
| **Quote Requests** | Inbound B2B quote leads from the /quote form |
| **Contact Messages** | Inbound messages from the /contact form |
| **Orders** | Checkout orders submitted from /checkout |
| **Newsletter Subscribers** | Email addresses collected from the newsletter strip |
| **Media / Files** | All uploaded images served to the frontend |
| **Site Settings** | Key-value store for global config (company info, feature flags, etc.) |
| **Admin Users** | CMS administrator accounts (separate from customer accounts) |

---

## 3. Database Schema

### 3.1 `products`

```sql
CREATE TABLE products (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku             VARCHAR(50)    NOT NULL UNIQUE,
  brand           VARCHAR(100)   NOT NULL,
  name            VARCHAR(200)   NOT NULL,
  size            VARCHAR(50)    NOT NULL,           -- e.g. "205/55R16"
  spec            VARCHAR(50)    NOT NULL DEFAULT '', -- e.g. "91H", "152/148M"
  season          ENUM('Summer','Winter','All Season','All-Terrain') NOT NULL,
  type            ENUM('PCR','TBR','Used','OTR')     NOT NULL,
  price           DECIMAL(10,2)  NOT NULL,
  description     TEXT           NOT NULL,
  primary_image   VARCHAR(500)   NULL,               -- relative path or URL
  is_active       TINYINT(1)     NOT NULL DEFAULT 1,
  sort_order      INT            NOT NULL DEFAULT 0,
  created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_type   (type),
  INDEX idx_brand  (brand),
  INDEX idx_season (season),
  INDEX idx_active (is_active)
);
```

### 3.2 `product_images` (gallery — one-to-many)

```sql
CREATE TABLE product_images (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  BIGINT UNSIGNED NOT NULL,
  path        VARCHAR(500)   NOT NULL,
  sort_order  INT            NOT NULL DEFAULT 0,
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_product (product_id)
);
```

### 3.3 `articles`

```sql
CREATE TABLE articles (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(300)   NOT NULL UNIQUE,
  image        VARCHAR(500)   NULL,
  published_at DATE           NULL,
  is_published TINYINT(1)     NOT NULL DEFAULT 0,
  sort_order   INT            NOT NULL DEFAULT 0,
  created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_slug      (slug),
  INDEX idx_published (is_published, published_at)
);
```

### 3.4 `article_translations` (multilingual content per article)

```sql
CREATE TABLE article_translations (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id  BIGINT UNSIGNED NOT NULL,
  locale      ENUM('en','de','fr') NOT NULL,
  category    VARCHAR(100)   NOT NULL,              -- e.g. "Logistics", "Industry"
  title       VARCHAR(500)   NOT NULL,
  read_time   VARCHAR(30)    NOT NULL DEFAULT '',   -- e.g. "5 min read"
  summary     TEXT           NOT NULL,
  body        LONGTEXT       NOT NULL,              -- JSON-encoded array of paragraph strings
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_article_locale (article_id, locale),
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);
```

> `body` stores a JSON array of paragraph strings matching the frontend `body: string[]` type.
> Example: `["Paragraph one.", "Paragraph two."]`

### 3.5 `categories`

```sql
CREATE TABLE categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(100)   NOT NULL UNIQUE, -- "pcr", "tbr", "used", "otr"
  image       VARCHAR(500)   NULL,
  sort_order  INT            NOT NULL DEFAULT 0,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE category_translations (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  locale      ENUM('en','de','fr') NOT NULL,
  title       VARCHAR(200)   NOT NULL,
  label       VARCHAR(100)   NOT NULL,
  subtitle    VARCHAR(500)   NOT NULL,
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_cat_locale (category_id, locale),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);
```

### 3.6 `hero_slides`

```sql
CREATE TABLE hero_slides (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image       VARCHAR(500)   NULL,
  sort_order  INT            NOT NULL DEFAULT 0,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE hero_slide_translations (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slide_id      BIGINT UNSIGNED NOT NULL,
  locale        ENUM('en','de','fr') NOT NULL,
  title         VARCHAR(300)   NOT NULL,
  subtitle      VARCHAR(500)   NOT NULL,
  cta_primary   VARCHAR(100)   NOT NULL DEFAULT 'Shop Catalogue',
  cta_secondary VARCHAR(100)   NOT NULL DEFAULT 'Get a Quote',
  created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_slide_locale (slide_id, locale),
  FOREIGN KEY (slide_id) REFERENCES hero_slides(id) ON DELETE CASCADE
);
```

### 3.7 `brands`

```sql
CREATE TABLE brands (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)   NOT NULL,
  logo        VARCHAR(500)   NULL,     -- path to uploaded SVG or PNG
  sort_order  INT            NOT NULL DEFAULT 0,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3.8 `quote_requests`

```sql
CREATE TABLE quote_requests (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_number        VARCHAR(30)    NOT NULL UNIQUE,
  full_name         VARCHAR(200)   NOT NULL,
  company_name      VARCHAR(200)   NULL,
  email             VARCHAR(255)   NOT NULL,
  phone             VARCHAR(50)    NULL,
  country           VARCHAR(100)   NOT NULL,
  business_type     VARCHAR(100)   NULL,
  tyre_category     VARCHAR(100)   NOT NULL,
  brand_preference  VARCHAR(200)   NULL,
  tyre_size         VARCHAR(100)   NULL,
  quantity          VARCHAR(100)   NOT NULL,
  budget_range      VARCHAR(100)   NULL,
  delivery_location VARCHAR(300)   NOT NULL,
  delivery_timeline VARCHAR(100)   NULL,
  notes             TEXT           NOT NULL,
  status            ENUM('new','reviewing','quoted','closed') NOT NULL DEFAULT 'new',
  admin_notes       TEXT           NULL,
  ip_address        VARCHAR(45)    NULL,
  created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_email  (email)
);
```

### 3.9 `contact_messages`

```sql
CREATE TABLE contact_messages (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200)   NOT NULL,
  email       VARCHAR(255)   NOT NULL,
  subject     VARCHAR(200)   NOT NULL,
  inquiry     TEXT           NOT NULL,
  status      ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  admin_notes TEXT           NULL,
  ip_address  VARCHAR(45)    NULL,
  created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_email  (email)
);
```

### 3.10 `orders`

```sql
CREATE TABLE orders (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref            VARCHAR(30)    NOT NULL UNIQUE,
  customer_name  VARCHAR(200)   NOT NULL,
  customer_email VARCHAR(255)   NOT NULL,
  customer_phone VARCHAR(50)    NULL,
  address        VARCHAR(300)   NOT NULL,
  city           VARCHAR(100)   NOT NULL,
  postal_code    VARCHAR(20)    NOT NULL,
  country        VARCHAR(100)   NOT NULL,
  payment_method VARCHAR(50)    NOT NULL,
  subtotal       DECIMAL(10,2)  NOT NULL,
  delivery_cost  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  total          DECIMAL(10,2)  NOT NULL,
  status         ENUM('pending','confirmed','processing','shipped','delivered','cancelled')
                                NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  mode           ENUM('live','manual')            NOT NULL DEFAULT 'manual',
  admin_notes    TEXT           NULL,
  ip_address     VARCHAR(45)    NULL,
  created_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_ref    (ref),
  INDEX idx_status (status),
  INDEX idx_email  (customer_email)
);
```

### 3.11 `order_items`

```sql
CREATE TABLE order_items (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    BIGINT UNSIGNED NOT NULL,
  product_id  BIGINT UNSIGNED NULL,       -- NULL if product was later deleted
  sku         VARCHAR(50)    NOT NULL,
  brand       VARCHAR(100)   NOT NULL,
  name        VARCHAR(200)   NOT NULL,
  size        VARCHAR(50)    NOT NULL,
  unit_price  DECIMAL(10,2)  NOT NULL,
  quantity    INT            NOT NULL,
  line_total  DECIMAL(10,2)  NOT NULL,

  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_order (order_id)
);
```

### 3.12 `newsletter_subscribers`

```sql
CREATE TABLE newsletter_subscribers (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(255)   NOT NULL UNIQUE,
  locale          ENUM('en','de','fr') NOT NULL DEFAULT 'en',
  is_confirmed    TINYINT(1)     NOT NULL DEFAULT 0,
  token           VARCHAR(100)   NULL,    -- double opt-in confirmation token
  subscribed_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at TIMESTAMP      NULL,

  INDEX idx_email (email)
);
```

### 3.13 `media`

```sql
CREATE TABLE media (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename      VARCHAR(300)   NOT NULL,
  original_name VARCHAR(300)   NOT NULL,
  path          VARCHAR(500)   NOT NULL,   -- storage path relative to disk root
  url           VARCHAR(500)   NOT NULL,   -- full public URL
  mime_type     VARCHAR(100)   NOT NULL,
  size_bytes    BIGINT         NOT NULL,
  width         INT            NULL,
  height        INT            NULL,
  alt_text      VARCHAR(300)   NULL,
  collection    VARCHAR(100)   NULL DEFAULT 'general',
  uploaded_by   BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_collection (collection)
);
```

### 3.14 `admin_users`

```sql
CREATE TABLE admin_users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200)   NOT NULL,
  email         VARCHAR(255)   NOT NULL UNIQUE,
  password      VARCHAR(255)   NOT NULL,    -- bcrypt hashed
  role          ENUM('super_admin','editor') NOT NULL DEFAULT 'editor',
  last_login_at TIMESTAMP      NULL,
  created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3.15 `site_settings`

```sql
CREATE TABLE site_settings (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`      VARCHAR(100)   NOT NULL UNIQUE,
  value      TEXT           NULL,
  type       ENUM('string','boolean','json') NOT NULL DEFAULT 'string',
  `group`    VARCHAR(50)    NOT NULL DEFAULT 'general',
  updated_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

Seed with keys: `company_name`, `company_email`, `company_phone`, `rex_reg_number`,
`maintenance_mode`, `payment_stripe_enabled`, `payment_paypal_enabled`.

---

## 4. API Endpoints

All endpoints are prefixed with `/api/v1`.

**Response envelope:**
- Collections: `{ "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }`
- Single resource: `{ "data": {...} }`
- Actions / confirmations: `{ "message": "..." }`
- Errors: `{ "message": "...", "errors": { "field": ["..."] } }`

**Auth header for protected routes:**
```
Authorization: Bearer {sanctum_token}
```

---

### 4.1 Products

#### `GET /api/v1/products` — Public

| Query param | Type | Description |
|---|---|---|
| `type` | string | PCR, TBR, Used, OTR |
| `brand` | string | Brand name |
| `season` | string | Summer, Winter, All Season |
| `search` | string | Full-text across brand, name, size, sku |
| `sort` | string | `price_asc`, `price_desc`, `newest` (default) |
| `per_page` | int | Default 24, max 100 |
| `page` | int | Page number |

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "sku": "OKL-0001",
      "brand": "Michelin",
      "name": "Energy Saver+",
      "size": "205/55R16",
      "spec": "91H",
      "season": "Summer",
      "type": "PCR",
      "price": 89.99,
      "description": "...",
      "primary_image": "https://api.okelcor.de/storage/products/michelin-energy-saver.jpg",
      "images": ["https://api.okelcor.de/storage/products/..."],
      "is_active": true
    }
  ],
  "meta": { "current_page": 1, "per_page": 24, "total": 156, "last_page": 7 },
  "filters": {
    "brands": ["Michelin", "Bridgestone", "Continental", "Pirelli", "Goodyear", "Dunlop"],
    "types": ["PCR", "TBR", "Used", "OTR"],
    "seasons": ["Summer", "Winter", "All Season"]
  }
}
```

> The `filters` block returns the distinct available values for the current filtered result set.
> The frontend uses this to populate the filter sidebar checkboxes dynamically.

---

#### `GET /api/v1/products/{id}` — Public

Returns a single product with full gallery and up to 4 related products of the same type.

**Response:**
```json
{
  "data": {
    "id": 1,
    "sku": "OKL-0001",
    "brand": "Michelin",
    "name": "Energy Saver+",
    "size": "205/55R16",
    "spec": "91H",
    "season": "Summer",
    "type": "PCR",
    "price": 89.99,
    "description": "...",
    "primary_image": "https://api.okelcor.de/storage/...",
    "images": ["...", "..."],
    "is_active": true,
    "related": [
      {
        "id": 2,
        "brand": "Bridgestone",
        "name": "Turanza T005",
        "size": "225/45R17",
        "price": 124.50,
        "primary_image": "..."
      }
    ]
  }
}
```

---

#### `POST /api/v1/admin/products` — Admin

Creates a product. Accepts `multipart/form-data` for simultaneous image upload.

**Request body:**
```json
{
  "sku": "OKL-0020",
  "brand": "Michelin",
  "name": "Pilot Sport 4",
  "size": "245/40R18",
  "spec": "97Y XL",
  "season": "Summer",
  "type": "PCR",
  "price": 189.00,
  "description": "...",
  "is_active": true,
  "sort_order": 0
}
```

**Response:** `201 Created` with the created product object.

---

#### `PUT /api/v1/admin/products/{id}` — Admin

Updates an existing product. Same body shape as POST.

---

#### `DELETE /api/v1/admin/products/{id}` — Admin

Soft-deletes (sets `is_active = 0`). Does not destroy the row.

---

#### `POST /api/v1/admin/products/{id}/images` — Admin

Uploads gallery images. `multipart/form-data`.

**Form fields:**
- `images[]` — one or more image files (JPEG/PNG/WebP, max 5 MB each)

**Response:** `201 Created` — array of created image objects with full URLs.

---

#### `DELETE /api/v1/admin/products/{id}/images/{imageId}` — Admin

Removes a gallery image and deletes the file from storage.

---

### 4.2 Articles

#### `GET /api/v1/articles` — Public

| Query param | Type | Description |
|---|---|---|
| `locale` | string | en (default), de, fr |
| `category` | string | Filter by category name |
| `per_page` | int | Default 12 |
| `page` | int | Page number |

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "sourcing-tyres-at-scale",
      "image": "https://api.okelcor.de/storage/articles/sourcing-tyres.jpg",
      "published_at": "2026-03-14",
      "category": "Logistics",
      "title": "How to Source Tyres at Scale for International Markets",
      "read_time": "5 min read",
      "summary": "...",
      "locale": "en"
    }
  ],
  "meta": { "current_page": 1, "per_page": 12, "total": 8, "last_page": 1 }
}
```

---

#### `GET /api/v1/articles/{slug}` — Public

**Query parameters:** `locale` (default `en`)

**Response:**
```json
{
  "data": {
    "id": 1,
    "slug": "sourcing-tyres-at-scale",
    "image": "https://api.okelcor.de/storage/...",
    "published_at": "2026-03-14",
    "category": "Logistics",
    "title": "How to Source Tyres at Scale for International Markets",
    "read_time": "5 min read",
    "summary": "...",
    "body": [
      "First paragraph text.",
      "Second paragraph text."
    ],
    "locale": "en"
  }
}
```

---

#### `POST /api/v1/admin/articles` — Admin

**Request body:**
```json
{
  "slug": "my-article-slug",
  "image": "media_id_or_url",
  "published_at": "2026-04-01",
  "is_published": true,
  "sort_order": 0,
  "translations": {
    "en": {
      "category": "Logistics",
      "title": "Article Title in English",
      "read_time": "4 min read",
      "summary": "Short summary...",
      "body": ["Paragraph one.", "Paragraph two."]
    },
    "de": {
      "category": "Logistik",
      "title": "...",
      "read_time": "...",
      "summary": "...",
      "body": ["..."]
    },
    "fr": {
      "category": "Logistique",
      "title": "...",
      "read_time": "...",
      "summary": "...",
      "body": ["..."]
    }
  }
}
```

---

#### `PUT /api/v1/admin/articles/{id}` — Admin

Same shape as POST. Partial updates accepted.

---

#### `DELETE /api/v1/admin/articles/{id}` — Admin

Hard-deletes the article and all its translations.

---

### 4.3 Categories

#### `GET /api/v1/categories` — Public

**Query parameters:** `locale` (default `en`)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "pcr",
      "image": "https://api.okelcor.de/storage/categories/pcr.jpg",
      "title": "PCR Tyres",
      "label": "PCR",
      "subtitle": "Passenger Car Radial tyres from leading global brands."
    }
  ]
}
```

---

#### `PUT /api/v1/admin/categories/{id}` — Admin

Update content or image for a category. Accepts `multipart/form-data` for image upload.

---

### 4.4 Hero Slides

#### `GET /api/v1/hero-slides` — Public

**Query parameters:** `locale` (default `en`)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "image": "https://api.okelcor.de/storage/hero/slide-1.jpg",
      "sort_order": 0,
      "title": "Your Global Tyre Partner",
      "subtitle": "PCR, TBR, and Used tyres supplied to 40+ countries.",
      "cta_primary": "Shop Catalogue",
      "cta_secondary": "Get a Quote"
    }
  ]
}
```

---

#### `POST /api/v1/admin/hero-slides` — Admin
#### `PUT /api/v1/admin/hero-slides/{id}` — Admin
#### `DELETE /api/v1/admin/hero-slides/{id}` — Admin

Standard CRUD. Image upload via `multipart/form-data`.

---

### 4.5 Brands

#### `GET /api/v1/brands` — Public

**Response:**
```json
{
  "data": [
    { "id": 1, "name": "Michelin", "logo": "https://api.okelcor.de/storage/brands/michelin.svg" },
    { "id": 2, "name": "Bridgestone", "logo": "https://api.okelcor.de/storage/brands/bridgestone.svg" }
  ]
}
```

---

#### `POST /api/v1/admin/brands` — Admin
#### `PUT /api/v1/admin/brands/{id}` — Admin
#### `DELETE /api/v1/admin/brands/{id}` — Admin

---

### 4.6 Quote Requests

#### `POST /api/v1/quote-requests` — Public

**Request body:**
```json
{
  "full_name": "John Doe",
  "company_name": "Acme Tyres Ltd",
  "email": "john@acmetyres.com",
  "phone": "+44 7900 123456",
  "country": "United Kingdom",
  "business_type": "Wholesale Distributor",
  "tyre_category": "PCR",
  "brand_preference": "Michelin, Bridgestone",
  "tyre_size": "205/55R16",
  "quantity": "500-1000 units",
  "budget_range": "€10,000 – €25,000",
  "delivery_location": "London, UK",
  "delivery_timeline": "Within 30 days",
  "notes": "Multiple delivery locations across the UK."
}
```

**Required fields:** `full_name`, `email`, `country`, `tyre_category`, `quantity`, `delivery_location`, `notes`

**Behaviour:**
1. Validate required fields; email regex check
2. Generate `ref_number` (format: `OKL-QR-{6-digit-timestamp}-{3-char-rand}`)
3. Save to `quote_requests`
4. Send notification email to `QUOTE_EMAIL` env var
5. Send confirmation email to requester with ref number

**Response `201`:**
```json
{
  "data": {
    "ref_number": "OKL-QR-123456-A7B",
    "message": "Quote request received. Our team will respond within 1 business day."
  }
}
```

**Rate limit:** 5 requests per IP per hour.

---

#### `GET /api/v1/admin/quote-requests` — Admin

**Query parameters:** `status` (new/reviewing/quoted/closed), `page`, `per_page` (default 25)

Returns paginated list ordered by `created_at DESC`.

---

#### `PATCH /api/v1/admin/quote-requests/{id}/status` — Admin

**Request body:** `{ "status": "reviewing", "admin_notes": "Contacted via email." }`

---

### 4.7 Contact Messages

#### `POST /api/v1/contact` — Public

**Request body:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "subject": "General Inquiry",
  "inquiry": "I would like to know more about your TBR tyre supply."
}
```

**Behaviour:**
1. Validate all 4 fields; email format check
2. Save to `contact_messages`
3. Send notification to `CONTACT_EMAIL` env var; `replyTo` set to sender's email

**Response `201`:**
```json
{ "data": { "message": "Message received. We will respond within 2 business days." } }
```

**Rate limit:** 10 requests per IP per hour.

---

#### `GET /api/v1/admin/contact-messages` — Admin

**Query parameters:** `status` (new/read/replied), `page`, `per_page`

---

#### `PATCH /api/v1/admin/contact-messages/{id}/status` — Admin

**Request body:** `{ "status": "replied", "admin_notes": "..." }`

---

### 4.8 Orders

#### `POST /api/v1/orders` — Public

**Request body:**
```json
{
  "delivery": {
    "name": "Hans Müller",
    "email": "hans@example.de",
    "phone": "+49 151 12345678",
    "address": "Landsberger Str. 10",
    "city": "Munich",
    "postal_code": "80687",
    "country": "Germany"
  },
  "payment_method": "card",
  "items": [
    {
      "product_id": 1,
      "sku": "OKL-0001",
      "brand": "Michelin",
      "name": "Energy Saver+",
      "size": "205/55R16",
      "unit_price": 89.99,
      "quantity": 4
    }
  ]
}
```

**Required delivery fields:** all 7 (name, email, phone, address, city, postal_code, country)
**Required items:** non-empty array; each item must have sku, unit_price, quantity

**Behaviour:**
1. Validate delivery + items
2. Calculate `subtotal` = sum(unit_price * quantity); `total` = subtotal + delivery_cost (0)
3. Generate `ref` (format: `OKL-{5-char-timestamp}{3-char-rand}`)
4. Save order + order_items
5. Send notification to `ORDER_EMAIL` env var with full order details
6. Send acknowledgement email to customer

**Response `201`:**
```json
{
  "data": {
    "ref": "OKL-X7FA2B",
    "mode": "manual",
    "message": "Order received. Our team will contact you to arrange payment."
  }
}
```

> `mode: "manual"` until a live payment SDK is integrated. When Stripe/PayPal is added, the
> route should process the charge and return `mode: "live"`. No frontend changes are required.

**Rate limit:** 10 requests per IP per hour.

---

#### `GET /api/v1/admin/orders` — Admin

**Query parameters:** `status`, `payment_status`, `page`, `per_page`

---

#### `PATCH /api/v1/admin/orders/{id}/status` — Admin

**Request body:**
```json
{ "status": "confirmed", "payment_status": "paid", "admin_notes": "Payment received by bank transfer." }
```

---

### 4.9 Newsletter

#### `POST /api/v1/newsletter/subscribe` — Public

**Request body:** `{ "email": "user@example.com", "locale": "en" }`

**Behaviour:**
1. Validate email format
2. If already confirmed: return 200 silently (do not re-send)
3. Generate confirmation token; save `is_confirmed = 0`
4. Send double opt-in confirmation email with link to `/api/v1/newsletter/confirm/{token}`

**Response `201`:**
```json
{ "data": { "message": "Please check your email to confirm your subscription." } }
```

---

#### `GET /api/v1/newsletter/confirm/{token}` — Public

Confirms the subscription: sets `is_confirmed = 1`, clears token.
Redirects to `https://okelcor.de/?newsletter=confirmed`.

---

#### `GET /api/v1/admin/newsletter` — Admin

**Query parameters:** `confirmed_only` (boolean), `locale`, `page`, `per_page`

---

#### `DELETE /api/v1/admin/newsletter/{email}` — Admin

Unsubscribes / removes a subscriber.

---

### 4.10 Media / File Upload

#### `POST /api/v1/admin/media` — Admin

Upload a file. `multipart/form-data`.

| Field | Required | Description |
|---|---|---|
| `file` | Yes | Image file |
| `collection` | No | `products`, `articles`, `hero`, `brands`, `categories`, `general` |
| `alt_text` | No | Alt text for accessibility |

**Validation:**
- Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`, `image/svg+xml`
- Max file size: **5 MB**
- Filename sanitized to UUID prefix to prevent collisions and path traversal

**Response `201`:**
```json
{
  "data": {
    "id": 42,
    "url": "https://api.okelcor.de/storage/products/uuid-filename.jpg",
    "path": "products/uuid-filename.jpg",
    "filename": "uuid-filename.jpg",
    "original_name": "michelin-energy-saver.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 184320,
    "width": 1200,
    "height": 900,
    "alt_text": null,
    "collection": "products"
  }
}
```

---

#### `GET /api/v1/admin/media` — Admin

**Query parameters:** `collection`, `search` (original filename), `page`, `per_page` (default 48)

---

#### `DELETE /api/v1/admin/media/{id}` — Admin

Deletes the DB record and the physical file from storage.

---

### 4.11 Admin Authentication

#### `POST /api/v1/admin/login`

**Request body:** `{ "email": "admin@okelcor.de", "password": "..." }`

**Response `200`:**
```json
{
  "data": {
    "token": "1|abc123...",
    "admin": {
      "id": 1,
      "name": "Admin",
      "email": "admin@okelcor.de",
      "role": "super_admin"
    }
  }
}
```

Uses **Laravel Sanctum token authentication** (not cookie sessions — Next.js is on a different origin).

---

#### `POST /api/v1/admin/logout` — Admin

Revokes the current token. **Response:** `200 OK`.

---

#### `GET /api/v1/admin/me` — Admin

Returns the authenticated admin user object. Used to verify token validity.

---

### 4.12 Search

#### `GET /api/v1/search` — Public

| Query param | Required | Description |
|---|---|---|
| `q` | Yes | Minimum 2 characters |
| `locale` | No | Default `en` |

**Response:**
```json
{
  "data": {
    "products": [
      {
        "id": 1,
        "brand": "Michelin",
        "name": "Energy Saver+",
        "size": "205/55R16",
        "type": "PCR",
        "price": 89.99,
        "primary_image": "...",
        "href": "/shop/1"
      }
    ],
    "articles": [
      {
        "slug": "sourcing-tyres-at-scale",
        "title": "How to Source Tyres at Scale",
        "category": "Logistics",
        "date": "2026-03-14",
        "image": "...",
        "href": "/news/sourcing-tyres-at-scale"
      }
    ],
    "total": 5
  }
}
```

Max results: 6 products, 4 articles. **Rate limit:** 30 requests per IP per minute.

---

### 4.13 Site Settings

#### `GET /api/v1/settings` — Public (read-only)

Returns non-sensitive public-facing settings (company info, feature flags).

**Response:**
```json
{
  "data": {
    "company_name": "Okelcor",
    "company_email": "info@okelcor.de",
    "company_phone": "+49 (0) 89 / 545 583 60",
    "rex_reg_number": "REG-XXXX",
    "maintenance_mode": false
  }
}
```

---

#### `PUT /api/v1/admin/settings` — Admin

Accepts a partial object — only keys present in the body are updated.

---

## 5. Media / File Storage

### Storage driver

Use Laravel's **local disk** (`storage/app/public`) with `php artisan storage:link` to create
the `public/storage` symlink. All file URLs follow:

```
https://api.okelcor.de/storage/{collection}/{filename}
```

### Directory structure

```
storage/app/public/
├── products/      ← product primary + gallery images
├── articles/      ← article featured images
├── hero/          ← hero slide backgrounds
├── brands/        ← brand logo SVGs/PNGs
├── categories/    ← category carousel images
└── general/       ← miscellaneous uploads
```

### Image processing

Install **`intervention/image ^3.0`** for server-side processing on upload:

- Auto-resize: max 2000 px on the longest side; preserve aspect ratio
- Strip EXIF metadata
- Generate a WebP variant alongside the original JPEG/PNG
- Store both paths in the `media` table (add a `webp_url` column if needed)

### Allowed types & limits

| Type | Max size |
|---|---|
| JPEG / PNG / WebP | 5 MB |
| SVG (brand logos only) | 500 KB |

Reject all other types with `422 Unprocessable Entity`.

---

## 6. CORS Configuration

The Next.js frontend is on `https://okelcor.de`. The API is on `https://api.okelcor.de`.
CORS must be configured to allow cross-origin requests from the frontend origin.

### `config/cors.php`

```php
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://okelcor.de',
        'https://www.okelcor.de',
        // Add Vercel preview URLs during development:
        // 'https://okelcor-git-main-yourteam.vercel.app',
    ],

    // For local development, change to:
    // 'allowed_origins' => ['http://localhost:3000'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Must be false when using Bearer tokens (not cookies).
    // Never use true with wildcard origins — browsers will reject it.
    'supports_credentials' => false,
];
```

### `.env` additions (Laravel side)

```env
APP_URL=https://api.okelcor.de
FRONTEND_URL=https://okelcor.de
SANCTUM_STATEFUL_DOMAINS=okelcor.de,www.okelcor.de
```

---

## 7. Authentication & Security

### Admin API auth (Laravel Sanctum — token mode)

1. Use **token-based auth** (not cookie sessions) — Next.js sends `Authorization: Bearer {token}`
2. Protect all `/api/v1/admin/*` routes with `auth:sanctum` middleware
3. Token expiry: `SANCTUM_TOKEN_EXPIRATION=1440` (24 hours) in `.env`
4. On login, return the token in the response body; the CMS frontend stores it

### Rate limiting

Define in `bootstrap/app.php` (Laravel 11) or `Kernel.php` (Laravel 10):

```php
RateLimiter::for('public-form', function (Request $request) {
    return Limit::perHour(10)->by($request->ip());
});

RateLimiter::for('search', function (Request $request) {
    return Limit::perMinute(30)->by($request->ip());
});
```

Apply:
```php
Route::post('/quote-requests', ...)->middleware('throttle:public-form');
Route::post('/contact', ...)->middleware('throttle:public-form');
Route::post('/orders', ...)->middleware('throttle:public-form');
Route::get('/search', ...)->middleware('throttle:search');
```

### Input sanitization

- Strip HTML from all text inputs: `'bail', 'string', Rule::notRegex('/<[^>]+>/')` or `strip_tags()`
- Validate emails with `email:rfc,dns`
- Sanitize filenames: `Str::uuid() . '.' . $validated_extension`
- Never expose raw `id` sequences in public responses where slugs can be used instead

### `ForceJsonResponse` middleware

Register on all API routes so Laravel never returns HTML error pages to the frontend:

```php
// app/Http/Middleware/ForceJsonResponse.php
public function handle(Request $request, Closure $next): Response
{
    $request->headers->set('Accept', 'application/json');
    return $next($request);
}
```

Register in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [ForceJsonResponse::class]);
})
```

---

## 8. Laravel Recommendations

### PHP & Laravel version

- **PHP:** 8.2 or 8.3 (Hostinger Business supports both — set in hPanel)
- **Laravel:** 11.x

### Recommended packages

| Package | Purpose |
|---|---|
| `laravel/sanctum` | API token auth for admin (included in Laravel 11) |
| `spatie/laravel-query-builder` | Clean filter/sort/include handling for public endpoints |
| `intervention/image ^3.0` | Image resizing and WebP conversion on upload |
| `laravel/telescope` | Dev-only request/query debugger |

### Folder structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/                    ← public API controllers
│   │   │   ├── ProductController.php
│   │   │   ├── ArticleController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── HeroSlideController.php
│   │   │   ├── BrandController.php
│   │   │   ├── QuoteRequestController.php
│   │   │   ├── ContactController.php
│   │   │   ├── OrderController.php
│   │   │   ├── NewsletterController.php
│   │   │   ├── SearchController.php
│   │   │   └── SettingController.php
│   │   └── Admin/                  ← admin-only controllers (auth:sanctum)
│   │       ├── AuthController.php
│   │       ├── AdminProductController.php
│   │       ├── AdminArticleController.php
│   │       ├── AdminCategoryController.php
│   │       ├── AdminHeroSlideController.php
│   │       ├── AdminBrandController.php
│   │       ├── AdminQuoteRequestController.php
│   │       ├── AdminContactController.php
│   │       ├── AdminOrderController.php
│   │       ├── AdminNewsletterController.php
│   │       ├── MediaController.php
│   │       └── AdminSettingController.php
│   ├── Middleware/
│   │   └── ForceJsonResponse.php
│   └── Requests/                   ← FormRequest classes for all mutations
├── Models/
│   ├── Product.php
│   ├── ProductImage.php
│   ├── Article.php
│   ├── ArticleTranslation.php
│   ├── Category.php
│   ├── CategoryTranslation.php
│   ├── HeroSlide.php
│   ├── HeroSlideTranslation.php
│   ├── Brand.php
│   ├── QuoteRequest.php
│   ├── ContactMessage.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── NewsletterSubscriber.php
│   ├── Media.php
│   ├── AdminUser.php
│   └── SiteSetting.php
└── Mail/
    ├── QuoteRequestReceived.php
    ├── QuoteRequestConfirmation.php
    ├── ContactMessageReceived.php
    ├── OrderReceived.php
    ├── OrderConfirmation.php
    └── NewsletterConfirmation.php
```

### Route skeleton (`routes/api.php`)

```php
Route::prefix('v1')->group(function () {

    // ── Public routes ─────────────────────────────────────────────────────────
    Route::get('/products',           [ProductController::class, 'index']);
    Route::get('/products/{id}',      [ProductController::class, 'show']);
    Route::get('/articles',           [ArticleController::class, 'index']);
    Route::get('/articles/{slug}',    [ArticleController::class, 'show']);
    Route::get('/categories',         [CategoryController::class, 'index']);
    Route::get('/hero-slides',        [HeroSlideController::class, 'index']);
    Route::get('/brands',             [BrandController::class, 'index']);
    Route::get('/settings',           [SettingController::class, 'index']);
    Route::get('/search',             [SearchController::class, 'index'])->middleware('throttle:search');

    Route::post('/quote-requests',           [QuoteRequestController::class, 'store'])->middleware('throttle:public-form');
    Route::post('/contact',                  [ContactController::class, 'store'])->middleware('throttle:public-form');
    Route::post('/orders',                   [OrderController::class, 'store'])->middleware('throttle:public-form');
    Route::post('/newsletter/subscribe',     [NewsletterController::class, 'subscribe']);
    Route::get('/newsletter/confirm/{token}',[NewsletterController::class, 'confirm']);

    // ── Admin auth ────────────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::post('/login', [Admin\AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout',  [Admin\AuthController::class, 'logout']);
            Route::get('/me',       [Admin\AuthController::class, 'me']);

            Route::apiResource('products',    Admin\AdminProductController::class);
            Route::post('products/{id}/images',             [Admin\AdminProductController::class, 'uploadImages']);
            Route::delete('products/{id}/images/{imageId}', [Admin\AdminProductController::class, 'deleteImage']);

            Route::apiResource('articles',    Admin\AdminArticleController::class);
            Route::apiResource('categories',  Admin\AdminCategoryController::class)->only(['index', 'update']);
            Route::apiResource('hero-slides', Admin\AdminHeroSlideController::class);
            Route::apiResource('brands',      Admin\AdminBrandController::class);

            Route::get('quote-requests',                    [Admin\AdminQuoteRequestController::class, 'index']);
            Route::patch('quote-requests/{id}/status',      [Admin\AdminQuoteRequestController::class, 'updateStatus']);

            Route::get('contact-messages',                  [Admin\AdminContactController::class, 'index']);
            Route::patch('contact-messages/{id}/status',    [Admin\AdminContactController::class, 'updateStatus']);

            Route::get('orders',                            [Admin\AdminOrderController::class, 'index']);
            Route::patch('orders/{id}/status',              [Admin\AdminOrderController::class, 'updateStatus']);

            Route::get('newsletter',                        [Admin\AdminNewsletterController::class, 'index']);
            Route::delete('newsletter/{email}',             [Admin\AdminNewsletterController::class, 'destroy']);

            Route::get('media',                             [Admin\MediaController::class, 'index']);
            Route::post('media',                            [Admin\MediaController::class, 'store']);
            Route::delete('media/{id}',                     [Admin\MediaController::class, 'destroy']);

            Route::get('settings',                          [Admin\AdminSettingController::class, 'index']);
            Route::put('settings',                          [Admin\AdminSettingController::class, 'update']);
        });
    });
});
```

### Model conventions

- Use `$fillable` on every model (never `$guarded = []` — security risk)
- Cast JSON columns: `protected $casts = ['body' => 'array']` on `ArticleTranslation`
- Use `$hidden = ['password', 'token']` on models that store sensitive fields
- Define Eloquent relationships for all foreign keys (hasMany, belongsTo, etc.)

---

## 9. Environment Variables (Laravel `.env`)

```env
APP_NAME="Okelcor API"
APP_ENV=production
APP_KEY=                          # php artisan key:generate
APP_DEBUG=false
APP_URL=https://api.okelcor.de

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=okelcor_cms
DB_USERNAME=okelcor_user
DB_PASSWORD=

# Mail — use Hostinger SMTP or a transactional service (Mailgun, Resend, etc.)
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=noreply@okelcor.de
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@okelcor.de
MAIL_FROM_NAME="Okelcor"

# Notification destinations
CONTACT_EMAIL=info@okelcor.de
QUOTE_EMAIL=quotes@okelcor.de
ORDER_EMAIL=orders@okelcor.de

# Frontend origin
FRONTEND_URL=https://okelcor.de

# Sanctum
SANCTUM_STATEFUL_DOMAINS=okelcor.de,www.okelcor.de
SANCTUM_TOKEN_EXPIRATION=1440

# Storage
FILESYSTEM_DISK=public
```

---

## 10. Deployment (Hostinger Business Hosting)

1. Set the **document root** of `api.okelcor.de` subdomain to `/public_html/api/public`
2. PHP version: set to **8.2** in hPanel → Advanced → PHP Configuration
3. Enable `mod_rewrite` if not already on (required for Laravel routing via `.htaccess`)
4. Create the MySQL database and user in hPanel → Databases before running migrations
5. Deploy via hPanel File Manager, FTP, or SSH Git pull:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SiteSettingsSeeder
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Add cron job for scheduled tasks (hPanel → Cron Jobs):
```
* * * * * /usr/local/bin/php /home/{username}/public_html/api/artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Next.js Integration Notes

These notes clarify how the frontend will consume this API once it is live.

- **Server components** — public `GET` endpoints will be fetched from Next.js server components
  using `fetch(url, { next: { revalidate: 60 } })` for ISR (60-second cache refresh)
- **Static generation** — `generateStaticParams()` on product/article detail pages should call
  `GET /api/v1/products?per_page=1000` and `GET /api/v1/articles?per_page=500` to get all IDs/slugs
- **Filter sidebar** — the `filters` block on `GET /api/v1/products` replaces the hardcoded
  arrays in `components/shop/filter-sidebar.tsx`
- **Search** — `GET /api/v1/search` replaces the client-side `lib/search.ts` logic;
  `context/search-context.tsx` should be updated to fetch from the API endpoint
- **Image domains** — add `api.okelcor.de` to `images.remotePatterns` in `next.config.ts`:
  ```ts
  { protocol: 'https', hostname: 'api.okelcor.de', pathname: '/storage/**' }
  ```
- **Locale** — `?locale=en|de|fr` is passed on all multilingual endpoints;
  the `useLanguage()` hook's `locale` value maps directly to this query parameter
- **All image URLs returned by the API are absolute** (`https://api.okelcor.de/storage/...`)

---

*End of document. Questions about frontend behaviour or data shape? Contact the frontend team before starting implementation.*
