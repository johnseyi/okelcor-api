<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WixOrderImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderImportController extends Controller
{
    /**
     * POST /api/v1/admin/orders/import
     */
    public function import(Request $request, WixOrderImportService $service): JsonResponse
    {
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'max:51200'],
        ]);

        try {
            $result = $service->import($request->file('file')->getRealPath());

            return response()->json([
                'data'    => [
                    'imported' => $result['imported'],
                    'updated'  => $result['updated'],
                    'skipped'  => $result['skipped'],
                    'errors'   => $result['errors'],
                ],
                'message' => "Import complete: {$result['imported']} new, {$result['updated']} updated, {$result['skipped']} skipped.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data'    => null,
                'message' => 'Import failed.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/admin/orders/export
     *
     * Exports all orders as CSV — one row per order item, order fields repeated.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'okelcor_orders_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'order number',
                'date created',
                'buyer name',
                'buyer email',
                'buyer phone',
                'address',
                'city',
                'postal code',
                'country',
                'payment method',
                'subtotal',
                'shipping',
                'total',
                'fulfillment status',
                'payment status',
                'vat number',
                'notes',
                'carrier',
                'tracking number',
                'estimated delivery',
                'item name',
                'item sku',
                'item brand',
                'item size',
                'item quantity',
                'item price',
                'item total',
            ]);

            Order::with('items')
                ->orderBy('id')
                ->chunk(200, function ($orders) use ($handle) {
                    foreach ($orders as $o) {
                        $items = $o->items;

                        if ($items->isEmpty()) {
                            // Order with no items — one row, item columns empty
                            fputcsv($handle, [
                                $o->ref,
                                $o->created_at?->toDateTimeString(),
                                $o->customer_name,
                                $o->customer_email,
                                $o->customer_phone,
                                $o->address,
                                $o->city,
                                $o->postal_code,
                                $o->country,
                                $o->payment_method,
                                $o->subtotal,
                                $o->delivery_cost,
                                $o->total,
                                $o->status,
                                $o->payment_status,
                                $o->vat_number,
                                $o->admin_notes,
                                $o->carrier,
                                $o->tracking_number,
                                $o->estimated_delivery,
                                '', '', '', '', '', '', '',
                            ]);
                        } else {
                            foreach ($items as $item) {
                                fputcsv($handle, [
                                    $o->ref,
                                    $o->created_at?->toDateTimeString(),
                                    $o->customer_name,
                                    $o->customer_email,
                                    $o->customer_phone,
                                    $o->address,
                                    $o->city,
                                    $o->postal_code,
                                    $o->country,
                                    $o->payment_method,
                                    $o->subtotal,
                                    $o->delivery_cost,
                                    $o->total,
                                    $o->status,
                                    $o->payment_status,
                                    $o->vat_number,
                                    $o->admin_notes,
                                    $o->carrier,
                                    $o->tracking_number,
                                    $o->estimated_delivery,
                                    $item->name,
                                    $item->sku,
                                    $item->brand,
                                    $item->size,
                                    $item->quantity,
                                    $item->unit_price,
                                    $item->line_total,
                                ]);
                            }
                        }
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

}
