<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * POST /api/buyer/seller-request-action.php
 *
 * Allows a seller to accept, decline, or complete a purchase request for their textbook listing.
 *
 * State transitions and invariants enforced:
 * 1. 'accept':
 *    - Request must be 'pending'.
 *    - Listing must be 'available'.
 *    - Concurrency check: No other request for this listing can already be 'accepted'.
 *    - Status transitions to 'accepted'.
 * 2. 'decline':
 *    - Request must be 'pending' or 'accepted'.
 *    - Status transitions to 'declined'.
 * 3. 'complete' (Cash on Meet concluded):
 *    - Request must be 'accepted'.
 *    - Listing transitions to 'sold'.
 *    - Request transitions to 'completed' with completed_at timestamp.
 *    - All other remaining pending requests for this listing are declined.
 *
 * Concurrency protected with database transactions and row-level locking (FOR UPDATE).
 *
 * Access: Authenticated Seller only
 * Requires valid CSRF token.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 405);
}

$seller = requireRole('seller');
$sellerId = (int) $seller['id'];
requireCsrfToken();

$body = getJsonRequestBody();
$requestId = isset($body['request_id']) ? (int) $body['request_id'] : 0;
$action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : '';

$errors = [];
if ($requestId <= 0) {
    $errors['request_id'] = 'A valid request_id is required.';
}
if (!in_array($action, ['accept', 'decline', 'complete'], true)) {
    $errors['action'] = 'Action must be "accept", "decline", or "complete".';
}

if (!empty($errors)) {
    sendErrorResponse('Validation failed. Please correct the errors.', 422, $errors);
}

$db = getDbConnection();

try {
    $db->beginTransaction();

    // 1. Lock the purchase request
    $stmtReq = $db->prepare('
        SELECT id, listing_id, buyer_id, seller_id, status 
        FROM purchase_requests 
        WHERE id = ? 
        FOR UPDATE
    ');
    $stmtReq->execute([$requestId]);
    $request = $stmtReq->fetch();

    if (!$request) {
        $db->rollBack();
        sendErrorResponse('Purchase request not found.', 404);
    }

    $listingId = (int) $request['listing_id'];

    // 2. Lock the listing
    $stmtListing = $db->prepare('
        SELECT id, seller_id, title, status, price 
        FROM listings 
        WHERE id = ? 
        FOR UPDATE
    ');
    $stmtListing->execute([$listingId]);
    $listing = $stmtListing->fetch();

    if (!$listing) {
        $db->rollBack();
        sendErrorResponse('Associated listing not found.', 404);
    }

    // Ownership check: seller must own this listing and request
    if ((int) $listing['seller_id'] !== $sellerId || (int) $request['seller_id'] !== $sellerId) {
        $db->rollBack();
        sendErrorResponse('Access forbidden. You can only manage purchase requests for your own listings.', 403);
    }

    $currentStatus = $request['status'];

    if ($action === 'accept') {
        // Invariant: Request must be 'pending'
        if ($currentStatus !== 'pending') {
            $db->rollBack();
            sendErrorResponse('Cannot accept request. Only pending requests can be accepted (current: ' . $currentStatus . ').', 422);
        }

        // Invariant: Listing must be 'available'
        if ($listing['status'] !== 'available') {
            $db->rollBack();
            sendErrorResponse('Cannot accept request. Listing is currently "' . $listing['status'] . '".', 422);
        }

        // Invariant: No other request for this listing can already be 'accepted'
        $stmtConflict = $db->prepare("
            SELECT id FROM purchase_requests 
            WHERE listing_id = ? AND status = 'accepted' AND id != ?
            FOR UPDATE
        ");
        $stmtConflict->execute([$listingId, $requestId]);
        $conflicting = $stmtConflict->fetch();

        if ($conflicting) {
            $db->rollBack();
            sendErrorResponse('Another purchase request (# ' . $conflicting['id'] . ') is already accepted for this listing. Complete or decline it first.', 422);
        }

        // Update request to 'accepted'
        $stmtUpdate = $db->prepare("UPDATE purchase_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$requestId]);

        $db->commit();

        sendSuccessResponse('Purchase request accepted! You can now coordinate meeting details in Messages.', [
            'request_id'     => $requestId,
            'status'         => 'accepted',
            'listing_id'     => $listingId,
            'listing_status' => $listing['status'],
        ]);

    } elseif ($action === 'decline') {
        // Can decline if 'pending' or 'accepted'
        if (!in_array($currentStatus, ['pending', 'accepted'], true)) {
            $db->rollBack();
            sendErrorResponse('Cannot decline request in its current status (' . $currentStatus . ').', 422);
        }

        $stmtUpdate = $db->prepare("UPDATE purchase_requests SET status = 'declined', updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$requestId]);

        $db->commit();

        sendSuccessResponse('Purchase request declined.', [
            'request_id' => $requestId,
            'status'     => 'declined',
        ]);

    } elseif ($action === 'complete') {
        // Invariant: Only 'accepted' requests can be marked completed
        if ($currentStatus !== 'accepted') {
            $db->rollBack();
            sendErrorResponse('Cannot complete request. Only an accepted request can be marked as completed (current: ' . $currentStatus . ').', 422);
        }

        // Invariant: Listing must not already be sold
        if ($listing['status'] === 'sold') {
            $db->rollBack();
            sendErrorResponse('Listing is already marked as sold.', 422);
        }

        // 1. Mark listing as 'sold'
        $stmtSold = $db->prepare("UPDATE listings SET status = 'sold', updated_at = NOW() WHERE id = ?");
        $stmtSold->execute([$listingId]);

        // 2. Mark this request as 'completed'
        $stmtComplete = $db->prepare("
            UPDATE purchase_requests 
            SET status = 'completed', completed_at = NOW(), sale_price = ?, updated_at = NOW()
            WHERE id = ?
        ");
        // The listing row is locked; bind the decimal string without float rounding.
        $stmtComplete->execute([$listing['price'], $requestId]);

        // 3. Invariant: Clean up any other remaining pending requests for this now-sold listing
        $stmtCleanup = $db->prepare("
            UPDATE purchase_requests 
            SET status = 'declined', updated_at = NOW() 
            WHERE listing_id = ? AND id != ? AND status = 'pending'
        ");
        $stmtCleanup->execute([$listingId, $requestId]);

        $db->commit();

        sendSuccessResponse('Transaction completed successfully! Listing is now marked as sold.', [
            'request_id'     => $requestId,
            'status'         => 'completed',
            'listing_id'     => $listingId,
            'listing_status' => 'sold',
            'sale_price'     => (float)$listing['price'],
        ]);
    }

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Seller request action error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while updating the purchase request.', 500);
}
