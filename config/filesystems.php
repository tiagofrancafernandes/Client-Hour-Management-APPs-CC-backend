<?php

$isOnLambda = str_starts_with(env('LAMBDA_TASK_ROOT', ''), '/var/task')
        || env('AWS_LAMBDA_FUNCTION_VERSION')
        || env('AWS_LAMBDA_EXEC_WRAPPER')
        || env('AWS_LAMBDA_RUNTIME_API')
        || env('AWS_LAMBDA_FUNCTION_NAME') || false;

$pathsToLink = [];

if (env('ON_SERVERLESS')) {
    $pathsToLink[public_path('storage')] = sys_get_temp_dir() . '/app/public';
}

if (!env('ON_SERVERLESS')) {
    $pathsToLink[public_path('storage')] = storage_path('app/public');
}

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', (env('ON_SERVERLESS') || $isOnLambda) ? 'serverless_public' : 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [
        'local' => [
            'driver' => 'local',
            // 'root' => storage_path('app/private'),
            'root' => env('ON_SERVERLESS') ? sys_get_temp_dir() . '/app' : storage_path('app'),
            'serve' => true,
            'throw' => false,
            'report' => false,
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            // 'root' => storage_path('app/public'),
            // 'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'root' => env('ON_SERVERLESS') ? sys_get_temp_dir() . '/app/public' : storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'serverless_public' => [
            'driver' => 'local',
            'root' => sys_get_temp_dir() . '/app/public',
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        ...(function () use ($pathsToLink, $isOnLambda): array {
            $links = [];
            $links[public_path('storage')] = in_array(env('FILESYSTEM_DISK', 'local'), ['local', 'private'])
                ? storage_path('app/private')
                : storage_path('app/public');

            $links[public_path('pv_storage')] = storage_path('app/private');
            $links[public_path('pb_storage')] = storage_path('app/public');

            if ($isOnLambda) {
                $links[public_path('tmp_storage')] = sys_get_temp_dir() . '/storage';
            }

            $links = array_merge($links, $pathsToLink);

            if (isset($links[public_path('tmp_storage')]) && !is_dir($links[public_path('tmp_storage')])) {
                mkdir($links[public_path('tmp_storage')], 0777, true);
            }

            return $links;
        })(),
    ],
];
