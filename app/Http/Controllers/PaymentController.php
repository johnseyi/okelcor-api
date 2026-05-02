<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StripeService;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class PaymentController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private VatValidationService $vatService,
    ) {}

    /**
     * POST /api/v1/payments/create-session
     *
     * Saves a pending order, creates a Stripe Checkout Session, and returns the
     * hosted checkout URL for the frontend.
     *
     * Request body:
     * {
     *   "delivery": { "name", "email", "address", "city", "postalCode", "country", "phone" },
     *   "paymentMethod": "stripe",
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
                    'payment_method' => 'stripe',
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

            $currency = strtolower((string) config('services.stripe.currency', 'eur'));
            $result = $this->stripeService->createCheckoutSession([
                'ref'            => $ref,
                'customer_email' => $delivery['email'],
                'currency'       => $currency,
                'items'          => $lineItems,
            ]);

            $order->update(['payment_session_id' => $result['checkout_session_id']]);

            Log::info('Stripe checkout session created', [
                'ref'                 => $ref,
                'checkout_session_id' => $result['checkout_session_id'],
                'amount'              => $subtotal,
            ]);

            return response()->json([
                'data' => [
                    'provider'            => 'stripe',
                    'order_ref'           => $ref,
                    'checkout_session_id' => $result['checkout_session_id'],
                    'checkout_url'        => $result['checkout_url'],
                ],
            ], 201);
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
     * Handles Stripe webhook notifications for Checkout payments.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json(['message' => 'Stripe webhook is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret
            );
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $object = $this->stripeObjectToArray($event->data->object ?? []);
        $order = $this->resolveOrderFromStripeObject($object);

        Log::info('Stripe webhook received', [
            'event'     => $event->type,
            'order_ref' => $order?->ref,
            'object_id' => $object['id'] ?? null,
        ]);

        if (! $order && in_array($event->type, [
            'checkout.session.completed',
            'payment_intent.payment_failed',
            'charge.refunded',
        ], true)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->markOrderPaid($order, $object),
            'payment_intent.payment_failed' => $this->markOrderFailed($order),
            'charge.refunded' => $this->markOrderRefunded($order),
            default => null,
        };

        return response()->json(['message' => 'Webhook received.']);
    }

    private function resolveOrderFromStripeObject(array $object): ?Order
    {
        $checkoutSessionId = $object['id'] ?? null;
        if (is_string($checkoutSessionId) && str_starts_with($checkoutSessionId, 'cs_')) {
            $order = Order::where('payment_session_id', $checkoutSessionId)->first();
            if ($order) {
                return $order;
            }
        }

        $orderRef = $object['metadata']['order_ref'] ?? $object['client_reference_id'] ?? null;
        if (is_string($orderRef) && $orderRef !== '') {
            return Order::where('ref', $orderRef)->first();
        }

        return null;
    }

    private function markOrderPaid(?Order $order, array $object): void
    {
        if (! $order) {
            return;
        }

        $order->update([
            'payment_status'    => 'paid',
            'payment_session_id' => $object['id'] ?? $order->payment_session_id,
            'status'             => 'processing',
        ]);
    }

    private function markOrderFailed(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->update([
            'payment_status' => 'failed',
            'status'         => 'cancelled',
        ]);
    }

    private function markOrderRefunded(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->update([
            'payment_status' => 'refunded',
        ]);
    }

    private function stripeObjectToArray(mixed $object): array
    {
        if (is_array($object)) {
            return $object;
        }

        if (is_object($object) && method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        return [];
    }

    private function generateRef(): string
    {
        $timestamp = strtoupper(base_convert(substr((string) now()->timestamp, -5), 10, 36));
        $rand      = strtoupper(Str::random(3));

        return "OKL-{$timestamp}{$rand}";
    }
}
