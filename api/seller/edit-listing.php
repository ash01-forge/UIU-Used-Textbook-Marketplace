<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Edit Listing (api/seller/edit-listing.php)
 *
 * Endpoint: PUT /api/seller/edit-listing.php (or POST)
 * Access  : Authenticated Seller only
 * CSRF    : Required
 */

require_once __DIR__ . '/helpers.php';

sellerRequireMethod(['PUT', 'POST']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
requireCsrfToken();

$body = getJsonRequestBody();

// Retrieve listing ID
$rawId = $body['id'] ?? ($body['listing_id'] ?? ($_GET['id'] ?? null));
if ($rawId === null) {
    sendErrorResponse('Listing ID is required.', 422, [
        'id' => 'Please provide a valid listing ID.',
    ]);
}
$listingId = sellerPositiveId($rawId, 'id');

$db = getDbConnection();

// Fetch existing listing
$stmt = $db->prepare('SELECT * FROM listings WHERE id = ?');
$stmt->execute([$listingId]);
$existing = $stmt->fetch();

if (!$existing) {
    sendErrorResponse('Listing not found.', 404);
}

// Ownership verification
if ((int) $existing['seller_id'] !== $sellerId) {
    sendErrorResponse('Access forbidden. You do not have permission to edit this listing.', 403);
}

// Disallow editing sold listings directly
if ($existing['status'] === 'sold') {
    sendErrorResponse('Sold listings cannot be edited. Mark it as unsold first if you wish to relist it.', 409, [
        'status' => 'Listing is currently sold.',
    ]);
}

$errors = [];
$updates = [];
$params = [];

// Validate editable allowlist fields if present in payload
if (array_key_exists('title', $body)) {
    $title = trim((string) $body['title']);
    if ($title === '') {
        $errors['title'] = 'Title cannot be empty.';
    } elseif (mb_strlen($title) > 200) {
        $errors['title'] = 'Title must not exceed 200 characters.';
    } else {
        $updates['title'] = $title;
    }
}

if (array_key_exists('course_code', $body)) {
    $courseCode = trim((string) $body['course_code']);
    if ($courseCode === '') {
        $errors['course_code'] = 'Course code cannot be empty.';
    } elseif (mb_strlen($courseCode) > 30) {
        $errors['course_code'] = 'Course code must not exceed 30 characters.';
    } else {
        $updates['course_code'] = $courseCode;
    }
}

if (array_key_exists('department', $body)) {
    $department = trim((string) $body['department']);
    if ($department === '') {
        $errors['department'] = 'Department cannot be empty.';
    } elseif (mb_strlen($department) > 100) {
        $errors['department'] = 'Department must not exceed 100 characters.';
    } else {
        $updates['department'] = $department;
    }
}

if (array_key_exists('price', $body)) {
    $rawPrice = $body['price'];
    if (!is_numeric($rawPrice) || (float) $rawPrice <= 0) {
        $errors['price'] = 'Price must be a positive number.';
    } elseif ((float) $rawPrice > 999999.99) {
        $errors['price'] = 'Price exceeds maximum allowed value.';
    } else {
        $updates['price'] = round((float) $rawPrice, 2);
    }
}

if (array_key_exists('description', $body)) {
    $description = trim((string) $body['description']);
    if ($description === '') {
        $errors['description'] = 'Description cannot be empty.';
    } elseif (mb_strlen($description) < 5) {
        $errors['description'] = 'Description must be at least 5 characters long.';
    } else {
        $updates['description'] = $description;
    }
}

if (array_key_exists('item_type', $body)) {
    $allowedItemTypes = ['Textbook', 'Notes', 'Lab Manual'];
    $itemType = trim((string) $body['item_type']);
    if (!in_array($itemType, $allowedItemTypes, true)) {
        $errors['item_type'] = 'Item type must be one of: ' . implode(', ', $allowedItemTypes) . '.';
    } else {
        $updates['item_type'] = $itemType;
    }
}

if (array_key_exists('condition_type', $body)) {
    $allowedConditions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];
    $conditionType = trim((string) $body['condition_type']);
    if (!in_array($conditionType, $allowedConditions, true)) {
        $errors['condition_type'] = 'Condition must be one of: ' . implode(', ', $allowedConditions) . '.';
    } else {
        $updates['condition_type'] = $conditionType;
    }
}

if (array_key_exists('author', $body)) {
    $author = $body['author'] !== null ? trim((string) $body['author']) : null;
    if ($author !== null && mb_strlen($author) > 150) {
        $errors['author'] = 'Author must not exceed 150 characters.';
    } else {
        $updates['author'] = $author;
    }
}

if (array_key_exists('edition', $body)) {
    $edition = $body['edition'] !== null ? trim((string) $body['edition']) : null;
    if ($edition !== null && mb_strlen($edition) > 50) {
        $errors['edition'] = 'Edition must not exceed 50 characters.';
    } else {
        $updates['edition'] = $edition;
    }
}

if (array_key_exists('subject', $body)) {
    $subject = $body['subject'] !== null ? trim((string) $body['subject']) : null;
    if ($subject !== null && mb_strlen($subject) > 100) {
        $errors['subject'] = 'Subject must not exceed 100 characters.';
    } else {
        $updates['subject'] = $subject;
    }
}

if (array_key_exists('image_url', $body)) {
    $imageUrl = $body['image_url'] !== null ? trim((string) $body['image_url']) : null;
    if ($imageUrl !== null && mb_strlen($imageUrl) > 500) {
        $errors['image_url'] = 'Image URL must not exceed 500 characters.';
    } else {
        $updates['image_url'] = $imageUrl;
    }
}

if (array_key_exists('category_id', $body)) {
    $rawCat = $body['category_id'];
    if ($rawCat === null || $rawCat === '') {
        $updates['category_id'] = null;
    } elseif (!is_numeric($rawCat) || (int) $rawCat < 1) {
        $errors['category_id'] = 'Category ID must be a positive integer.';
    } else {
        $catCheck = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $catCheck->execute([(int) $rawCat]);
        if (!$catCheck->fetch()) {
            $errors['category_id'] = 'Selected category does not exist.';
        } else {
            $updates['category_id'] = (int) $rawCat;
        }
    }
}

if (!empty($errors)) {
    sendErrorResponse('Validation failed. Please correct the errors below.', 422, $errors);
}

if (empty($updates)) {
    sendSuccessResponse('No changes provided; listing remains unchanged.', [
        'listing' => sellerFormatListing($existing),
    ]);
}

// Determine status transition:
// If status was changes_requested or rejected, editing resubmits it for review (pending_approval)
$newStatus = $existing['status'];
if (in_array($existing['status'], ['changes_requested', 'rejected'], true)) {
    $newStatus = 'pending_approval';
}
$updates['status'] = $newStatus;

// Build SET clauses
$setClauses = [];
$bindParams = [];
foreach ($updates as $col => $val) {
    $setClauses[] = "`{$col}` = ?";
    $bindParams[] = $val;
}
$setClauses[] = '`updated_at` = NOW()';
$bindParams[] = $listingId;
$bindParams[] = $sellerId;

$sql = 'UPDATE listings SET ' . implode(', ', $setClauses) . ' WHERE id = ? AND seller_id = ?';
$updateStmt = $db->prepare($sql);
$updateStmt->execute($bindParams);

// Fetch updated row
$refreshStmt = $db->prepare('SELECT * FROM listings WHERE id = ?');
$refreshStmt->execute([$listingId]);
$updatedListing = $refreshStmt->fetch();

$message = ($newStatus === 'pending_approval' && $existing['status'] !== 'pending_approval')
    ? 'Listing updated and resubmitted for admin approval.'
    : 'Listing updated successfully.';

sendSuccessResponse($message, [
    'listing' => sellerFormatListing($updatedListing),
]);
