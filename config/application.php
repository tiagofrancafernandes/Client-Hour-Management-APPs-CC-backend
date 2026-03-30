<?php

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
];
