<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * POST /api/buyer/purchase-request.php
 *
 * Submits a Cash on Meet purchase request for an available textbook listing.
 *
 * Strict platform rules enforced:
 * - Only authenticated buyers can submit purchase requests.
 * - Requesters cannot buy their own listings.
 * - Listings must be in 'available' status.
 * - Conflicting or duplicate active requests for the same listing by the buyer are rejected.
 * - Strictly Cash on Meet (no online payments).
 * - Concurrency protected via database transactions and row-level locking (FOR UPDATE).
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

// Require buyer role and CSRF validation
$buyer = requireRole('buyer');
$buyerId = (int) $buyer['id'];
requireCsrfToken();

$body = getJsonRequestBody();
$errors = [];

// 1. Validate listing_id
$listingId = isset($body['listing_id']) ? (int) $body['listing_id'] : 0;
if ($listingId <= 0) {
    $errors['listing_id'] = 'A valid listing ID is required.';
}

// 2. Validate meeting_location
$meetingLocation = trim((string) ($body['meeting_location'] ?? ''));
if ($meetingLocation === '') {
    $errors['meeting_location'] = 'Meeting location is required (e.g. UIU Library, Main Gate).';
} elseif (mb_strlen($meetingLocation) < 2) {
    $errors['meeting_location'] = 'Meeting location must be at least 2 characters.';
} elseif (mb_strlen($meetingLocation) > 150) {
    $errors['meeting_location'] = 'Meeting location cannot exceed 150 characters.';
}

// 3. Validate preferred_date
$preferredDate = trim((string) ($body['preferred_date'] ?? ''));
if ($preferredDate === '') {
    $errors['preferred_date'] = 'Preferred meeting date is required.';
} else {
    $dateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $preferredDate) {
        $errors['preferred_date'] = 'Preferred date must be in YYYY-MM-DD format.';
    } else {
        $today = new DateTime('today');
        if ($dateObj < $today) {
            $errors['preferred_date'] = 'Preferred date cannot be in the past.';
        }
    }
}

// 4. Validate payment_method policy (Strict Cash on Meet)
if (isset($body['payment_method'])) {
    $submittedPayment = strtolower(trim((string) $body['payment_method']));
    if ($submittedPayment !== 'cash_on_meet' && $submittedPayment !== 'cash on meet') {
        $errors['payment_method'] = 'Cash on Meet is the only supported payment method on BookBridge.';
    }
}

// 5. Validate note (optional)
$note = isset($body['note']) ? trim((string) $body['note']) : null;
if ($note !== null && mb_strlen($note) > 1000) {
    $errors['note'] = 'Note cannot exceed 1000 characters.';
}
if ($note === '') {
    $note = null;
}

if (!empty($errors)) {
    sendErrorResponse('Validation failed. Please correct the errors.', 422, $errors);
}

// Database transaction with row locking
$db = getDbConnection();

try {
    $db->beginTransaction();

    // Lock listing row to prevent race conditions
    $stmtListing = $db->prepare('
        SELECT id, seller_id, title, price, status 
        FROM listings 
        WHERE id = ? 
        FOR UPDATE
    ');
    $stmtListing->execute([$listingId]);
    $listing = $stmtListing->fetch();

    if (!$listing) {
        $db->rollBack();
        sendErrorResponse('Listing not found.', 404);
    }

    // Invariant: Requester cannot buy their own listing
    if ((int) $listing['seller_id'] === $buyerId) {
        $db->rollBack();
        sendErrorResponse('You cannot submit a purchase request for your own listing.', 422, [
            'listing_id' => 'You are the seller of this textbook.'
        ]);
    }

    // Invariant: Listing must be currently 'available'
    if ($listing['status'] !== 'available') {
        $db->rollBack();
        sendErrorResponse('This listing is no longer available for purchase.', 422, [
            'listing_id' => 'Listing status is ' . $listing['status'] . '.'
        ]);
    }

    // Invariant: Check if buyer already has an active request (pending or accepted)
    $stmtActiveCheck = $db->prepare("
        SELECT id, status 
        FROM purchase_requests 
        WHERE listing_id = ? AND buyer_id = ? AND status IN ('pending', 'accepted')
        FOR UPDATE
    ");
    $stmtActiveCheck->execute([$listingId, $buyerId]);
    $activeRequest = $stmtActiveCheck->fetch();

    if ($activeRequest) {
        $db->rollBack();
        sendErrorResponse('You already have an active purchase request for this textbook.', 422, [
            'listing_id' => 'Existing request #' . $activeRequest['id'] . ' is currently ' . $activeRequest['status'] . '.'
        ]);
    }

    $sellerId = (int) $listing['seller_id'];

    // Insert purchase request
    $stmtInsert = $db->prepare("
        INSERT INTO purchase_requests (
            listing_id,
            buyer_id,
            seller_id,
            meeting_location,
            preferred_date,
            payment_method,
            note,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 'cash_on_meet', ?, 'pending', NOW(), NOW())
    ");
    $stmtInsert->execute([
        $listingId,
        $buyerId,
        $sellerId,
        $meetingLocation,
        $preferredDate,
        $note
    ]);

    $newRequestId = (int) $db->lastInsertId();

    $db->commit();

    sendSuccessResponse('Purchase request submitted successfully! The seller will review your proposal.', [
        'request' => [
            'id'               => $newRequestId,
            'listing_id'       => $listingId,
            'listing_title'    => $listing['title'],
            'price'            => (float) $listing['price'],
            'seller_id'        => $sellerId,
            'meeting_location' => $meetingLocation,
            'preferred_date'   => $preferredDate,
            'payment_method'   => 'cash_on_meet',
            'note'             => $note,
            'status'           => 'pending',
        ]
    ], 201);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Purchase request creation error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while creating your purchase request.', 500);
}
