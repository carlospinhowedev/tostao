<?php

use App\Banking\Providers\DemoProvider;
use App\Banking\Providers\EnableBankingProvider;
use App\Brokers\Trading212Provider;

return [
    /*
    | Currency shown across the app (ISO 4217). Amounts are always stored as
    | integer cents, so there are no floating point surprises.
    */
    'currency' => env('TOSTAO_CURRENCY', 'EUR'),

    /*
    | Locales offered in the language switcher. Add a lang/<code>.json file
    | to add a new one.
    */
    'locales' => ['pt' => 'Português', 'en' => 'English'],

    /*
    | Default palette used for the members and the starter categories.
    */
    'member_colors' => ['#2f6fb5', '#c2548a', '#2a9078', '#c98a1b', '#7a5cc2', '#d4553f'],

    /*
    | Optional bank connections (open banking). Off until a provider has its
    | credentials. To add another provider, implement
    | App\Banking\BankProvider and list it here; the banks themselves are
    | never hard-coded, they come from the provider.
    */
    'banks' => [
        'country' => env('TOSTAO_BANK_COUNTRY', 'PT'),
        // Must be allowed in the provider's dashboard. Defaults to APP_URL/banks/callback.
        'redirect_url' => env('TOSTAO_BANK_REDIRECT_URL'),
        // How long a consent lasts before the bank asks you to renew it.
        'consent_days' => (int) env('TOSTAO_BANK_CONSENT_DAYS', 180),
        // First sync reaches back this far when the account has no history.
        'history_days' => (int) env('TOSTAO_BANK_HISTORY_DAYS', 90),

        'providers' => [
            'enablebanking' => [
                'class' => EnableBankingProvider::class,
                'app_id' => env('ENABLE_BANKING_APP_ID'),
                'private_key_path' => env('ENABLE_BANKING_KEY_PATH'),
                'private_key' => env('ENABLE_BANKING_PRIVATE_KEY'),
            ],
            'demo' => [
                'class' => DemoProvider::class,
                'enabled' => (bool) env('TOSTAO_BANK_DEMO', false),
            ],
        ],
    ],

    /*
    | Optional investment brokers with an API. Credentials come from .env; with
    | none set the Investments page explains how. To add a broker, implement
    | App\Brokers\BrokerProvider and list it here.
    */
    'brokers' => [
        'history_days' => (int) env('TOSTAO_BROKER_HISTORY_DAYS', 365),

        'providers' => [
            'trading212' => [
                'class' => Trading212Provider::class,
                'api_key' => env('TRADING212_API_KEY'),
                'api_secret' => env('TRADING212_API_SECRET'),
                'environment' => env('TRADING212_ENV', 'live'), // live or demo
            ],
        ],
    ],
];
