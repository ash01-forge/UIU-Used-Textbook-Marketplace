<?php
/**
 * GET /api/admin/dashboard.php
 * Admin-only marketplace counts. Revenue is omitted because completed
 * purchases do not store a transaction-time price.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

try {
    $db = getDbConnection();

    $listingCounts = [
        'pending_approval' => 0,
        'available' => 0,
        'changes_requested' => 0,
        'rejected' => 0,
        'sold' => 0,
    ];
    foreach ($db->query('SELECT status, COUNT(*) AS total FROM listings GROUP BY status') as $row) {
        $listingCounts[$row['status']] = (int) $row['total'];
    }

    $userCounts = ['buyer' => 0, 'seller' => 0, 'admin' => 0];
    foreach ($db->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role') as $row) {
        $userCounts[$row['role']] = (int) $row['total'];
    }

    $completedSales = (int) $db->query(
        "SELECT COUNT(*) FROM purchase_requests WHERE status = 'completed'"
    )->fetchColumn();

    sendSuccessResponse('Admin dashboard counts retrieved.', [
        'pending_review_count' => $listingCounts['pending_approval'],
        'listing_counts' => $listingCounts,
        'user_counts' => $userCounts,
        'completed_sales_count' => $completedSales,
        'revenue' => null,
        'revenue_note' => 'Unavailable: completed purchases do not store transaction-time prices.',
    ]);
} catch (Throwable $e) {
    error_log('Admin dashboard query failed: ' . $e->getMessage());
    sendErrorResponse('Unable to retrieve admin dashboard data.', 500);
}