<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportWixOrders extends Command
{
    protected $signature = 'import:wix-orders {file : Path to the Wix orders CSV export file}';

    protected $description = 'Import orders from a Wix CSV export';

    // Map Wix fulfillment status → our status enum
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

    // Map Wix payment status → our payment_status enum
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

        $rawHeaders = fgetcsv($handle);
        $headers    = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        $this->info('CSV columns: ' . implode(', ', $headers));

        // Count rows for progress bar
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle); // skip header

        if ($totalRows === 0) {
            $this->warn('No data rows found.');
            fclose($handle);
            return self::SUCCESS;
        }

        $this->info("Processing {$totalRows} rows…");
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        // Group all rows by order ref first — Wix sometimes outputs one row per item
        $orderGroups = [];

        while (($row = fgetcsv($handle)) !== false) {
            $bar->advance();

            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);
            $ref  = $this->col($data, ['order number', 'order #', 'order id', 'ordernumber', 'number']);

            if ($ref === '') {
                continue;
            }

            // Normalise ref — strip leading # if present
            $ref = ltrim($ref, '#');

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

                DB::transaction(function () use ($ref, $orderData, $items, &$imported, &$updated) {
                    $exists = Order::where('ref', $ref)->exists();

                    $order = Order::updateOrCreate(['ref' => $ref], $orderData);

                    // Replace items on every import so they stay in sync
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

    private function mapOrder(string $ref, array $data): array
    {
        $rawStatus  = strtolower(trim($this->col($data, ['fulfillment status', 'order status', 'status', 'shipping status'])));
        $rawPayment = strtolower(trim($this->col($data, ['payment status'])));
        $rawMethod  = $this->col($data, ['payment method', 'payment gateway']);

        // Address fields — try billing first, fall back to shipping
        $address    = $this->col($data, ['billing address line 1', 'billing address', 'address line 1', 'address', 'shipping address line 1', 'shipping address']);
        $address2   = $this->col($data, ['billing address line 2', 'address line 2', 'shipping address line 2']);
        $city       = $this->col($data, ['billing city', 'city', 'shipping city']);
        $postal     = $this->col($data, ['billing zip code', 'billing postcode', 'zip code', 'postal code', 'postcode', 'shipping zip code']);
        $country    = $this->col($data, ['billing country', 'country', 'shipping country']);

        if ($address2 !== '') {
            $address = trim($address . ', ' . $address2);
        }

        $subtotal     = $this->parseDecimal($this->col($data, ['subtotal', 'items total']));
        $deliveryCost = $this->parseDecimal($this->col($data, ['shipping', 'shipping cost', 'delivery', 'delivery cost']));
        $total        = $this->parseDecimal($this->col($data, ['total', 'order total', 'grand total']));

        // Derive total if missing
        if ($total === null) {
            $total = ($subtotal ?? 0) + ($deliveryCost ?? 0);
        }

        $createdAt = $this->parseDate($this->col($data, ['date created', 'order date', 'created at', 'date']));

        return [
            'ref'            => $ref,
            'customer_name'  => $this->col($data, ['buyer name', 'customer name', 'billing name', 'name', 'contact name']),
            'customer_email' => $this->col($data, ['buyer email', 'customer email', 'billing email', 'email']),
            'customer_phone' => $this->col($data, ['buyer phone', 'customer phone', 'billing phone', 'phone']) ?: null,
            'address'        => $address ?: 'N/A',
            'city'           => $city ?: 'N/A',
            'postal_code'    => $postal ?: 'N/A',
            'country'        => $country ?: 'N/A',
            'payment_method' => $rawMethod ?: 'unknown',
            'subtotal'       => $subtotal ?? $total ?? 0,
            'delivery_cost'  => $deliveryCost ?? 0,
            'total'          => $total ?? 0,
            'status'         => self::STATUS_MAP[$rawStatus] ?? 'pending',
            'payment_status' => self::PAYMENT_MAP[$rawPayment] ?? 'unpaid',
            'mode'           => 'manual',
            'admin_notes'    => $this->col($data, ['notes', 'admin notes', 'buyer note', 'gift message']) ?: null,
            'vat_number'     => $this->col($data, ['vat number', 'tax id', 'vat']) ?: null,
            'created_at'     => $createdAt ?? now(),
            'updated_at'     => now(),
        ];
    }

    private function mapItems(array $rows): array
    {
        $items = [];

        foreach ($rows as $data) {
            // Each row may contain one line item
            $itemName  = $this->col($data, ['item name', 'product name', 'title', 'line item name']);
            $itemSku   = $this->col($data, ['item sku', 'sku', 'product sku', 'variant sku']);
            $itemQty   = (int) ($this->col($data, ['item quantity', 'quantity', 'qty']) ?: 1);
            $itemPrice = $this->parseDecimal($this->col($data, ['item price', 'price', 'unit price', 'item total']));

            if ($itemName === '' && $itemSku === '') {
                continue;
            }

            $unitPrice = $itemPrice ?? 0;
            $lineTotal = $unitPrice * $itemQty;

            // Try to parse brand and size from item name (same pattern as products)
            $brand = '';
            $size  = '';
            if (preg_match('/(\d{3})\/(\d{2})R\s*(\d{2})/i', $itemName, $m)) {
                $size = "{$m[1]}/{$m[2]}R{$m[3]}";
                $brand = trim(preg_replace('/\d{3}\/\d{2}R\s*\d{2}.*/i', '', $itemName));
                $brand = explode(' ', $brand)[0] ?? '';
            }

            $items[] = [
                'sku'        => $itemSku ?: 'N/A',
                'brand'      => $brand,
                'name'       => $itemName ?: $itemSku,
                'size'       => $size,
                'unit_price' => $unitPrice,
                'quantity'   => max(1, $itemQty),
                'line_total' => $lineTotal,
            ];
        }

        return $items;
    }

    /**
     * Look up a value from $data trying multiple possible column names.
     */
    private function col(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim($data[$key]) !== '') {
                return trim($data[$key]);
            }
        }
        return '';
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
        if (! $value) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
