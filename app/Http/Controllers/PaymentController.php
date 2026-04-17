<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AdyenService;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private AdyenService $adyenService,
        private VatValidationService $vatService,
    ) {}

    /**
     * POST /api/v1/payments/create-session
     *
     * Saves a pending order, creates an Adyen payment session, and returns the
     * session data for the frontend Drop-in / Components integration.
     *
     * Request body:
     * {
     *   "delivery": { "name", "email", "address", "city", "postalCode", "country", "phone" },
     *   "paymentMethod": "adyen",
     *   "vat_number": "DE...",   (optional)
     *   "items": [
     *     { "product": { "id", "brand", "name", "size", "price" }, "quantity": 4 }
     *   ]
     * }
     */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery'               => ['required', 'array'],
            'delivery.name'          => ['required', 'string', 'max:200'],
            'delivery.email'         => ['required', 'email', 'max:255'],
            'delivery.address'       => ['required', 'string', 'max:300'],
            'delivery.city'          => ['required', 'string', 'max:100'],
            'delivery.postalCode'    => ['required', 'string', 'max:20'],
            'delivery.country'       => ['required', 'string', 'max:100'],
            'delivery.phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'paymentMethod'          => ['required', 'string'],
            'vat_number'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product'        => ['required', 'array'],
            'items.*.product.id'     => ['required', 'integer'],
            'items.*.product.brand'  => ['required', 'string', 'max:200'],
            'items.*.product.name'   => ['required', 'string', 'max:300'],
            'items.*.product.size'   => ['required', 'string', 'max:50'],
            'items.*.product.price'  => ['required', 'numeric', 'min:0'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
        ]);

        $delivery = $validated['delivery'];
        $items    = $validated['items'];

        $vatNumber = $validated['vat_number'] ?? null;
        $vatValid  = null;
        if ($vatNumber) {
            $vatResult = $this->vatService->validate($vatNumber);
            $vatValid  = $vatResult['valid'] ? 1 : 0;
        }

        // Use DB prices to prevent client-side price manipulation
        $productIds = collect($items)->pluck('product.id')->unique()->values()->all();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lineItems = [];
        $subtotal  = 0;

        foreach ($items as $item) {
            $productData = $item['product'];
            $productId   = $productData['id'];
            $quantity    = (int) $item['quantity'];
            $dbProduct   = $products->get($productId);

            $unitPrice = $dbProduct ? (float) $dbProduct->price : (float) $productData['price'];
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;

            $lineItems[] = [
                'product_id' => $productId,
                'sku'        => $dbProduct?->sku,
                'brand'      => $productData['brand'],
                'name'       => $productData['name'],
                'size'       => $productData['size'],
                'unit_price' => $unitPrice,
                'quantity'   => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        $ref = $this->generateRef();

        try {
            $order = DB::transaction(function () use (
                $delivery, $lineItems, $subtotal, $ref, $request, $vatNumber, $vatValid
            ) {
                $order = Order::create([
                    'ref'            => $ref,
                    'customer_name'  => $delivery['name'],
                    'customer_email' => $delivery['email'],
                    'customer_phone' => $delivery['phone'] ?? null,
                    'address'        => $delivery['address'],
                    'city'           => $delivery['city'],
                    'postal_code'    => $delivery['postalCode'],
                    'country'        => $delivery['country'],
                    'payment_method' => 'adyen',
                    'subtotal'       => $subtotal,
                    'delivery_cost'  => 0.00,
                    'total'          => $subtotal,
                    'status'         => 'pending',
                    'payment_status' => 'pending',
                    'mode'           => 'live',
                    'ip_address'     => $request->ip(),
                    'vat_number'     => $vatNumber,
                    'vat_valid'      => $vatValid,
                ]);

                foreach ($lineItems as $line) {
                    OrderItem::create(['order_id' => $order->id] + $line);
                }

                return $order;
            });

            $result = $this->adyenService->createPaymentSession(
                $subtotal, 'eur', $ref, $delivery['email']
            );

            $order->update(['payment_session_id' => $result['session_id']]);

            Log::info('Adyen session created', [
                'ref'        => $ref,
                'session_id' => $result['session_id'],
                'amount'     => $subtotal,
            ]);

            return response()->json(['data' => $result], 201);
        } catch (\Throwable $e) {
            Log::error('createSession failed', ['error' => $e->getMessage(), 'ref' => $ref]);

            return response()->json([
                'message' => 'Payment gateway error. Please try again.',
            ], 502);
        }
    }

    /**
     * POST /api/v1/payments/webhook
     *
     * Handles Adyen webhook notifications (Standard Notification format).
     * Adyen expects a "[accepted]" plain-text response on success.
     */
    public function webhook(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $payload = $request->all();

        if (empty($payload['notificationItems'])) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        foreach ($payload['notificationItems'] as $notificationItem) {
            $item = $notificationItem['NotificationRequestItem'] ?? [];
            $this->handleNotification($item);
        }

        return response('[accepted]', 200)->header('Content-Type', 'text/plain');
    }

    private function handleNotification(array $item): void
    {
        $eventCode = $item['eventCode'] ?? '';
        $success   = ($item['success'] ?? 'false') === 'true';
        $ref       = $item['merchantReference'] ?? null;
        $pspRef    = $item['pspReference'] ?? null;

        if (! $ref) {
            return;
        }

        match ($eventCode) {
            'AUTHORISATION' => $success
                ? Order::where('ref', $ref)->update([
                    'payment_status'    => 'paid',
                    'payment_session_id' => $pspRef,
                    'status'             => 'processing',
                ])
                : Order::where('ref', $ref)->update(['payment_status' => 'failed']),
            'CANCELLATION', 'CANCEL_OR_REFUND' => Order::where('ref', $ref)->update([
                'payment_status' => 'refunded',
            ]),
            default => null,
        };

        Log::info('Adyen notification', [
            'event'   => $eventCode,
            'success' => $success,
            'ref'     => $ref,
            'psp'     => $pspRef,
        ]);
    }

    private function generateRef(): string
    {
        $timestamp = strtoupper(base_convert(substr((string) now()->timestamp, -5), 10, 36));
        $rand      = strtoupper(Str::random(3));

        return "OKL-{$timestamp}{$rand}";
    }
}
