<?php

namespace App\Services;

use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe PaymentIntent.
     *
     * @param  int    $amount    Amount in cents (e.g. 5000 = €50.00)
     * @param  string $currency  ISO 4217 currency code (default: eur)
     * @param  array  $metadata  Key-value pairs attached to the PaymentIntent
     * @return array{client_secret: string, payment_intent_id: string}
     */
    public function createPaymentIntent(int $amount, string $currency = 'eur', array $metadata = []): array
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount'   => $amount,
            'currency' => strtolower($currency),
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return [
            'client_secret'     => $intent->client_secret,
            'payment_intent_id' => $intent->id,
        ];
    }
}
