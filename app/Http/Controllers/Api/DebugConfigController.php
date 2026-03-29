<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class DebugConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->validateDevAuth();

        $result = [];

        if ($request->has('config')) {
            $result['config'] = $this->getConfigValues($request->input('config'));
        }

        if ($request->has('env')) {
            $result['env'] = $this->getEnvValues($request->input('env'));
        }

        if ($request->has('server')) {
            $result['server'] = $this->getServerValues($request->input('server'));
        }

        if (empty($result)) {
            return response()->json([
                'status' => 'ok',
                'message' => 'No parameters provided. Use config, env, or server query parameters.',
            ]);
        }

        return response()->json($result);
    }

    private function validateDevAuth(): void
    {
        $devAuthAllowed = Config::get('app.dev-auth-allowed', false);

        if (! $devAuthAllowed) {
            abort(403, 'Debug access is not enabled');
        }

        $token = $this->getTokenFromRequest();
        $configuredToken = Config::get('app.dev-auth-token');

        if (! $token || $token !== $configuredToken) {
            abort(401, 'Invalid or missing authentication token');
        }
    }

    private function getTokenFromRequest(): ?string
    {
        $request = request();

        return $request->header('X-Dev-Token') ?? $request->input('token');
    }

    private function getConfigValues($configInput): mixed
    {
        if (is_array($configInput)) {
            $result = [];

            foreach ($configInput as $key) {
                $result[$key] = $this->getNestedConfig($key);
            }

            return $result;
        }

        return $this->getNestedConfig($configInput);
    }

    private function getNestedConfig(string $key): mixed
    {
        return Config::get($key);
    }

    private function getEnvValues($envInput): mixed
    {
        if ($envInput === 'all') {
            return $this->filterSensitiveEnv($_ENV);
        }

        if (is_array($envInput)) {
            $result = [];

            foreach ($envInput as $key) {
                $result[$key] = $this->filterSensitiveValue($key, env($key));
            }

            return $result;
        }

        return $this->filterSensitiveValue($envInput, env($envInput));
    }

    private function getServerValues($serverInput): mixed
    {
        if ($serverInput === 'all') {
            return $this->filterSensitiveServer($_SERVER);
        }

        return $_SERVER[$serverInput] ?? null;
    }

    private function filterSensitiveEnv(array $env): array
    {
        $sensitiveKeys = [
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'AWS_SECRET_ACCESS_KEY',
            'MAIL_PASSWORD',
            'API_KEY',
            'SECRET',
            'TOKEN',
            'PASSWORD',
            'PRIVATE_KEY',
        ];

        $filtered = [];

        foreach ($env as $key => $value) {
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;

                    break;
                }
            }

            if (! $isSensitive) {
                $filtered[$key] = $value;
            } else {
                $filtered[$key] = '***REDACTED***';
            }
        }

        return $filtered;
    }

    private function filterSensitiveValue(string $key, mixed $value): mixed
    {
        $sensitiveKeys = [
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'AWS_SECRET_ACCESS_KEY',
            'MAIL_PASSWORD',
            'API_KEY',
            'SECRET',
            'TOKEN',
            'PASSWORD',
            'PRIVATE_KEY',
        ];

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (stripos($key, $sensitiveKey) !== false) {
                return '***REDACTED***';
            }
        }

        return $value;
    }

    private function filterSensitiveServer(array $server): array
    {
        $sensitivePatterns = [
            'PASSWORD',
            'SECRET',
            'KEY',
            'TOKEN',
            'AUTH',
        ];

        $filtered = [];

        foreach ($server as $key => $value) {
            $isSensitive = false;

            foreach ($sensitivePatterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $isSensitive = true;

                    break;
                }
            }

            if (! $isSensitive) {
                $filtered[$key] = $value;
            } else {
                $filtered[$key] = '***REDACTED***';
            }
        }

        return $filtered;
    }
}
