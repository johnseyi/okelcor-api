<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $this->stripe = new StripeClient($secret);
    }

    public function createCheckoutSession(array $orderData): array
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://okelcor.com'), '/');

        $orderRef = $orderData['ref'] ?? $orderData['order_ref'] ?? null;

        $successUrl = $frontendUrl . '/checkout/return?session_id={CHECKOUT_SESSION_ID}';
        if ($orderRef) {
            $successUrl .= '&order_ref=' . urlencode((string) $orderRef);
        }

        $payload = [
            'mode'        => 'payment',
            'line_items'  => $this->lineItems($orderData),
            'success_url' => $successUrl,
            'cancel_url'  => $frontendUrl . '/checkout/cancel',
        ];

        $email = $orderData['customer_email'] ?? $orderData['email'] ?? null;
        if (is_string($email) && $email !== '') {
            $payload['customer_email'] = $email;
        }

        if (is_string($orderRef) && $orderRef !== '') {
            $payload['client_reference_id'] = $orderRef;
            $payload['metadata'] = ['order_ref' => $orderRef];
            $payload['payment_intent_data'] = [
                'metadata' => ['order_ref' => $orderRef],
            ];
        }

        Log::info('Stripe checkout success_url', [
            'success_url' => $successUrl,
            'order_ref'   => $orderRef,
        ]);

        $session = $this->stripe->checkout->sessions->create($payload);

        return [
            'checkout_session_id' => $session->id,
            'checkout_url'        => $session->url,
        ];
    }

    private function lineItems(array $orderData): array
    {
        if (! empty($orderData['line_items']) && is_array($orderData['line_items'])) {
            return $orderData['line_items'];
        }

        if (empty($orderData['items']) || ! is_array($orderData['items'])) {
            throw new InvalidArgumentException('Stripe checkout requires at least one line item.');
        }

        $currency = strtolower((string) ($orderData['currency'] ?? config('services.stripe.currency', 'eur')));
        $lineItems = [];

        foreach ($orderData['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->itemName($item);
            $unitAmount = $this->unitAmount($item);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $lineItems[] = [
                'price_data' => [
                    'currency'     => $currency,
                    'product_data' => [
                        'name' => $name,
                    ],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => $quantity,
            ];
        }

        if ($lineItems === []) {
            throw new InvalidArgumentException('Stripe checkout requires at least one valid line item.');
        }

        return $lineItems;
    }

    private function itemName(array $item): string
    {
        $name = $item['name'] ?? $item['product_name'] ?? $item['description'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('Stripe line items require a product name.');
        }

        return trim($name);
    }

    private function unitAmount(array $item): int
    {
        if (isset($item['unit_amount'])) {
            return (int) $item['unit_amount'];
        }

        $amount = $item['unit_price'] ?? $item['price'] ?? $item['amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount < 0) {
            throw new InvalidArgumentException('Stripe line items require a non-negative unit amount.');
        }

        return (int) round((float) $amount * 100);
    }
}
