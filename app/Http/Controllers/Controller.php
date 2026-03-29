<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Config;

abstract class Controller
{
    use AuthorizesRequests;

    protected function validateDevAuth(): void
    {
        $devAuthAllowed = Config::get('app.dev-auth-allowed', false);

        if (! $devAuthAllowed) {
            abort(403, 'Debug access is not enabled');
        }

        $token = $this->getTokenFromRequest();
        $devToken = Config::get('app.dev-auth-token');
        $devToken = is_string($devToken) && strlen(trim($devToken)) >= 10 ? trim($devToken) : null;

        if (!$devToken || !$token || $token !== $devToken) {
            abort(401, 'Invalid or missing authentication token');
        }
    }

    protected function getTokenFromRequest(): ?string
    {
        $request = request();

        return $request->header('X-Dev-Token') ?? $request->input('token');
    }
}
