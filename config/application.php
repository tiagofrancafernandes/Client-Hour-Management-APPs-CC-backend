<?php

return [
    'enabled_resources' => env('ENABLED_RESOURCES'),
    'disabled_resources' => env('DISABLED_RESOURCES'),
    'resources' => [
        'auth' => [
            /* Show link and form to recovery/renew password */
            'self_recovery_password' => (bool) env('self_recovery_password'),
        ],
    ],
];
