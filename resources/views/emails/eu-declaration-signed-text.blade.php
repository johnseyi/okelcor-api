Entry certificate submitted — {{ $declaration->order_ref }}

Thank you. Your EU entry certificate (Gelangensbestätigung) for order {{ $declaration->order_ref }} has been received and is under review.

ORDER REFERENCE:      {{ $declaration->order_ref }}
STATUS:               Signed
SIGNED BY:            {{ $declaration->representative_name }}{{ $declaration->representative_title ? ' — ' . $declaration->representative_title : '' }}
MEMBER STATE:         {{ $declaration->member_state_of_entry }}
PLACE OF ENTRY:       {{ $declaration->place_of_entry }}
MONTH/YEAR RECEIVED:  {{ $declaration->month_year_received }}
DATE SIGNED:          {{ $declaration->signed_at?->format('d M Y') }}

You can download a copy of your signed declaration from your account under Orders.

Our team will review and acknowledge your certificate. If any information needs clarifying we will contact you directly.

---
Okelcor — support@okelcor.com — okelcor.com
