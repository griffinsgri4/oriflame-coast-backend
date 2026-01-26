<?php

return [
    'enabled' => env('MPESA_ENABLED', false),

    'environment' => env('MPESA_ENV', 'sandbox'),

    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    'shortcode' => env('MPESA_SHORTCODE'),
    'passkey' => env('MPESA_PASSKEY'),

    'callback_url' => env('MPESA_CALLBACK_URL'),
    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

    'account_reference' => env('MPESA_ACCOUNT_REFERENCE', 'Oriflame Coast'),
    'transaction_desc' => env('MPESA_TRANSACTION_DESC', 'Order payment'),
];
