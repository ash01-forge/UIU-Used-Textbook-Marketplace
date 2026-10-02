<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/buyer/dashboard.php
 *
 * Buyer dashboard endpoint:
 * Returns overview statistics (wishlist count, active requests, completed purchases,
 * money saved), recommended available books, and recent purchase requests.
 *
 * Access: Authenticated Buyer only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

// Ensure the user is logged in as a buyer
$buyer = requireRole('buyer');
$buyerId = (int) $buyer['id'];

try {
    $db = getDbConnection();

    // 1. Wishlist item count
    $stmtWishlist = $db->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
    $stmtWishlist->execute([$buyerId]);
    $wishlistCount = (int) $stmtWishlist->fetchColumn();

    // 2. Active purchase requests count (pending or accepted)
    $stmtActive = $db->prepare("SELECT COUNT(*) FROM purchase_requests WHERE buyer_id = ? AND status IN ('pending', 'accepted')");
    $stmtActive->execute([$buyerId]);
    $activeRequestsCount = (int) $stmtActive->fetchColumn();

    // 3. Completed purchases count
    $stmtCompleted = $db->prepare("SELECT COUNT(*) FROM purchase_requests WHERE buyer_id = ? AND status = 'completed'");
    $stmtCompleted->execute([$buyerId]);
    $completedPurchasesCount = (int) $stmtCompleted->fetchColumn();

    // Historical amounts cannot be inferred from mutable listing prices.
    // Neither sale-time prices nor retail comparison prices are recorded.
    $totalSpent = null;
    $estimatedSavings = null;

    // 5. Recommended available listings (up to 4 items)
    $stmtRec = $db->prepare("
        SELECT 
            l.id,
            l.seller_id,
            l.title,
            l.author,
            l.edition,
            l.course_code,
            l.department,
            l.item_type,
            l.condition_type,
            l.price,
            l.image_url,
            u.full_name AS seller_name
        FROM listings l
        JOIN users u ON l.seller_id = u.id
        WHERE l.status = 'available' AND l.seller_id != ?
        ORDER BY l.created_at DESC
        LIMIT 4
    ");
    $stmtRec->execute([$buyerId]);
    $recommended = $stmtRec->fetchAll();

    // 6. Recent purchase requests (up to 5 items)
    $stmtRecent = $db->prepare("
        SELECT 
            pr.id,
            pr.listing_id,
            l.title AS book_title,
            l.price,
            u.full_name AS seller_name,
            pr.meeting_location,
            pr.preferred_date,
            pr.payment_method,
            pr.status,
            pr.created_at
        FROM purchase_requests pr
        JOIN listings l ON pr.listing_id = l.id
        JOIN users u ON pr.seller_id = u.id
        WHERE pr.buyer_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 5
    ");
    $stmtRecent->execute([$buyerId]);
    $recentRequests = $stmtRecent->fetchAll();

    sendSuccessResponse('Buyer dashboard retrieved successfully', [
        'stats' => [
            'wishlist_count'      => $wishlistCount,
            'active_requests'     => $activeRequestsCount,
            'completed_purchases' => $completedPurchasesCount,
            'total_spent'         => $totalSpent,
            'money_saved'         => $estimatedSavings,
            'amounts_note'        => 'Unavailable: transaction-time and retail comparison prices are not recorded.',
        ],
        'recommended_listings' => $recommended,
        'recent_requests'      => $recentRequests,
    ]);

} catch (PDOException $e) {
    error_log('Buyer dashboard error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve buyer dashboard data.', 500);
}
