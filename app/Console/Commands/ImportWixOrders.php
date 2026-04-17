<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWixOrders extends Command
{
    protected $signature = 'import:wix-orders {file : Path to the Wix orders CSV export file}';

    protected $description = 'Import orders from a Wix CSV export';

    // Wix fulfillment status → our status enum
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

    // Wix payment status → our payment_status enum
    private const PAYMENT_MAP = [
        'paid'     => 'paid',
        'pending'  => 'unpaid',
        'unpaid'   => 'unpaid',
        'refunded' => 'refunded',
    ];

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error("Cannot open file: {$filePath}");
            return self::FAILURE;
        }

        // Read and normalise headers
        $rawHeaders = fgetcsv($handle);
        $headers    = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        $this->info('Detected columns: ' . implode(', ', $headers));

        // Count rows for progress bar
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle); // skip header again

        if ($totalRows === 0) {
            $this->warn('No data rows found.');
            fclose($handle);
            return self::SUCCESS;
        }

        $this->info("Processing {$totalRows} rows…");
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        // Group rows by order number — handles multi-item orders (one row per item)
        $orderGroups = [];

        while (($row = fgetcsv($handle)) !== false) {
            $bar->advance();

            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);

            // Wix column: "Order number"
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
        $bar->finish();
        $this->newLine();

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($orderGroups as $ref => $group) {
            try {
                $orderData = $this->mapOrder($ref, $group['order']);
                $items     = $this->mapItems($group['rows']);

                // Extract created_at before passing to updateOrCreate (not in $fillable)
                $createdAt = $orderData['created_at'];
                unset($orderData['created_at']);

                DB::transaction(function () use ($ref, $orderData, $createdAt, $items, &$imported, &$updated) {
                    $exists = Order::where('ref', $ref)->exists();
                    $order  = Order::updateOrCreate(['ref' => $ref], $orderData);

                    // Preserve the original Wix order date for new records
                    if (! $exists) {
                        DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt]);
                    }

                    // Replace items on every run to stay in sync
                    $order->items()->delete();
                    foreach ($items as $item) {
                        $order->items()->create($item);
                    }

                    $exists ? $updated++ : $imported++;
                });
            } catch (\Throwable $e) {
                $this->warn("Skipped order {$ref}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info('Import complete.');
        $this->table(
            ['Imported (new)', 'Updated (existing)', 'Skipped'],
            [[$imported, $updated, $skipped]]
        );

        return self::SUCCESS;
    }

    /**
     * Map a single Wix order CSV row to Order attributes.
     *
     * Wix column names (exact, lowercased):
     *   order number, date created, time, contact email, note from customer,
     *   billing name, billing phone, billing address, billing city,
     *   billing zip/postal code, billing country,
     *   delivery address, delivery city, delivery zip/postal code, delivery country,
     *   payment status, payment method, shipping rate, total, fulfillment status,
     *   tracking number, delivery time
     */
    private function mapOrder(string $ref, array $data): array
    {
        // ── Status mapping ─────────────────────────────────────────────────
        $rawStatus  = strtolower(trim($data['fulfillment status'] ?? ''));
        $rawPayment = strtolower(trim($data['payment status'] ?? ''));
        $rawMethod  = trim($data['payment method'] ?? 'unknown');

        // ── Customer info ──────────────────────────────────────────────────
        // Wix uses "Contact email" for the buyer's email, "Billing name" for name
        $email = trim($data['contact email'] ?? $data['buyer email'] ?? $data['email'] ?? '');
        $name  = trim($data['billing name'] ?? $data['recipient name'] ?? $data['buyer name'] ?? '');
        $phone = trim($data['billing phone'] ?? $data['recipient phone'] ?? '') ?: null;

        // ── Address — prefer billing, fall back to delivery ────────────────
        $address = trim($data['billing address'] ?? $data['delivery address'] ?? '');
        $city    = trim($data['billing city']    ?? $data['delivery city']    ?? '');
        // Wix column is "Billing zip/postal code" (with slash)
        $postal  = trim($data['billing zip/postal code'] ?? $data['delivery zip/postal code'] ?? $data['billing zip code'] ?? '');
        $country = trim($data['billing country'] ?? $data['delivery country'] ?? '');

        // Strip stray quotes Wix sometimes wraps around postal/phone values
        $postal = trim($postal, '"');
        $phone  = $phone ? trim($phone, '"') : null;

        // ── Financials ─────────────────────────────────────────────────────
        // Wix has no separate subtotal column — total IS the order total
        $total        = $this->parseDecimal($data['total'] ?? null) ?? 0;
        $deliveryCost = $this->parseDecimal($data['shipping rate'] ?? null) ?? 0;
        // Derive subtotal by subtracting shipping
        $subtotal     = max(0, $total - $deliveryCost);

        // ── Dates ──────────────────────────────────────────────────────────
        // "Date created" is "Apr 14, 2026", "Time" is "2:36:22 PM" — combine them
        $dateStr   = trim($data['date created'] ?? '');
        $timeStr   = trim($data['time'] ?? '');
        $createdAt = $this->parseDate($dateStr . ' ' . $timeStr) ?? now()->toDateTimeString();

        // Estimated delivery from "Delivery time" (ISO 8601 in Wix export)
        $estimatedDelivery = null;
        if (! empty($data['delivery time'])) {
            $estimatedDelivery = $this->parseDate($data['delivery time']);
        }

        // "Tracking number" column
        $trackingNumber = trim($data['tracking number'] ?? '') ?: null;

        // "Note from customer"
        $notes = trim($data['note from customer'] ?? $data['notes'] ?? '') ?: null;

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

    /**
     * Build order_items from all CSV rows belonging to one order.
     *
     * Wix columns per row: Item, Variant, SKU, Qty, Price
     */
    private function mapItems(array $rows): array
    {
        $items = [];

        foreach ($rows as $data) {
            // Wix column is "Item" (not "item name")
            $itemName = trim($data['item'] ?? $data['item name'] ?? $data['product name'] ?? '');
            $itemSku  = trim($data['sku']  ?? $data['item sku']  ?? '');
            $itemQty  = (int) ($data['qty'] ?? $data['quantity'] ?? $data['item quantity'] ?? 1);
            $itemQty  = max(1, $itemQty);

            // "Price" is the unit price in Wix
            $unitPrice = $this->parseDecimal($data['price'] ?? $data['item price'] ?? null) ?? 0;
            $lineTotal = round($unitPrice * $itemQty, 2);

            if ($itemName === '' && $itemSku === '') {
                continue;
            }

            // Try to extract brand and size from item name
            $brand = '';
            $size  = '';
            if (preg_match('/(\d{3})\/(\d{2})R\s*(\d{2})/i', $itemName, $m)) {
                $size  = "{$m[1]}/{$m[2]}R{$m[3]}";
                $before = trim(preg_replace('/\d{3}\/\d{2}R\s*\d{2}.*/i', '', $itemName));
                $brand  = explode(' ', $before)[0] ?? '';
            }

            $items[] = [
                'sku'        => $itemSku ?: 'N/A',
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
