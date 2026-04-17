<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation — {{ $order->ref }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto; padding: 24px;">

    <p style="font-size: 1.1rem; font-weight: bold;">Thank you for your order, {{ $order->customer_name }}!</p>

    <p>We have received your order and our team will be in touch to confirm availability and arrange payment.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 24px 0;">
        <tr>
            <td style="padding: 6px 0; color: #6b7280; width: 40%;">Order reference</td>
            <td style="padding: 6px 0; font-weight: bold;">{{ $order->ref }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Order date</td>
            <td style="padding: 6px 0;">{{ $order->created_at?->format('d M Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Order total</td>
            <td style="padding: 6px 0;">€{{ number_format((float) $order->total, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Status</td>
            <td style="padding: 6px 0;">{{ ucfirst($order->status) }}</td>
        </tr>
        @if ($order->carrier)
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Carrier</td>
            <td style="padding: 6px 0;">{{ $order->carrier }}</td>
        </tr>
        @endif
        @if ($order->tracking_number)
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Tracking number</td>
            <td style="padding: 6px 0;">{{ $order->tracking_number }}</td>
        </tr>
        @endif
        @if ($order->container_number)
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Container number</td>
            <td style="padding: 6px 0;">{{ $order->container_number }}</td>
        </tr>
        @endif
        @if ($order->eta)
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Estimated arrival (ETA)</td>
            <td style="padding: 6px 0;">{{ \Carbon\Carbon::parse($order->eta)->format('d M Y') }}</td>
        </tr>
        @endif
    </table>

    <p style="font-weight: bold; margin-top: 24px;">Items ordered</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr style="border-bottom: 2px solid #e5e7eb;">
                <th style="text-align: left; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Product</th>
                <th style="text-align: right; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Qty</th>
                <th style="text-align: right; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Unit price</th>
                <th style="text-align: right; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 6px 4px;">
                    {{ $item->name }}
                    @if ($item->size)<br><span style="font-size: 0.85rem; color: #6b7280;">{{ $item->size }}</span>@endif
                </td>
                <td style="padding: 6px 4px; text-align: right;">{{ $item->quantity }}</td>
                <td style="padding: 6px 4px; text-align: right;">€{{ number_format((float) $item->unit_price, 2) }}</td>
                <td style="padding: 6px 4px; text-align: right;">€{{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="padding: 8px 4px; text-align: right; font-weight: bold;">Total</td>
                <td style="padding: 8px 4px; text-align: right; font-weight: bold;">€{{ number_format((float) $order->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p>You can track your order at any time:</p>
    <p>
        <a href="{{ $trackingUrl }}" style="color: #2563eb;">{{ $trackingUrl }}</a>
    </p>

    <p style="margin-top: 32px; color: #6b7280; font-size: 0.9rem;">
        If you have any questions, please reply to this email or contact us at
        <a href="mailto:info@okelcor.de" style="color: #2563eb;">info@okelcor.de</a>.
    </p>

    <p style="color: #6b7280; font-size: 0.9rem;">— The Okelcor Team</p>

</body>
</html>
