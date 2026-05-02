<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>New Order — {{ $order->ref }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;">
<tr><td align="center" style="padding:32px 16px;">

<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;">

    <!-- Top accent line -->
    <tr>
        <td style="background-color:#f4511e;height:3px;font-size:0;line-height:0;">&nbsp;</td>
    </tr>

    <!-- Header -->
    <tr>
        <td style="padding:28px 36px 20px 36px;border-bottom:1px solid #eeeeee;">
            <span style="font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;letter-spacing:2px;color:#171a20;text-transform:uppercase;">OKELCOR</span>
            <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#9e9e9e;margin-left:8px;">Admin notification</span>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px 36px 0 36px;">
            <p style="margin:0 0 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#171a20;">New order received</p>
            <p style="margin:0 0 24px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#5c5e62;">{{ $order->ref }} &mdash; {{ $order->created_at?->format('d M Y, H:i') }} UTC</p>

            <!-- Customer + address -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #eeeeee;margin-bottom:28px;">
                <tr>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;width:40%;background-color:#fafafa;border-bottom:1px solid #eeeeee;">Customer</td>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;font-weight:700;border-bottom:1px solid #eeeeee;">{{ $order->customer_name }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;background-color:#fafafa;border-bottom:1px solid #eeeeee;">Email</td>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;border-bottom:1px solid #eeeeee;"><a href="mailto:{{ $order->customer_email }}" style="color:#555555;text-decoration:underline;">{{ $order->customer_email }}</a></td>
                </tr>
                @if ($order->customer_phone)
                <tr>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;background-color:#fafafa;border-bottom:1px solid #eeeeee;">Phone</td>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;border-bottom:1px solid #eeeeee;">{{ $order->customer_phone }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;background-color:#fafafa;border-bottom:1px solid #eeeeee;">Address</td>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;border-bottom:1px solid #eeeeee;">{{ implode(', ', array_filter([$order->address, $order->city, $order->postal_code, $order->country])) }}</td>
                </tr>
                @if ($order->vat_number)
                <tr>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;background-color:#fafafa;border-bottom:1px solid #eeeeee;">VAT</td>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;border-bottom:1px solid #eeeeee;">{{ $order->vat_number }} ({{ $order->vat_valid ? 'valid' : 'not validated' }})</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;background-color:#fafafa;">Payment</td>
                    <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;">{{ $order->payment_method ?? '—' }}</td>
                </tr>
            </table>

            <!-- Items -->
            <p style="margin:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#171a20;">Items</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #eeeeee;margin-bottom:28px;">
                <thead>
                    <tr style="background-color:#fafafa;">
                        <th style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#5c5e62;text-align:left;border-bottom:1px solid #eeeeee;">Product</th>
                        <th style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#5c5e62;text-align:left;width:80px;border-bottom:1px solid #eeeeee;">SKU</th>
                        <th style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#5c5e62;text-align:center;width:40px;border-bottom:1px solid #eeeeee;">Qty</th>
                        <th style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#5c5e62;text-align:right;width:80px;border-bottom:1px solid #eeeeee;">Unit</th>
                        <th style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#5c5e62;text-align:right;width:90px;border-bottom:1px solid #eeeeee;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                    <tr>
                        <td style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;border-bottom:1px solid #eeeeee;vertical-align:top;">
                            {{ $item->name }}@if ($item->brand)<span style="color:#5c5e62;"> &middot; {{ $item->brand }}</span>@endif
                            @if ($item->size)<br><span style="font-size:12px;color:#5c5e62;">{{ $item->size }}</span>@endif
                        </td>
                        <td style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#5c5e62;border-bottom:1px solid #eeeeee;vertical-align:top;">{{ $item->sku ?? '—' }}</td>
                        <td style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;text-align:center;border-bottom:1px solid #eeeeee;vertical-align:top;">{{ $item->quantity }}</td>
                        <td style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;text-align:right;border-bottom:1px solid #eeeeee;vertical-align:top;">€{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;text-align:right;border-bottom:1px solid #eeeeee;vertical-align:top;">€{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#171a20;text-align:right;border-top:2px solid #eeeeee;">Total</td>
                        <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#171a20;text-align:right;border-top:2px solid #eeeeee;">€{{ number_format((float) $order->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- CTA -->
            <p style="margin:0 0 32px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;">
                <a href="{{ $trackingUrl }}" style="color:#171a20;text-decoration:underline;">View order in admin: {{ $trackingUrl }}</a>
            </p>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:24px 36px;border-top:1px solid #eeeeee;">
            <p style="margin:0 0 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;">Reply to customer: <a href="mailto:{{ $order->customer_email }}" style="color:#555555;text-decoration:underline;">{{ $order->customer_email }}</a></p>
            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#9e9e9e;">Okelcor &mdash; {{ date('Y') }} &mdash; Internal notification</p>
        </td>
    </tr>

</table>

</td></tr>
</table>

</body>
</html>
