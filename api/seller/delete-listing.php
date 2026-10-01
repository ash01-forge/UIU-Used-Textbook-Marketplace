<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Delete Listing (api/seller/delete-listing.php)
 *
 * Endpoint: POST /api/seller/delete-listing.php (or DELETE)
 * Access  : Authenticated Seller only
 * CSRF    : Required
 */

require_once __DIR__ . '/helpers.php';

sellerRequireMethod(['POST', 'DELETE']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
requireCsrfToken();

$body = getJsonRequestBody();

$rawId = $body['listing_id'] ?? ($body['id'] ?? ($_GET['id'] ?? null));
if ($rawId === null) {
    sendErrorResponse('Listing ID is required.', 422, [
        'listing_id' => 'Please provide a valid listing ID.',
    ]);
}
$listingId = sellerPositiveId($rawId, 'listing_id');

$db = getDbConnection();

// Fetch listing
$stmt = $db->prepare('SELECT * FROM listings WHERE id = ?');
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    sendErrorResponse('Listing not found.', 404);
}

// Ownership verification
if ((int) $listing['seller_id'] !== $sellerId) {
    sendErrorResponse('Access forbidden. You do not have permission to delete this listing.', 403);
}

// 1. Cannot delete sold listings or listings with completed sales
if ($listing['status'] === 'sold') {
    sendErrorResponse('Cannot delete a sold listing as it represents a completed campus transaction.', 409, [
        'status' => 'Sold listings are preserved for sales records.',
    ]);
}

$completedStmt = $db->prepare("SELECT COUNT(*) FROM purchase_requests WHERE listing_id = ? AND status = 'completed'");
$completedStmt->execute([$listingId]);
if ((int) $completedStmt->fetchColumn() > 0) {
    sendErrorResponse('Cannot delete a listing associated with completed purchase records.', 409, [
        'purchase_requests' => 'Completed sales transactions depend on this listing.',
    ]);
}

// 2. Cannot delete listings with pending or accepted purchase requests
$activeStmt = $db->prepare("SELECT COUNT(*) FROM purchase_requests WHERE listing_id = ? AND status IN ('pending', 'accepted')");
$activeStmt->execute([$listingId]);
if ((int) $activeStmt->fetchColumn() > 0) {
    sendErrorResponse('Cannot delete a listing with active purchase requests. Please resolve or decline them first.', 409, [
        'purchase_requests' => 'Active buyer proposals exist for this listing.',
    ]);
}

// 3. Clean up associated local image if any
$imageUrl = $listing['image_url'] ?? '';
$deleteImageFile = null;
if (!empty($imageUrl) && strpos($imageUrl, 'uploads/listings/') === 0) {
    $projectRoot = realpath(__DIR__ . '/../../');
    $potentialPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imageUrl);
    if (file_exists($potentialPath) && is_file($potentialPath)) {
        $deleteImageFile = $potentialPath;
    }
}

// 4. Perform deletion
$deleteStmt = $db->prepare('DELETE FROM listings WHERE id = ? AND seller_id = ?');
$deleteStmt->execute([$listingId, $sellerId]);

if ($deleteImageFile !== null) {
    @unlink($deleteImageFile);
}

sendSuccessResponse('Listing deleted successfully.', [
    'deleted_listing_id' => $listingId,
]);
