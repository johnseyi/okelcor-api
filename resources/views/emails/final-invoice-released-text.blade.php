OKELCOR
================================================================================

YOUR FINAL INVOICE IS READY — {{ $declaration->order_ref }}

The EU Entry Certificate for the above order has been reviewed and acknowledged.
Your final tax invoice is now available for download from your account.

--------------------------------------------------------------------------------
INVOICE DETAILS
--------------------------------------------------------------------------------
Order reference : {{ $declaration->order_ref }}
Invoice number  : {{ $invoice->invoice_number }}
Amount          : €{{ number_format((float) $invoice->amount, 2) }}
VAT treatment   : Reverse charge — 0% VAT (§ 6a UStG)

View and download your invoice:
{{ $invoicesUrl }}

--------------------------------------------------------------------------------
EU VAT COMPLIANCE COMPLETE

Your EU Entry Certificate (Gelangensbestätigung) has been signed and
acknowledged, confirming that the goods have been transported to the destination
EU member state. The VAT zero-rating under the reverse-charge mechanism applies
to this transaction.

================================================================================
Questions? Email us at support@okelcor.com
Okelcor — {{ date('Y') }}
