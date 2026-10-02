<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/reviews/seller-reviews.php?seller_id={id}
 *
 * Retrieves reviews received by a specific seller, including aggregate rating
 * statistics (average rating, total reviews, and star breakdown).
 *
 * Access: Public / Authenticated
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

$sellerId = isset($_GET['seller_id']) ? (int) $_GET['seller_id'] : 0;
if ($sellerId <= 0) {
    sendErrorResponse('A valid seller_id query parameter is required.', 422, [
        'seller_id' => 'Valid seller ID is required.'
    ]);
}

$db = getDbConnection();

try {
    // 1. Verify seller exists
    $stmtSeller = $db->prepare('SELECT id, full_name, role FROM users WHERE id = ?');
    $stmtSeller->execute([$sellerId]);
    $seller = $stmtSeller->fetch();

    if (!$seller || $seller['role'] !== 'seller') {
        sendErrorResponse('Seller not found.', 404);
    }

    // 2. Fetch aggregate stats
    $stmtAgg = $db->prepare('
        SELECT 
            COUNT(*) AS total_reviews,
            COALESCE(AVG(rating), 0) AS average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS one_star
        FROM reviews
        WHERE seller_id = ?
    ');
    $stmtAgg->execute([$sellerId]);
    $stats = $stmtAgg->fetch();

    $totalReviews = (int) $stats['total_reviews'];
    $averageRating = round((float) $stats['average_rating'], 1);

    // 3. Fetch reviews list
    $stmtList = $db->prepare("
        SELECT 
            r.id,
            r.rating,
            r.comment,
            r.created_at,
            r.reviewer_id,
            u.full_name AS reviewer_name,
            u.avatar_url AS reviewer_avatar,
            r.listing_id,
            l.title AS book_title
        FROM reviews r
        JOIN users u ON r.reviewer_id = u.id
        LEFT JOIN listings l ON r.listing_id = l.id
        WHERE r.seller_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ");
    $stmtList->execute([$sellerId]);
    $reviews = $stmtList->fetchAll();

    $formattedReviews = array_map(function ($rev) {
        return [
            'id'              => (int) $rev['id'],
            'rating'          => (int) $rev['rating'],
            'comment'         => $rev['comment'],
            'created_at'      => $rev['created_at'],
            'reviewer_name'   => $rev['reviewer_name'],
            'reviewer_avatar' => $rev['reviewer_avatar'],
            'book_title'      => $rev['book_title'],
        ];
    }, $reviews);

    sendSuccessResponse('Seller reviews retrieved successfully', [
        'seller' => [
            'id'             => (int) $seller['id'],
            'name'           => $seller['full_name'],
            'total_reviews'  => $totalReviews,
            'average_rating' => $averageRating,
            'breakdown'      => [
                '5_star' => (int) $stats['five_star'],
                '4_star' => (int) $stats['four_star'],
                '3_star' => (int) $stats['three_star'],
                '2_star' => (int) $stats['two_star'],
                '1_star' => (int) $stats['one_star'],
            ]
        ],
        'reviews' => $formattedReviews,
    ]);

} catch (PDOException $e) {
    error_log('Seller reviews fetch error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve seller reviews.', 500);
}
