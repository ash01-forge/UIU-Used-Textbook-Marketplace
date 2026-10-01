<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Listings Endpoint (api/seller/listings.php)
 *
 * Endpoints:
 *   GET    /api/seller/listings.php          - Returns seller's own listings (with optional filters/pagination)
 *   GET    /api/seller/listings.php?id={id}  - Returns details of a specific listing owned by seller
 *   POST   /api/seller/listings.php          - Creates a new listing (alias to add-listing.php)
 *   DELETE /api/seller/listings.php          - Deletes a listing owned by seller (alias to delete-listing.php)
 * Access  : Authenticated Seller only
 */

require_once __DIR__ . '/helpers.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
sellerRequireMethod(['GET', 'POST', 'DELETE']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
$db = getDbConnection();

if ($method === 'POST') {
    require __DIR__ . '/add-listing.php';
    exit;
}

if ($method === 'DELETE') {
    require __DIR__ . '/delete-listing.php';
    exit;
}

// ---------------------------------------------------------------------
// GET Request Handling
// ---------------------------------------------------------------------

// Check if a single listing detail is requested by ID
if (array_key_exists('id', $_GET)) {
    $listingId = sellerPositiveId($_GET['id'], 'id');

    $stmt = $db->prepare('SELECT * FROM listings WHERE id = ?');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        sendErrorResponse('Listing not found.', 404);
    }

    if ((int) $listing['seller_id'] !== $sellerId) {
        sendErrorResponse('Access forbidden. You do not have permission to view this listing.', 403);
    }

    sendSuccessResponse('Listing details retrieved.', [
        'listing' => sellerFormatListing($listing),
    ]);
}

// Multiple listings list with optional filters and pagination
$page = sellerQueryInt('page', 1, 1, 1000000);
$perPage = sellerQueryInt('per_page', 20, 1, 100);
$status = sellerQueryText('status', 30);
$department = sellerQueryText('department', 100);
$itemType = sellerQueryText('type', 50);
$search = sellerQueryText('search', 100);

$allowedStatuses = ['pending_approval', 'available', 'changes_requested', 'rejected', 'sold'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    sendErrorResponse('Invalid status filter.', 422, [
        'status' => 'Allowed values: ' . implode(', ', $allowedStatuses) . '.',
    ]);
}

$allowedTypes = ['Textbook', 'Notes', 'Lab Manual'];
if ($itemType !== '' && !in_array($itemType, $allowedTypes, true)) {
    sendErrorResponse('Invalid type filter.', 422, [
        'type' => 'Allowed values: ' . implode(', ', $allowedTypes) . '.',
    ]);
}

$conditions = ['seller_id = ?'];
$parameters = [$sellerId];

if ($status !== '') {
    $conditions[] = 'status = ?';
    $parameters[] = $status;
}

if ($department !== '') {
    $conditions[] = 'department = ?';
    $parameters[] = $department;
}

if ($itemType !== '') {
    $conditions[] = 'item_type = ?';
    $parameters[] = $itemType;
}

if ($search !== '') {
    $conditions[] = '(title LIKE ? OR course_code LIKE ? OR author LIKE ? OR subject LIKE ?)';
    $like = '%' . $search . '%';
    $parameters[] = $like;
    $parameters[] = $like;
    $parameters[] = $like;
    $parameters[] = $like;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Count total matching listings
$countStmt = $db->prepare("SELECT COUNT(*) FROM listings {$whereClause}");
$countStmt->execute($parameters);
$total = (int) $countStmt->fetchColumn();

$totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
$offset = ($page - 1) * $perPage;

// Fetch paginated rows
$sql = "SELECT * FROM listings {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($parameters);
$rows = $stmt->fetchAll();

$formattedListings = array_map('sellerFormatListing', $rows);

sendSuccessResponse('Seller listings retrieved.', [
    'listings'   => $formattedListings,
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ],
    'filters'    => [
        'status'     => $status !== '' ? $status : null,
        'department' => $department !== '' ? $department : null,
        'type'       => $itemType !== '' ? $itemType : null,
        'search'     => $search !== '' ? $search : null,
    ],
]);
