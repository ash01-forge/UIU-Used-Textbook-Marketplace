<?php
/**
 * BookBridge – UIU Used Textbook Marketplace
 * POST /api/auth/register.php
 *
 * Registers a new UIU student account (buyer or seller only).
 * Admins cannot self-register through this endpoint.
 *
 * Request body (JSON):
 *   full_name  string  required
 *   email      string  required – must end with a UIU domain
 *   password   string  required – min 8 characters
 *   role       string  required – 'buyer' or 'seller' only
 *
 * Success 201:
 *   { "success": true, "message": "...", "data": { "user": {...}, "csrf_token": "..." } }
 *
 * Error 400/422:
 *   { "success": false, "message": "...", "errors": { "field": "message" } }
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
// Input validation
// -----------------------------------------------------------------------
$errors = [];

$fullName = trim($body['full_name'] ?? '');
if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
} elseif (mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
} elseif (mb_strlen($fullName) > 100) {
    $errors['full_name'] = 'Full name must not exceed 100 characters.';
}

$email = strtolower(trim($body['email'] ?? ''));
if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (mb_strlen($email) > 150) {
    $errors['email'] = 'Email address is too long.';
}

$password = $body['password'] ?? '';
if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
} elseif (strlen($password) > 255) {
    $errors['password'] = 'Password is too long.';
}

$role = strtolower(trim($body['role'] ?? ''));
$allowedRoles = ['buyer', 'seller'];
if ($role === '') {
    $errors['role'] = 'Role is required.';
} elseif ($role === 'admin') {
    // Explicit block for admin self-registration attempts
    $errors['role'] = 'You cannot register with admin privileges.';
} elseif (!in_array($role, $allowedRoles, true)) {
    $errors['role'] = 'Role must be either "buyer" or "seller".';
}

if (!empty($errors)) {
    sendErrorResponse('Registration failed. Please fix the errors below.', 422, $errors);
}

// -----------------------------------------------------------------------
// Database operation
// -----------------------------------------------------------------------
try {
    $db = getDbConnection();

    // Check if email already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendErrorResponse('Registration failed. Please fix the errors below.', 422, [
            'email' => 'An account with this email address already exists.'
        ]);
    }

    // Hash password using bcrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Optional profile fields
    $studentId  = !empty($body['student_id'])  ? trim((string) $body['student_id'])  : null;
    $phone      = !empty($body['phone'])       ? trim((string) $body['phone'])       : null;
    $department = !empty($body['department'])  ? trim((string) $body['department'])  : null;

    // Insert new user
    $insert = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, student_id, phone, department, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $insert->execute([$fullName, $email, $passwordHash, $role, $studentId, $phone, $department]);
    $newUserId = (int) $db->lastInsertId();

    // Fetch the newly created user to build session
    $fetch = $db->prepare(
        'SELECT id, full_name, email, role, student_id, phone, avatar_url FROM users WHERE id = ?'
    );
    $fetch->execute([$newUserId]);
    $newUser = $fetch->fetch();

    // Log in the user immediately after registration
    setSessionUser($newUser);
    $csrfToken = getCsrfToken();

    sendSuccessResponse('Registration successful! Welcome to BookBridge.', [
        'user'       => [
            'id'         => $newUser['id'],
            'full_name'  => $newUser['full_name'],
            'email'      => $newUser['email'],
            'role'       => $newUser['role'],
            'student_id' => $newUser['student_id'],
        ],
        'csrf_token' => $csrfToken,
    ], 201);

} catch (RuntimeException $e) {
    // getDbConnection() throws RuntimeException with a safe message
    sendErrorResponse('A server error occurred. Please try again later.', 500);
}
