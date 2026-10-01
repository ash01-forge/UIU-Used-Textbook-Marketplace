<?php
require_once __DIR__ . '/_common.php';
marketGet();
$page = marketInt('page', 1, 1000000);
$perPage = marketInt('per_page', 20, 100);
$search = marketText('search');
$department = marketText('department');
$subject = marketText('subject');
$type = marketChoice('type', ['', 'Textbook', 'Notes', 'Lab Manual']);
$condition = marketChoice('condition', ['', 'New', 'Like New', 'Good', 'Fair', 'Poor']);
$sort = marketChoice('sort', ['created_at', 'price', 'title'], 'created_at');
$direction = marketChoice('direction', ['asc', 'desc'], 'desc');
$category = isset($_GET['category_id']) ? marketInt('category_id', 1, 2147483647) : null;
$min = marketPrice('min_price');
$max = marketPrice('max_price');
if ($min !== null && $max !== null && (float)$min > (float)$max) marketInvalid('min_price');
$where = ["l.status = 'available'"];
$params = [];
if ($search !== '') {
    // Escape LIKE metacharacters so search is a literal substring.
    $needle = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(l.title LIKE ? ESCAPE '!' OR l.author LIKE ? ESCAPE '!' OR l.course_code LIKE ? ESCAPE '!' OR l.subject LIKE ? ESCAPE '!')";
    array_push($params, $needle, $needle, $needle, $needle);
}
foreach (['department' => $department, 'subject' => $subject, 'item_type' => $type, 'condition_type' => $condition] as $field => $value) {
    if ($value !== '') { $where[] = "l.$field = ?"; $params[] = $value; }
}
if ($category !== null) { $where[] = 'l.category_id = ?'; $params[] = $category; }
if ($min !== null) { $where[] = 'l.price >= ?'; $params[] = $min; }
if ($max !== null) { $where[] = 'l.price <= ?'; $params[] = $max; }
$predicate = implode(' AND ', $where);
try {
    $db = getDbConnection();
    $count = $db->prepare("SELECT COUNT(*) FROM listings l JOIN users u ON u.id=l.seller_id WHERE $predicate");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $offset = ($page - 1) * $perPage;
    // SQL identifiers are allowlisted above; LIMIT values are bounded integers.
    $statement = $db->prepare('SELECT ' . marketFields() . " FROM listings l JOIN users u ON u.id=l.seller_id WHERE $predicate ORDER BY l.$sort $direction, l.id $direction LIMIT $perPage OFFSET $offset");
    $statement->execute($params);
    sendSuccessResponse('Listings retrieved', [
        'total' => $total,
        'listings' => array_map('marketRow', $statement->fetchAll()),
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int)ceil($total / $perPage)],
    ]);
} catch (Throwable $error) { marketFailure($error); }
