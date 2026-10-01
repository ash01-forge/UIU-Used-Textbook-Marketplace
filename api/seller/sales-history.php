<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Sales History (api/seller/sales-history.php)
 *
 * Endpoint: GET /api/seller/sales-history.php
 * Access  : Authenticated Seller only
 */

require_once __DIR__ . '/helpers.php';

sellerRequireMethod(['GET']);

$user = requireRole('seller');
$sellerId = (int) $user['id'];
$db = getDbConnection();

$page = sellerQueryInt('page', 1, 1, 1000000);
$perPage = sellerQueryInt('per_page', 20, 1, 100);
$offset = ($page - 1) * $perPage;

// Count the same joined records as the paginated query. Keep completed sales
// visible even after the listing is relisted.
$countStmt = $db->prepare("SELECT COUNT(*) FROM listings l
    LEFT JOIN purchase_requests pr ON l.id = pr.listing_id AND pr.status = 'completed' AND pr.seller_id = l.seller_id
    WHERE l.seller_id = ? AND (l.status = 'sold' OR pr.id IS NOT NULL)");
$countStmt->execute([$sellerId]);
$total = (int) $countStmt->fetchColumn();
$totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

// Fetch sales rows joining with completed purchase requests if available
$sql = "SELECT
            l.id AS listing_id,
            l.title,
            l.course_code,
            l.department,
            l.item_type,
            l.price,
            l.status,
            l.updated_at AS sold_at,
            pr.id AS purchase_request_id,
            pr.meeting_location,
            pr.completed_at,
            buyer.full_name AS buyer_name,
            buyer.email AS buyer_email
        FROM listings l
        LEFT JOIN purchase_requests pr
            ON l.id = pr.listing_id AND pr.status = 'completed' AND pr.seller_id = l.seller_id
        LEFT JOIN users buyer
            ON pr.buyer_id = buyer.id
        WHERE l.seller_id = ? AND (l.status = 'sold' OR pr.id IS NOT NULL)
        ORDER BY COALESCE(pr.completed_at, l.updated_at) DESC, l.id DESC, pr.id DESC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute([$sellerId]);
$rows = $stmt->fetchAll();

$sales = [];
foreach ($rows as $row) {
    $sales[] = [
        'listing_id'          => (int) $row['listing_id'],
        'title'               => $row['title'],
        'course_code'         => $row['course_code'],
        'department'          => $row['department'],
        'item_type'           => $row['item_type'],
        'price'               => (float) $row['price'],
        'status'              => $row['status'],
        'sold_at'             => $row['sold_at'],
        'purchase_request_id' => $row['purchase_request_id'] !== null ? (int) $row['purchase_request_id'] : null,
        'meeting_location'    => $row['meeting_location'] ?? null,
        'completed_at'        => $row['completed_at'] ?? null,
        'buyer_name'          => $row['buyer_name'] ?? null,
        'buyer_email'         => $row['buyer_email'] ?? null,
    ];
}

sendSuccessResponse('Seller sales history retrieved.', [
    'sales'      => $sales,
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ],
]);
