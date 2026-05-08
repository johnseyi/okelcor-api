<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Action required: EU entry certificate pending</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, Helvetica, sans-serif; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-top: 3px solid #f4511e; }
        .body { padding: 32px 36px; }
        h2 { font-size: 18px; color: #171a20; margin: 0 0 8px 0; }
        p { font-size: 14px; color: #3c3f45; line-height: 1.6; margin: 0 0 14px 0; }
        .info-block { background: #f9f9f9; border: 1px solid #e0e0e0; padding: 14px 16px; margin: 20px 0; font-size: 13px; line-height: 1.8; color: #3c3f45; }
        .info-block strong { color: #171a20; }
        .badge { display: inline-block; background: #fff3e0; color: #e65100; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 3px 8px; }
        .cta { display: inline-block; background: #f4511e; color: #ffffff; font-size: 14px; font-weight: 700; padding: 12px 24px; text-decoration: none; margin: 8px 0 20px 0; }
        .footer { padding: 16px 36px; border-top: 1px solid #eeeeee; font-size: 11px; color: #9e9e9e; line-height: 1.6; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="body">
        <h2>EU entry certificate still outstanding</h2>
        <p>We have not yet received your signed EU entry certificate (Gelangensbestätigung) for order <strong>{{ $declaration->order_ref }}</strong>. This document is required under § 17a UStDV to confirm that the goods have been transported to another EU member state.</p>

        <div class="info-block">
            <strong>Order reference:</strong> {{ $declaration->order_ref }}<br>
            <strong>Company:</strong> {{ $declaration->company_name }}<br>
            <strong>Status:</strong> <span class="badge">Awaiting signature</span><br>
            <strong>Certificate requested:</strong> {{ $declaration->created_at?->format('d M Y') }}
        </div>

        <p>Please log in to your account and complete the declaration at your earliest convenience.</p>
        <a href="{{ config('app.frontend_url', 'https://okelcor.com') }}/account/orders/{{ $declaration->order_ref }}" class="cta">Sign declaration</a>

        <p>If you have already sent this document by other means or believe you have received this message in error, please contact us at <a href="mailto:support@okelcor.com">support@okelcor.com</a>.</p>
        <p style="color:#9e9e9e;font-size:12px;">This certificate is required for intra-community VAT zero-rating under § 6a UStG. Failure to provide it may result in VAT being applied retrospectively.</p>
    </div>
    <div class="footer">
        Okelcor &mdash; support@okelcor.com &mdash; okelcor.com
    </div>
</div>
</body>
</html>
