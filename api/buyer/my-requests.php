<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/buyer/my-requests.php
 *
 * Lists all purchase requests submitted by the logged-in buyer.
 * Supports optional filtering by request status.
 *
 * Access: Authenticated Buyer only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

$buyer = requireRole('buyer');
$buyerId = (int) $buyer['id'];

$db = getDbConnection();

$statusFilter = null;
if (isset($_GET['status'])) {
    if (!is_string($_GET['status'])) {
        sendErrorResponse('Invalid request status.', 422, ['status' => 'Status must be a string.']);
    }
    $statusFilter = strtolower(trim($_GET['status']));
    if (!in_array($statusFilter, ['', 'active', 'pending', 'accepted', 'declined', 'completed', 'cancelled'], true)) {
        sendErrorResponse('Invalid request status.', 422, ['status' => 'Choose a supported request status.']);
    }
}

try {
    $sql = "
        SELECT 
            pr.id,
            pr.listing_id,
            l.title AS book_title,
            l.author,
            l.edition,
            l.course_code,
            l.department,
            l.condition_type,
            l.price,
            l.image_url,
            pr.seller_id,
            u.full_name AS seller_name,
            CASE 
                WHEN pr.status IN ('accepted', 'completed') THEN u.phone 
                ELSE NULL 
            END AS seller_phone,
            pr.meeting_location,
            pr.preferred_date,
            pr.payment_method,
            pr.note,
            pr.status,
            pr.completed_at,
            pr.created_at,
            pr.updated_at,
            (SELECT COUNT(*) FROM reviews rev WHERE rev.purchase_request_id = pr.id) AS review_count
        FROM purchase_requests pr
        JOIN listings l ON pr.listing_id = l.id
        JOIN users u ON pr.seller_id = u.id
        WHERE pr.buyer_id = ?
    ";

    $params = [$buyerId];

    if ($statusFilter !== null && $statusFilter !== '') {
        if ($statusFilter === 'active') {
            $sql .= " AND pr.status IN ('pending', 'accepted')";
        } elseif (in_array($statusFilter, ['pending', 'accepted', 'declined', 'completed', 'cancelled'], true)) {
            $sql .= " AND pr.status = ?";
            $params[] = $statusFilter;
        }
    }

    $sql .= " ORDER BY pr.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Format output and add eligibility flags
    $formatted = array_map(function ($req) {
        $isCompleted = ($req['status'] === 'completed');
        $hasReviewed = ((int) $req['review_count']) > 0;
        return [
            'id'               => (int) $req['id'],
            'listing_id'       => (int) $req['listing_id'],
            'book_title'       => $req['book_title'],
            'author'           => $req['author'],
            'edition'          => $req['edition'],
            'course_code'      => $req['course_code'],
            'department'       => $req['department'],
            'condition_type'   => $req['condition_type'],
            'price'            => (float) $req['price'],
            'image_url'        => $req['image_url'],
            'seller_id'        => (int) $req['seller_id'],
            'seller_name'      => $req['seller_name'],
            'seller_phone'     => $req['seller_phone'],
            'meeting_location' => $req['meeting_location'],
            'preferred_date'   => $req['preferred_date'],
            'payment_method'   => $req['payment_method'],
            'note'             => $req['note'],
            'status'           => $req['status'],
            'can_cancel'       => in_array($req['status'], ['pending', 'accepted'], true),
            'can_review'       => ($isCompleted && !$hasReviewed),
            'has_reviewed'     => $hasReviewed,
            'completed_at'     => $req['completed_at'],
            'created_at'       => $req['created_at'],
            'updated_at'       => $req['updated_at'],
        ];
    }, $requests);

    sendSuccessResponse('Purchase requests retrieved successfully', [
        'total'    => count($formatted),
        'requests' => $formatted,
    ]);

} catch (PDOException $e) {
    error_log('Buyer requests fetch error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve purchase requests.', 500);
}
