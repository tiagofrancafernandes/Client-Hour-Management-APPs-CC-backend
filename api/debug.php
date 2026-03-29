<?php

/**
 * Debug endpoint to inspect server variables in Vercel serverless context
 * This helps us understand what values Vercel is passing
 */

header('Content-Type: application/json');

$debug_info = [
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT SET',
    'SCRIPT_URL' => $_SERVER['SCRIPT_URL'] ?? 'NOT SET',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT SET',
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'NOT SET',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT SET',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT SET',
];

echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
