<?php
/** GET /api/admin/sales-report.php — completed transaction counts and rows. */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sales.php';
require_once __DIR__ . '/../../includes/admin.php';

requireRole('admin');
adminRequireMethod(['GET']);

$page = adminQueryInt('page', 1, 1, 1000000);
$perPage = adminQueryInt('per_page', 25, 1, 100);
$from = adminQueryText('from', 10);
$to = adminQueryText('to', 10);

foreach (['from' => $from, 'to' => $to] as $field => $date) {
    if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
        (int) substr($date, 5, 2),
        (int) substr($date, 8, 2),
        (int) substr($date, 0, 4)
    ))) {
        sendErrorResponse('Invalid date filter.', 422, [$field => 'Use a valid YYYY-MM-DD date.']);
    }
}
if ($from !== '' && $to !== '' && $from > $to) {
    sendErrorResponse('Invalid date range.', 422, ['from' => 'Must be on or before to.']);
}

$conditions = ["pr.status = 'completed'"];
$parameters = [];
if ($from !== '') {
    $conditions[] = 'pr.completed_at >= ?';
    $parameters[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $conditions[] = 'pr.completed_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $parameters[] = $to;
}
$where = implode(' AND ', $conditions);
$offset = ($page - 1) * $perPage;

try {
    $db = getDbConnection();
    $summary = saleSummary($db, $where, $parameters);
    $total = $summary['completed_sales_count'];

    $query = $db->prepare(
        "SELECT pr.id AS purchase_request_id, pr.listing_id,
                l.title AS listing_title, l.course_code,
                buyer.full_name AS buyer_name,
                seller.full_name AS seller_name,
                pr.completed_at, pr.sale_price
         FROM purchase_requests pr
         LEFT JOIN listings l ON l.id = pr.listing_id
         LEFT JOIN users buyer ON buyer.id = pr.buyer_id
         LEFT JOIN users seller ON seller.id = pr.seller_id
         WHERE {$where}
         ORDER BY pr.completed_at DESC, pr.id DESC
         LIMIT ? OFFSET ?"
    );
    $query->execute([...$parameters, $perPage, $offset]);

    $transactions = $query->fetchAll();
    foreach ($transactions as &$transaction) {
        $transaction['purchase_request_id'] = (int) $transaction['purchase_request_id'];
        $transaction['listing_id'] = (int) $transaction['listing_id'];
        $transaction['sale_price'] = $transaction['sale_price'] === null ? null : (float)$transaction['sale_price'];
    }
    unset($transaction);

    sendSuccessResponse('Completed sales report retrieved.', [
        'summary' => $summary,
        'transactions' => $transactions,
        'filters' => ['from' => $from, 'to' => $to],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ],
        'revenue' => $summary['revenue'],
        'revenue_note' => $summary['revenue_note'],
    ]);
} catch (Throwable $e) {
    error_log('Admin sales report query failed: ' . $e->getMessage());
    sendErrorResponse('Unable to retrieve completed sales report.', 500);
}