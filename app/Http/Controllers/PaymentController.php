<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;

class PaymentController extends Controller
{
    public function __construct(private StripeService $stripeService) {}

    /**
     * POST /api/v1/payments/create-intent
     *
     * Body: { amount (euros float), currency?, order_ref?, customer_email? }
     * Returns: { data: { client_secret, payment_intent_id } }
     */
    public function createIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.5'],
            'currency'       => ['sometimes', 'string', 'size:3'],
            'order_ref'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        // Convert euros to cents
        $amountCents = (int) round($validated['amount'] * 100);
        $currency    = $validated['currency'] ?? 'eur';

        $metadata = [];
        if (! empty($validated['order_ref'])) {
            $metadata['order_ref'] = $validated['order_ref'];
        }
        if (! empty($validated['customer_email'])) {
            $metadata['customer_email'] = $validated['customer_email'];
        }

        try {
            $result = $this->stripeService->createPaymentIntent($amountCents, $currency, $metadata);

            return response()->json(['data' => $result], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Payment gateway error. Please try again.',
            ], 502);
        }
    }

    /**
     * POST /api/v1/payments/webhook
     *
     * Handles Stripe webhook events. Raw body required for signature verification.
     * Excluded from CSRF and ForceJsonResponse middleware in bootstrap/app.php.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload       = $request->getContent();
        $sigHeader     = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret) {
            try {
                WebhookSignature::verifyHeader(
                    $payload,
                    $sigHeader,
                    $webhookSecret
                );
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
            'payment_intent.succeeded'       => $this->handlePaymentSucceeded($intentData),
            'payment_intent.payment_failed'  => $this->handlePaymentFailed($intentData),
            default                          => null,
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
    }
}
