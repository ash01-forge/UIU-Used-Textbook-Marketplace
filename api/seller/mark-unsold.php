<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Mark Listing Unsold (api/seller/mark-unsold.php)
 *
 * Endpoint: POST /api/seller/mark-unsold.php
 * Access  : Authenticated Seller only
 * CSRF    : Required
 */

require_once __DIR__ . '/helpers.php';

sellerRequireMethod(['POST']);

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

try {
    $db->beginTransaction();

    // Row lock with FOR UPDATE to prevent concurrent stale transitions
    $stmt = $db->prepare('SELECT id, seller_id, status FROM listings WHERE id = ? FOR UPDATE');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        $db->rollBack();
        sendErrorResponse('Listing not found.', 404);
    }

    if ((int) $listing['seller_id'] !== $sellerId) {
        $db->rollBack();
        sendErrorResponse('Access forbidden. You do not have permission to modify this listing.', 403);
    }

    // Only sold listings can be marked as unsold; stale status returns 409 Conflict
    if ($listing['status'] !== 'sold') {
        $db->rollBack();
        sendErrorResponse('Only sold listings can be marked as unsold.', 409, [
            'status' => "Current status is '{$listing['status']}', not sold.",
        ]);
    }

    // Revert status to 'available' since it was an approved listing previously sold
    $updateStmt = $db->prepare("UPDATE listings SET status = 'available', updated_at = NOW() WHERE id = ? AND seller_id = ? AND status = 'sold'");
    $updateStmt->execute([$listingId, $sellerId]);

    if ($updateStmt->rowCount() === 0) {
        $db->rollBack();
        sendErrorResponse('Stale transition detected. Listing status was updated concurrently.', 409, [
            'status' => 'Status conflict occurred during update.',
        ]);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error marking listing as unsold: ' . $e->getMessage());
    sendErrorResponse('Unable to mark listing as unsold. Please try again later.', 500);
}

sendSuccessResponse('Listing marked as unsold and is now available in the marketplace.', [
    'listing_id' => $listingId,
    'status'     => 'available',
]);
