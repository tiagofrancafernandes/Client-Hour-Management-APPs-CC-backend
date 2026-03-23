<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Http\Middleware\ValidateCsrfToken as AppValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerifyCsrfToken as AppVerifyCsrfToken;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // /* https://laravel.com/docs/12.x/middleware#laravels-default-middleware-groups */
        $middleware->api(replace: [
            VerifyCsrfToken::class => AppVerifyCsrfToken::class,
            ValidateCsrfToken::class => AppValidateCsrfToken::class,
        ]);

        $middleware->web(replace: [
            VerifyCsrfToken::class => AppVerifyCsrfToken::class,
            ValidateCsrfToken::class => AppValidateCsrfToken::class,
        ]);

        // This API authenticates first-party requests with bearer tokens.
        // Keeping stateful Sanctum middleware enabled here forces session-cookie auth
        // for frontend origins and conflicts with token-based login.
        // $middleware->statefulApi();

        // $middleware->trustHosts();

        /** @see https://laravel.com/docs/12.x/csrf#csrf-excluding-uris */
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            '/*',
            '*',
            'api/*',
            // 'http://example.com/foo/bar',
            // 'http://example.com/foo/*',
            'http://local-frontend.com/*',
            'http://local-*.com/*',
            'http://localcom/*',
            'http://*.localcom/*',
        ]);

        $middleware->alias([
            'verified' => App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
