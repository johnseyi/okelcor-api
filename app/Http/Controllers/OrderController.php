<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(private VatValidationService $vatService) {}

    /**
     * GET /api/v1/orders?email=customer@example.com
     *
     * Returns orders for a given email address, with items.
     * Requires ?email= — returns empty list if omitted.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $orders = Order::with('items')
            ->where('customer_email', $request->email)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $orders->map(fn ($o) => [
                'ref'            => $o->ref,
                'status'         => $o->status,
                'payment_status' => $o->payment_status,
                'payment_method' => $o->payment_method,
                'subtotal'       => (float) $o->subtotal,
                'delivery_cost'  => (float) $o->delivery_cost,
                'total'          => (float) $o->total,
                'created_at'     => $o->created_at?->toIso8601String(),
                'items'          => $o->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'brand'      => $i->brand,
                    'name'       => $i->name,
                    'size'       => $i->size,
                    'sku'        => $i->sku,
                    'quantity'   => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                ])->values(),
            ])->values(),
            'meta'    => ['total' => $orders->count()],
            'message' => 'success',
        ]);
    }

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
