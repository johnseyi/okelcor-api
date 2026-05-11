OKELCOR
================================================================================

PAYMENT INSTRUCTIONS — {{ $order->ref }}

Hello {{ explode(' ', trim($order->customer_name))[0] }},

Your quote has been reviewed and an order has been created. Please see the
payment details below.

--------------------------------------------------------------------------------
REFERENCES
--------------------------------------------------------------------------------
Quote reference : {{ $quote->ref_number }}
Order reference : {{ $order->ref }}
Date            : {{ $order->created_at?->format('d M Y') }}
Payment method  : {{ ucwords(str_replace('_', ' ', $order->payment_method ?? 'Bank transfer')) }}
Payment status  : Pending

--------------------------------------------------------------------------------
ITEMS
--------------------------------------------------------------------------------
@foreach ($order->items as $item)
{{ $item->name }}@if ($item->size) ({{ $item->size }})@endif
  Qty {{ $item->quantity }} x €{{ number_format((float) $item->unit_price, 2) }} = €{{ number_format((float) $item->line_total, 2) }}
@endforeach

--------------------------------------------------------------------------------
@if ($order->tax_treatment !== null)
Subtotal (net)  : €{{ number_format((float) $order->subtotal, 2) }}
@if ((float) $order->delivery_cost > 0)
Delivery        : €{{ number_format((float) $order->delivery_cost, 2) }}
@endif
@if ((float) ($order->discount_amount ?? 0) > 0)
{{ $order->discount_label ?? 'Discount' }}{{ str_repeat(' ', max(1, 16 - strlen($order->discount_label ?? 'Discount'))) }}: -€{{ number_format((float) $order->discount_amount, 2) }}
@endif
VAT             : €{{ number_format((float) ($order->tax_amount ?? 0), 2) }}
@endif
TOTAL           : €{{ number_format((float) $order->total, 2) }}

@if (!empty($checkoutUrl))
================================================================================
PAY WITH STRIPE
================================================================================
Click the link below to pay securely. This link expires in 24 hours.

{{ $checkoutUrl }}

After payment you will receive an order confirmation email.
@else
================================================================================
BANK TRANSFER DETAILS
================================================================================
Account Name    : {{ config('payment.bank_transfer.account_name') }}
Account Number  : {{ config('payment.bank_transfer.account_number') }}
IBAN            : {{ config('payment.bank_transfer.iban') }}
BIC / SWIFT     : {{ config('payment.bank_transfer.swift_bic') }}
Bank            : {{ config('payment.bank_transfer.bank_name') }}
Bank Address    : {{ config('payment.bank_transfer.bank_address') }}
Payment Ref     : {{ $order->ref }}
Delivery / Shipping Terms: @if ($quote->incoterm){{ match(strtoupper($quote->incoterm)) { 'FOB' => 'Incoterms 2020: FOB Germany', 'CIF' => 'Incoterms 2020: CIF destination port — freight and insurance included to destination port.', default => 'Incoterms 2020: ' . strtoupper($quote->incoterm) } }}@else{{ config('payment.bank_transfer.delivery_term') }}@endif

{{ config('payment.bank_transfer.terms') }}

{{ config('payment.bank_transfer.sepa_note') }}
{{ config('payment.bank_transfer.international_note') }}
@endif
================================================================================
Questions? Email us at support@okelcor.com
Okelcor — {{ date('Y') }}
