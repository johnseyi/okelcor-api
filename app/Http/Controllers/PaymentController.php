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
use Stripe\WebhookSignature;

class PaymentController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private VatValidationService $vatService,
    ) {}

    /**
     * POST /api/v1/payments/create-intent
     *
     * Accepts the frontend cart body, saves a pending order, creates a Stripe
     * PaymentIntent, and returns only the client_secret for client-side confirmation.
     *
     * Request body:
     * {
     *   "delivery": { "name", "email", "address", "city", "postalCode", "country", "phone" },
     *   "paymentMethod": "card",
     *   "vat_number": "DE...",   (optional)
     *   "items": [
     *     { "product": { "id", "brand", "name", "size", "price" }, "quantity": 4 }
     *   ]
     * }
     */
    public function createIntent(Request $request): JsonResponse
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

        // --- VAT validation (optional) ---
        $vatNumber = $validated['vat_number'] ?? null;
        $vatValid  = null;
        if ($vatNumber) {
            $vatResult = $this->vatService->validate($vatNumber);
            $vatValid  = $vatResult['valid'] ? 1 : 0;
        }

        // --- Calculate total using DB prices (prevents client-side price manipulation) ---
        $productIds = collect($items)->pluck('product.id')->unique()->values()->all();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lineItems = [];
        $subtotal  = 0;

        foreach ($items as $item) {
            $productData = $item['product'];
            $productId   = $productData['id'];
            $quantity    = (int) $item['quantity'];
            $dbProduct   = $products->get($productId);

            // Use authoritative DB price; fall back to client-provided price only if product not found
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

        // --- Save pending order + items, then create PaymentIntent atomically ---
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

            // Create Stripe PaymentIntent (outside DB transaction — Stripe call is not rollback-able)
            $amountCents = (int) round($subtotal * 100);
            $result = $this->stripeService->createPaymentIntent($amountCents, 'eur', [
                'order_ref'      => $ref,
                'customer_email' => $delivery['email'],
            ]);

            // Store the payment intent ID on the order
            $order->update(['payment_intent_id' => $result['payment_intent_id']]);

            Log::info('Stripe PaymentIntent created', [
                'ref'               => $ref,
                'payment_intent_id' => $result['payment_intent_id'],
                'amount_cents'      => $amountCents,
            ]);

            return response()->json([
                'data' => [
                    'client_secret' => $result['client_secret'],
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('createIntent failed', ['error' => $e->getMessage(), 'ref' => $ref]);

            return response()->json([
                'message' => 'Payment gateway error. Please try again.',
            ], 502);
        }
    }

    /**
     * POST /api/v1/payments/webhook
     *
     * Handles Stripe webhook events. Raw body required for signature verification.
     * Excluded from ForceJsonResponse middleware (see routes/api.php).
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload       = $request->getContent();
        $sigHeader     = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret) {
            try {
                WebhookSignature::verifyHeader($payload, $sigHeader, $webhookSecret);
            } catch (SignatureVerificationException $e) {
                return response()->json(['message' => 'Invalid signature.'], 400);
            }
        }

        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $type       = $event['type'] ?? '';
        $intentData = $event['data']['object'] ?? [];

        match ($type) {
            'payment_intent.succeeded'      => $this->handlePaymentSucceeded($intentData),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($intentData),
            default                         => null,
        };

        return response()->json(['received' => true]);
    }

    private function handlePaymentSucceeded(array $intent): void
    {
        $orderRef = $intent['metadata']['order_ref'] ?? null;

        if (! $orderRef) {
            return;
        }

        Order::where('ref', $orderRef)->update([
            'payment_status'    => 'paid',
            'payment_intent_id' => $intent['id'] ?? null,
            'status'            => 'processing',
        ]);

        Log::info('Payment succeeded', ['ref' => $orderRef, 'intent' => $intent['id'] ?? null]);
    }

    private function handlePaymentFailed(array $intent): void
    {
        $orderRef = $intent['metadata']['order_ref'] ?? null;

        if (! $orderRef) {
            return;
        }

        Order::where('ref', $orderRef)->update([
            'payment_status'    => 'failed',
            'payment_intent_id' => $intent['id'] ?? null,
        ]);

        Log::warning('Payment failed', ['ref' => $orderRef, 'intent' => $intent['id'] ?? null]);
    }

    private function generateRef(): string
    {
        $timestamp = strtoupper(base_convert(substr((string) now()->timestamp, -5), 10, 36));
        $rand      = strtoupper(Str::random(3));

        return "OKL-{$timestamp}{$rand}";
    }
}
