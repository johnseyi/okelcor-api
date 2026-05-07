<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Entry certificate submitted</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, Helvetica, sans-serif; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-top: 3px solid #f4511e; }
        .body { padding: 32px 36px; }
        h2 { font-size: 18px; color: #171a20; margin: 0 0 8px 0; }
        p { font-size: 14px; color: #3c3f45; line-height: 1.6; margin: 0 0 14px 0; }
        .info-block { background: #f9f9f9; border: 1px solid #e0e0e0; padding: 14px 16px; margin: 20px 0; font-size: 13px; line-height: 1.8; color: #3c3f45; }
        .info-block strong { color: #171a20; }
        .badge { display: inline-block; background: #e8f5e9; color: #2e7d32; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 3px 8px; }
        .footer { padding: 16px 36px; border-top: 1px solid #eeeeee; font-size: 11px; color: #9e9e9e; line-height: 1.6; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="body">
        <h2>Entry certificate submitted</h2>
        <p>Thank you. Your EU entry certificate (Gelangensbestätigung) for order <strong>{{ $declaration->order_ref }}</strong> has been received and is under review by our team.</p>

        <div class="info-block">
            <strong>Order reference:</strong> {{ $declaration->order_ref }}<br>
            <strong>Status:</strong> <span class="badge">Signed</span><br>
            <strong>Signed by:</strong> {{ $declaration->representative_name }}@if ($declaration->representative_title) &mdash; {{ $declaration->representative_title }}@endif<br>
            <strong>Member state of entry:</strong> {{ $declaration->member_state_of_entry }}<br>
            <strong>Place of entry:</strong> {{ $declaration->place_of_entry }}<br>
            <strong>Month / year goods received:</strong> {{ $declaration->month_year_received }}<br>
            <strong>Date signed:</strong> {{ $declaration->signed_at?->format('d M Y') }}
        </div>

        <p>You can download a copy of your signed declaration at any time from your account under <strong>Orders</strong>.</p>
        <p>Our team will review and acknowledge your certificate. If any information needs clarifying we will contact you directly.</p>
        <p style="color:#9e9e9e;font-size:12px;">This document is required under § 17a UStDV as proof that goods have been transported into the EU member state named above.</p>
    </div>
    <div class="footer">
        Okelcor &mdash; support@okelcor.com &mdash; okelcor.com
    </div>
</div>
</body>
</html>
