<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * POST /api/buyer/cancel-request.php
 *
 * Allows a buyer to cancel their own purchase request.
 * Permitted only when the request status is 'pending' or 'accepted'.
 *
 * Access: Authenticated Buyer only
 * Requires valid CSRF token.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 405);
}

$buyer = requireRole('buyer');
$buyerId = (int) $buyer['id'];
requireCsrfToken();

$body = getJsonRequestBody();
$requestId = isset($body['request_id']) ? (int) $body['request_id'] : 0;

if ($requestId <= 0) {
    sendErrorResponse('A valid request_id is required.', 422, ['request_id' => 'Valid request_id is required.']);
}

$db = getDbConnection();

try {
    $db->beginTransaction();

    // Lock request row
    $stmt = $db->prepare('SELECT id, listing_id, buyer_id, seller_id, status FROM purchase_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $db->rollBack();
        sendErrorResponse('Purchase request not found.', 404);
    }

    // Ownership check: only the buyer who created the request can cancel it
    if ((int) $request['buyer_id'] !== $buyerId) {
        $db->rollBack();
        sendErrorResponse('Access forbidden. You can only cancel your own purchase requests.', 403);
    }

    // Allowed statuses for cancellation: pending or accepted
    if (!in_array($request['status'], ['pending', 'accepted'], true)) {
        $db->rollBack();
        sendErrorResponse(
            'Cannot cancel request. Current status is "' . $request['status'] . '".',
            422,
            ['status' => 'Only pending or accepted requests can be cancelled.']
        );
    }

    // Update status to cancelled
    $stmtUpdate = $db->prepare("UPDATE purchase_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmtUpdate->execute([$requestId]);

    $db->commit();

    sendSuccessResponse('Purchase request cancelled successfully.', [
        'request_id' => $requestId,
        'status'     => 'cancelled',
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Cancel request error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while cancelling the purchase request.', 500);
}
