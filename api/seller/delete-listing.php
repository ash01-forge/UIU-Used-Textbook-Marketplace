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

// Serialize deletion against listing changes and purchase-request creation.
try {
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT * FROM listings WHERE id = ? FOR UPDATE');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        $db->rollBack();
        sendErrorResponse('Listing not found.', 404);
    }
    if ((int) $listing['seller_id'] !== $sellerId) {
        $db->rollBack();
        sendErrorResponse('Access forbidden. You do not have permission to delete this listing.', 403);
    }
    $requests = $db->prepare("SELECT id FROM purchase_requests WHERE listing_id = ? AND status IN ('pending', 'accepted', 'completed') LIMIT 1");
    $requests->execute([$listingId]);
    if ($listing['status'] === 'sold' || $requests->fetch()) {
        $db->rollBack();
        sendErrorResponse('Sold listings and listings with active or completed purchase requests cannot be deleted.', 409);
    }
    $imageUrl = $listing['image_url'] ?? '';
    $deleteImageFile = sellerLocalImagePath($imageUrl);
    $deleteStmt = $db->prepare('DELETE FROM listings WHERE id = ? AND seller_id = ?');
    $deleteStmt->execute([$listingId, $sellerId]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Seller listing deletion failed: ' . $e->getMessage());
    sendErrorResponse('Unable to delete listing. Please try again later.', 500);
}

// Never remove a cover still referenced by another listing. Only generated
// direct image paths are eligible; traversal and links outside storage are ignored.
$imageRemoved = false;
if ($deleteImageFile !== null) {
    try {
        $references = $db->prepare('SELECT id FROM listings WHERE image_url = ? LIMIT 1');
        $references->execute([$imageUrl]);
        if (!$references->fetch()) {
            $imageRemoved = @unlink($deleteImageFile);
        }
    } catch (Throwable $e) {
        // Deletion already committed. Retain the image if references cannot be checked.
        error_log('Seller cover cleanup failed: ' . $e->getMessage());
    }
}

sendSuccessResponse('Listing deleted successfully.', [
    'deleted_listing_id' => $listingId,
    'image_removed' => $imageRemoved,
]);
