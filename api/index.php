<?php

/**
 * Vercel Serverless Function Entry Point for Laravel API Routes
 * Routes all requests to the Laravel application
 */

// Set proper base path for Vercel serverless environment
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

// Debug: Log incoming variables
$debug_log = fopen('/tmp/api_debug.log', 'a');
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n");
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] PATH_INFO=" . ($_SERVER['PATH_INFO'] ?? 'NOT SET') . "\n");
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] SCRIPT_URL=" . ($_SERVER['SCRIPT_URL'] ?? 'NOT SET') . "\n");
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] SCRIPT_NAME=" . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET') . "\n");

// Use PATH_INFO for the request path when available (Vercel provides this)
// Vercel routes /api/(.*) to /api/index.php and sets PATH_INFO to the captured group
// For example: /api/health-check/database → /api/index.php with PATH_INFO=/health-check/database
// We need to reconstruct the full REQUEST_URI with /api prefix for Laravel routing
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $_SERVER['REQUEST_URI'] = '/api' . $_SERVER['PATH_INFO'];
} elseif (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
    // Fallback: construct REQUEST_URI from available data
    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_URL'] ?? '/';
}

// Ensure REQUEST_URI is properly formatted for Laravel routing
if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === 'undefined') {
    $_SERVER['REQUEST_URI'] = '/';
} elseif ($_SERVER['REQUEST_URI'][0] !== '/') {
    $_SERVER['REQUEST_URI'] = '/' . $_SERVER['REQUEST_URI'];
}

fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] FINAL REQUEST_URI=" . $_SERVER['REQUEST_URI'] . "\n");
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "\n");
fclose($debug_log);

// Call the main Laravel entry point
require_once dirname(__DIR__) . '/public/index.php';
