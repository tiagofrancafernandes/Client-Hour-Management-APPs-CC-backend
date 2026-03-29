<?php

/**
 * Vercel Serverless Function Entry Point for Laravel API Routes
 * Routes all requests to the Laravel application
 */

// Set proper base path for Vercel serverless environment
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

// Simple debug endpoint
if (($_SERVER['REQUEST_URI'] ?? '') === '/api/debug' || ($_SERVER['PATH_INFO'] ?? '') === '/debug') {
    header('Content-Type: application/json');

    $debug = [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT SET',
        'SCRIPT_URL' => $_SERVER['SCRIPT_URL'] ?? 'NOT SET',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT SET',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'NOT SET',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT SET',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
        'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'NOT SET',
        'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NOT SET',
    ];

    echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    exit;
}

// Use REQUEST_URI as-is from Vercel - it already contains the full path with /api prefix
// Vercel sets both REQUEST_URI and PATH_INFO correctly:
// - REQUEST_URI = /api/health-check/database (with query string if present)
// - PATH_INFO = /health-check/database (just the captured path without /api)
// Laravel routing expects REQUEST_URI to be properly set

// Ensure REQUEST_URI is properly formatted for Laravel routing
if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === 'undefined') {
    $_SERVER['REQUEST_URI'] = '/';
} elseif ($_SERVER['REQUEST_URI'][0] !== '/') {
    $_SERVER['REQUEST_URI'] = '/' . $_SERVER['REQUEST_URI'];
}

// Call the main Laravel entry point
require_once dirname(__DIR__) . '/public/index.php';
