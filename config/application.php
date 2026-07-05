<?php

declare(strict_types=1);

$disabledResources = (array) value(function () {
    /* For easy understanding */
    $DISABLED = true;
    $NOT_DISABLED = false;

    $hardDisabled = array_keys(array_filter([
        // 'self_recovery_password' => $NOT_DISABLED,
        // 'self_register' => $DISABLED,
    ]));

    $values = array_merge(array_values(
        array_filter(array_map('trim', explode(';', ensure_string(env('DISABLED_RESOURCES')))))
    ), $hardDisabled);

    return $values;
});

$availabledResources = (array) value(function () use ($disabledResources) {
    /* For easy understanding */
    $ENABLED = true;
    $DISABLED = false;

    $hardAvailable = array_keys(array_filter([
        // 'abc' => $ENABLED,
        // 'def' => $DISABLED,
        // 'ghi' => $ENABLED,
        'self_recovery_password' => $ENABLED,
        'self_register' => $ENABLED,
    ]));

    $available = array_merge(array_values(
        array_filter(array_map('trim', explode(';', ensure_string(env('AVAILABLED_RESOURCES')))))
    ), $hardAvailable);

    $available = array_filter(array_combine($available, $available));

    return array_diff(array_keys($available), $disabledResources);
});

return [
    'disabled_resources' => $disabledResources,
    'availabled_resources' => $availabledResources,
    'resources' => [
        'auth' => [
            /* Show link and form to recovery/renew password */
            'self_recovery_password' => in_array('self_recovery_password', $availabledResources),

            /* Show link and form to register */
            'self_register' => in_array('self_register', $availabledResources),

            /* Default role for user for self registration flow */
            'default_role_on_register' => null,

            /* Default role for user on creation */
            'default_role' => null,
        ],
    ],
    'mail' => [
        'footer_name' => env('MAIL_FOOTER_NAME', env('APP_NAME', 'Hour Ledger Team')),
    ],
    'date' => [
        'timezones' => [
            'America/Sao_Paulo' => [
                'offset' => -3,
                'label' => 'America/Sao_Paulo',
                'timezone_id' => 'America/Sao_Paulo',
                'country' => 'BR',
            ],

            'America/New_York' => [
                'offset' => -5,
                'label' => 'America/New_York',
                'timezone_id' => 'America/New_York',
                'country' => 'US',
            ],

            'America/Chicago' => [
                'offset' => -6,
                'label' => 'America/Chicago',
                'timezone_id' => 'America/Chicago',
                'country' => 'US',
            ],

            'America/Denver' => [
                'offset' => -7,
                'label' => 'America/Denver',
                'timezone_id' => 'America/Denver',
                'country' => 'US',
            ],

            'America/Los_Angeles' => [
                'offset' => -8,
                'label' => 'America/Los_Angeles',
                'timezone_id' => 'America/Los_Angeles',
                'country' => 'US',
            ],

            'America/Toronto' => [
                'offset' => -5,
                'label' => 'America/Toronto',
                'timezone_id' => 'America/Toronto',
                'country' => 'CA',
            ],

            'America/Vancouver' => [
                'offset' => -8,
                'label' => 'America/Vancouver',
                'timezone_id' => 'America/Vancouver',
                'country' => 'CA',
            ],

            'Europe/London' => [
                'offset' => 0,
                'label' => 'Europe/London',
                'timezone_id' => 'Europe/London',
                'country' => 'GB',
            ],
        ],
    ],
];
