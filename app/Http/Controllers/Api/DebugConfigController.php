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

        $hasAny = $request->has('config') || $request->has('env') || $request->has('server');

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

        if (!$hasAny) {
            return response()->json([
                'status' => 'ok',
                'message' => 'No parameters provided. Use config, env, or server query parameters.',
            ]);
        }

        return response()->json($result);
    }

    protected function getConfigValues($configInput): mixed
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

    protected function getNestedConfig(string $key): mixed
    {
        return Config::get($key);
    }

    protected function getEnvValues($envInput): mixed
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

    protected function getServerValues($serverInput): mixed
    {
        if ($serverInput === 'all') {
            return $this->filterSensitiveServer($_SERVER);
        }

        return $_SERVER[$serverInput] ?? null;
    }

    protected function filterSensitiveEnv(array $env): array
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

            if (!$isSensitive) {
                $filtered[$key] = $value;
            } else {
                $filtered[$key] = '***REDACTED***';
            }
        }

        return $filtered;
    }

    protected function filterSensitiveValue(string $key, mixed $value): mixed
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

    protected function filterSensitiveServer(array $server): array
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

            if (!$isSensitive) {
                $filtered[$key] = $value;
            } else {
                $filtered[$key] = '***REDACTED***';
            }
        }

        return $filtered;
    }

    public static function rootPathInfo(Request $request, ?string $file = null): array
    {
        $file = $file ?: __FILE__;

        return [
            'app_name' => config('app.name', 'Hour Ledger'),
            'path' => $request->path(),
            'file' => $file ? str_replace(base_path() . '/', '', $file) : null,
            'fullUrl' => $request->fullUrl(),
            'url' => $request->url(),
            'Laravel' => app()->version(),
            'version' => value(function () {
                $branch = config('dev-plug.project-state.git.branch');
                $commitShaShort = config('dev-plug.project-state.git.commit_sha_short');

                return implode(':', array_filter([
                    $branch,
                    $commitShaShort,
                ])) ?: null;
            }),
        ];
    }
}
