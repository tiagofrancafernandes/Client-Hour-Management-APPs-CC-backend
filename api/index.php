<?php

/**
 * Vercel Serverless Function Entry Point
 * Routes all requests to the Laravel application
 */

// Set proper base path for Vercel serverless environment
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

// Ensure REQUEST_URI is set and preserved for Laravel routing
if (!isset($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '') {
    $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'] ?? '/';
}

// Strip /api prefix if present (since we're already in api context)
// This ensures /api/health-check/database becomes /health-check/database for Laravel
if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 4);
}

if (!isset($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '') {
    $_SERVER['REQUEST_URI'] = '/';
}

// Call the main Laravel entry point
require_once dirname(__DIR__) . '/public/index.php';
