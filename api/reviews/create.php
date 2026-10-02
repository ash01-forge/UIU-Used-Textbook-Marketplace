<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * POST /api/reviews/create.php
 *
 * Submits a 1-5 star review for an eligible completed textbook purchase.
 *
 * Business Rules Enforced:
 * - Only authenticated buyers can submit reviews.
 * - The review must be linked to a purchase request with status = 'completed'.
 * - Requesters can only review purchases they made (buyer_id = session user id).
 * - Duplicate reviews for the same completed purchase request are rejected.
 * - Target seller and listing IDs are authoritatively extracted from the purchase_request record.
 * - Rating must be an integer between 1 and 5.
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
$errors = [];

// 1. Validate purchase_request_id
$requestId = isset($body['purchase_request_id']) ? (int) $body['purchase_request_id'] : 0;
if ($requestId <= 0) {
    $errors['purchase_request_id'] = 'A valid purchase_request_id is required.';
}

// 2. Validate rating (1 to 5)
$rating = isset($body['rating']) ? (int) $body['rating'] : 0;
if ($rating < 1 || $rating > 5) {
    $errors['rating'] = 'Rating must be an integer between 1 and 5 stars.';
}

// 3. Validate comment (optional, max 1000 chars)
$comment = isset($body['comment']) ? trim((string) $body['comment']) : null;
if ($comment !== null && mb_strlen($comment) > 1000) {
    $errors['comment'] = 'Review comment cannot exceed 1000 characters.';
}
if ($comment === '') {
    $comment = null;
}

if (!empty($errors)) {
    sendErrorResponse('Validation failed. Please correct the errors.', 422, $errors);
}

$db = getDbConnection();

try {
    $db->beginTransaction();
    // 1. Verify purchase request exists and belongs to the buyer
    $stmtPR = $db->prepare('
        SELECT id, listing_id, buyer_id, seller_id, status 
        FROM purchase_requests 
        WHERE id = ? FOR UPDATE
    ');
    $stmtPR->execute([$requestId]);
    $pr = $stmtPR->fetch();

    if (!$pr) {
        $db->rollBack();
        sendErrorResponse('Purchase request not found.', 404);
    }

    // Ownership check: only the buyer of the request can submit a review
    if ((int) $pr['buyer_id'] !== $buyerId) {
        $db->rollBack();
        sendErrorResponse('Access forbidden. You can only review your own purchases.', 403);
    }

    // Eligibility check: purchase must be completed
    if ($pr['status'] !== 'completed') {
        $db->rollBack();
        sendErrorResponse(
            'Cannot submit review. Purchase request is not completed (current status: "' . $pr['status'] . '").',
            422,
            ['status' => 'Reviews can only be submitted for completed transactions.']
        );
    }

    // Duplicate check: ensure no review already exists for this purchase request
    $stmtCheck = $db->prepare('SELECT id FROM reviews WHERE purchase_request_id = ?');
    $stmtCheck->execute([$requestId]);
    if ($stmtCheck->fetch()) {
        $db->rollBack();
        sendErrorResponse('You have already submitted a review for this purchase.', 422, [
            'purchase_request_id' => 'Duplicate review. Each completed purchase can only be reviewed once.'
        ]);
    }

    $sellerId = (int) $pr['seller_id'];
    $listingId = (int) $pr['listing_id'];

    // Insert review
    $stmtInsert = $db->prepare('
        INSERT INTO reviews (
            purchase_request_id,
            listing_id,
            reviewer_id,
            seller_id,
            rating,
            comment,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmtInsert->execute([$requestId, $listingId, $buyerId, $sellerId, $rating, $comment]);
    $newReviewId = (int) $db->lastInsertId();
    $db->commit();

    sendSuccessResponse('Review submitted successfully! Thank you for helping the UIU student community.', [
        'review' => [
            'id'                  => $newReviewId,
            'purchase_request_id' => $requestId,
            'listing_id'          => $listingId,
            'reviewer_id'         => $buyerId,
            'seller_id'           => $sellerId,
            'rating'              => $rating,
            'comment'             => $comment,
            'created_at'          => date('Y-m-d H:i:s'),
        ]
    ], 201);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Review creation error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while submitting the review.', 500);
}
