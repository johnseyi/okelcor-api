<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'adyen' => [
        'api_key'          => env('ADYEN_API_KEY'),
        'merchant_account' => env('ADYEN_MERCHANT_ACCOUNT'),
        'environment'      => env('ADYEN_ENVIRONMENT', 'test'),
        'client_key'       => env('ADYEN_CLIENT_KEY'),
    ],

    'shipsgo' => [
        'key' => env('SHIPSGO_API_KEY'),
    ],

    'dhl' => [
        'api_key' => env('DHL_API_KEY'),
    ],

    'ebay' => [
        'client_id'     => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'environment'   => env('EBAY_ENVIRONMENT', 'sandbox'),
    ],

    'ebay_sell' => [
        'client_id'             => env('EBAY_CLIENT_ID'),
        'client_secret'         => env('EBAY_CLIENT_SECRET'),
        'refresh_token'         => env('EBAY_REFRESH_TOKEN'),
        'ru_name'               => env('EBAY_RU_NAME'),
        'marketplace_id'        => env('EBAY_MARKETPLACE_ID', 'EBAY_DE'),
        'category_id'           => env('EBAY_CATEGORY_ID', '11755'),
        'fulfillment_policy_id' => env('EBAY_FULFILLMENT_POLICY_ID'),
        'payment_policy_id'     => env('EBAY_PAYMENT_POLICY_ID'),
        'return_policy_id'      => env('EBAY_RETURN_POLICY_ID'),
    ],

];
