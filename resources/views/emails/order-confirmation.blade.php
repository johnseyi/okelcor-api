<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Order Confirmation — {{ $order->ref }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;">
<tr><td align="center" style="padding:32px 16px;">

    <!-- Email card -->
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:4px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">

        <!-- Orange accent bar -->
        <tr>
            <td style="background-color:#f4511e;height:4px;font-size:0;line-height:0;">&nbsp;</td>
        </tr>

        <!-- Header -->
        <tr>
            <td style="background-color:#171a20;padding:28px 40px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;letter-spacing:3px;color:#ffffff;text-transform:uppercase;">OKELCOR</span>
                    </td>
                    <td align="right">
                        <span style="display:inline-block;background-color:#f4511e;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:5px 12px;border-radius:2px;">Payment Confirmed</span>
                    </td>
                </tr>
                </table>
            </td>
        </tr>

        <!-- Greeting -->
        <tr>
            <td style="padding:36px 40px 0 40px;">
                <p style="margin:0 0 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;color:#171a20;line-height:1.3;">Thank you for your order, {{ explode(' ', trim($order->customer_name))[0] }}.</p>
                <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#5c5e62;line-height:1.6;">Your payment has been confirmed. We will be in touch shortly to confirm availability and shipping details.</p>
            </td>
        </tr>

        <!-- Order meta strip -->
        <tr>
            <td style="padding:24px 40px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;border-radius:4px;">
                <tr>
                    <td style="padding:16px 20px;width:33%;vertical-align:top;">
                        <p style="margin:0 0 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;">Order Ref</p>
                        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#171a20;">{{ $order->ref }}</p>
                    </td>
                    <td style="padding:16px 20px;width:33%;vertical-align:top;border-left:1px solid #e0e0e0;">
                        <p style="margin:0 0 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;">Date</p>
                        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#171a20;">{{ $order->created_at?->format('d M Y') }}</p>
                    </td>
                    <td style="padding:16px 20px;width:34%;vertical-align:top;border-left:1px solid #e0e0e0;">
                        <p style="margin:0 0 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;">Total</p>
                        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#f4511e;">€{{ number_format((float) $order->total, 2) }}</p>
                    </td>
                </tr>
                </table>
            </td>
        </tr>

        <!-- Shipping fields (conditional) -->
        @if ($order->carrier || $order->tracking_number || $order->container_number || $order->eta)
        <tr>
            <td style="padding:0 40px 24px 40px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #f0f0f0;">
                @if ($order->carrier)
                <tr>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;width:40%;vertical-align:top;">Carrier</td>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;font-weight:600;">{{ $order->carrier }}</td>
                </tr>
                @endif
                @if ($order->tracking_number)
                <tr>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;vertical-align:top;border-top:1px solid #f0f0f0;">Tracking number</td>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;font-weight:600;border-top:1px solid #f0f0f0;">{{ $order->tracking_number }}</td>
                </tr>
                @endif
                @if ($order->container_number)
                <tr>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;vertical-align:top;border-top:1px solid #f0f0f0;">Container</td>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;font-weight:600;border-top:1px solid #f0f0f0;">{{ $order->container_number }}</td>
                </tr>
                @endif
                @if ($order->eta)
                <tr>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;vertical-align:top;border-top:1px solid #f0f0f0;">ETA</td>
                    <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#171a20;font-weight:600;border-top:1px solid #f0f0f0;">{{ \Carbon\Carbon::parse($order->eta)->format('d M Y') }}</td>
                </tr>
                @endif
                </table>
            </td>
        </tr>
        @endif

        <!-- Items heading -->
        <tr>
            <td style="padding:0 40px 12px 40px;">
                <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;">Items Ordered</p>
            </td>
        </tr>

        <!-- Items table -->
        <tr>
            <td style="padding:0 40px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <thead>
                        <tr style="background-color:#f5f5f5;">
                            <th style="padding:10px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;text-align:left;">Product</th>
                            <th style="padding:10px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;text-align:center;width:40px;">Qty</th>
                            <th style="padding:10px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;text-align:right;width:90px;">Unit</th>
                            <th style="padding:10px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5c5e62;text-align:right;width:90px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:12px 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#171a20;vertical-align:top;">
                                {{ $item->name }}
                                @if ($item->size)
                                <br><span style="font-size:12px;color:#5c5e62;">{{ $item->size }}</span>
                                @endif
                            </td>
                            <td style="padding:12px 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#171a20;text-align:center;vertical-align:top;">{{ $item->quantity }}</td>
                            <td style="padding:12px 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#171a20;text-align:right;vertical-align:top;">€{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td style="padding:12px 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#171a20;text-align:right;vertical-align:top;">€{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color:#171a20;">
                            <td colspan="3" style="padding:14px 12px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;text-align:right;letter-spacing:0.5px;">Order Total</td>
                            <td style="padding:14px 12px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#f4511e;text-align:right;">€{{ number_format((float) $order->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>

        <!-- CTA button -->
        <tr>
            <td align="center" style="padding:36px 40px;">
                <a href="{{ $trackingUrl }}" style="display:inline-block;background-color:#f4511e;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:3px;letter-spacing:0.5px;">View Your Order &rarr;</a>
            </td>
        </tr>

        <!-- Divider -->
        <tr>
            <td style="padding:0 40px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr><td style="border-top:1px solid #f0f0f0;font-size:0;line-height:0;">&nbsp;</td></tr>
                </table>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="padding:24px 40px 32px 40px;">
                <p style="margin:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#5c5e62;line-height:1.6;">Questions about your order? Contact us at <a href="mailto:support@okelcor.com" style="color:#f4511e;text-decoration:none;font-weight:600;">support@okelcor.com</a></p>
                <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#9e9e9e;">© {{ date('Y') }} Okelcor. All rights reserved.</p>
            </td>
        </tr>

    </table>
    <!-- /Email card -->

</td></tr>
</table>

</body>
</html>
