<?php
/**
 * BookBridge – UIU Used Textbook Marketplace
 * GET /api/auth/csrf.php
 *
 * Issues a CSRF token for the current session.
 * This is typically called once by the frontend before sending any
 * state-modifying request (POST, PUT, DELETE).
 *
 * The returned token must be sent back in the X-CSRF-Token header
 * or as the csrf_token field in the JSON request body.
 *
 * Success 200:
 *   { "success": true, "data": { "csrf_token": "..." } }
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

// Initialise session so token is stored (works for both guests and logged-in users)
initSession();

sendSuccessResponse('CSRF token issued.', [
    'csrf_token' => getCsrfToken(),
]);
