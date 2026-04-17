<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class WixOrderImportService
{
    private const STATUS_MAP = [
        'fulfilled'           => 'delivered',
        'partially fulfilled' => 'processing',
        'not fulfilled'       => 'pending',
        'cancelled'           => 'cancelled',
        'canceled'            => 'cancelled',
        'shipped'             => 'shipped',
        'processing'          => 'processing',
        'confirmed'           => 'confirmed',
        'pending'             => 'pending',
        'delivered'           => 'delivered',
    ];

    private const PAYMENT_MAP = [
        'paid'     => 'paid',
        'pending'  => 'unpaid',
        'unpaid'   => 'unpaid',
        'refunded' => 'refunded',
    ];

    /**
     * Import orders from a Wix CSV file.
     *
     * Returns ['imported' => N, 'updated' => N, 'skipped' => N, 'errors' => []]
     *
     * @throws \RuntimeException if the file cannot be opened
     */
    public function import(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        // Read and normalise headers — strip UTF-8 BOM if present (Wix exports include it)
        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or unreadable.');
        }

        if (isset($rawHeaders[0])) {
            $rawHeaders[0] = ltrim($rawHeaders[0], "\xEF\xBB\xBF");
        }

        $headers = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        // Group rows by order number — handles multi-item orders (one row per item)
        $orderGroups = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);

            $ref = trim($data['order number'] ?? $data['order #'] ?? $data['order id'] ?? '');
            $ref = ltrim($ref, '#');

            if ($ref === '') {
                continue;
            }

            if (! isset($orderGroups[$ref])) {
                $orderGroups[$ref] = ['order' => $data, 'rows' => []];
            }

            $orderGroups[$ref]['rows'][] = $data;
        }

        fclose($handle);

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($orderGroups as $ref => $group) {
            try {
                $orderData = $this->mapOrder($ref, $group['order']);
                $items     = $this->mapItems($group['rows']);

                // Extract created_at before updateOrCreate — not in $fillable
                $createdAt = $orderData['created_at'];
                unset($orderData['created_at']);

                DB::transaction(function () use ($ref, $orderData, $createdAt, $items, &$imported, &$updated) {
                    $exists = Order::where('ref', $ref)->exists();
                    $order  = Order::updateOrCreate(['ref' => $ref], $orderData);

                    // Preserve the original Wix order date for new records only
                    if (! $exists) {
                        DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt]);
                    }

                    $order->items()->delete();
                    foreach ($items as $item) {
                        $order->items()->create($item);
                    }

                    $exists ? $updated++ : $imported++;
                });
            } catch (\Throwable $e) {
                $errors[] = "Order {$ref}: {$e->getMessage()}";
                $skipped++;
            }
        }

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    private function mapOrder(string $ref, array $data): array
    {
        $rawStatus  = strtolower(trim($data['fulfillment status'] ?? ''));
        $rawPayment = strtolower(trim($data['payment status'] ?? ''));
        $rawMethod  = trim($data['payment method'] ?? 'unknown');

        $email = trim($data['contact email'] ?? $data['buyer email'] ?? $data['email'] ?? '');
        $name  = trim($data['billing name'] ?? $data['recipient name'] ?? $data['buyer name'] ?? '');
        $phone = trim($data['billing phone'] ?? $data['recipient phone'] ?? '') ?: null;

        $address = trim($data['billing address'] ?? $data['delivery address'] ?? '');
        $city    = trim($data['billing city']    ?? $data['delivery city']    ?? '');
        $postal  = trim($data['billing zip/postal code'] ?? $data['delivery zip/postal code'] ?? $data['billing zip code'] ?? '');
        $country = trim($data['billing country'] ?? $data['delivery country'] ?? '');

        $postal = trim($postal, '"');
        $phone  = $phone ? trim($phone, '"') : null;

        $total        = $this->parseDecimal($data['total'] ?? null) ?? 0;
        $deliveryCost = $this->parseDecimal($data['shipping rate'] ?? null) ?? 0;
        $subtotal     = max(0, $total - $deliveryCost);

        $dateStr   = trim($data['date created'] ?? '');
        $timeStr   = trim($data['time'] ?? '');
        $createdAt = $this->parseDate($dateStr . ' ' . $timeStr) ?? now()->toDateTimeString();

        $estimatedDelivery = null;
        if (! empty($data['delivery time'])) {
            $estimatedDelivery = $this->parseDate($data['delivery time']);
        }

        $trackingNumber = trim($data['tracking number'] ?? '') ?: null;
        $notes          = trim($data['note from customer'] ?? $data['notes'] ?? '') ?: null;

        return [
            'ref'                => $ref,
            'customer_name'      => $name  ?: 'Unknown',
            'customer_email'     => $email ?: 'unknown@import.local',
            'customer_phone'     => $phone,
            'address'            => $address  ?: 'N/A',
            'city'               => $city     ?: 'N/A',
            'postal_code'        => $postal   ?: 'N/A',
            'country'            => $country  ?: 'N/A',
            'payment_method'     => $rawMethod ?: 'unknown',
            'subtotal'           => $subtotal,
            'delivery_cost'      => $deliveryCost,
            'total'              => $total,
            'status'             => self::STATUS_MAP[$rawStatus]  ?? 'pending',
            'payment_status'     => self::PAYMENT_MAP[$rawPayment] ?? 'unpaid',
            'mode'               => 'manual',
            'admin_notes'        => $notes,
            'tracking_number'    => $trackingNumber,
            'estimated_delivery' => $estimatedDelivery,
            'created_at'         => $createdAt,
        ];
    }

    private function mapItems(array $rows): array
    {
        $items = [];

        foreach ($rows as $data) {
            $itemName = trim($data['item'] ?? $data['item name'] ?? $data['product name'] ?? '');
            $itemSku  = trim($data['sku']  ?? $data['item sku']  ?? '');
            $itemQty  = max(1, (int) ($data['qty'] ?? $data['quantity'] ?? $data['item quantity'] ?? 1));

            $unitPrice = $this->parseDecimal($data['price'] ?? $data['item price'] ?? null) ?? 0;
            $lineTotal = round($unitPrice * $itemQty, 2);

            if ($itemName === '' && $itemSku === '') {
                continue;
            }

            $brand = '';
            $size  = '';
            if (preg_match('/(\d{3})\/(\d{2})R\s*(\d{2})/i', $itemName, $m)) {
                $size  = "{$m[1]}/{$m[2]}R{$m[3]}";
                $before = trim(preg_replace('/\d{3}\/\d{2}R\s*\d{2}.*/i', '', $itemName));
                $brand  = explode(' ', $before)[0] ?? '';
            }

            $items[] = [
                'sku'        => $itemSku ?: null,
                'brand'      => $brand,
                'name'       => $itemName ?: $itemSku,
                'size'       => $size,
                'unit_price' => $unitPrice,
                'quantity'   => $itemQty,
                'line_total' => $lineTotal,
            ];
        }

        return $items;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $clean = preg_replace('/[^\d.]/', '', $value);
        return $clean !== '' ? (float) $clean : null;
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse(trim($value))->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
