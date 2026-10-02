<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/buyer/seller-requests.php
 *
 * Lists all purchase requests received by the logged-in seller for their listings.
 * Supports filtering by listing_id and request status.
 *
 * Access: Authenticated Seller only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

$seller = requireRole('seller');
$sellerId = (int) $seller['id'];

$db = getDbConnection();

$listingIdFilter = isset($_GET['listing_id']) ? (int) $_GET['listing_id'] : 0;
$statusFilter = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : null;

try {
    $sql = "
        SELECT 
            pr.id,
            pr.listing_id,
            l.title AS book_title,
            l.price,
            l.status AS listing_status,
            pr.buyer_id,
            b.full_name AS buyer_name,
            b.student_id AS buyer_student_id,
            CASE 
                WHEN pr.status IN ('accepted', 'completed') THEN b.phone 
                ELSE NULL 
            END AS buyer_phone,
            pr.meeting_location,
            pr.preferred_date,
            pr.payment_method,
            pr.note,
            pr.status,
            pr.completed_at,
            pr.created_at,
            pr.updated_at
        FROM purchase_requests pr
        JOIN listings l ON pr.listing_id = l.id
        JOIN users b ON pr.buyer_id = b.id
        WHERE pr.seller_id = ?
    ";

    $params = [$sellerId];

    if ($listingIdFilter > 0) {
        $sql .= " AND pr.listing_id = ?";
        $params[] = $listingIdFilter;
    }

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

    $formatted = array_map(function ($req) {
        return [
            'id'               => (int) $req['id'],
            'listing_id'       => (int) $req['listing_id'],
            'book_title'       => $req['book_title'],
            'price'            => (float) $req['price'],
            'listing_status'   => $req['listing_status'],
            'buyer_id'         => (int) $req['buyer_id'],
            'buyer_name'       => $req['buyer_name'],
            'buyer_student_id' => $req['buyer_student_id'],
            'buyer_phone'      => $req['buyer_phone'],
            'meeting_location' => $req['meeting_location'],
            'preferred_date'   => $req['preferred_date'],
            'payment_method'   => $req['payment_method'],
            'note'             => $req['note'],
            'status'           => $req['status'],
            'can_accept'       => ($req['status'] === 'pending' && $req['listing_status'] === 'available'),
            'can_decline'      => in_array($req['status'], ['pending', 'accepted'], true),
            'can_complete'     => ($req['status'] === 'accepted'),
            'completed_at'     => $req['completed_at'],
            'created_at'       => $req['created_at'],
            'updated_at'       => $req['updated_at'],
        ];
    }, $requests);

    sendSuccessResponse('Seller purchase requests retrieved successfully', [
        'total'    => count($formatted),
        'requests' => $formatted,
    ]);

} catch (PDOException $e) {
    error_log('Seller requests fetch error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve requests.', 500);
}
