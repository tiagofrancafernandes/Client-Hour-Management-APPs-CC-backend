<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        ...array_unique(
            array_filter(
                array_map(fn ($v) => trim("{$v}"), [
                    env('FRONTEND_URL', 'http://localhost:3000'),
                    env('CENTRAL_DOMAIN'),
                    ...explode(',', strval(env('CENTRAL_DOMAINS'))),
                    ...explode(',', strval(env('ALLOWED_ORIGINS'))),
                    env('SAAS_DOMAIN'),
                    env('CUSTOMER_APP_DOMAIN'),
                    env('BACKOFFICE_DOMAIN'),
                    env('API_DOMAIN'),
                    '127.0.0.1',
                    'localhost',
                ])
            )
        )
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Disposition',
        'X-Filename',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
