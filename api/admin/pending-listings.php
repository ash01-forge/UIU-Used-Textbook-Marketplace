<?php
/** GET /api/admin/pending-listings.php — paginated moderation queue. */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin.php';

requireRole('admin');
adminRequireMethod(['GET']);

$page = adminQueryInt('page', 1, 1, 1000000);
$perPage = adminQueryInt('per_page', 25, 1, 100);
$search = adminQueryText('search', 100);
$department = adminQueryText('department', 100);
$type = adminQueryText('type', 30);
$sort = adminQueryText('sort', 20);
$direction = strtolower(adminQueryText('direction', 4) ?: 'asc');

if ($type !== '' && !in_array($type, ['Textbook', 'Notes', 'Lab Manual'], true)) {
    sendErrorResponse('Invalid listing filter.', 422, ['type' => 'Choose Textbook, Notes, or Lab Manual.']);
}
if ($sort === '') {
    $sort = 'created_at';
}
if (!in_array($sort, ['created_at', 'title'], true)) {
    sendErrorResponse('Invalid sort field.', 422, ['sort' => 'Allowed values are created_at and title.']);
}
if (!in_array($direction, ['asc', 'desc'], true)) {
    sendErrorResponse('Invalid sort direction.', 422, ['direction' => 'Allowed values are asc and desc.']);
}

$conditions = ["l.status = 'pending_approval'"];
$parameters = [];
if ($search !== '') {
    $conditions[] = '(l.title LIKE ? OR l.author LIKE ? OR l.course_code LIKE ? OR l.subject LIKE ?)';
    $term = '%' . $search . '%';
    array_push($parameters, $term, $term, $term, $term);
}
if ($department !== '') {
    $conditions[] = 'l.department = ?';
    $parameters[] = $department;
}
if ($type !== '') {
    $conditions[] = 'l.item_type = ?';
    $parameters[] = $type;
}
$where = implode(' AND ', $conditions);
$sortColumn = $sort === 'title' ? 'l.title' : 'l.created_at';
$offset = ($page - 1) * $perPage;

try {
    $db = getDbConnection();
    $count = $db->prepare("SELECT COUNT(*) FROM listings l WHERE {$where}");
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();

    $query = $db->prepare(
        "SELECT l.id, l.seller_id, l.category_id, l.title, l.author, l.edition,
                l.department, l.course_code, l.subject, l.item_type,
                l.condition_type, l.price, l.description, l.image_url,
                l.status, l.admin_feedback, l.created_at, l.updated_at,
                u.full_name AS seller_name, c.name AS category_name,
                c.type AS category_type
         FROM listings l
         INNER JOIN users u ON u.id = l.seller_id
         LEFT JOIN categories c ON c.id = l.category_id
         WHERE {$where}
         ORDER BY {$sortColumn} {$direction}, l.id ASC
         LIMIT ? OFFSET ?"
    );
    $query->execute([...$parameters, $perPage, $offset]);
    $listings = $query->fetchAll();

    foreach ($listings as &$listing) {
        $listing['id'] = (int) $listing['id'];
        $listing['seller_id'] = (int) $listing['seller_id'];
        $listing['category_id'] = $listing['category_id'] === null ? null : (int) $listing['category_id'];
        $listing['price'] = (float) $listing['price'];
    }
    unset($listing);

    sendSuccessResponse('Pending listings retrieved.', [
        'listings' => $listings,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ],
        'filters' => [
            'search' => $search,
            'department' => $department,
            'type' => $type,
            'sort' => $sort,
            'direction' => $direction,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Admin pending-listings query failed: ' . $e->getMessage());
    sendErrorResponse('Unable to retrieve pending listings.', 500);
}