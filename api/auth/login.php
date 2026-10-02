<?php
/**
 * BookBridge – UIU Used Textbook Marketplace
 * POST /api/auth/login.php
 *
 * Authenticates user credentials and sets a secure session cookie.
 * Regenerates the session ID on successful login (prevents session fixation).
 *
 * Request body (JSON):
 *   email     string  required
 *   password  string  required
 *
 * Success 200:
 *   { "success": true, "message": "...", "data": { "user": {...}, "csrf_token": "..." } }
 *
 * Error 400/401:
 *   { "success": false, "message": "..." }
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 405);
}

// Parse JSON body
$body = getJsonRequestBody();

// -----------------------------------------------------------------------
// Input validation (basic presence check)
// -----------------------------------------------------------------------
$errors = [];

$email = strtolower(trim($body['email'] ?? ''));
if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

$password = $body['password'] ?? '';
if ($password === '') {
    $errors['password'] = 'Password is required.';
}

if (!empty($errors)) {
    sendErrorResponse('Login failed. Please check your credentials.', 400, $errors);
}

// -----------------------------------------------------------------------
// Database lookup and password verification
// -----------------------------------------------------------------------
try {
    $db = getDbConnection();

    $stmt = $db->prepare(
        'SELECT id, full_name, email, password_hash, role, student_id, phone, avatar_url
         FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Use constant-time comparison to prevent timing attacks.
    // Always run password_verify even if user not found (dummy hash).
    $dummyHash = '$2y$10$invalidhashusedtopreventimingtimingattacksonmissingemails';
    $hashToVerify = $user ? $user['password_hash'] : $dummyHash;
    $passwordOk = password_verify($password, $hashToVerify);

    if (!$user || !$passwordOk) {
        // Deliberate generic message — do not reveal whether the email exists
        sendErrorResponse('Invalid email address or password.', 401);
    }

    // Set session (regenerates session ID internally via session_regenerate_id)
    setSessionUser($user);
    $csrfToken = getCsrfToken();

    sendSuccessResponse('Login successful.', [
        'user'       => [
            'id'         => (int) $user['id'],
            'full_name'  => $user['full_name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'student_id' => $user['student_id'],
        ],
        'csrf_token' => $csrfToken,
    ]);

} catch (RuntimeException $e) {
    sendErrorResponse('A server error occurred. Please try again later.', 500);
}
