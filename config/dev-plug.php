<?php

return [
    'is_on_loop' => boolval(env('IS_ON_LOOP', false)),
    'to_force_action' => boolval(env('LARAVEL_FORCE_ACTION', false)),
    'project-state' => [
        'git' => [
            'branch' => env('GIT_BRANCH', env('GIT_COMMIT_REF', env('VERCEL_GIT_COMMIT_REF'))),
            'commit_ref' => env('GIT_COMMIT_REF', env('VERCEL_GIT_COMMIT_REF')),
            'commit_message' => env('GIT_COMMIT_MESSAGE', env('VERCEL_GIT_COMMIT_MESSAGE')),
            'commit_sha' => env('GIT_COMMIT_SHA', env('VERCEL_GIT_COMMIT_SHA')),
            'commit_sha_short' => value(function () {
                $value = env('GIT_COMMIT_SHA', env('VERCEL_GIT_COMMIT_SHA'));

                if (!is_string($value) || !trim($value)) {
                    return null;
                }

                return substr(trim($value), 0, 7);
            }),
        ],

        'vercel' => [
            'branch_url' => env('VERCEL_BRANCH_URL'),
            'deployment_id' => env('VERCEL_DEPLOYMENT_ID'),
            'git_commit_ref' => env('VERCEL_GIT_COMMIT_REF'),
            'git_commit_message' => env('VERCEL_GIT_COMMIT_MESSAGE'),
            'git_commit_sha' => env('VERCEL_GIT_COMMIT_SHA'),
            'project_id' => env('VERCEL_PROJECT_ID'),
            'project_name' => env('VERCEL_PROJECT_NAME'),
            'project_production_url' => env('VERCEL_PROJECT_PRODUCTION_URL'),
        ],

        'lambda' => [
            'initialization_type' => env('AWS_LAMBDA_INITIALIZATION_TYPE'),
            'task_root' => env('LAMBDA_TASK_ROOT'),
            'handler' => env('_HANDLER'),
            'function_version' => env('AWS_LAMBDA_FUNCTION_VERSION'),
            'exec_wrapper' => env('AWS_LAMBDA_EXEC_WRAPPER'),
            'runtime_api' => env('AWS_LAMBDA_RUNTIME_API'),
            'function_name' => env('AWS_LAMBDA_FUNCTION_NAME'),
        ],
    ],
    'defaults' => [
        'users' => [
            'superadmin' => [
                'name' => value(function () {
                    $value = filter_var(env('DEFAULT_SUPERADMIN_NAME'), FILTER_DEFAULT, FILTER_NULL_ON_FAILURE);

                    if (!is_string($value) || strlen(trim($value)) < 6) {
                        return 'System Admin';
                    }

                    return $value;
                }),
                'email' => value(function () {
                    $value = filter_var(env('DEFAULT_SUPERADMIN_EMAIL'), FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE);

                    if (!is_string($value) || !trim($value)) {
                        return 'admin@mail.com';
                    }

                    return $value;
                }),
                'password' => value(function () {
                    $value = filter_var(env('DEFAULT_SUPERADMIN_PASSWORD'), FILTER_DEFAULT, FILTER_NULL_ON_FAILURE);

                    if (!is_string($value) || strlen(trim($value)) < 6) {
                        return null;
                    }

                    return $value;
                }),
            ],
        ],
    ],
];
