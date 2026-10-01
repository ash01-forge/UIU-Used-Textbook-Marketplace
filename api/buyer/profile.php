<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET / PUT / POST /api/buyer/profile.php
 *
 * Buyer profile management endpoint:
 * - GET: Retrieves the current buyer's profile details.
 * - POST / PUT: Updates permitted fields (full_name, phone, student_id, avatar_url).
 *
 * Access: Authenticated Buyer only
 * State-modifying requests require CSRF token.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$buyer = requireRole('buyer');
$buyerId = (int) $buyer['id'];

$db = getDbConnection();

// -------------------------------------------------------------------------
// GET: Retrieve buyer profile
// -------------------------------------------------------------------------
if ($method === 'GET') {
    try {
        $stmt = $db->prepare('SELECT id, full_name, email, role, student_id, phone, avatar_url, created_at FROM users WHERE id = ?');
        $stmt->execute([$buyerId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            sendErrorResponse('User profile not found.', 404);
        }

        sendSuccessResponse('Profile retrieved successfully', [
            'user' => $profile
        ]);
    } catch (PDOException $e) {
        error_log('Buyer profile retrieval error: ' . $e->getMessage());
        sendErrorResponse('Failed to retrieve profile.', 500);
    }
}

// -------------------------------------------------------------------------
// POST / PUT: Update permitted profile fields
// -------------------------------------------------------------------------
if ($method === 'POST' || $method === 'PUT') {
    // Validate CSRF token for modifications
    requireCsrfToken();

    $body = getJsonRequestBody();
    $errors = [];

    // 1. Validate full_name
    $fullName = trim($body['full_name'] ?? $buyer['full_name']);
    if ($fullName === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($fullName) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name cannot exceed 100 characters.';
    }

    // 2. Validate phone
    $phone = isset($body['phone']) ? trim((string) $body['phone']) : null;
    if ($phone !== null && $phone !== '' && mb_strlen($phone) > 20) {
        $errors['phone'] = 'Phone number cannot exceed 20 characters.';
    }
    if ($phone === '') {
        $phone = null;
    }

    // 3. Validate student_id
    $studentId = isset($body['student_id']) ? trim((string) $body['student_id']) : null;
    if ($studentId !== null && $studentId !== '' && mb_strlen($studentId) > 30) {
        $errors['student_id'] = 'Student ID cannot exceed 30 characters.';
    }
    if ($studentId === '') {
        $studentId = null;
    }

    // 4. Validate avatar_url
    $avatarUrl = isset($body['avatar_url']) ? trim((string) $body['avatar_url']) : null;
    if ($avatarUrl !== null && $avatarUrl !== '') {
        if (mb_strlen($avatarUrl) > 255) {
            $errors['avatar_url'] = 'Avatar URL cannot exceed 255 characters.';
        } elseif (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            $errors['avatar_url'] = 'Avatar URL must be a valid URL.';
        }
    }
    if ($avatarUrl === '') {
        $avatarUrl = null;
    }

    if (!empty($errors)) {
        sendErrorResponse('Validation failed. Please correct the errors.', 422, $errors);
    }

    try {
        $stmtUpdate = $db->prepare('
            UPDATE users 
            SET full_name = ?, phone = ?, student_id = ?, avatar_url = ?, updated_at = NOW() 
            WHERE id = ?
        ');
        $stmtUpdate->execute([$fullName, $phone, $studentId, $avatarUrl, $buyerId]);

        // Refresh user in session
        $stmtFresh = $db->prepare('SELECT id, full_name, email, role, student_id, phone, avatar_url FROM users WHERE id = ?');
        $stmtFresh->execute([$buyerId]);
        $updatedUser = $stmtFresh->fetch();
        setSessionUser($updatedUser);

        sendSuccessResponse('Profile updated successfully', [
            'user' => $updatedUser
        ]);

    } catch (PDOException $e) {
        error_log('Buyer profile update error: ' . $e->getMessage());
        sendErrorResponse('Failed to update profile.', 500);
    }
}

sendErrorResponse('Method not allowed. Use GET, POST, or PUT.', 405);
