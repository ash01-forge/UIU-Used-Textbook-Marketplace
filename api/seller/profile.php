<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Profile Management (api/seller/profile.php)
 *
 * Endpoints:
 *   GET /api/seller/profile.php  - View seller profile & metrics
 *   PUT /api/seller/profile.php  - Update seller profile (allowlisted fields only)
 * Access: Authenticated Seller only
 */

require_once __DIR__ . '/helpers.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
sellerRequireMethod(['GET', 'PUT', 'POST']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
$db = getDbConnection();

if ($method === 'GET') {
    $stmt = $db->prepare('SELECT id, full_name, email, role, student_id, phone, avatar_url, created_at, updated_at FROM users WHERE id = ?');
    $stmt->execute([$sellerId]);
    $profile = $stmt->fetch();

    if (!$profile) {
        sendErrorResponse('Seller profile not found.', 404);
    }

    // Include summary stats
    $countStmt = $db->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN status = "sold" THEN 1 ELSE 0 END) AS sold_cnt FROM listings WHERE seller_id = ?');
    $countStmt->execute([$sellerId]);
    $counts = $countStmt->fetch();

    $ratingStmt = $db->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_cnt FROM reviews WHERE seller_id = ?');
    $ratingStmt->execute([$sellerId]);
    $ratingData = $ratingStmt->fetch();

    sendSuccessResponse('Seller profile retrieved.', [
        'profile' => [
            'id'             => (int) $profile['id'],
            'full_name'      => $profile['full_name'],
            'email'          => $profile['email'],
            'role'           => $profile['role'],
            'student_id'     => $profile['student_id'] ?? null,
            'phone'          => $profile['phone'] ?? null,
            'avatar_url'     => $profile['avatar_url'] ?? null,
            'created_at'     => $profile['created_at'],
            'updated_at'     => $profile['updated_at'] ?? null,
            'total_listings' => (int) ($counts['total'] ?? 0),
            'sold_listings'  => (int) ($counts['sold_cnt'] ?? 0),
            'seller_rating'  => round((float) ($ratingData['avg_rating'] ?? 0), 1),
            'review_count'   => (int) ($ratingData['review_cnt'] ?? 0),
        ],
    ]);
}

// ---------------------------------------------------------------------
// PUT / POST Request Handling (Profile Update)
// ---------------------------------------------------------------------
requireCsrfToken();
$body = getJsonRequestBody();
$errors = [];
$updates = [];

// Explicit allowlist: full_name, phone, avatar_url
if (array_key_exists('full_name', $body)) {
    $fullName = trim((string) $body['full_name']);
    if ($fullName === '') {
        $errors['full_name'] = 'Full name cannot be empty.';
    } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
    } else {
        $updates['full_name'] = $fullName;
    }
}

if (array_key_exists('phone', $body)) {
    $phone = $body['phone'] !== null ? trim((string) $body['phone']) : null;
    if ($phone !== null && $phone !== '') {
        if (mb_strlen($phone) > 20) {
            $errors['phone'] = 'Phone number must not exceed 20 characters.';
        } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $errors['phone'] = 'Invalid phone number format.';
        } else {
            $updates['phone'] = $phone;
        }
    } else {
        $updates['phone'] = null;
    }
}

if (array_key_exists('avatar_url', $body)) {
    $avatarUrl = $body['avatar_url'] !== null ? trim((string) $body['avatar_url']) : null;
    if ($avatarUrl !== null && $avatarUrl !== '') {
        if (mb_strlen($avatarUrl) > 255) {
            $errors['avatar_url'] = 'Avatar URL must not exceed 255 characters.';
        } else {
            $updates['avatar_url'] = $avatarUrl;
        }
    } else {
        $updates['avatar_url'] = null;
    }
}

// Reject attempt to modify privileged fields
$blockedFields = ['id', 'email', 'role', 'password', 'password_hash', 'student_id'];
foreach ($blockedFields as $bf) {
    if (array_key_exists($bf, $body) && $body[$bf] !== ($user[$bf] ?? null)) {
        $errors[$bf] = 'This field cannot be modified through this endpoint.';
    }
}

if (!empty($errors)) {
    sendErrorResponse('Profile update validation failed.', 422, $errors);
}

if (empty($updates)) {
    sendSuccessResponse('No changes provided; profile remains unchanged.', [
        'user' => [
            'id'         => (int) $user['id'],
            'full_name'  => $user['full_name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'student_id' => $user['student_id'] ?? null,
            'phone'      => $user['phone'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
        ],
    ]);
}

$setParts = [];
$params = [];
foreach ($updates as $col => $val) {
    $setParts[] = "`{$col}` = ?";
    $params[] = $val;
}
$setParts[] = '`updated_at` = NOW()';
$params[] = $sellerId;

$sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?';
$updateStmt = $db->prepare($sql);
$updateStmt->execute($params);

// Update session profile
foreach ($updates as $k => $v) {
    $_SESSION['user'][$k] = $v;
}

$freshStmt = $db->prepare('SELECT id, full_name, email, role, student_id, phone, avatar_url, updated_at FROM users WHERE id = ?');
$freshStmt->execute([$sellerId]);
$updatedUser = $freshStmt->fetch();

sendSuccessResponse('Seller profile updated successfully.', [
    'user' => [
        'id'         => (int) $updatedUser['id'],
        'full_name'  => $updatedUser['full_name'],
        'email'      => $updatedUser['email'],
        'role'       => $updatedUser['role'],
        'student_id' => $updatedUser['student_id'] ?? null,
        'phone'      => $updatedUser['phone'] ?? null,
        'avatar_url' => $updatedUser['avatar_url'] ?? null,
        'updated_at' => $updatedUser['updated_at'],
    ],
]);
