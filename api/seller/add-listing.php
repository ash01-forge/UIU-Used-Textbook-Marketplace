<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Create Listing (api/seller/add-listing.php)
 *
 * Endpoint: POST /api/seller/add-listing.php
 * Access  : Authenticated Seller only
 * CSRF    : Required
 */

require_once __DIR__ . '/helpers.php';

sellerRequireMethod(['POST']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
requireCsrfToken();

$body = getJsonRequestBody();
$errors = [];

// 1. Title validation
$title = trim((string) ($body['title'] ?? ''));
if ($title === '') {
    $errors['title'] = 'Title is required.';
} elseif (mb_strlen($title) > 200) {
    $errors['title'] = 'Title must not exceed 200 characters.';
}

// 2. Course code validation
$courseCode = trim((string) ($body['course_code'] ?? ''));
if ($courseCode === '') {
    $errors['course_code'] = 'Course code is required.';
} elseif (mb_strlen($courseCode) > 30) {
    $errors['course_code'] = 'Course code must not exceed 30 characters.';
}

// 3. Department validation
$department = trim((string) ($body['department'] ?? ''));
if ($department === '') {
    $errors['department'] = 'Department is required.';
} elseif (mb_strlen($department) > 100) {
    $errors['department'] = 'Department must not exceed 100 characters.';
}

// 4. Price validation
$rawPrice = $body['price'] ?? null;
if ($rawPrice === null || $rawPrice === '') {
    $errors['price'] = 'Price is required.';
} elseif (!is_numeric($rawPrice) || (float) $rawPrice <= 0) {
    $errors['price'] = 'Price must be a positive number.';
} elseif ((float) $rawPrice > 999999.99) {
    $errors['price'] = 'Price exceeds maximum allowed value.';
}
$price = round((float) $rawPrice, 2);

// 5. Description validation
$description = trim((string) ($body['description'] ?? ''));
if ($description === '') {
    $errors['description'] = 'Description is required.';
} elseif (mb_strlen($description) < 5) {
    $errors['description'] = 'Description must be at least 5 characters long.';
}

// 6. Item type validation
$allowedItemTypes = ['Textbook', 'Notes', 'Lab Manual'];
$itemType = !empty($body['item_type']) ? trim((string) $body['item_type']) : 'Textbook';
if (!in_array($itemType, $allowedItemTypes, true)) {
    $errors['item_type'] = 'Item type must be one of: ' . implode(', ', $allowedItemTypes) . '.';
}

// 7. Condition type validation
$allowedConditions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];
$conditionType = !empty($body['condition_type']) ? trim((string) $body['condition_type']) : 'Good';
if (!in_array($conditionType, $allowedConditions, true)) {
    $errors['condition_type'] = 'Condition must be one of: ' . implode(', ', $allowedConditions) . '.';
}

// 8. Optional fields
$author = !empty($body['author']) ? trim((string) $body['author']) : null;
if ($author !== null && mb_strlen($author) > 150) {
    $errors['author'] = 'Author must not exceed 150 characters.';
}

$edition = !empty($body['edition']) ? trim((string) $body['edition']) : null;
if ($edition !== null && mb_strlen($edition) > 50) {
    $errors['edition'] = 'Edition must not exceed 50 characters.';
}

$subject = !empty($body['subject']) ? trim((string) $body['subject']) : null;
if ($subject !== null && mb_strlen($subject) > 100) {
    $errors['subject'] = 'Subject must not exceed 100 characters.';
}

$imageUrl = !empty($body['image_url']) ? trim((string) $body['image_url']) : null;
if ($imageUrl !== null && mb_strlen($imageUrl) > 500) {
    $errors['image_url'] = 'Image URL must not exceed 500 characters.';
}

$categoryId = null;
if (!empty($body['category_id'])) {
    if (!is_numeric($body['category_id']) || (int) $body['category_id'] < 1) {
        $errors['category_id'] = 'Category ID must be a positive integer.';
    } else {
        $categoryId = (int) $body['category_id'];
    }
}

if (!empty($errors)) {
    sendErrorResponse('Validation failed. Please correct the errors below.', 422, $errors);
}

$db = getDbConnection();
sellerValidateImageUrl($db, $imageUrl, $sellerId);

// If category_id was provided, verify it exists
if ($categoryId !== null) {
    $catCheck = $db->prepare('SELECT id FROM categories WHERE id = ?');
    $catCheck->execute([$categoryId]);
    if (!$catCheck->fetch()) {
        sendErrorResponse('Invalid category selected.', 422, [
            'category_id' => 'Selected category does not exist.',
        ]);
    }
}

// Security: status is ALWAYS pending_approval on creation.
// Privileged fields (reviewed_by, reviewed_at, admin_feedback) are NEVER set by seller.
$status = 'pending_approval';

$sql = 'INSERT INTO listings (
            seller_id, category_id, title, author, edition, course_code,
            department, subject, item_type, condition_type, price,
            description, image_url, status, admin_feedback, reviewed_by,
            reviewed_at, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, NULL, NULL,
            NULL, NOW(), NOW()
        )';

$stmt = $db->prepare($sql);
$stmt->execute([
    $sellerId,
    $categoryId,
    $title,
    $author,
    $edition,
    $courseCode,
    $department,
    $subject,
    $itemType,
    $conditionType,
    $price,
    $description,
    $imageUrl,
    $status,
]);

$listingId = (int) $db->lastInsertId();

// Retrieve created listing
$fetchStmt = $db->prepare('SELECT * FROM listings WHERE id = ?');
$fetchStmt->execute([$listingId]);
$newListing = $fetchStmt->fetch();

sendSuccessResponse('Listing submitted successfully and is pending admin approval.', [
    'listing' => sellerFormatListing($newListing),
], 201);
