<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Dashboard Statistics (api/seller/dashboard.php)
 *
 * Endpoint: GET /api/seller/dashboard.php
 * Access  : Authenticated Seller only
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../includes/sales.php';

sellerRequireMethod(['GET']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
$db = getDbConnection();

// 1. Total listings and counts by status
$statusStmt = $db->prepare('SELECT status, COUNT(*) AS cnt FROM listings WHERE seller_id = ? GROUP BY status');
$statusStmt->execute([$sellerId]);
$statusRows = $statusStmt->fetchAll();

$counts = [
    'pending_approval'  => 0,
    'available'         => 0,
    'changes_requested' => 0,
    'rejected'          => 0,
    'sold'              => 0,
];

$totalListings = 0;
foreach ($statusRows as $r) {
    $st = $r['status'];
    $cnt = (int) $r['cnt'];
    $totalListings += $cnt;
    if (array_key_exists($st, $counts)) {
        $counts[$st] = $cnt;
    }
}

// 2. Immutable completed-sale amounts, scoped to this seller.
$summary = saleSummary($db, "pr.status = 'completed' AND pr.seller_id = ?", [$sellerId]);
$revenue = $summary['revenue'];
$revenueNote = $summary['revenue_note'];

// 3. Average rating and total review count from buyers
$ratingStmt = $db->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_cnt FROM reviews WHERE seller_id = ?');
$ratingStmt->execute([$sellerId]);
$ratingData = $ratingStmt->fetch();
$avgRating = round((float) ($ratingData['avg_rating'] ?? 0), 1);
$reviewCount = (int) ($ratingData['review_cnt'] ?? 0);

// 4. Active purchase requests count
$reqStmt = $db->prepare("SELECT COUNT(*) AS active_reqs FROM purchase_requests WHERE seller_id = ? AND status IN ('pending', 'accepted')");
$reqStmt->execute([$sellerId]);
$activeRequests = (int) $reqStmt->fetchColumn();

sendSuccessResponse('Seller dashboard metrics retrieved.', [
    'stats' => [
        'total_listings'        => $totalListings,
        'active_listings'       => $counts['available'],
        'pending_approval'      => $counts['pending_approval'],
        'changes_requested'     => $counts['changes_requested'],
        'rejected'              => $counts['rejected'],
        'sold_listings'         => $counts['sold'],
        'total_revenue'         => $revenue,
        'revenue'               => $revenue,
        'revenue_note'          => $revenueNote,
        'unpriced_sales_count'  => $summary['unpriced_sales_count'],
        'seller_rating'         => $avgRating,
        'review_count'          => $reviewCount,
        'active_requests_count' => $activeRequests,
    ],
]);
