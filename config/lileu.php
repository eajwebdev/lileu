<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Brand defaults
    |--------------------------------------------------------------------------
    | These seed the editable settings table on first boot. Anything the owner
    | changes in Admin → Settings wins over the values here.
    */
    'business' => [
        'name' => env('LILEU_BUSINESS_NAME', "Lil'Eu Sweets"),
        'tagline' => env('LILEU_TAGLINE', 'Est. 2026'),
        'phone' => env('LILEU_PHONE', '0917 000 0000'),
        'email' => env('LILEU_EMAIL', 'hello@lileu.test'),
        'address' => env('LILEU_ADDRESS', 'Dahile, Mabinay, Negros Oriental'),
        'facebook' => env('LILEU_FACEBOOK', 'facebook.com/lileucafe'),
        'website' => env('LILEU_WEBSITE', 'lileu.test'),
    ],

    'receipt' => [
        'prefix' => env('LILEU_RECEIPT_PREFIX', 'LE'),
        'order_prefix' => env('LILEU_ORDER_PREFIX', 'RE'),
        'pos_prefix' => env('LILEU_POS_PREFIX', 'POS'),
        'consignment_prefix' => env('LILEU_CONSIGNMENT_PREFIX', 'CN'),
        'pad' => 6,
        'show_logo' => true,
        'footer' => "Thank you for growing with Lil'Eu. 💗",
        'paper' => 'a4', // a4|thermal
    ],

    'orders' => [
        'downpayment_percent' => 50,
        'currency' => 'PHP',
        'currency_symbol' => '₱',
    ],

    /*
    |--------------------------------------------------------------------------
    | PayMongo
    |--------------------------------------------------------------------------
    | Leave the keys empty to run in "demo" mode: QR Ph payments are simulated
    | locally so the whole flow is clickable without live credentials.
    */
    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
        'demo_mode' => env('PAYMONGO_DEMO_MODE', true),
    ],
];
