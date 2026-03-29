<?php

/**
 * Vercel Serverless Function Entry Point
 * Routes all requests to the Laravel application
 */

// Set proper base path for Vercel serverless environment
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

// Call the main Laravel entry point
require_once dirname(__DIR__) . '/public/index.php';
