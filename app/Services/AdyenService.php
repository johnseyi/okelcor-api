<?php

namespace App\Services;

use Adyen\Client;
use Adyen\Environment;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\CreateCheckoutSessionRequest;
use Adyen\Service\Checkout\PaymentSessionsApi;

class AdyenService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setXApiKey(config('services.adyen.api_key'));
        $this->client->setEnvironment(
            config('services.adyen.environment') === 'live' ? Environment::LIVE : Environment::TEST
        );
    }

    public function createPaymentSession(float $amount, string $currency, string $orderRef, string $customerEmail): array
    {
        $amountObj = new Amount();
        $amountObj->setCurrency(strtoupper($currency));
        $amountObj->setValue((int) round($amount * 100));

        $sessionRequest = new CreateCheckoutSessionRequest();
        $sessionRequest->setMerchantAccount(config('services.adyen.merchant_account'));
        $sessionRequest->setAmount($amountObj);
        $sessionRequest->setReference($orderRef);
        $sessionRequest->setReturnUrl(
            rtrim(config('app.frontend_url', 'https://okelcor.de'), '/') . '/checkout/return'
        );
        $sessionRequest->setShopperEmail($customerEmail);

        $api      = new PaymentSessionsApi($this->client);
        $response = $api->sessions($sessionRequest);

        return [
            'session_id'   => $response->getId(),
            'session_data' => $response->getSessionData(),
            'client_key'   => config('services.adyen.client_key'),
        ];
    }
}
