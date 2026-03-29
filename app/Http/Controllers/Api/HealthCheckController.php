<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Basic health check - only checks if the application is up.
     */
    public function basic(): JsonResponse
    {
        return response()->json([
            'status' => 'up',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Database health check - tests connection and users table existence.
     */
    public function database(): JsonResponse
    {
        $checks = [
            'status' => 'down',
            'database' => [
                'connection' => false,
                'basic_query' => null,
                'basic_query_success' => false,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            // Test database connection
            DB::connection()->getPdo();
            $checks['database']['connection'] = true;

            // Check if users table exists
            $tableExists = DB::getSchemaBuilder()->hasTable('users');
            $checks['database']['users_table_exists'] = $tableExists;
            $checks['database']['basic_query'] = DB::select('select 1');
            $checks['database']['basic_query_success'] = boolval($checks['database']['basic_query']);

            $checks['status'] = $tableExists ? 'up' : 'degraded';
        } catch (\Throwable $e) {
            $checks['database']['error'] = $e->getMessage();

            if (config('app.debug')) {
                $checks['database']['trace'] = $e->getTrace();
            }
        }

        $statusCode = $checks['status'] === 'up' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    /**
     * Storage health check - tests read and write operations.
     */
    public function storage(): JsonResponse
    {
        $checks = [
            'status' => 'down',
            'storage' => [
                'read' => false,
                'write' => false,
                'delete' => false,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $testFile = 'health-check/' . uniqid() . '.txt';
        $testContent = 'Health check test - ' . now()->toIso8601String();

        try {
            // Test write
            Storage::disk('local')->put($testFile, $testContent);
            $checks['storage']['write'] = true;

            // Test read
            $content = Storage::disk('local')->get($testFile);
            $checks['storage']['read'] = $content === $testContent;

            // Test delete
            Storage::disk('local')->delete($testFile);
            $checks['storage']['delete'] = !Storage::disk('local')->exists($testFile);

            $checks['status'] = 'up';
        } catch (\Throwable $e) {
            $checks['storage']['error'] = $e->getMessage();
        }

        $statusCode = $checks['status'] === 'up' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    /**
     * Comprehensive health check - tests everything or selected checks.
     * Query parameters: checks=basic,database,storage (comma-separated)
     */
    public function all(Request $request): JsonResponse
    {
        $requestedChecks = $request->input('checks');
        $allChecks = ['basic', 'database', 'storage'];

        $checks = is_string($requestedChecks) ? array_map('trim', explode(',', $requestedChecks)) : $requestedChecks;

        $checks = is_array($checks) ? $checks : [];

        if (!$checks) {
            $checks = $allChecks;
        }

        $result = [
            'status' => 'up',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Basic check
        if (in_array('basic', $checks)) {
            $result['checks']['basic'] = [
                'status' => 'up',
            ];
        }

        // Database check
        if (in_array('database', $checks)) {
            $dbCheck = [
                'status' => 'down',
                'connection' => false,
                'users_table_exists' => false,
                'database' => [
                    'basic_query' => null,
                    'basic_query_success' => false,
                ],
            ];

            try {
                DB::connection()->getPdo();
                $dbCheck['connection'] = true;

                $tableExists = DB::getSchemaBuilder()->hasTable('users');
                $dbCheck['users_table_exists'] = $tableExists;

                $checks['database']['basic_query'] = DB::select('select 1');
                $checks['database']['basic_query_success'] = boolval($checks['database']['basic_query']);

                $dbCheck['status'] = $tableExists ? 'up' : 'degraded';

                if (!$tableExists) {
                    $result['status'] = 'degraded';
                }
            } catch (\Throwable $e) {
                $dbCheck['error'] = $e->getMessage();
                $result['status'] = 'down';
            }

            $result['checks']['database'] = $dbCheck;
        }

        // Storage check
        if (in_array('storage', $checks)) {
            $storageCheck = [
                'status' => 'down',
                'read' => false,
                'write' => false,
                'delete' => false,
            ];

            $testFile = 'health-check/' . uniqid() . '.txt';
            $testContent = 'Health check test - ' . now()->toIso8601String();

            try {
                // Test write
                Storage::disk('local')->put($testFile, $testContent);
                $storageCheck['write'] = true;

                // Test read
                $content = Storage::disk('local')->get($testFile);
                $storageCheck['read'] = $content === $testContent;

                // Test delete
                Storage::disk('local')->delete($testFile);
                $storageCheck['delete'] = !Storage::disk('local')->exists($testFile);

                $storageCheck['status'] = 'up';
            } catch (\Throwable $e) {
                $storageCheck['error'] = $e->getMessage();
                $result['status'] = 'down';
            }

            $result['checks']['storage'] = $storageCheck;
        }

        $statusCode = $result['status'] === 'up' ? 200 : ($result['status'] === 'degraded' ? 200 : 503);

        return response()->json($result, $statusCode);
    }
}
