Action required: EU entry certificate pending — {{ $declaration->order_ref }}

We have not yet received your signed EU entry certificate (Gelangensbestätigung)
for order {{ $declaration->order_ref }}.

This document is required under § 17a UStDV to confirm that goods have been
transported to another EU member state.

ORDER REFERENCE:        {{ $declaration->order_ref }}
COMPANY:                {{ $declaration->company_name }}
STATUS:                 Awaiting signature
CERTIFICATE REQUESTED:  {{ $declaration->created_at?->format('d M Y') }}

Please log in to your account and complete the declaration:
{{ config('app.frontend_url', 'https://okelcor.com') }}/account/orders/{{ $declaration->order_ref }}

If you have already sent this document by other means or believe you have
received this message in error, please contact us at support@okelcor.com.

This certificate is required for intra-community VAT zero-rating under § 6a UStG.

---
Okelcor — support@okelcor.com — okelcor.com
