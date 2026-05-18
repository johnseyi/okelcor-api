# Session Handoff — Okelcor API
Last updated: 2026-05-18 (session 23)

## eBay listing status (session 23) — EAN fix

**Root cause of session 23:** errorId 25002 "Das Feld EAN fehlt" — eBay DE category 10183 requires an EAN (European Article Number / barcode). Products table had no `ean` column.

**Fix applied:**
- Migration `2026_05_18_121827_add_ean_to_products_table.php` — adds nullable `ean` VARCHAR(20) column to products
- `Product::$fillable` — added `ean`
- `EbaySellingService.upsertInventoryItem()` — sends `product.ean: [$product->ean]` if EAN is stored; falls back to `["Does not apply"]` (eBay GTIN exemption) when none is set
- `EbaySellingService.syncInventory()` — same EAN logic
- `EbaySellingService.diagnoseProduct()` — same EAN in Step D inventory PUT

**Action required on production:**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```
Then test: `/opt/alt/php83/usr/bin/php artisan ebay:debug-product 235776 --publish`

**If EAN "Does not apply" is rejected by eBay for category 10183:**
- The `ean` column is ready — populate it via the admin product edit form or a bulk import
- RAPID tyre EANs can be obtained from the supplier data sheet or barcode on the tyre sidewall

**Files changed (session 23):**
- `database/migrations/2026_05_18_121827_add_ean_to_products_table.php` (new)
- `app/Models/Product.php`
- `app/Services/EbaySellingService.php`

---

**Chain of eBay bugs fixed in sessions 22–23 (all proven by diagnoseProduct diagnostic):**
1. Locale mismatch: `Content-Language: en-US` → inventory stored as `locale:en_US` → 25751 when POST /offer used EBAY_DE. Fix: `marketplaceLocale()` returns `de-DE` for EBAY_DE.
2. `->ok()` false for HTTP 201: POST /offer returns 201 Created, not 200. Fix: `->successful()` (200-299).
3. `->ok()` false for HTTP 204: PUT /offer and PUT inventory_item return 204. Fix: `->successful()` everywhere.
4. Missing merchantLocationKey: eBay couldn't determine Item.Country for EBAY_DE. Fix: `ensureMerchantLocation()` auto-creates OKELCOR-MAIN location; `merchantLocationKey` added to offer body.
5. English aspect names: EBAY_DE category 10183 requires German names (`Marke` not `Brand`, etc.). Fix: `buildAspects()` uses German names for EBAY_DE/AT/CH.
6. Missing EAN: category 10183 requires EAN field. Fix: send `product.ean` (or "Does not apply" fallback). [this session]

---

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

**Session 22 — eBay error 25751 fix: inventory indexing race condition (no migrations):**

**Root cause:** eBay returns error 25751 ("SKU not found for marketplace EBAY_DE") when `POST /offer` fires before eBay has finished indexing the just-PUT inventory item. This is an async race condition on eBay's side, not a config issue.

**Fix applied:**
- `EbaySellingService.createOrUpdateListing()`: now calls `waitForInventoryItem()` between `upsertInventoryItem()` and `upsertOffer()`. This GET-verifies the SKU is reachable (up to 3 attempts with 1 s gap), then throws a clear RuntimeException if still not available after all attempts.
- `EbaySellingService`: applied `rawurlencode()` to SKU in URL path segments in `upsertInventoryItem()`, `syncInventory()`, `deleteListing()` — prevents 400 errors for SKUs containing spaces or special chars.
- `EbaySellingService.diagnoseProduct()`: new public 6-step diagnostic method (validation → token → PUT inventory → GET verify → offer check → optional publish). Returns structured `$report` for the Artisan command.
- `EbayListingController.safeError()`: added pattern for 25751 ('error 25751' / 'was not available after') → returns user-readable "retry in a few seconds" message.
- Added `app/Console/Commands/EbayDebugProduct.php` — `php artisan ebay:debug-product {product_id} {--publish}` — runs full diagnostic and displays results in table format.

**No .env changes needed for this session's fix** — the retry logic is automatic.

**Files changed (session 22):**
- `app/Services/EbaySellingService.php`
- `app/Http/Controllers/Admin/EbayListingController.php`
- `app/Console/Commands/EbayDebugProduct.php` (new)

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

**Debug command usage (SSH on production):**
```bash
/opt/alt/php83/usr/bin/php artisan ebay:debug-product 235776
/opt/alt/php83/usr/bin/php artisan ebay:debug-product 235776 --publish
```

---

**Session 21 — eBay 502 diagnosis: category mismatch + error surfacing (no migrations):**

**Root cause confirmed:** `EBAY_CATEGORY_ID=179680` is from **ebay.com (US)** (`ebay.com/b/Car-Truck-Tires/179680`) and is **NOT valid for EBAY_DE**. eBay rejects the offer create/update call with an API error. Backend returns 502 with the safe message but frontend was displaying a generic "eBay action failed" instead of reading the `message` field.

**Correct EBAY_DE category IDs for tyres:**
- `10183` — PKW-Reifen (passenger car tyres)
- `10209` — LKW/Bus-Reifen (truck/bus/TBR tyres)

**Action required on production — update .env:**
```
EBAY_CATEGORY_ID=10183   # for PCR tyres
```
Also verify EBAY_FULFILLMENT_POLICY_ID / EBAY_PAYMENT_POLICY_ID / EBAY_RETURN_POLICY_ID were fetched with EBAY_DE marketplace set. If policies were configured while wrong marketplace was active, re-fetch from GET /admin/ebay/policies after updating the category.

**Backend changes (session 21):**
- `EbayListingController`: added `extractEbayErrors(\Throwable $e): array` — parses eBay JSON response body embedded in exception messages; returns `[{errorId, domain, category, message, longMessage, parameters}]`
- `EbayListingController`: `listProduct`, `updateProduct`, `removeListing` 502 responses now include `data.ebay_errors[]` — frontend can read exact eBay error codes and messages
- `EbayListingController.safeError()`: added specific detection before generic patterns:
  - Category invalid for marketplace (errorIds 25002/25003/21917182/95500 + text patterns) → names the bad category ID and suggests EBAY_DE alternatives (10183/10209)
  - Policy not found / doesn't belong to marketplace (errorIds 20400/20402/25004/25005 + text) → directs to /admin/ebay/policies
  - Seller permission error → directs to Seller Hub
  - Image not accessible → mentions storage symlink
- `EbayListingController.readiness()`: added `category_marketplace_mismatch` check — fails if EBAY_MARKETPLACE_ID contains "DE" and EBAY_CATEGORY_ID is a known US-only category (179680, 6030); names the correct EBAY_DE alternatives

**Files changed (session 21):**
- `app/Http/Controllers/Admin/EbayListingController.php`

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```
Then on production update .env: `EBAY_CATEGORY_ID=10183`

---

**Session 20 — eBay error 932 diagnosis + backend hardening (no migrations):**

**Root cause confirmed:** Error 932 is a Trading API (XML/SOAP) error code. The backend uses only the Sell REST API — it cannot emit error 932. The source is the frontend `lib/ebay.ts` calling the old Trading API with an expired `EBAY_ACCESS_TOKEN` env var. Frontend audit and fix is a separate task.

**Backend gaps closed:**
- `EbaySellingService`: added `$tokenSource` property, set on every `getAccessToken()` call (`cache` | `db_token_{id}` | `env_fallback` | `none`)
- `EbaySellingService`: added `logEbayApiError()` private helper — called before every `throw` in API methods; logs `api_family=Sell API (REST)`, `operation`, `endpoint`, `http_status`, `token_source`, `ebay_errors` (parsed from response body)
- `EbaySellingService`: added `parseEbayErrors()` private helper — parses eBay REST `{"errors":[{"errorId":...}]}` and OAuth `{"error":"invalid_grant"}` shapes; returns raw snippet for non-JSON
- `EbayListingController.safeError()`: added detection for eBay REST 401 patterns (`errorId 1001`, `Invalid access token`, `invalid_token`, `IAF token`, `token is expired`, `invalid_grant`) → returns reconnect-prompt message
- `routes/api.php`: removed legacy route aliases `POST products/{id}/list-on-ebay` and `DELETE products/{id}/ebay-listing` — canonical routes are the only paths now

**Route count: 172** (was 174 — two legacy aliases removed)

**How to confirm 932 source on production:**
```bash
tail -n 200 storage/logs/laravel.log | grep "eBay"
```
If error 932 appears, it will NOT be in Laravel logs (backend never calls Trading API).
Check browser Network tab: if the failed request URL is a Next.js `/api/admin/...` route handler
(not `/api/v1/admin/...`), the Trading API call is happening client-side in `lib/ebay.ts`.

**Files changed (session 20):**
- `app/Services/EbaySellingService.php` — token source tracking, structured error logging before every throw, `logEbayApiError()` + `parseEbayErrors()` helpers
- `app/Http/Controllers/Admin/EbayListingController.php` — `safeError()` extended with eBay REST 401 patterns and `invalid_grant`
- `routes/api.php` — legacy `list-on-ebay` and `ebay-listing` aliases removed

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

**Session 19 — Policies endpoint verification (no code changes):**

Confirmed `GET /api/v1/admin/ebay/policies` is working correctly end-to-end:
- Route registered ✓
- Token refresh + eBay Account API calls succeed (OAuth connected, `sell.account.readonly` scope active)
- `POST /api/v1/admin/ebay/test-connection` confirmed working — returns `"eBay connection is working. Token is valid and the API is reachable."`
- Response shape matches frontend spec exactly:
  ```json
  {
    "data": {
      "payment":     [{ "id": "...", "name": "..." }],
      "fulfillment": [{ "id": "...", "name": "..." }],
      "return":      [{ "id": "...", "name": "..." }]
    },
    "message": "success"
  }
  ```
- Partial failure behaviour confirmed: if one policy group fails, returns `[]` for that group; others succeed
- No backend changes needed — frontend can consume `data.payment`, `data.fulfillment`, `data.return` directly

---

**Session 18 deploy note (Phase EB-4 — eBay Settings Readiness Checklist):**

**No new database migrations.**

**Files changed (session 18):**
- `config/services.php` — added `seller_postal_code` (`EBAY_SELLER_POSTAL_CODE`) and `seller_location` (`EBAY_SELLER_LOCATION`) keys to `ebay_sell` config block
- `.env.example` — added `EBAY_SELLER_POSTAL_CODE` and `EBAY_SELLER_LOCATION`
- `app/Services/EbaySellingService.php` — added `accountBaseUrl()` private helper (eBay Account API); new `pingConnection(): array` calls `GET /inventory_item?limit=1` to verify token is valid; new `fetchPolicies(): array` calls eBay Account API for payment/fulfillment/return policies (sell.account.readonly scope), returns `[id, name]` per policy
- `app/Http/Controllers/Admin/EbayListingController.php` — new `readiness()` method (12 checks: credentials, connection, marketplace, category, 3 policy IDs, seller postal code, environment warning, live token test; returns structured checks + missing_config array; never exposes credential values); new `testConnection()` method (delegates to pingConnection, returns ok bool + safe message); new `policies()` method (delegates to fetchPolicies, returns payment/fulfillment/return arrays); extended `safeError()` with connection-test-failed pattern
- `routes/api.php` — added `GET ebay/readiness`, `POST ebay/test-connection`, `GET ebay/policies`

**New .env keys to set on production:**
```
EBAY_SELLER_POSTAL_CODE=your_postal_code
EBAY_SELLER_LOCATION=Germany
```

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

**Session 17 deploy note (Phase EB-3 — eBay Price/Title Update Sync & Enhanced Validation):**

**No new database migrations.**

**Files changed (session 17):**
- `app/Services/EbaySellingService.php` — extracted `buildOfferBody(Product): array` private helper (shared by upsertOffer/updateListing/syncFull); new `updateListing(Product): array` — strict update (requires existing offer, calls guardProduct, upserts inventory item + PUT offer, does NOT re-publish, returns offer_id + listing_id); new `syncFull(Product): void` — permissive full sync (stock + price + title + description) used by sync-all batch; expanded `guardProduct()` with 7 new checks: eBay connection (`EbayToken::active()->exists()`), non-empty title, stock > 0, absolute image URL validation (http/https), marketplace ID configured, category ID configured
- `app/Http/Controllers/Admin/EbayListingController.php` — new `updateProduct(int $id)` method for PATCH endpoint; `listProduct()` now catches `\InvalidArgumentException` separately (logged as `validation_failed`, returns 422); `syncAll()` changed from `syncInventory()` to `syncFull()` (syncs price + title + description too); log payloads include `price` in sync actions; `safeError()` extended with 8 new patterns (no-offer, no-title, no-stock, invalid-image, marketplace/category not configured, syncFull failed)
- `routes/api.php` — added `PATCH products/{id}/ebay/update`

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

**Session 16 deploy note (Phase EB-2 — eBay Listing Status Tracking & Sync Logs):**

**New database migrations:**
- `2026_05_14_000002_add_ebay_status_fields_to_products_table` — adds `ebay_offer_id`, `ebay_status`, `ebay_last_synced_at`, `ebay_sync_error` to `products`
- `2026_05_14_000003_create_ebay_listing_logs_table` — append-only audit log for all eBay listing actions

**Files changed (session 16):**
- `database/migrations/2026_05_14_000002_add_ebay_status_fields_to_products_table.php` — **NEW**
- `database/migrations/2026_05_14_000003_create_ebay_listing_logs_table.php` — **NEW**
- `app/Models/EbayListingLog.php` — **NEW** — append-only model (`UPDATED_AT = null`); BelongsTo Product + AdminUser
- `app/Models/Product.php` — added `ebay_offer_id`, `ebay_status`, `ebay_last_synced_at`, `ebay_sync_error` to `$fillable`; added `ebay_last_synced_at` datetime cast
- `app/Services/EbaySellingService.php` — `createOrUpdateListing()` return type changed `string → array` (`['listing_id', 'offer_id']`); new `getListingStatus(string $sku): array` fetches offer status from eBay and maps to internal values
- `app/Http/Controllers/Admin/EbayListingController.php` — `listProduct()` + `removeListing()` now update all 4 new product fields and write log entries; `syncAll()` updates `ebay_last_synced_at`, does best-effort status refresh, logs per-product success/failure; new `refreshStatus(int $id)` method; new `logs()` method with filters; `listings()` includes new status fields; `safeError()` helper maps exception messages to safe user-readable strings; `writeLog()` helper (try-catch — never blocks primary action)
- `routes/api.php` — added `GET ebay/logs`, `POST products/{id}/ebay/refresh-status`

**Deploy steps:**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

**Session 15 deploy note (Phase EB-1 — eBay OAuth & Token Stability):**

**New database migration:**
- `2026_05_14_000001_create_ebay_tokens_table` — creates `ebay_tokens` table with encrypted `access_token` + `refresh_token`, expiry timestamps, `is_active` flag, marketplace_id, connected_at, last_refreshed_at

**Files changed (session 15):**
- `database/migrations/2026_05_14_000001_create_ebay_tokens_table.php` — **NEW**
- `app/Models/EbayToken.php` — **NEW** — encrypted casts for access/refresh tokens; `scopeActive()`
- `app/Services/EbaySellingService.php` — `getAccessToken()` now loads from DB (active token) → persists any rotated refresh_token on every refresh call; fallback to `EBAY_REFRESH_TOKEN` env var (legacy only); `getAuthUrl()` renamed to `buildAuthUrl(string $state)`; new `exchangeCodeForTokens(string $code)` method exchanges auth code and creates DB token record; `cacheKey()` helper extracted
- `app/Http/Controllers/Admin/EbayListingController.php` — `authUrl()` now generates secure state (stored in cache 15 min); new `callback()` (public — redirects browser after eBay OAuth); new `status()` returns connection status + missing config; new `disconnect()` deactivates token + clears cache
- `routes/api.php` — added `GET admin/ebay/callback` (public, throttle:10,1); added `GET admin/ebay/status`, `POST admin/ebay/disconnect` inside `permission:ebay.manage`
- `.env.example` — `EBAY_REFRESH_TOKEN` marked as legacy fallback only

**IMPORTANT — security action required before deploy:**
- Rotate `EBAY_CLIENT_SECRET` in the eBay Developer Portal (it was exposed in a prior session)
- Set `EBAY_RU_NAME` to the redirect URI registered in the eBay Developer Portal
- The callback URL to register in the eBay Developer Portal is: `https://api.okelcor.com/api/v1/admin/ebay/callback`

**Deploy steps:**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

**Session 14 deploy note (Phase 2C-6 — Logistics Dashboard):**

**No new database migrations.**

**Files changed (session 14):**
- `app/Http/Controllers/Admin/AdminLogisticsController.php` — **NEW** — `dashboard()` method; builds 10-metric summary via COUNT queries; paginates non-cancelled orders with eager-loaded `tradeDocuments` (issued only) + `euDeclaration`; batch-loads `Invoice` records by `order_ref` to avoid N+1; per-order checklist: `checkDocuments()` (5 doc types), `computeMissing()` (business rules), `computeRiskLevel()` (high/medium/low/none), `computeNextAction()` (priority-ordered action string)
- `routes/api.php` — added `GET logistics/dashboard` under `permission:orders.view`

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
```

---

**Session 13 deploy note (Phase 2C-5 — Send Trade Document by Email):**

**New database migration:**
- `2026_05_13_090256_add_document_sent_to_order_logs_action` — extends `order_logs.action` enum to include `document_generated`, `document_uploaded`, `document_deleted` (backfill — these were used in code but missing from original migration) and new `document_sent` value

**Files changed (session 13):**
- `app/Http/Controllers/Admin/AdminTradeDocumentController.php` — added `sendEmail()` method; validates `recipient_email` (nullable email) + `message` (nullable string max:1000); checks `pdf_path ?? file_path` exists on disk; defaults recipient to `order.customer_email`; sends `TradeDocumentEmail` mailable with attachment; stamps `sent_at`; logs `document_sent` to order_logs
- `app/Mail/TradeDocumentEmail.php` — new mailable; builds subject + label from document type; attaches file from private disk; passes `documentLabel` + `adminMessage` to views
- `resources/views/emails/trade-document-email.blade.php` — transactional HTML email; orange top border; document details table; optional admin note block (orange left border); attachment notice
- `resources/views/emails/trade-document-email-text.blade.php` — plain-text fallback
- `routes/api.php` — added `POST trade-documents/{id}/send-email` under `permission:trade_documents.manage`
- `database/migrations/2026_05_13_090256_add_document_sent_to_order_logs_action.php` — new

**Deploy steps:**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
/opt/alt/php83/usr/bin/php artisan view:clear
```

---

**Session 12 deploy note (Phase 2C-4 — Commercial Invoice):**

**No new database migrations** — uses existing `trade_documents` table.

**Files changed (session 12):**
- `app/Services/TradeDocumentService.php` — added `generateCommercialInvoiceForOrder()` method; idempotent on `commercial_invoice` + `status=issued`; stores PDF to `trade-documents/commercial-invoice/CI-YYYY-XXXX.pdf`
- `app/Http/Controllers/Admin/AdminTradeDocumentController.php` — added `generateCommercialInvoice()` method; wraps service in try/catch; writes `document_generated` order log; returns 201 new / 200 existing
- `routes/api.php` — added `POST orders/{id}/generate-commercial-invoice` under `permission:trade_documents.manage`
- `resources/views/pdf/commercial-invoice.blade.php` — new DomPDF template with export notice, seller/buyer, trade terms bar (incoterms, country of export, destination, carrier, tracking), items table with HS code + country of origin placeholders, totals, VAT/customs declaration block (reverse-charge / exempt / standard), signature + stamp blocks

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
/opt/alt/php83/usr/bin/php artisan view:clear
```

---

**Session 11 deploy note (Phase 2C-1/2/3 — Trade Documents):**

**New database migration:**
- `2026_05_12_163809_add_type_label_to_trade_documents_table` — adds nullable `type_label` column to `trade_documents`

**Files changed (session 11):**
- `app/Services/TradeDocumentService.php` — added `packing_list` (PL-) and `delivery_note` (DN-) to PREFIXES; added `generatePackingListForOrder()` and `generateDeliveryNoteForOrder()` methods; fixed Invoice lookup from wrong `order_id` column to correct `order_ref` column
- `app/Http/Controllers/Admin/AdminTradeDocumentController.php` — added `generatePackingList()`, `generateDeliveryNote()`, `uploadShipmentDocument()`, `destroy()` methods; added `type_label` to `formatDocument()`; accepts `document_label` as alias for `type_label` on upload
- `app/Http/Controllers/OrderController.php` — added `delivery_note` and `shipment_document` to customer trade_documents whitelist; added `type_label`, `has_file`, `sent_at`, `original_filename`, `mime_type`, `file_size` to response shape
- `app/Http/Controllers/TradeDocumentController.php` — added `delivery_note` and `shipment_document` to customer whitelist; fixed download to check `file_path` as fallback for uploaded docs (was only checking `pdf_path`); added `type_label`, `has_file`, `mime_type`, `file_size` to response shape
- `app/Models/TradeDocument.php` — added `type_label` to `$fillable`
- `routes/api.php` — added `POST orders/{id}/generate-packing-list`, `POST orders/{id}/generate-delivery-note`, `POST orders/{id}/trade-documents/upload`, `DELETE trade-documents/{id}`
- `resources/views/pdf/packing-list.blade.php` — new DomPDF template (PL)
- `resources/views/pdf/delivery-note.blade.php` — new DomPDF template (DN) with EU reverse-charge Gelangensbestätigung notice
- `database/migrations/2026_05_12_163809_add_type_label_to_trade_documents_table.php` — new

**Deploy steps:**
```bash
git reset --hard origin/main
composer install --no-dev
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan config:clear && /opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
/opt/alt/php83/usr/bin/php artisan view:clear
```

---

**Session 10 deploy note (P1 Security Fixes):**

**No new database migrations.**

**Files changed (session 10):**
- `routes/api.php` — auth public group split into `throttle:auth` (register, login, reset-password) and `throttle:auth-email` (forgot-password, resend-verification); `GET /orders` and `GET /orders/{ref}` moved from public to `auth.customer`; `throttle:checkout` added to checkout route; `throttle:tracking` added to tracking route
- `app/Providers/AppServiceProvider.php` — added 4 named rate limiters: `auth` (10/min), `auth-email` (5/min), `checkout` (10/min by customer ID), `tracking` (30/min)
- `app/Http/Controllers/CustomerAuthController.php` — `recordLogin()` no longer accepts client-supplied `last_login_ip` or `last_login_at`; server IP and `now()` always used
- `app/Http/Controllers/OrderController.php` — `index()` uses `$request->user()->email` (token-derived) instead of `?email=` param; `show()` adds ownership check `WHERE customer_email = token email`

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
php artisan config:clear && php artisan config:cache
php artisan route:cache
```

---

**Session 9 deploy note (Security Audit Phase 1 + P0 fix):**

**No new database migrations** — this session's changes are middleware and migration-file-only.

**Files changed (session 9):**
- `app/Http/Middleware/EnsureAdminToken.php` — **NEW** — rejects any Sanctum token that is not an AdminUser instance (403)
- `bootstrap/app.php` — registered alias `auth.admin` → `EnsureAdminToken`
- `routes/api.php` — changed admin group from `middleware('auth:sanctum')` to `middleware(['auth:sanctum', 'auth.admin'])` — ALL 100+ admin routes now require AdminUser token
- `tests/Feature/AdminTokenGuardTest.php` — **NEW** — 16 passing tests covering customer-rejected/admin-passes/role-stacking
- `database/migrations/2026_03_30_000011_create_quote_requests_table.php` — renamed `idx_status` → `quote_requests_status_idx`, `idx_email` → `quote_requests_email_idx` (SQLite test compat only — no production effect)
- `database/migrations/2026_03_30_000012_create_contact_messages_table.php` — same pattern
- `database/migrations/2026_03_30_000013_create_orders_table.php` — same pattern
- `database/migrations/2026_03_30_000015_create_newsletter_subscribers_table.php` — same pattern
- `database/migrations/2026_04_19_185023_create_invoices_table.php` — same pattern

**Deploy steps (no migration needed):**
```bash
git reset --hard origin/main
composer install --no-dev
php artisan config:clear && php artisan config:cache
php artisan route:cache
```

**Session 8 migrations (all previously ran on production):**
- `2026_05_11_140000_backfill_rapid_cost_price_and_recalculate_prices` — ran, did nothing
- `2026_05_11_150000_force_rapid_prices_at_35pct` — applied 35% to Rapid price
- `2026_05_11_160000_fix_rapid_price_b2b_b2c_to_match_price` — aligned price_b2b/price_b2c

---

## Current Route Count: 174

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
GET    /api/v1/auth/invoices     ← customer's own invoices (released_at IS NOT NULL only)
GET    /api/v1/invoices/{id}/download  ← download invoice PDF (auth.customer Bearer token)

GET    /api/v1/auth/addresses
POST   /api/v1/auth/addresses
PUT    /api/v1/auth/addresses/{id}
DELETE /api/v1/auth/addresses/{id}

POST   /api/v1/auth/orders/{ref}/checkout              ← Pay Now — creates/refreshes Stripe Checkout Session for pending order
POST   /api/v1/auth/orders/{ref}/declaration           ← sign EU entry certificate (Gelangensbestätigung)
GET    /api/v1/auth/orders/{ref}/declaration/download  ← download signed declaration PDF

GET    /api/v1/orders/{ref}/trade-documents            ← customer's trade documents for an order (issued docs only)
GET    /api/v1/trade-documents/{id}/download           ← download a trade document PDF (auth.customer)

GET    /api/v1/orders                      ← customer's own orders (email from token — no ?email= param)
GET    /api/v1/orders/{ref}               ← single order — ownership verified via token email
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
      "released_at": "2024-04-01T00:00:00+00:00",
      "amount": 4850.00,
      "status": "paid",
      "order_ref": "OKL-AB123",
      "tax_treatment": "standard",
      "is_reverse_charge": false,
      "download_available": true,
      "pdf_url": "https://api.okelcor.com/api/v1/invoices/1/download"
    }
  ]
}
```
Status values: `paid` | `unpaid` | `overdue`

**Visibility gate:** only invoices where `released_at IS NOT NULL` are returned. For EU reverse-charge orders, `released_at` is `null` until admin acknowledges the EU Entry Certificate. Non-reverse-charge invoices have `released_at = now()` set at payment time (immediately visible).

**Lazy invoice creation:** `GET /auth/invoices` auto-creates any missing invoices for paid orders linked to the customer's email (covers the case where payment webhook fired before the customer account existed). Idempotent — safe to call on every request.

#### GET /api/v1/invoices/{id}/download
- Middleware: `auth.customer` — requires `Authorization: Bearer {customer_token}`
- Verifies `invoice.customer_id === authenticated customer.id` — 403 if mismatch
- Returns file as `Content-Disposition: inline; filename="INV-YYYY-NNNN.pdf"` — opens in browser tab, not forced download
- Error responses (all JSON):
  - 401 `"Unauthenticated."` — no/invalid token
  - 403 `"You do not have access to this invoice."` — wrong customer
  - 423 `"Invoice is not available until the EU Entry Certificate has been reviewed and acknowledged."` — `released_at` is null (reverse-charge orders pending admin acknowledgement)
  - 404 `"Invoice PDF is not available yet."` — `pdf_url` is null
  - 404 `"Invoice PDF file was not found."` — file missing on disk
- Controller: `InvoiceDownloadController@download`
- All 403/404/423 cases write `Log::warning` with `invoice_id`, `invoice_customer_id`, `auth_customer_id`, `pdf_url`

#### GET /api/v1/orders/{ref}/trade-documents
- Middleware: `auth.customer`
- Ownership: matches `order.customer_email` to `customer.email` (case-insensitive); 404 if no match
- Returns issued docs only: types `proforma`, `commercial_invoice`, `packing_list`, `delivery_note`, `shipment_document` with `status=issued`
- Response shape per item: `{ id, type, type_label, number, status, has_pdf, has_file, issued_at, sent_at, original_filename, mime_type, file_size }`

#### GET /api/v1/trade-documents/{id}/download
- Middleware: `auth.customer`
- Ownership: resolved via `order.customer_email`; 404 if no match (does not leak doc existence)
- Checks `pdf_path` first, falls back to `file_path` (for uploaded shipment docs)
- Returns file as download using `original_filename` as the filename
- Controller: `TradeDocumentController@download`

#### POST /api/v1/auth/orders/{ref}/checkout — Customer Pay Now
- Middleware: `auth.customer` — requires `Authorization: Bearer {customer_token}`
- Ownership: matches `order.customer_email` to `customer.email` (case-insensitive); returns 404 if no match (does not leak order existence)
- Guards:
  - 404 — order not found or wrong customer
  - 422 `"This order cannot be paid by Stripe."` — `payment_method ≠ stripe`
  - 409 `"This order is not awaiting payment."` — `payment_status ≠ pending`
  - 502 — Stripe API error
- Creates a new Stripe Checkout Session via `StripeService::createCheckoutSessionForOrder($order)`, saves `payment_session_id`
- Does NOT create an invoice — invoice is deferred to the Stripe webhook `checkout.session.completed`
- Controller: `CustomerOrderController@checkout`

Response (200):
```json
{
  "data": {
    "checkout_url": "https://checkout.stripe.com/...",
    "checkout_session_id": "cs_...",
    "order_ref": "OKL-XXXXX"
  }
}
```

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
POST   /api/v1/payments/tax-preview        ← tax calculation preview (no order/session created)
POST   /api/v1/payments/webhook            ← Stripe webhook handler
GET    /api/v1/tracking/{container}        ← auto-detects DHL vs sea freight (throttle:tracking)
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

GET    /admin/eu-declarations
GET    /admin/eu-declarations/{id}
GET    /admin/eu-declarations/{id}/download          ← download signed PDF from private disk
POST   /admin/eu-declarations/{id}/acknowledge       ← mark declaration acknowledged; releases invoice + sends FinalInvoiceReleased email

# Trade documents — permission:trade_documents.manage
POST   /admin/orders/{id}/trade-documents/proforma      ← generate/fetch proforma invoice PDF (idempotent)
POST   /admin/orders/{id}/generate-commercial-invoice   ← generate/fetch commercial invoice PDF (idempotent)
POST   /admin/orders/{id}/generate-packing-list         ← generate/fetch packing list PDF (idempotent)
POST   /admin/orders/{id}/generate-delivery-note        ← generate/fetch delivery note PDF (idempotent)
POST   /admin/orders/{id}/trade-documents/upload        ← upload shipment doc (Bill of Lading, CMR, etc.)
GET    /admin/orders/{id}/trade-documents               ← list all trade docs for an order (all types/statuses)
GET    /admin/trade-documents/{id}/download             ← download any trade document file from private disk
POST   /admin/trade-documents/{id}/send-email           ← send document to customer by email with file attached; stamps sent_at; logs document_sent
DELETE /admin/trade-documents/{id}                      ← delete uploaded shipment_document only (generated PDFs protected)

# Logistics dashboard — orders.view (super_admin, admin, order_manager, sales_manager)
GET    /admin/logistics/dashboard              ← summary cards + paginated document checklist

GET    /admin/contact-messages
GET    /admin/contact-messages/{id}
PATCH  /admin/contact-messages/{id}/status

GET    /admin/newsletter
DELETE /admin/newsletter/{email}

# eBay marketplace — permission:ebay.manage (super_admin, admin)
# Callback is PUBLIC (no auth — eBay redirects browser here after OAuth consent)
GET    /admin/ebay/callback                          ← PUBLIC; verifies state, exchanges code, stores tokens in DB; redirects to frontend
GET    /admin/ebay/auth-url                          ← returns { url, state }; state stored in cache (15 min CSRF guard)
GET    /admin/ebay/status                            ← connection status + missing config keys
GET    /admin/ebay/readiness                         ← 12-check pre-listing checklist (pass/warning/fail per check) + missing_config[]; never exposes credential values
POST   /admin/ebay/test-connection                   ← refreshes token + pings Inventory API; returns { ok: bool, message }
GET    /admin/ebay/policies                          ← fetches payment/fulfillment/return policy [id, name] from eBay Account API for configured marketplace
POST   /admin/ebay/disconnect                        ← deactivates active token; clears cache; logs ebay_disconnected
GET    /admin/ebay/listings                          ← products where ebay_listed=true (includes ebay_status, ebay_last_synced_at, ebay_sync_error)
GET    /admin/ebay/logs                              ← paginated ebay_listing_logs; filters: product_id, sku, action, status, date_from, date_to
POST   /admin/ebay/sync-all                          ← bulk full sync (stock + price + title + description) + best-effort status refresh; logs each product individually
POST   /admin/products/{id}/ebay/list                ← publish product to eBay; sets ebay_status=active; logs publish/validation_failed/publish_failed (canonical)
PATCH  /admin/products/{id}/ebay/update              ← update existing eBay listing (price, title, stock); does NOT re-publish; requires ebay_listed=true; logs update/validation_failed/update_failed
DELETE /admin/products/{id}/ebay/remove              ← remove product from eBay; sets ebay_status=withdrawn; logs remove/remove_failed (canonical)
POST   /admin/products/{id}/ebay/refresh-status      ← fetch current eBay offer status; update ebay_status + ebay_last_synced_at; log refresh_status/refresh_status_failed

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

Request body (`delivery` object is optional — quote's stored address fields are used as fallback):
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

**Delivery address fallback chain:**
- `address` → `delivery.address` ?? `quote.delivery_address`
- `city` → `delivery.city` ?? `quote.delivery_city`
- `postal_code` → `delivery.postal_code` ?? `quote.delivery_postal_code`
- `country` → `delivery.country` ?? `quote.country`
- `customer_phone` → `delivery.phone` ?? `quote.phone`

Response (201):
```json
{
  "data": {
    "order_ref": "OKL-XXXXXX",
    "quote_ref": "OKL-QR-XXXXXX",
    "status": "confirmed",
    "payment_status": "pending",
    "total": 17900.00,
    "checkout_url": "https://checkout.stripe.com/..."
  },
  "message": "Quote converted to order successfully."
}
```
`checkout_url` is null for bank transfer orders; present for Stripe orders. Frontend should show a "Pay now" button if non-null.
```
- Creates order with `mode='manual'`, `status='confirmed'`, `payment_status='pending'`
- `payment_method` defaults to `bank_transfer` if not provided
- Tax calculated before transaction via `TaxService::calculate(country, vatValid, customerType)` — stored on order as `tax_treatment`, `tax_rate`, `tax_amount`, `is_reverse_charge`
- `customerType` inferred: `company_name` present → `b2b`; else linked `customer.customer_type`; else null (treated as B2C)
- `taxableBase = subtotal + delivery_cost`; `taxAmount = taxableBase × tax_rate / 100`; `total = taxableBase + taxAmount`
- If `payment_method=stripe`: calls `StripeService::createCheckoutSessionForOrder($order)`, saves `payment_session_id`, passes `checkout_url` to email — failure is caught, logged, and does NOT block the 201 response
- If `payment_method=bank_transfer`: auto-generates proforma invoice PDF via `TradeDocumentService::generateProformaForOrder()` — non-blocking (failure logged, never rolls back)
- Sets `quote_requests.order_id` = new order ID to prevent re-conversion
- Writes `OrderLog` entry with `action='status_changed'`, `new_value='confirmed'`, notes referencing quote ref
- Sends `QuoteConvertedToOrder` email to `quote.email` after transaction — failure is caught and logged, never rolls back the order
- Invoice (INV-YYYY-NNNN) is NOT auto-created on conversion — invoice is created by the Stripe webhook (Stripe path) or when admin marks `payment_status=paid` (bank transfer path)
- FormRequest: `ConvertQuoteToOrderRequest`

#### GET /admin/quote-requests/{id} — detail response fields
```json
{
  "data": {
    "id": 1,
    "ref_number": "OKL-QR-877755-MWM",
    "status": "quoted",
    "created_at": "...",
    "updated_at": "...",

    "full_name": "Hans Müller",
    "contact_person": "Anna Schmidt",
    "company_name": "Reifen GmbH",
    "company_address": "Industriestr. 12",
    "company_city": "Hamburg",
    "company_postal_code": "20095",
    "email": "...",
    "phone": "...",
    "country": "Germany",
    "business_type": "b2b",
    "vat_number": "DE123456789",
    "vat_valid": true,

    "tyre_category": "TBR",
    "brand_preference": "Michelin",
    "tyre_size": "315/80R22.5",
    "quantity": "200",
    "tyre_condition": "used",
    "used_tyre_grade": "grade_a",
    "used_tyre_notes": "No sidewall damage.",
    "tyre_items": [
      { "size": "315/80R22.5", "quantity": "200" },
      { "size": "385/65R22.5", "quantity": "100" }
    ],

    "budget_range": null,
    "delivery_location": "Hamburg port",
    "delivery_timeline": "4–6 weeks",
    "delivery_address": "Musterstraße 1",
    "delivery_city": "Hamburg",
    "delivery_postal_code": "20095",
    "incoterm": "DAP",
    "incoterm_type": "delivery_terms",

    "notes": "...",
    "admin_notes": "...",

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
- `business_type` — `"b2b"` or `"b2c"` from the quote request form; distinct from the linked customer's `customer_type`
- `vat_valid` — JSON boolean (`true` / `false` / `null`), never integer; the DB tinyint is explicitly cast via `(bool)` in the formatter to preserve JSON type correctness
- `tyre_size` + `quantity` — legacy single-row fields; kept for backwards compatibility
- `tyre_items` — null or decoded JSON array; cast as PHP array by model
- `tyre_condition` — `null`, `"new"`, or `"used"`; `used_tyre_grade` / `used_tyre_notes` only populated when condition is `used`
- `incoterm` / `incoterm_type` — nullable preferred logistics terms
- `company_address`, `company_city`, `company_postal_code` — company registered address (distinct from delivery address)
- `contact_person` — purchasing contact, may differ from `full_name`
- `delivery_address`, `delivery_city`, `delivery_postal_code` — nullable structured fields; supplement the free-text `delivery_location`
- `attachment_name` is an alias of `attachment_original_name` (both present for frontend compatibility)
- List response also includes `contact_person`, `business_type`, `tyre_condition`, `tyre_items`, `incoterm`, `order_id`, `has_attachment`, `delivery_address`, `delivery_city`, `delivery_postal_code`

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
| `released_at` | timestamp | nullable — null = hidden from customer; set to `issued_at` for non-reverse-charge invoices; set by admin acknowledge for reverse-charge |
| `order_ref` | varchar(30) | nullable — unique per invoice |
| `subtotal_net` | decimal(10,2) | nullable — `order.subtotal + order.delivery_cost` |
| `tax_treatment` | varchar(30) | nullable — mirrors `orders.tax_treatment` |
| `tax_rate` | decimal(5,2) | nullable |
| `tax_amount` | decimal(10,2) | nullable |
| `is_reverse_charge` | tinyint | default 0 |
| `created_at` / `updated_at` | timestamp | |

Migration: `2026_05_08_000004_add_released_at_to_invoices_table` — adds column + backfill (non-RC → `issued_at`, RC with **acknowledged** declaration → `admin_acknowledged_at`, RC signed-only or pending → `null`). Migration: `2026_05_11_000001_correct_released_at_for_signed_only_declarations` — correction for local DBs that ran the original backfill; nulls `released_at` for RC invoices whose declaration is only `signed` (not `acknowledged`).

### `trade_documents`
Migrations:
- `2026_05_08_000003_create_trade_documents_table` — initial table
- `2026_05_12_163809_add_type_label_to_trade_documents_table` — adds `type_label` column

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_id` | bigint FK | cascade delete → orders |
| `order_ref` | varchar(30) | denormalized snapshot — readable after order deletion |
| `type` | varchar | `proforma`, `commercial_invoice`, `packing_list`, `delivery_note`, `shipment_document` |
| `type_label` | varchar(100) | nullable — human label for shipment_document (e.g. "Bill of Lading", "CMR") |
| `number` | varchar(50) | nullable — sequential document number e.g. `PI-2026-0001` |
| `status` | varchar | `draft`, `issued`, `cancelled` — default `draft` |
| `pdf_path` | varchar(500) | **hidden from API** — private disk path for generated PDFs |
| `file_path` | varchar(500) | **hidden from API** — private disk path for uploaded files |
| `original_filename` | varchar(255) | nullable — original filename for uploads and generated docs |
| `mime_type` | varchar(100) | nullable — MIME type of uploaded file |
| `file_size` | int | nullable — file size in bytes |
| `notes` | text | nullable — admin free-text notes |
| `issued_by` | bigint FK | nullable → nullOnDelete → admin_users |
| `issued_at` | timestamp | nullable — when document was issued |
| `sent_at` | timestamp | nullable — when document was sent to customer |
| `created_at` / `updated_at` | timestamp | |

Indexes: `order_id`, `order_ref`, `type`, `status`

**Model:** `App\Models\TradeDocument` — `pdf_path` and `file_path` are in `$hidden`; use `getRawOriginal('pdf_path')` / `getRawOriginal('file_path')` in controllers to access.

**Document number formats (sequential per year, `lockForUpdate()` in DB transaction):**
| Type | Prefix | Example |
|------|--------|---------|
| `proforma` | PI | `PI-2026-0001` |
| `commercial_invoice` | CI | `CI-2026-0001` |
| `packing_list` | PL | `PL-2026-0001` |
| `delivery_note` | DN | `DN-2026-0001` |

**Service:** `App\Services\TradeDocumentService`
- `generateProformaForOrder(Order $order, ?AdminUser $admin = null): TradeDocument` — idempotent; returns existing issued proforma or creates new; generates PDF via DomPDF; stored at `trade-documents/proforma/PI-YYYY-XXXX.pdf`
- `generateCommercialInvoiceForOrder(Order $order, ?AdminUser $admin = null): TradeDocument` — idempotent; same pattern; stored at `trade-documents/commercial-invoice/CI-YYYY-XXXX.pdf`; eager-loads `items.product`; includes invoice ref, trade terms bar, HS code/origin placeholders, customs declaration block
- `generatePackingListForOrder(Order $order, ?AdminUser $admin = null): TradeDocument` — idempotent; same pattern; stored at `trade-documents/packing-list/PL-YYYY-XXXX.pdf`; eager-loads `items.product` for tyre spec fields
- `generateDeliveryNoteForOrder(Order $order, ?AdminUser $admin = null): TradeDocument` — idempotent; same pattern; stored at `trade-documents/delivery-note/DN-YYYY-XXXX.pdf`; includes EU reverse-charge Gelangensbestätigung notice when `is_reverse_charge=true`
- All generation methods: PDF failure is non-blocking (logged as warning, DB record still returned)
- Invoice lookup in all generate methods: `Invoice::where('order_ref', $order->ref)` — NOT `order_id` (invoices table uses `order_ref` string FK, no `order_id` column)

**Auto-generation (bank transfer orders):**
- `AdminQuoteRequestController::convertToOrder()` — auto-generates proforma after bank_transfer quote conversion (non-blocking)

**PDF templates:**
- `resources/views/pdf/proforma-invoice.blade.php` — proforma invoice (pre-payment quotation doc)
- `resources/views/pdf/commercial-invoice.blade.php` — commercial invoice for export/customs; blue accent; export notice banner; trade terms bar (incoterms, country of export=Germany, destination, carrier, tracking); items with HS code + country of origin placeholders; customs declaration block (reverse-charge / exempt / standard); authorised signatory + stamp blocks
- `resources/views/pdf/packing-list.blade.php` — packing list with items table, weight placeholders, signature blocks
- `resources/views/pdf/delivery-note.blade.php` — delivery note with receipt confirmation + EU reverse-charge notice; uses `@php $rowClass` for alternating rows (DomPDF nth-child unreliable)

**Storage paths (all on `local` disk = `storage/app/private/`):**
| Type | Path |
|------|------|
| Proforma PDF | `trade-documents/proforma/PI-YYYY-XXXX.pdf` |
| Commercial invoice PDF | `trade-documents/commercial-invoice/CI-YYYY-XXXX.pdf` |
| Packing list PDF | `trade-documents/packing-list/PL-YYYY-XXXX.pdf` |
| Delivery note PDF | `trade-documents/delivery-note/DN-YYYY-XXXX.pdf` |
| Shipment document upload | `trade-documents/uploads/{order_ref}/{YmdHis}_{safe-slug}.{ext}` |

**Upload validation (shipment_document type):**
- `file` required, `mimes:pdf,jpg,jpeg,png,xls,xlsx,csv`, `max:20480` (20 MB)
- `type_label` required string max:100 — accepts either `type_label` or `document_label` field name (frontend alias)
- `notes` optional string max:500
- Filename sanitized: `Str::slug(basename, '_')` + timestamp prefix + original extension

**Admin endpoints:**
- `POST /admin/orders/{id}/trade-documents/proforma` — idempotent; 201 new / 200 existing
- `POST /admin/orders/{id}/generate-packing-list` — idempotent; 201 new / 200 existing
- `POST /admin/orders/{id}/generate-delivery-note` — idempotent; 201 new / 200 existing
- `POST /admin/orders/{id}/trade-documents/upload` — upload shipment doc; 201; logs `document_uploaded`
- `POST /admin/orders/{id}/generate-commercial-invoice` — idempotent; 201 new / 200 existing; logs `document_generated`
- `POST /admin/trade-documents/{id}/send-email` — send document by email with file attached; request: `{ recipient_email?: string, message?: string }`; defaults recipient to `order.customer_email`; 422 if no file; 404 if file missing on disk; 500 on mail failure; on success: stamps `sent_at`, logs `document_sent`, returns `{ data: { id, sent_at, recipient_email }, message }`
- `DELETE /admin/trade-documents/{id}` — delete uploaded shipment_document only; 422 if type is not `shipment_document` (generated PDFs are protected); deletes physical file then DB record; logs `document_deleted`
- `GET /admin/orders/{id}/trade-documents` — all docs for the order (all types + statuses)
- `GET /admin/trade-documents/{id}/download` — serves `pdf_path ?? file_path` from private disk; 404 if no file
- All write endpoints require `permission:trade_documents.manage`
- All return `formatDocument()` shape: `{ id, order_id, order_ref, type, type_label, number, status, has_pdf, has_file, original_filename, mime_type, file_size, notes, issued_by, issued_at, sent_at, created_at, updated_at }`
- All generate/upload endpoints write `document_generated` / `document_uploaded` to `order_logs` (try/catch — never blocks)

**Customer-facing filter:** types `proforma`, `commercial_invoice`, `packing_list`, `delivery_note`, `shipment_document` with `status='issued'` are shown via both customer endpoints.

**Customer download fix:** `GET /api/v1/auth/trade-documents/{id}/download` now falls back to `file_path` for uploaded docs (previously only checked `pdf_path`). Returns `original_filename` as the download filename.

**Order response — trade_documents[] shape (customer endpoints):**
```json
{
  "id": 6,
  "type": "delivery_note",
  "type_label": null,
  "number": "DN-2026-0001",
  "status": "issued",
  "has_pdf": true,
  "has_file": false,
  "issued_at": "2026-05-12T...",
  "sent_at": null,
  "original_filename": "DN-2026-0001.pdf",
  "mime_type": null,
  "file_size": null
}
```
For `shipment_document`: `type_label` = "Bill of Lading" etc., `has_pdf=false`, `has_file=true`, `number=null`, `mime_type` and `file_size` populated.

#### GET /admin/logistics/dashboard — response shape
Permission: `orders.view` (super_admin, admin, order_manager, sales_manager)

Query params: `status`, `payment_status`, `country`, `missing_document` (packing_list | commercial_invoice | shipment_document | delivery_note), `risk_level` (high — DB-filtered; medium/low approximate), `reverse_charge_only` (boolean), `date_from`, `date_to`, `per_page` (max 100, default 20)

```json
{
  "data": {
    "summary": {
      "total_active_orders": 42,
      "awaiting_payment": 5,
      "paid_orders": 37,
      "orders_shipped": 18,
      "orders_delivered": 12,
      "missing_packing_list": 3,
      "missing_commercial_invoice": 4,
      "missing_shipment_document": 2,
      "pending_eu_declarations": 1,
      "high_risk_orders": 1
    },
    "checklist": [
      {
        "order_id": 172,
        "order_ref": "OKL-XXXXXX",
        "customer_name": "Hans Müller",
        "customer_email": "hans@example.com",
        "country": "Germany",
        "status": "delivered",
        "payment_status": "paid",
        "is_reverse_charge": true,
        "total": "4850.00",
        "created_at": "2026-05-01T10:00:00+00:00",
        "documents": {
          "proforma": true,
          "commercial_invoice": true,
          "packing_list": true,
          "delivery_note": true,
          "shipment_document": false
        },
        "missing": ["shipment_document"],
        "eu_declaration": {
          "id": 8,
          "status": "signed",
          "signed_at": "2026-05-10T14:22:00+00:00",
          "admin_acknowledged_at": null
        },
        "invoice_number": "INV-2026-0042",
        "invoice_locked": true,
        "risk_level": "high",
        "next_action": "Acknowledge signed EU declaration"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 42,
    "last_page": 3
  },
  "message": "success"
}
```

**Risk level rules:**
- `high` — reverse charge + delivered + declaration not acknowledged (or missing)
- `medium` — declaration signed but not yet acknowledged, OR missing shipment_document/delivery_note
- `low` — missing packing_list or commercial_invoice (paid order)
- `none` — all docs present, compliance complete

**Missing document rules (what triggers each entry in `missing[]`):**
- `packing_list` — `payment_status=paid`
- `commercial_invoice` — `payment_status=paid`
- `shipment_document` — `status` in `[shipped, delivered]`
- `delivery_note` — `status=delivered`

**N+1 prevention:** invoices batch-loaded via `Invoice::whereIn('order_ref', $refs)->keyBy('order_ref')`; trade documents and eu_declarations eager-loaded with the paginated query.

**Controller:** `AdminLogisticsController@dashboard`

---

**Public/customer order response includes `trade_documents[]`:**
- `GET /api/v1/orders/{ref}` (inline in order) — all issued types including delivery_note and shipment_document
- `GET /api/v1/auth/orders/{ref}/trade-documents` (standalone list) — same filter
- Admin `GET /admin/orders/{id}` — full array (all types/statuses/uploads)

### `eu_declarations`
Migration: `2026_05_07_200000_create_eu_declarations_table.php`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_id` | bigint FK | cascade delete → orders |
| `customer_id` | bigint FK | nullable → nullOnDelete → customers |
| `invoice_id` | bigint FK | nullable → nullOnDelete → invoices |
| `order_ref` | varchar(30) | denormalised snapshot — readable after order deletion |
| `company_name` | varchar(200) | snapshot at declaration creation |
| `customer_email` | varchar(255) | indexed snapshot |
| `customer_address` | text | snapshot: address, city, postal_code, country joined |
| `vat_number` | varchar(30) | snapshot |
| `country` | varchar(100) | snapshot |
| `goods_description` | text | auto-generated: "Qty× Brand Name Size" per item |
| `quantity_description` | varchar(300) | auto-generated summary e.g. "Total: 200 pcs across 2 lines" |
| `member_state_of_entry` | varchar(100) | nullable — filled by customer during signing |
| `place_of_entry` | varchar(200) | nullable |
| `month_year_received` | varchar(7) | nullable — MM/YYYY format |
| `self_transported` | tinyint | default 0 — boolean |
| `month_year_transport_ended` | varchar(7) | nullable — MM/YYYY |
| `representative_name` | varchar(200) | nullable |
| `representative_title` | varchar(100) | nullable |
| `signed_name` | varchar(200) | nullable — name as signed |
| `accepted_terms` | tinyint | default 0 — must be true to submit |
| `issue_date` | date | nullable — date declaration was issued |
| `signed_at` | timestamp | nullable — when customer submitted signing form |
| `signature_path` | varchar(500) | **hidden from API** — stored on private disk |
| `pdf_path` | varchar(500) | private disk — `eu-declarations/DECL-OKL-XXXXX.pdf` |
| `status` | enum | `pending`, `signed`, `acknowledged` — default `pending` |
| `admin_acknowledged_at` | timestamp | nullable |
| `admin_acknowledged_by` | bigint | nullable — admin user ID |
| `ip_address` | varchar(45) | **hidden from API** |
| `user_agent` | text | **hidden from API** |
| `created_at` / `updated_at` | timestamp | |

Indexes: `order_ref`, `status`, `customer_email`

**Trigger:** Declaration is created inside `InvoiceService::createForOrder()` — non-blocking, wrapped in try/catch — when `EuDeclarationService::shouldRequireForOrder()` returns true.

**shouldRequireForOrder conditions (all three must be true):**
- `order.is_reverse_charge === true`
- `order.tax_treatment === 'reverse_charge'`
- `(bool) order.vat_valid === true`

**Idempotency:** `EuDeclarationService::createForOrder()` returns existing record if one already exists for the order. If `invoice_id` was not set on the existing record, it is updated.

**Admin endpoints:**
- `GET /admin/eu-declarations` — paginated list; filterable by `status` and `q` (order_ref, company_name, email, vat_number); roles: super_admin, admin, order_manager
- `GET /admin/eu-declarations/{id}` — full detail including `has_signature` and `has_pdf` booleans; `signature_path` itself is never returned
- `GET /admin/eu-declarations/{id}/download` — download signed PDF from private disk; 404 if not signed or file missing
- `POST /admin/eu-declarations/{id}/acknowledge` — mark signed declaration as acknowledged; 409 if status !== `signed`; sets `status='acknowledged'`, `admin_acknowledged_at`, `admin_acknowledged_by`; **releases the linked invoice** (`released_at = now()`); sends `FinalInvoiceReleased` email to customer (non-blocking try/catch)

### `ebay_tokens`
Migration: `2026_05_14_000001_create_ebay_tokens_table`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `marketplace_id` | varchar(20) | default `EBAY_DE` |
| `seller_username` | varchar | nullable — not populated automatically yet |
| `access_token` | text | nullable — **encrypted** via Laravel `encrypted` cast |
| `refresh_token` | text | **encrypted** via Laravel `encrypted` cast |
| `access_token_expires_at` | timestamp | nullable |
| `refresh_token_expires_at` | timestamp | nullable — eBay default ~18 months |
| `scopes` | json | nullable — array of granted scope strings |
| `connected_at` | timestamp | nullable — when OAuth flow completed |
| `last_refreshed_at` | timestamp | nullable — updated on every token refresh |
| `is_active` | boolean | default true — index; only one active token used |
| `created_at` / `updated_at` | timestamp | |

**Token access order:** Cache (hot path, TTL = `expires_in - 60s`) → DB active record → `EBAY_REFRESH_TOKEN` env fallback (legacy only).

### `ebay_listing_logs`
Migration: `2026_05_14_000003_create_ebay_listing_logs_table`

Append-only audit log — no `updated_at`. Records survive product/user deletion (FK nullOnDelete).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `product_id` | bigint FK | nullable → nullOnDelete → products |
| `admin_user_id` | bigint FK | nullable → nullOnDelete → admin_users |
| `sku` | varchar | nullable, indexed |
| `action` | varchar | `publish` \| `publish_failed` \| `remove` \| `remove_failed` \| `sync` \| `sync_failed` \| `refresh_status` \| `refresh_status_failed` |
| `ebay_item_id` | varchar | nullable |
| `ebay_offer_id` | varchar | nullable |
| `status` | varchar | nullable — `active` \| `draft` \| `error` \| `ended` \| `withdrawn` \| `unknown` |
| `error_message` | text | nullable — safe error string |
| `response_code` | smallint unsigned | nullable — HTTP status from eBay |
| `payload_summary` | json | nullable — e.g. `{ "stock": 5 }` |
| `created_at` | timestamp | auto-set, indexed |

**New product columns (Phase EB-2):**
`ebay_offer_id` (varchar, nullable), `ebay_status` (varchar, nullable), `ebay_last_synced_at` (timestamp, nullable), `ebay_sync_error` (text, nullable)

**eBay status values:** `active` | `draft` | `error` | `ended` | `withdrawn` | `unknown`

**eBay status → product field mapping:**
- Publish success → `ebay_status = active`
- Remove success → `ebay_status = withdrawn`
- Sync / refresh failure → `ebay_status = error`
- No offer found on eBay → `ebay_status = ended`
- Status check failed → `ebay_status = unknown`

**Token rotation:** every `getAccessToken()` call that hits eBay's token endpoint persists the new access_token and any rotated refresh_token back to the DB record.

**Model:** `App\Models\EbayToken` — `access_token` + `refresh_token` in `$hidden`; use `getRawOriginal()` if ever needed in service code (the service always reads via the model cast transparently).

---

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
| `cost_price` | decimal(10,2) | nullable — **permanent base/reference price** (Excel import value); never overwritten by sync or promotion recalculation |
| `price` | decimal(10,2) | derived: `ROUND(cost_price * (1 - discount_pct/100), 2)` — recalculated by PromotionPricingService when admin changes discount |
| `price_b2b` | decimal(10,2) | nullable — for Rapid: set equal to `price` by PromotionPricingService; new SyncRapidProducts imports leave this `null` so the service owns it |
| `price_b2c` | decimal(10,2) | nullable — same as `price_b2b` for Rapid products |
| `primary_image` | varchar | nullable, relative path e.g. `products/uuid.jpg` |
| `width` | varchar(10) | nullable |
| `height` | varchar(10) | nullable |
| `rim` | varchar(10) | nullable |
| `load_index` | varchar(10) | nullable |
| `speed_rating` | varchar(5) | nullable |
| `stock` | int | nullable |
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
| `contact_person` | varchar(150) | nullable — purchasing manager / decision-maker |
| `company_name` | varchar(200) | nullable |
| `company_address` | varchar(300) | nullable — company registered/billing street address |
| `company_city` | varchar(100) | nullable |
| `company_postal_code` | varchar(30) | nullable |
| `email` | varchar(255) | indexed |
| `phone` | varchar(50) | nullable |
| `country` | varchar(100) | |
| `tyre_category` | varchar(100) | |
| `brand_preference` | varchar(200) | nullable |
| `tyre_size` | varchar(100) | nullable — legacy single-size field; kept for BC |
| `tyre_condition` | varchar(50) | nullable — `new` or `used` |
| `used_tyre_grade` | varchar(50) | nullable — `grade_a`, `grade_b`, `mixed` |
| `used_tyre_notes` | text | nullable — free-text condition notes |
| `quantity` | varchar(100) | free text — legacy; kept for BC |
| `tyre_items` | json | nullable — multi-row items: `[{"size":"315/80R22.5","quantity":"200"},…]` |
| `budget_range` | varchar(100) | nullable |
| `delivery_location` | varchar(300) | |
| `delivery_timeline` | varchar(100) | nullable |
| `incoterm` | varchar(10) | nullable — `DAP`, `DDP`, `EXW`, `FOB`, `CIF`, `Custom` |
| `incoterm_type` | varchar(30) | nullable — `delivery_terms`, `shipping_terms` |
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

Migration: `2026_05_07_000001_add_rfq_fields_to_quote_requests_table.php` — adds 10 columns above (all nullable, safe to deploy to existing rows).

**Quote attachment upload:**
- Field: `attachment` (multipart/form-data), optional
- Accepted types: `pdf`, `csv`, `xls`, `xlsx` — max 10 MB
- Stored to `storage/app/public/quote-attachments/{uuid}.ext`
- Admin list + detail responses include: `attachment_url` (absolute), `attachment_name`, `attachment_original_name`, `attachment_mime`, `attachment_size`, `has_attachment` — all null/false when no file attached

**Quote tyre items:**
- Legacy single-row: `tyre_size` + `quantity` — still accepted, required for BC
- Multi-row: `tyre_items` JSON array — each entry `{ "size": string, "quantity": string }`
- Both can coexist; admin reads `tyre_items` for complex RFQs, `tyre_size`/`quantity` for simple ones
- `QuoteRequest` model casts `tyre_items` as `array`

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
| `tax_treatment` | varchar(30) | nullable — `standard`, `reverse_charge`, `exempt` |
| `tax_rate` | decimal(5,2) | nullable — e.g. `19.00` |
| `tax_amount` | decimal(10,2) | nullable — computed: `taxable_base × tax_rate / 100` |
| `is_reverse_charge` | tinyint | default 0 — true for EU B2B with valid VAT |
| `admin_notes` | text | nullable |
| `ip_address` | varchar(45) | nullable, hidden from API |

**Order mode values:**
- `live` — Stripe checkout orders
- `manual` — Wix imported orders, organic manual orders, and quote-converted orders

**Order model relationships:**
- `items()` — `HasMany OrderItem`
- `euDeclaration()` — `HasOne EuDeclaration`
- `tradeDocuments()` — `HasMany TradeDocument` ordered by `created_at DESC`
- `quoteRequest()` — `HasOne QuoteRequest`

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
| `action` | enum | `status_changed`, `cancelled`, `deleted`, `tracking_updated`, `payment_status_changed`, `document_generated`, `document_uploaded`, `document_deleted`, `document_sent` |
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

### POST /api/v1/payments/tax-preview
Public endpoint (no auth required; authenticated customer token is optional).
Returns the tax breakdown that will be applied at checkout — no order or Stripe session is created.

Rate limit: `throttle:payments` (20/min). Controller: `PaymentController@taxPreview`.

Request:
```json
{
  "items": [{ "price": 29.40, "quantity": 1 }],
  "delivery_cost": 0,
  "country": "Germany",
  "vat_number": "DE118716043",
  "vat_valid": true,
  "customer_type": "b2b"
}
```

VAT validity resolution order:
1. `vat_valid` boolean provided → use it (skip VIES call)
2. `vat_number` provided, no `vat_valid` → call `VatValidationService::validate()`
3. Neither → `null` (B2C safe default)

Customer type resolution:
- Authenticated `auth.customer` Bearer token → `customer.customer_type` wins
- Else `request.customer_type`
- Else `null` (treated as B2C)

Response (200):
```json
{
  "data": {
    "subtotal_net": 29.40,
    "delivery_cost": 0.00,
    "tax_rate": 19.00,
    "tax_amount": 5.59,
    "tax_treatment": "standard",
    "is_reverse_charge": false,
    "total": 34.99,
    "note": null
  }
}
```

---

### Stripe Checkout Payment Gateway
- Active gateway: Stripe Checkout.
- Package: `stripe/stripe-php`.
- Config: `config/services.php` → `stripe.secret`, `stripe.webhook_secret`, `stripe.currency`.
- Env vars: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY`.
- `StripeService::createCheckoutSession(array $orderData): array` — used by `POST /payments/create-session` (cart checkout).
- `StripeService::createCheckoutSessionForOrder(Order $order): array` — used by quote-to-order conversion (when `payment_method=stripe`) and customer Pay Now endpoint. Builds line items from `$order->items`, adds delivery row if `delivery_cost > 0`, adds VAT line item if `tax_amount > 0`.

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
    - `checkout.session.completed` → `payment_status=paid`, `status=confirmed`, creates invoice record + generates PDF, sends `OrderConfirmation` (with invoice number for non-RC orders) to customer + `OrderReceived` to `ORDER_EMAIL`
    - `payment_intent.payment_failed` → `payment_status=failed`, `status=cancelled`
    - `charge.refunded` → `payment_status=refunded`

**Invoice auto-creation (Stripe path):**
- Triggered inside `markOrderPaid()` after the order is confirmed
- Only created if a `Customer` account exists for `order.customer_email` — guest checkouts produce no invoice
- Invoice number: `INV-YYYY-NNNN` — sequence generated inside `DB::transaction` with `lockForUpdate()` on all same-year rows to prevent race conditions on concurrent webhook retries
- PDF generated immediately via `barryvdh/laravel-dompdf` (v3.1.2) and stored to `storage/app/public/invoices/{invoice_number}.pdf`
- `invoices.pdf_url` stores the **relative** path; `GET /api/v1/auth/invoices` returns an **absolute URL** via `url(Storage::url($path))`
- `released_at`: set to `now()` for non-reverse-charge orders (immediately visible); set to `null` for reverse-charge orders (gated until admin acknowledges EU Entry Certificate)
- Idempotency: if invoice record already exists with `pdf_url` set → return early; if `pdf_url` is null → skip record creation, re-run PDF generation only
- PDF failure is non-blocking — logged as warning; invoice record is still returned and email still sent
- Recovery: run `php artisan invoices:generate-missing-pdfs` to backfill any invoices where PDF generation failed

**Reverse-charge invoice visibility (Phase 2C-3):**
- Reverse-charge invoices are generated at payment time but `released_at = null`
- Customer cannot see invoice in list or download until admin acknowledges the EU Entry Certificate
- `GET /api/v1/auth/invoices` filters `WHERE released_at IS NOT NULL`
- `GET /api/v1/invoices/{id}/download` returns 423 (Locked) if `released_at IS NULL`
- Admin `POST /admin/eu-declarations/{id}/acknowledge` sets `released_at = now()` and sends `FinalInvoiceReleased` email

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

### Transactional Emails

| Mailable | Trigger | Recipient | View |
|----------|---------|-----------|------|
| `OrderConfirmation` | Stripe webhook `checkout.session.completed` | customer | `emails/order-confirmation.blade.php` |
| `OrderReceived` | Stripe webhook `checkout.session.completed` | `ORDER_EMAIL` | `emails/order-received.blade.php` |
| `QuoteConvertedToOrder` | Admin converts quote to order | `quote.email` | `emails/quote-converted-to-order.blade.php` |
| `QuoteRequestReceived` | Customer submits quote (`POST /quote-requests`) | `QUOTE_EMAIL` | `emails/quote-request-received.blade.php` |
| `QuoteRequestAcknowledgement` | Customer submits quote (`POST /quote-requests`) | submitter's email | `emails/quote-request-acknowledgement.blade.php` |
| `AdminWelcome` | Super admin creates new admin user | new admin | `emails/admin-welcome.blade.php` |
| `CustomerEmailVerification` | Customer registers | customer | `emails/customer-verify-email.blade.php` |
| `CustomerPasswordReset` | Customer requests password reset | customer | `emails/customer-reset-password.blade.php` |
| `FinalInvoiceReleased` | Admin acknowledges EU Entry Certificate | customer | `emails/final-invoice-released.blade.php` |
| `TradeDocumentEmail` | Admin sends trade document (`POST /admin/trade-documents/{id}/send-email`) | `recipient_email` (default: `order.customer_email`) | `emails/trade-document-email.blade.php` |

- Manual order flow (`POST /api/v1/orders`) sends `OrderReceived` to admin only — no customer email on manual orders
- **Reverse-charge OrderConfirmation:** invoice block is suppressed (invoice null passed) — instead shows amber notice: "Your final invoice will be available after the EU Entry Certificate is signed."
- All sends are synchronous (`QUEUE_CONNECTION=sync`)
- All failures are wrapped in try/catch — email failure never rolls back the primary action
- Failures logged as `Log::error` (survives `LOG_LEVEL=error` on production)
- Config keys (not env() calls): `config('mail.quote_email')` → `QUOTE_EMAIL`, `config('mail.order_email')` → `ORDER_EMAIL`

**FinalInvoiceReleased email:**
- Subject: `Your final invoice is ready — {order_ref}`
- HTML view: `emails/final-invoice-released.blade.php` — orange accent, invoice details table, CTA button to `/account/invoices`
- Plain-text: `emails/final-invoice-released-text.blade.php`
- Variables: `declaration` (EuDeclaration), `invoice` (Invoice), `invoicesUrl` (frontend invoices page), `downloadUrl` (API download route)
- Compliance note: explains §6a UStG reverse-charge zero-rating

**QuoteRequestReceived (admin notification):**
- Subject: `New quote request — {ref_number}`
- Plain-text fallback: `emails/quote-request-received-text.blade.php`
- Reply-To header: customer's email — admin can reply directly from inbox
- Four labelled sections:
  - **CONTACT:** full_name, contact_person, company_name, company_address/city/postal_code, email, phone, country, business_type, vat_number with VERIFIED/NOT VERIFIED badge (green/red)
  - **TYRE REQUEST:** category, brand_preference, tyre_condition, used_tyre_grade/used_tyre_notes (only when condition=used), tyre_items loop with `@foreach`; falls back to legacy tyre_size/quantity when tyre_items is empty
  - **LOGISTICS:** delivery_location, delivery_address/city/postal_code, delivery_timeline, incoterm + incoterm_type, budget_range
  - **NOTES/ATTACHMENT:** notes, attachment name if present
- Sent to: `config('mail.quote_email')` — skipped silently if not set

**QuoteRequestAcknowledgement (customer auto-reply):**
- Subject: `We received your quote request — {ref_number}`
- Plain-text fallback: `emails/quote-request-acknowledgement-text.blade.php`
- "Your request summary" section: ref_number, submitted timestamp, tyre_category, brand_preference, tyre_condition, tyre_items loop (same pattern — falls back to legacy), delivery_location, incoterm, delivery_timeline, vat_number (if provided)
- Does NOT expose: business_type, company_address, contact_person, budget_range (internal fields)
- "What happens next?" 3-step block: review → quote → order

**QuoteConvertedToOrder email:**
- Subject: `Your quote has been converted to an order — {order_ref}`
- Shows quote_ref + order_ref, date, payment method, amber Pending badge
- Full items table with tax breakdown: Subtotal (net) / Delivery (if > 0) / VAT row / Total gross — guarded by `$order->tax_treatment !== null`; old orders (null) show total only
- If `$checkoutUrl` is set (Stripe orders): "Pay securely with Stripe" CTA button + Stripe next steps
- If no `$checkoutUrl` (bank transfer): bank transfer next steps block
- Sent to: `quote.email`

**OrderConfirmation email:**
- Tax breakdown in tfoot: Subtotal (net) / Delivery / VAT / Total — guarded by `$order->tax_treatment !== null`
- Optional invoice block (invoice number + link to `/account/invoices`) — omitted if invoice is null (guest checkout or reverse-charge order)
- Reverse-charge paid orders: amber compliance notice explaining invoice will be released after EU Entry Certificate signing + admin acknowledgement

**All email templates:**
- Plain transactional HTML — white background, 3px orange top border, no image assets
- Contact footer: `support@okelcor.com`
- Env vars required: `ORDER_EMAIL=support@okelcor.com`, `QUOTE_EMAIL=support@okelcor.com`

### Tax / VAT Calculation (TaxService)
- `App\Services\TaxService::calculate(?string $country, ?bool $vatValid, ?string $customerType = null): array`
- Returns: `['tax_treatment' => string, 'tax_rate' => float, 'is_reverse_charge' => bool, 'note' => string]`

**Decision tree:**
| Country | Customer | VAT valid? | Treatment | Rate |
|---------|----------|-----------|-----------|------|
| Germany (`DE`) | any | any | `standard` | 19% |
| EU member | B2B | yes | `reverse_charge` | 0% |
| EU member | B2B/B2C | no/null | `standard` | 19% |
| Non-EU | any | any | `exempt` | 0% |
| Unknown/null | any | any | `exempt` | 0% (safe default) |

- EU detection: `TaxService::isEu(?string $country): bool` — covers all 27 EU states + `XI` (Northern Ireland)
- EU excl. Germany: `TaxService::isEuCountryExceptGermany(?string $country): bool` — same but excludes `DE`
- EU VAT requirement check: `TaxService::requiresEuVat(?string $country, ?string $customerType): bool` — returns true when B2B + EU non-DE
- Country normalisation: `TaxService::resolveCountryCode(string $country): ?string` — accepts ISO codes, English names, German names (e.g. `"Deutschland"` → `"DE"`)
- `null` country → `exempt` (safe, avoids under-collection)
- `null` customerType → treated as B2C (standard rate, safe default)
- Wired into: `PaymentController::createSession()` (Stripe cart checkout) and `AdminQuoteRequestController::convertToOrder()` (quote conversion)
- Tax fields stored on `orders`: `tax_treatment`, `tax_rate`, `tax_amount`, `is_reverse_charge`
- Tax fields copied to `invoices` on creation: `subtotal_net`, `tax_treatment`, `tax_rate`, `tax_amount`, `is_reverse_charge`
- Invoice PDF (`pdf/invoice.blade.php`) and order confirmation / quote-converted emails show full tax breakdown

### EU Entry Certificate — Gelangensbestätigung (§17a UStDV)

Required for all reverse-charge EU B2B orders where `is_reverse_charge=true` and VAT is validated. This is the German legal proof that goods actually arrived in the EU member state (Gelangensbestätigung).

**Workflow:**
1. Customer places order → payment confirmed → `InvoiceService::createForOrder()` fires
2. Invoice created with `released_at = null` (reverse-charge gating)
3. `EuDeclarationService::shouldRequireForOrder()` checks the three conditions → if true, `createForOrder()` creates the `eu_declarations` record (status=`pending`)
4. Frontend shows a "Complete your declaration" banner on the customer account order page **only when** `declaration_required=true && declaration_status='pending' && payment_status='paid' && order.status='delivered'`
5. Customer clicks → goes to signing form at `/account/orders/{ref}/declaration` (Next.js page)
6. Customer fills & signs → `POST /api/v1/auth/orders/{ref}/declaration`
7. Backend validates guards, saves signing fields, stores signature PNG, generates PDF → status=`signed`; invoice `released_at` is **NOT** set here
8. Admin reviews via `GET /admin/eu-declarations` → marks acknowledged via `POST /admin/eu-declarations/{id}/acknowledge` → status=`acknowledged`; **sets `invoice.released_at = now()`**; sends `FinalInvoiceReleased` email to customer
9. Customer can now see and download their invoice

**Phase 2B-2 (DONE):** Migration, `EuDeclaration` model, `EuDeclarationService` (create + should-require logic), `AdminEuDeclarationController` (list + show), declaration fields wired into admin and public order detail responses.

**Phase 2B-3 (DONE):** Customer signing endpoint `POST /auth/orders/{ref}/declaration`, `SignEuDeclarationRequest` FormRequest, signature PNG stored to private disk, DomPDF PDF generation, `EuDeclarationSigned` mailable (HTML + plain-text), admin download `GET /admin/eu-declarations/{id}/download`, admin acknowledge `POST /admin/eu-declarations/{id}/acknowledge`, customer download `GET /auth/orders/{ref}/declaration/download`, public/customer order response updated with `declaration_signed_name` + `declaration_download_available`.

**Phase 2C-3 (DONE):** Invoice release gating via `released_at` column. Admin acknowledge now releases invoice and sends `FinalInvoiceReleased` email. **Compliance rule (final):** signing the declaration does NOT release the invoice — only admin acknowledgement does. Applies equally to Stripe and bank_transfer. Non-RC invoices are released immediately at payment.

**Signing endpoint — `POST /api/v1/auth/orders/{ref}/declaration`:**
- Auth: `auth.customer` Bearer token
- Ownership: `order.customer_email === customer.email` (case-insensitive); 404 if no match (does not leak order existence)
- **Missing row auto-create:** if no `eu_declarations` row exists for the order (common for orders placed before Phase 2B-2 was deployed), `EuDeclarationService::shouldRequireForOrder()` is called; if the order qualifies (all three conditions met) the row is created on the spot and signing continues; if the order does NOT qualify returns 422 `"This order does not require an EU entry certificate."`
- Guards:
  - 404 — order not found or wrong customer
  - 409 — declaration already signed or acknowledged
  - 422 — order does not require a declaration
  - 422 `"Payment must be confirmed before the EU Entry Certificate can be signed."` — `order.payment_status !== 'paid'`
  - 422 `"The EU Entry Certificate can only be signed after the order has been delivered."` — `order.status !== 'delivered'`
  - 422 — validation failure (FormRequest)
- Stores signature PNG to `storage/app/private/eu-declarations/signatures/{uuid}.png`
- Generates PDF to `storage/app/private/eu-declarations/pdf/{order_ref}.pdf` via DomPDF — non-blocking (failure logged, 200 still returned)
- Sends `EuDeclarationSigned` mailable to `declaration.customer_email` — non-blocking
- Sets `declaration.status = 'signed'`; does NOT release the invoice (`released_at` stays null)
- Returns 200: `{ status, signed_at, order_ref, has_pdf }`

**Customer download — `GET /api/v1/auth/orders/{ref}/declaration/download`:**
- Auth: `auth.customer` Bearer token; ownership verified same as above
- 404 if declaration not signed/acknowledged; 404 if pdf_path null or file missing on disk
- Returns file: `DECL-{order_ref}.pdf`

**Admin download — `GET /api/v1/admin/eu-declarations/{id}/download`:**
- Auth: `auth:sanctum` + `admin.role:super_admin,admin,order_manager`
- 404 if not signed/acknowledged; 404 if pdf missing; returns `DECL-{order_ref}.pdf`

**Admin acknowledge — `POST /api/v1/admin/eu-declarations/{id}/acknowledge`:**
- Auth: `auth:sanctum` + `admin.role:super_admin,admin,order_manager`
- 409 if status !== `signed`; sets `status='acknowledged'`, `admin_acknowledged_at`, `admin_acknowledged_by`
- Finds linked invoice: `$decl->invoice ?? Invoice::where('order_ref', $decl->order_ref)->first()`
- Sets `$invoice->released_at = now()` (makes invoice visible to customer)
- Sends `FinalInvoiceReleased` email to `declaration.customer_email` (non-blocking try/catch)
- Returns updated declaration detail

**Back-fill in order detail — `GET /api/v1/orders/{ref}` (show only, not list):**
- After loading the order, if `is_reverse_charge=true` and `euDeclaration` relation is null, calls `EuDeclarationService::createForOrder()` to create the pending row immediately
- `setRelation()` sets it on the in-memory model so the response always returns `declaration_status='pending'`
- The list endpoint (`GET /api/v1/orders?email=`) does NOT auto-create (would create rows for all old orders)

**Files created/modified (Phase 2B-3 + 2C-3 + compliance adjustment):**
- `app/Http/Requests/SignEuDeclarationRequest.php` ← new
- `app/Http/Controllers/EuDeclarationController.php` ← new; payment/delivery guards added; invoice release removed (moved to admin acknowledge)
- `resources/views/pdf/eu-declaration.blade.php` ← new — §17a UStDV Gelangensbestätigung layout
- `app/Mail/EuDeclarationSigned.php` ← new
- `resources/views/emails/eu-declaration-signed.blade.php` ← new
- `resources/views/emails/eu-declaration-signed-text.blade.php` ← new
- `app/Mail/FinalInvoiceReleased.php` ← new (Phase 2C-3)
- `resources/views/emails/final-invoice-released.blade.php` ← new (Phase 2C-3)
- `resources/views/emails/final-invoice-released-text.blade.php` ← new (Phase 2C-3)
- `app/Http/Controllers/Admin/AdminEuDeclarationController.php` ← added `download()` + `acknowledge()` + invoice release + FinalInvoiceReleased email
- `app/Http/Controllers/OrderController.php` ← injected `EuDeclarationService`; added declaration fields + trade_documents to `formatOrder()`; back-fill in `show()`
- `routes/api.php` ← added 9 new routes (2 EU declaration customer + 2 EU declaration admin + 5 trade document routes)

**Storage — private disk:**
- `local` disk root: `storage_path('app/private')`
- Signatures: `storage/app/private/eu-declarations/signatures/{uuid}.png`
- PDFs: `storage/app/private/eu-declarations/pdf/{order_ref}.pdf`
- Physical path for serving: `storage_path('app/private/' . $decl->pdf_path)`

**Service:** `App\Services\EuDeclarationService`
- `shouldRequireForOrder(Order $order): bool` — all three conditions: `is_reverse_charge=true`, `tax_treatment='reverse_charge'`, `(bool)vat_valid=true`
- `createForOrder(Order $order, ?Invoice $invoice = null): EuDeclaration` — idempotent; called by `InvoiceService` at payment time AND on-demand by `EuDeclarationController` and `OrderController::show()` for pre-2B-2 orders
- `buildGoodsDescription(Order $order): [string, string]` — returns [goods_description, quantity_description]; quantity_description truncated to 300 chars

**Controllers injecting `EuDeclarationService`:**
- `EuDeclarationController` — via constructor; used to auto-create missing declaration before signing
- `OrderController` — via constructor; used to back-fill missing row in `show()` only

### Trade Documents — Generated + Uploaded (Phase 2C-1/2/3)

Trade documents are commercial shipping/customs documents distinct from tax invoices (INV-YYYY-NNNN).

**Supported types:**
| Type | Number prefix | Generated/Uploaded | Customer visible |
|------|--------------|-------------------|-----------------|
| `proforma` | PI- | Generated (DomPDF) | Yes |
| `commercial_invoice` | CI- | Generated (DomPDF) | Yes |
| `packing_list` | PL- | Generated (DomPDF) | Yes |
| `delivery_note` | DN- | Generated (DomPDF) | Yes |
| `shipment_document` | none | Uploaded by admin | Yes |

**Key files:**
- `app/Models/TradeDocument.php` — `$hidden = ['pdf_path', 'file_path']`; use `getRawOriginal()` in controllers
- `app/Services/TradeDocumentService.php` — five idempotent generate methods; `PREFIXES` array defines number format
- `resources/views/pdf/proforma-invoice.blade.php` — proforma DomPDF template
- `resources/views/pdf/commercial-invoice.blade.php` — commercial invoice DomPDF template (export notice, trade terms bar, customs declaration, sig blocks)
- `resources/views/pdf/packing-list.blade.php` — packing list DomPDF template (items + weight block + sig blocks)
- `resources/views/pdf/delivery-note.blade.php` — delivery note DomPDF template (receipt confirmation + EU RC Gelangensbestätigung notice)
- `app/Http/Controllers/Admin/AdminTradeDocumentController.php` — all admin endpoints (generate/upload/delete/download/list)
- `app/Http/Controllers/TradeDocumentController.php` — customer list + download endpoints

### Rapid Product Pricing — Auto-Recalculation (Session 8)

Rapid-brand products use a cost-plus discount model. The fields are:
- `cost_price` — permanent base (Excel import value). Never overwritten after initial sync.
- `price` — derived: `ROUND(cost_price * (1 - discount_pct/100), 2)`
- `price_b2b` / `price_b2c` — set equal to `price` for Rapid (no B2B/B2C tier differentiation).

**PromotionPricingService** (`app/Services/PromotionPricingService.php`):
- `recalculateForPromotion(Promotion $promotion): int` — bulk-updates all products where `brand = $promotion->brand_name` and `cost_price IS NOT NULL` and `deleted_at IS NULL`.
- Uses a single `DB::table()->update()` with `DB::raw("ROUND(cost_price * {$factor}, 2)")` for `price`, `price_b2b`, and `price_b2c`.
- Returns count of updated rows.

**Hook in AdminPromotionController:**
- On `PUT /admin/promotions/{id}`, if `discount_pct` changed AND `brand_name` is set → PromotionPricingService fires automatically.
- Response includes `recalculated_products` count when recalculation ran.

**Migration history (session 8 — all ran on production):**
- `2026_05_11_140000` — backfill `cost_price` from `price` for Rapid; then try to apply discount. **Silent fail** — the migration had a guard (`if (! $promo) return;`) that short-circuited when the promotion lookup returned nothing. Migration was recorded as run but did nothing.
- `2026_05_11_150000` — hardcoded 0.65 factor (35% discount), no promotion lookup dependency; updated `price` only for Rapid products. **First version** ran before price_b2b/b2c were added to the UPDATE.
- `2026_05_11_160000` — final fix: `SET price_b2b = price, price_b2c = price WHERE brand = 'Rapid' AND deleted_at IS NULL`. Aligned all three price fields.

**Current state (production):** All 37 Rapid products have `price = price_b2b = price_b2c = ROUND(cost_price * 0.65, 2)` (35% off). Frontend `resolvePrice()` is field-selection only (picks between price_b2b / price_b2c / price by customer type) — no client-side arithmetic.

**SyncRapidProducts command change:** `createProduct()` now sets `price_b2b = null`, `price_b2c = null` for new rows. This prevents future Excel syncs from overwriting the promotion-calculated values with raw supplier prices.

---

### Incoterms / Delivery Terms — FOB-First Model (Session 8)

Replaced all hardcoded `"CIF"` defaults with a professional FOB-first logistics model across 8 files.

**Single source of truth:** `config('payment.bank_transfer.delivery_term')` = `"Incoterms 2020: FOB Germany unless otherwise agreed in writing."`

**Incoterm formatting rule** (used in proforma PDF + quote-converted email when `$quote->incoterm` is set):
```php
match(strtoupper($quote->incoterm)) {
    'FOB'    => 'Incoterms 2020: FOB Germany',
    'CIF'    => 'Incoterms 2020: CIF destination port — freight and insurance included to destination port.',
    default  => 'Incoterms 2020: ' . strtoupper($quote->incoterm),
}
```

**Valid incoterm values** (`StoreQuoteRequestRequest`): `DAP`, `DDP`, `EXW`, `FOB`, `CIF`, `Custom`

**Files changed:**
- `config/payment.php` — `delivery_term` updated to full FOB string
- `app/Http/Requests/StoreQuoteRequestRequest.php` — added `Custom` to `in:` validation rule
- `resources/views/pdf/invoice.blade.php` — label: "Delivery Term" → "Delivery / Shipping Terms"
- `resources/views/pdf/proforma-invoice.blade.php` — full incoterm formatting with `match()`; removed hardcoded `'CIF'` fallback second argument from `config()` call
- `resources/views/emails/order-confirmation.blade.php` — label renamed; uses config fallback (no `$quote` available)
- `resources/views/emails/order-confirmation-text.blade.php` — same
- `resources/views/emails/quote-converted-to-order.blade.php` — label renamed; incoterm formatting if `$quote->incoterm` set, else config fallback
- `resources/views/emails/quote-converted-to-order-text.blade.php` — same in plain text

---

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
- Actions logged: `status_changed` (any status transition including quote conversion), `cancelled` (status set to cancelled), `tracking_updated` (any of carrier/tracking_number/container_number/estimated_delivery/eta), `deleted` (hard delete before records removed), `document_generated` (proforma/packing_list/delivery_note/commercial_invoice created), `document_uploaded` (shipment_document file uploaded), `document_deleted` (shipment_document file deleted), `document_sent` (any trade document sent by email via `POST /admin/trade-documents/{id}/send-email`)

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

### EU VAT Enforcement (PHASE 2A-1)
**Rule:** B2B customers in EU countries outside Germany must supply a VIES-validated VAT number. Germany is domestic (19% standard regardless). Non-EU is exempt (no VAT required).

**Enforced on:**
| Endpoint | How customer_type is resolved |
|---|---|
| `POST /api/v1/payments/create-session` | Auth token wins; falls back to request `customer_type` field |
| `POST /api/v1/quote-requests` | Auth token wins; then request `customer_type`; then inferred from `company_name` presence |
| `PUT /api/v1/auth/profile` | Always from `customer.customer_type` DB field (cannot be changed in profile update) |

**Error responses (422):**
```json
{ "message": "A valid EU VAT number is required for business purchases in EU member states.",
  "errors": { "vat_number": ["A valid EU VAT number is required for business purchases in EU member states."] } }
```
If VAT number is present but VIES validation fails:
```json
{ "errors": { "vat_number": ["Your VAT number could not be validated. Please check it and try again."] } }
```

**Scenario table:**
| Country | customer_type | vat_number | Result |
|---|---|---|---|
| France | b2b | missing | ❌ 422 — required |
| France | b2b | invalid VIES | ❌ 422 — not validated |
| France | b2b | valid VIES | ✅ passes; reverse charge applied |
| Germany | b2b | missing | ✅ passes; 19% standard |
| France | b2c | missing | ✅ passes; 19% standard |
| USA | b2b | missing | ✅ passes; exempt |

**Profile update behaviour:** check runs against effective post-update state. Updating phone/name only while already holding a valid EU VAT always passes. Clearing the VAT number for a B2B EU customer is rejected.

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
carrier, carrier_type, tracking_number, container_number, estimated_delivery, eta,
created_at, items[], shipment_events[],
declaration_required, declaration_status, declaration_signed_at,
declaration_signed_name, declaration_download_available,
trade_documents[]
```
- `declaration_required` — `true` when `order.is_reverse_charge === true`; always present
- `declaration_status` — `"pending"` | `"signed"` | `"acknowledged"` | `null`
  - `GET /api/v1/orders/{ref}` (single-order show): if `declaration_required=true` and no row exists, a pending row is **auto-created** so `declaration_status` is always `"pending"`, never `null`, for qualifying orders
  - `GET /api/v1/orders?email=` (list): no auto-create; `declaration_status` may be `null` for old pre-2B-2 orders
- `declaration_signed_at` — ISO 8601 timestamp or `null`
- `declaration_signed_name` — signed name in capitals or `null` until signed
- `declaration_download_available` — `true` when `pdf_path` is set and `status` is `signed` or `acknowledged`
- `trade_documents[]` — issued docs (types: `proforma`, `commercial_invoice`, `packing_list`, `delivery_note`, `shipment_document`); shape: `{ id, type, type_label, number, status, has_pdf, has_file, issued_at, sent_at, original_filename, mime_type, file_size }`

Admin order detail (`GET /admin/orders/{id}`) additionally returns:
- `declaration_id` — the EU declaration record ID (needed to fetch/manage declaration as admin)
- `trade_documents[]` — all docs for the order (all types + statuses, including uploads)

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
- Locked (423): `{ "message": "Invoice is not available until the EU Entry Certificate is signed." }` — reverse-charge invoice before admin acknowledgement
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
| `invoices.pdf_url` | relative: `invoices/INV-YYYY-NNNN.pdf` | served via `/api/v1/invoices/{id}/download` (auth.customer); 423 if not yet released |
| `quote_requests.attachment_path` | relative: `quote-attachments/uuid.ext` | absolute URL — admin only |
| `eu_declarations.signature_path` | relative: `eu-declarations/uuid.png` | **private disk** — never returned raw in API; `has_signature` boolean returned instead |
| `eu_declarations.pdf_path` | relative: `eu-declarations/DECL-OKL-XXXXX.pdf` | **private disk** — served via authenticated download endpoint |
| `trade_documents.pdf_path` | relative: e.g. `trade-documents/proforma/PI-2026-0001.pdf` | **private disk** — served via admin + customer download endpoints |
| `trade_documents.file_path` | relative: `trade-documents/uploads/{order_ref}/{ts}_{slug}.ext` | **private disk** — served via admin + customer download endpoints; customer download added in Phase 2C-3 |

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
| `payments` | 20/min | `POST /payments/create-session`, `POST /payments/tax-preview` |
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
| `products:sync-rapid {file}` | Import/upsert Rapid products from Excel; new rows created with `price_b2b=null`, `price_b2c=null` so PromotionPricingService owns those values |
| `products:assign-rapid-images` | Copy `Image 1.png` / `Image 2.png` from storage and assign to all Rapid products that are missing images |
| `orders:cleanup-test-stripe --date=YYYY-MM-DD --email=EMAIL --dry-run` | Delete test Stripe orders by date + email (always dry-run first) |
| `orders:delete-specific [--dry-run]` | Delete 9 specific hard-coded test order refs — dry-run first, then run without flag |
| `orders:cleanup-test-data [--dry-run] [--force]` | Delete 10 specific hard-coded test order refs + all related data (items, invoices, declarations, trade docs, logs, files) — always dry-run first |
| `invoices:generate-missing-pdfs [--dry-run] [--invoice=INV-YYYY-NNNN]` | Generate PDF files for invoices where `pdf_url IS NULL`; dry-run lists affected rows without writing |

**`orders:cleanup-test-data` detail:**
- Hard-coded refs: `OKL-14CVV2C`, `OKL-1303SMU`, `OKL-13180T5`, `OKL-YOTFQM`, `OKL-XW6LHC`, `OKL-1FES6QA`, `OKL-1A8IOAI`, `OKL-VDUWAD`, `OKL-1M84OQ9`, `OKL-1CDIP0E`
- FK-safe deletion sequence: nullify `quote_requests.order_id` → delete `order_logs` (by order_ref) → `order_shipment_events` → `order_items` → `EuDeclaration` → `TradeDocument` → `Invoice` → `Order`
- File cleanup AFTER DB transaction: public disk (invoice PDFs) + local/private disk (declaration PDFs, signatures, trade document files)
- Customer accounts are NOT deleted
- `--dry-run` shows preview table with counts; `--force` skips confirmation prompt

---

## Pending / Not Yet Built

| Item | Notes |
|------|-------|
| Phase EB-1 — eBay OAuth & Token Stability | **DONE** — `ebay_tokens` table; encrypted token storage; callback handler; refresh_token rotation; status + disconnect endpoints; `.env` fallback preserved |
| Phase EB-2 — Listing Status Tracking & Logs | **DONE** — 4 new product columns (`ebay_offer_id`, `ebay_status`, `ebay_last_synced_at`, `ebay_sync_error`); `ebay_listing_logs` table; all publish/remove/sync/refresh operations log to DB; `refresh-status` endpoint; `logs` endpoint with filters; safe error messages to frontend |
| Phase EB-3 — Price/Title Update Sync & Enhanced Validation | **DONE** — `updateListing()` + `PATCH /products/{id}/ebay/update`; `syncFull()` replaces `syncInventory()` in sync-all (full field sync); `guardProduct()` expanded with 7 new checks (connection, title, stock, image URL, marketplace/category config); `validation_failed` log action; 8 new `safeError()` patterns |
| Phase EB-4 — Settings Readiness Checklist | **DONE** — `GET /ebay/readiness` (12-check list: credentials, connection, marketplace/category/policies, seller location, env flag, live token test); `POST /ebay/test-connection` (pings Inventory API); `GET /ebay/policies` (fetches business policy IDs+names from eBay Account API); `EBAY_SELLER_POSTAL_CODE` + `EBAY_SELLER_LOCATION` config keys added |
| eBay production credentials | Rotate `EBAY_CLIENT_SECRET` (exposed in prior session). Set `EBAY_RU_NAME`. Register callback URL `https://api.okelcor.com/api/v1/admin/ebay/callback` in eBay Developer Portal. Set `EBAY_ENVIRONMENT=production`. |
| Adyen approval | Legacy/inactive until business account/API credentials are approved |
| `GET /admin/products?trashed=only` | Restore works but no dedicated trashed product list endpoint |
| Admin customer edit/deactivate | GET /admin/customers list exists; no PUT/DELETE per customer yet |
| Phase 2C-1 — Packing List | **DONE** — `PL-YYYY-XXXX` sequential numbers, DomPDF template, admin endpoint, customer whitelist |
| Phase 2C-2 — Delivery Note | **DONE** — `DN-YYYY-XXXX` sequential numbers, DomPDF template with EU reverse-charge notice, admin endpoint, customer whitelist |
| Phase 2C-3 — Shipment Document Uploads | **DONE** — `POST upload` + `DELETE` endpoints, private disk storage, `type_label` column, customer whitelist; accepts `document_label` or `type_label` field |
| Phase 2C-4 — Commercial Invoice | **DONE** — `CI-YYYY-XXXX` sequential numbers, DomPDF template (export notice, trade terms bar, customs declaration, sig blocks), admin endpoint, customer whitelist |
| Phase 2C-5 — Send Trade Document by Email | **DONE** — `POST /admin/trade-documents/{id}/send-email`; `TradeDocumentEmail` mailable with file attachment; `document_sent` OrderLog action; migration extends order_logs enum |
| Phase 2C-6 — Logistics Dashboard | **DONE** — `GET /admin/logistics/dashboard`; 10-metric summary; paginated order checklist; `missing[]`, `risk_level`, `next_action`, `eu_declaration` state; batch-loaded invoices; filters: status, payment_status, country, missing_document, risk_level=high, reverse_charge_only, date_from, date_to |
| Invoice release gating | **DONE** — `released_at` column, 423 on locked download, admin acknowledge releases invoice + email |
| Rapid product auto-pricing | **DONE** — `cost_price` base, PromotionPricingService, AdminPromotionController hook; price/price_b2b/price_b2c all aligned |
| Incoterms FOB-first model | **DONE** — config, PDF templates, emails updated; `Custom` added as valid incoterm; label renamed to "Delivery / Shipping Terms" everywhere |

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

# Quote notifications — admin receives email here after every quote request submission
QUOTE_EMAIL=support@okelcor.com

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
