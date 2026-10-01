<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/reviews/my-reviews.php
 *
 * Retrieves all textbook and meetup reviews submitted by the logged-in buyer.
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

try {
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.purchase_request_id,
            r.listing_id,
            l.title AS book_title,
            l.price AS book_price,
            r.seller_id,
            s.full_name AS seller_name,
            r.rating,
            r.comment,
            r.created_at
        FROM reviews r
        JOIN users s ON r.seller_id = s.id
        LEFT JOIN listings l ON r.listing_id = l.id
        WHERE r.reviewer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$buyerId]);
    $reviews = $stmt->fetchAll();

    $formatted = array_map(function ($rev) {
        return [
            'id'                  => (int) $rev['id'],
            'purchase_request_id' => $rev['purchase_request_id'] ? (int) $rev['purchase_request_id'] : null,
            'listing_id'          => $rev['listing_id'] ? (int) $rev['listing_id'] : null,
            'book_title'          => $rev['book_title'],
            'book_price'          => $rev['book_price'] !== null ? (float) $rev['book_price'] : null,
            'seller_id'           => (int) $rev['seller_id'],
            'seller_name'         => $rev['seller_name'],
            'rating'              => (int) $rev['rating'],
            'comment'             => $rev['comment'],
            'created_at'          => $rev['created_at'],
        ];
    }, $reviews);

    sendSuccessResponse('My reviews retrieved successfully', [
        'total'   => count($formatted),
        'reviews' => $formatted,
    ]);

} catch (PDOException $e) {
    error_log('My reviews fetch error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve your reviews.', 500);
}
