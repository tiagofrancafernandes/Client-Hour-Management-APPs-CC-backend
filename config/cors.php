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
                    'https://api.hourledger.local.com',
                    'https://hourledger.local.com',
                    'https://local.com',
                    env('FRONTEND_URL', 'http://localhost:3000'),
                    ...explode(',', strval(env('CENTRAL_DOMAINS'))),
                    ...explode(',', strval(env('ALLOWED_ORIGINS'))),
                    env('CENTRAL_DOMAIN'),
                    env('SAAS_DOMAIN'),
                    env('CUSTOMER_APP_DOMAIN'),
                    env('BACKOFFICE_DOMAIN'),
                    env('API_DOMAIN'),
                    env('APP_MAIN_DOMAIN'),
                    env('FRONTEND_MAIN_DOMAIN'),
                    '127.0.0.1',
                    'localhost',
                ])
            )
        )
    ],

    'allowed_origins_patterns' => [
        '#^https?://.*\.vercel\.app$#',
        ...(env('CORS_ALLOW_ALL', false) || env('CORS_ALLOW_ALL_ORIGINS', false) ? ['#^.*$#'] : []),
        ...array_filter(
            array_map(function ($pattern) {
                $trimmed = trim("{$pattern}");

                if ($trimmed === '') {
                    return null;
                }

                if (preg_match('/^([#\/~]).*\1[imsxeADSUXJu]*$/', $trimmed)) {
                    return $trimmed;
                }

                $escaped = preg_quote($trimmed, '#');
                $regex = str_replace('\*', '.*', $escaped);

                return "#^{$regex}$#i";
            }, explode(',', strval(env('ALLOWED_ORIGINS_PATTERNS', env('ALLOWED_ORIGIN_PATTERNS', '')))))
        ),
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Disposition',
        'X-Filename',
        'Content-Type',
        'Response-Type',
        'X-Response-Type',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
