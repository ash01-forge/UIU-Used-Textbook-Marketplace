<?php
/**
 * BookBridge – UIU Used Textbook Marketplace
 * POST /api/auth/logout.php
 *
 * Destroys the session and clears the session cookie.
 * Requires CSRF validation on this state-changing request.
 *
 * Request headers:
 *   X-CSRF-Token: <token>   (or csrf_token field in JSON body)
 *
 * Success 200:
 *   { "success": true, "message": "Logged out successfully." }
 *
 * Error 401 if not logged in.
 * Error 403 if CSRF token is missing/invalid.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 405);
}

// Must be logged in to log out
if (!isLoggedIn()) {
    sendErrorResponse('You are not logged in.', 401);
}

// Validate CSRF token before destroying session
requireCsrfToken();

// Destroy session and cookie
destroySessionUser();

sendSuccessResponse('You have been logged out successfully.');
