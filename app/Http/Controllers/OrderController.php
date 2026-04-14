<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(private VatValidationService $vatService) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $delivery  = $validated['delivery'];
        $items     = $validated['items'];

        $vatNumber = $validated['vat_number'] ?? null;
        $vatValid  = null;

        if ($vatNumber) {
            $vatResult = $this->vatService->validate($vatNumber);
            $vatValid  = $vatResult['valid'] ? 1 : 0;
        }

        $subtotal = collect($items)->sum(fn ($i) => $i['unit_price'] * $i['quantity']);
        $total    = $subtotal; // delivery_cost = 0 until payment SDK is integrated
        $ref      = $this->generateRef();

        $order = DB::transaction(function () use ($delivery, $items, $subtotal, $total, $ref, $request, $vatNumber, $vatValid) {
            $order = Order::create([
                'ref'            => $ref,
                'customer_name'  => $delivery['name'],
                'customer_email' => $delivery['email'],
                'customer_phone' => $delivery['phone'],
                'address'        => $delivery['address'],
                'city'           => $delivery['city'],
                'postal_code'    => $delivery['postal_code'],
                'country'        => $delivery['country'],
                'payment_method' => $request->payment_method,
                'subtotal'       => $subtotal,
                'delivery_cost'  => 0.00,
                'total'          => $total,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'mode'           => 'manual',
                'ip_address'     => $request->ip(),
                'vat_number'     => $vatNumber,
                'vat_valid'      => $vatValid,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'sku'        => $item['sku'],
                    'brand'      => $item['brand'],
                    'name'       => $item['name'],
                    'size'       => $item['size'],
                    'unit_price' => $item['unit_price'],
                    'quantity'   => $item['quantity'],
                    'line_total' => $item['unit_price'] * $item['quantity'],
                ]);
            }

            return $order;
        });

        // Notify admin (logged in local dev — configure ORDER_EMAIL env var for prod)
        Log::info('New order', ['ref' => $ref, 'email' => $order->customer_email, 'total' => $total]);

        return response()->json([
            'data' => [
                'ref'     => $ref,
                'mode'    => 'manual',
                'message' => 'Order received. Our team will contact you to arrange payment.',
            ],
        ], 201);
    }

    private function generateRef(): string
    {
        $timestamp = strtoupper(base_convert(substr((string) now()->timestamp, -5), 10, 36));
        $rand      = strtoupper(Str::random(3));

        return "OKL-{$timestamp}{$rand}";
    }
}
