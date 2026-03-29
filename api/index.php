<?php

/**
 * Vercel Serverless Function Entry Point
 * Routes all requests to the Laravel application
 */

// Set proper base path for Vercel serverless environment
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

// Use PATH_INFO for the request path when available (Vercel provides this)
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
}

// Fallback: construct REQUEST_URI from available data
if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
    $request_uri = $_SERVER['SCRIPT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '/';
    $_SERVER['REQUEST_URI'] = $request_uri;
}

// Strip /api prefix if it's at the start (we're being called from /api/index.php)
// This converts /api/health-check/database → /health-check/database
if (strpos($_SERVER['REQUEST_URI'], '/api') === 0) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 4);

    // Ensure REQUEST_URI starts with / for routing
    if (empty($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = '/';
    } elseif ($_SERVER['REQUEST_URI'][0] !== '/') {
        $_SERVER['REQUEST_URI'] = '/' . $_SERVER['REQUEST_URI'];
    }
}

// Ensure REQUEST_URI is never empty
if (empty($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/';
}

// Call the main Laravel entry point
require_once dirname(__DIR__) . '/public/index.php';
