<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order — {{ $order->ref }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto; padding: 24px;">

    <p style="font-size: 1.1rem; font-weight: bold;">New order received: {{ $order->ref }}</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 5px 0; color: #6b7280; width: 40%;">Reference</td>
            <td style="padding: 5px 0; font-weight: bold;">{{ $order->ref }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Customer</td>
            <td style="padding: 5px 0;">{{ $order->customer_name }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Email</td>
            <td style="padding: 5px 0;"><a href="mailto:{{ $order->customer_email }}" style="color: #2563eb;">{{ $order->customer_email }}</a></td>
        </tr>
        @if ($order->customer_phone)
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Phone</td>
            <td style="padding: 5px 0;">{{ $order->customer_phone }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Address</td>
            <td style="padding: 5px 0;">{{ implode(', ', array_filter([$order->address, $order->city, $order->postal_code, $order->country])) }}</td>
        </tr>
        @if ($order->vat_number)
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">VAT number</td>
            <td style="padding: 5px 0;">{{ $order->vat_number }} ({{ $order->vat_valid ? 'valid' : 'not validated' }})</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Payment method</td>
            <td style="padding: 5px 0;">{{ $order->payment_method ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Total</td>
            <td style="padding: 5px 0; font-weight: bold;">€{{ number_format((float) $order->total, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Status</td>
            <td style="padding: 5px 0;">{{ ucfirst($order->status) }}</td>
        </tr>
        @if ($order->carrier)
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Carrier</td>
            <td style="padding: 5px 0;">{{ $order->carrier }}</td>
        </tr>
        @endif
        @if ($order->tracking_number)
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Tracking number</td>
            <td style="padding: 5px 0;">{{ $order->tracking_number }}</td>
        </tr>
        @endif
        @if ($order->container_number)
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Container number</td>
            <td style="padding: 5px 0;">{{ $order->container_number }}</td>
        </tr>
        @endif
        @if ($order->eta)
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">ETA</td>
            <td style="padding: 5px 0;">{{ \Carbon\Carbon::parse($order->eta)->format('d M Y') }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 5px 0; color: #6b7280;">Placed at</td>
            <td style="padding: 5px 0;">{{ $order->created_at?->format('d M Y H:i') }} UTC</td>
        </tr>
    </table>

    <p style="font-weight: bold; margin-top: 24px;">Items</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr style="border-bottom: 2px solid #e5e7eb;">
                <th style="text-align: left; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Product</th>
                <th style="text-align: left; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">SKU</th>
                <th style="text-align: right; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Qty</th>
                <th style="text-align: right; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Unit</th>
                <th style="text-align: right; padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 6px 4px;">
                    {{ $item->name }}
                    @if ($item->brand)<span style="color: #6b7280;"> · {{ $item->brand }}</span>@endif
                    @if ($item->size)<br><span style="font-size: 0.85rem; color: #6b7280;">{{ $item->size }}</span>@endif
                </td>
                <td style="padding: 6px 4px; font-size: 0.85rem; color: #6b7280;">{{ $item->sku ?? '—' }}</td>
                <td style="padding: 6px 4px; text-align: right;">{{ $item->quantity }}</td>
                <td style="padding: 6px 4px; text-align: right;">€{{ number_format((float) $item->unit_price, 2) }}</td>
                <td style="padding: 6px 4px; text-align: right;">€{{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="padding: 8px 4px; text-align: right; font-weight: bold;">Total</td>
                <td style="padding: 8px 4px; text-align: right; font-weight: bold;">€{{ number_format((float) $order->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p>
        <a href="{{ $trackingUrl }}" style="color: #2563eb;">View order in admin →</a>
    </p>

</body>
</html>
