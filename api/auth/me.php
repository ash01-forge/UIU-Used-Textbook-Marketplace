<?php
/**
 * BookBridge – UIU Used Textbook Marketplace
 * GET /api/auth/me.php
 *
 * Returns the currently logged-in user's profile and a fresh CSRF token.
 * Returns 401 if not logged in.
 *
 * Success 200:
 *   { "success": true, "data": { "user": {...}, "csrf_token": "..." } }
 *
 * Error 401:
 *   { "success": false, "message": "Authentication required." }
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

// Require a valid session
$user = requireLogin();

// Return current user profile and a fresh CSRF token for subsequent requests
sendSuccessResponse('Session is active.', [
    'user'       => $user,
    'csrf_token' => getCsrfToken(),
]);
