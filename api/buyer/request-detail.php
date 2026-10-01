<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/buyer/request-detail.php?id={id}
 *
 * Retrieves full details of a specific purchase request.
 * Enforces participant isolation: only the buyer or the seller of the request
 * can view its details.
 *
 * Access: Authenticated Buyer or Seller
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

$user = requireLogin();
$currentUserId = (int) $user['id'];

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($requestId <= 0) {
    sendErrorResponse('A valid request ID is required.', 422, ['id' => 'Valid request id is required.']);
}

$db = getDbConnection();

try {
    $stmt = $db->prepare("
        SELECT 
            pr.id,
            pr.listing_id,
            pr.buyer_id,
            pr.seller_id,
            pr.meeting_location,
            pr.preferred_date,
            pr.payment_method,
            pr.note,
            pr.status,
            pr.completed_at,
            pr.created_at,
            pr.updated_at,
            l.title AS book_title,
            l.author,
            l.edition,
            l.course_code,
            l.department,
            l.item_type,
            l.condition_type,
            l.price,
            l.image_url,
            l.status AS listing_status,
            b.full_name AS buyer_name,
            b.email AS buyer_email,
            b.phone AS buyer_phone,
            b.student_id AS buyer_student_id,
            s.full_name AS seller_name,
            s.email AS seller_email,
            s.phone AS seller_phone
        FROM purchase_requests pr
        JOIN listings l ON pr.listing_id = l.id
        JOIN users b ON pr.buyer_id = b.id
        JOIN users s ON pr.seller_id = s.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        sendErrorResponse('Purchase request not found.', 404);
    }

    $buyerId = (int) $request['buyer_id'];
    $sellerId = (int) $request['seller_id'];

    // Enforce participant isolation
    if ($currentUserId !== $buyerId && $currentUserId !== $sellerId && $user['role'] !== 'admin') {
        sendErrorResponse('Access forbidden. You are not a participant in this purchase request.', 403);
    }

    // Check if review exists
    $stmtReview = $db->prepare('SELECT id, rating, comment, created_at FROM reviews WHERE purchase_request_id = ?');
    $stmtReview->execute([$requestId]);
    $review = $stmtReview->fetch();

    $isBuyer = ($currentUserId === $buyerId);

    sendSuccessResponse('Purchase request details retrieved', [
        'request' => [
            'id'               => (int) $request['id'],
            'listing_id'       => (int) $request['listing_id'],
            'status'           => $request['status'],
            'meeting_location' => $request['meeting_location'],
            'preferred_date'   => $request['preferred_date'],
            'payment_method'   => $request['payment_method'],
            'note'             => $request['note'],
            'created_at'       => $request['created_at'],
            'completed_at'     => $request['completed_at'],
            'updated_at'       => $request['updated_at'],
        ],
        'listing' => [
            'id'             => (int) $request['listing_id'],
            'title'          => $request['book_title'],
            'author'         => $request['author'],
            'edition'        => $request['edition'],
            'course_code'    => $request['course_code'],
            'department'     => $request['department'],
            'item_type'      => $request['item_type'],
            'condition_type' => $request['condition_type'],
            'price'          => (float) $request['price'],
            'image_url'      => $request['image_url'],
            'status'         => $request['listing_status'],
        ],
        'buyer' => [
            'id'         => $buyerId,
            'name'       => $request['buyer_name'],
            'student_id' => $request['buyer_student_id'],
            'phone'      => in_array($request['status'], ['accepted', 'completed'], true) ? $request['buyer_phone'] : null,
        ],
        'seller' => [
            'id'    => $sellerId,
            'name'  => $request['seller_name'],
            'phone' => in_array($request['status'], ['accepted', 'completed'], true) ? $request['seller_phone'] : null,
        ],
        'review'      => $review ?: null,
        'user_role'   => $isBuyer ? 'buyer' : 'seller',
        'can_cancel'  => ($isBuyer && in_array($request['status'], ['pending', 'accepted'], true)),
        'can_review'  => ($isBuyer && $request['status'] === 'completed' && !$review),
    ]);

} catch (PDOException $e) {
    error_log('Request detail error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve request details.', 500);
}
