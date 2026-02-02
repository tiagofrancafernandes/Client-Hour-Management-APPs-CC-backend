<?php

return [
    'is_on_loop' => boolval(env('IS_ON_LOOP', false)),
    'to_force_action' => boolval(env('LARAVEL_FORCE_ACTION', false)),
];
