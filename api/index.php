<?php

/**
 * Vercel Serverless Function Entry Point for Laravel API Routes
 * Routes all requests to the Laravel application
 */

// Set proper base path for Vercel serverless environment
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

// Use PATH_INFO for the request path when available (Vercel provides this)
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
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

// Call the main Laravel entry point
require_once dirname(__DIR__) . '/public/index.php';
