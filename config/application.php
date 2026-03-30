<?php

$disabledResources = (array) value(function () {
    $values = array_values(
        array_filter(array_map('trim', explode(';', ensure_string(env('DISABLED_RESOURCES')))))
    );

    return $values;
});

$availabledResources = (array) value(function () use ($disabledResources) {
    $defaultAvailable = array_keys(array_filter([
        // 'abc' => true,
        // 'def' => false,
        // 'ghi' => true,
    ]));

    $available = array_merge(array_values(
        array_filter(array_map('trim', explode(';', ensure_string(env('AVAILABLED_RESOURCES')))))
    ), $defaultAvailable);

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
];
