<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * API Health Check (api/health.php)
 *
 * Verifies system availability, PHP version, and database connectivity
 * without leaking internal credentials, host paths, or database errors.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';

// Safe database ping
$dbStatus = testDbConnection();
$isDbConnected = $dbStatus['connected'];

$isHealthy = $isDbConnected;
$statusCode = $isHealthy ? 200 : 503;

$payload = [
    'status'      => $isHealthy ? 'ok' : 'degraded',
    'timestamp'   => date('c'), // ISO 8601
    'environment' => getAppConfig()['app']['env'] ?? 'development',
    'php_version' => PHP_VERSION,
    'services'    => [
        'database' => $isDbConnected ? 'connected' : 'disconnected',
    ],
];

sendJsonResponse($payload, $statusCode);
