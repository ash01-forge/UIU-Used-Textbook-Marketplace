<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Mark Listing Sold (api/seller/mark-sold.php)
 *
 * Endpoint: POST /api/seller/mark-sold.php
 * Access  : Authenticated Seller only
 * CSRF    : Required
 *
 * NOTE: Module ownership rule — Purchase request status completion belongs to Tanvir.
 * This endpoint only transitions the seller's listing status to 'sold' using concurrency-safe locking.
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

    // Row lock with FOR UPDATE to prevent stale concurrent transitions
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

    // If already marked as sold, return 409 Conflict
    if ($listing['status'] === 'sold') {
        $db->rollBack();
        sendErrorResponse('Listing is already marked as sold.', 409, [
            'status' => 'Current status is already sold.',
        ]);
    }

    // Only approved, available listings can transition to sold
    if ($listing['status'] !== 'available') {
        $db->rollBack();
        sendErrorResponse('Only approved, available listings can be marked as sold.', 422, [
            'status' => "Cannot mark a listing with status '{$listing['status']}' as sold.",
        ]);
    }

    // PR #8 Fix: Manual mark-sold is blocked while an accepted request exists
    $reqStmt = $db->prepare("SELECT COUNT(*) FROM purchase_requests WHERE listing_id = ? AND status = 'accepted'");
    $reqStmt->execute([$listingId]);
    if ((int) $reqStmt->fetchColumn() > 0) {
        $db->rollBack();
        sendErrorResponse('Cannot manually mark as sold while an accepted purchase request exists. Please complete or decline the meetup request.', 409, [
            'purchase_requests' => 'An accepted purchase request is active for this listing.',
        ]);
    }

    // Conditional atomic update ensuring previous status was strictly 'available'
    $updateStmt = $db->prepare("UPDATE listings SET status = 'sold', updated_at = NOW() WHERE id = ? AND seller_id = ? AND status = 'available'");
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
    error_log('Error marking listing as sold: ' . $e->getMessage());
    sendErrorResponse('Unable to mark listing as sold. Please try again later.', 500);
}

sendSuccessResponse('Listing marked as sold successfully.', [
    'listing_id' => $listingId,
    'status'     => 'sold',
]);
