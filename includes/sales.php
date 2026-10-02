<?php
/** Aggregate only immutable completed-sale snapshots, never current listing prices. */
function saleSummary(PDO $db, string $where = "pr.status = 'completed'", array $parameters = []): array {
    $query = $db->prepare("SELECT COUNT(*) AS total, COUNT(pr.sale_price) AS priced,
        COALESCE(SUM(pr.sale_price), 0) AS revenue, AVG(pr.sale_price) AS average
        FROM purchase_requests pr WHERE {$where}");
    $query->execute($parameters);
    $row = $query->fetch();
    $missing = (int)$row['total'] - (int)$row['priced'];
    return [
        'completed_sales_count' => (int)$row['total'],
        'priced_sales_count' => (int)$row['priced'],
        'unpriced_sales_count' => $missing,
        'revenue' => (float)$row['revenue'],
        'average_order_value' => $row['average'] === null ? null : (float)$row['average'],
        'revenue_note' => $missing > 0
            ? "$missing earlier sale(s) have no recorded amount and are excluded from revenue and average order value."
            : 'Revenue uses the listing price recorded when each meetup was completed.',
    ];
}
