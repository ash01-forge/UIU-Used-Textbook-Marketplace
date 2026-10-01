<?php
require_once __DIR__ . '/_common.php';
marketGet();
$department = marketText('department');
try {
    $db = getDbConnection();
    $columns = $db->query('SHOW COLUMNS FROM categories')->fetchAll(PDO::FETCH_COLUMN);
    $hasDepartment = in_array('department', $columns, true);
    $rows = $db->query('SELECT id, name, type' . ($hasDepartment ? ', department' : '') . ' FROM categories ORDER BY name, id')->fetchAll();
    $departments = [];
    $subjects = [];
    $categories = [];
    // Only public listing labels can supplement schema relationships.
    $pairs = $db->query("SELECT DISTINCT department, subject FROM listings WHERE status='available' AND subject IS NOT NULL AND subject<>''")->fetchAll();
    foreach ($rows as $row) {
        $row['id'] = (int)$row['id'];
        $row['department'] = $row['department'] ?? null;
        if ($row['type'] === 'Department') {
            $row['department'] = $row['name'];
            if ($department === '' || $department === $row['name']) $departments[] = ['id' => $row['id'], 'name' => $row['name']];
        } else {
            $parents = [];
            if ($row['department'] !== null && $row['department'] !== '') {
                $parents[] = $row['department'];
            } else {
                foreach ($pairs as $pair) if ($pair['subject'] === $row['name']) $parents[] = $pair['department'];
            }
            $parents = array_values(array_unique($parents));
            sort($parents);
            if ($department === '' || in_array($department, $parents, true)) {
                $subjects[] = ['id' => $row['id'], 'name' => $row['name'], 'departments' => $parents];
            }
            if ($department !== '' && !in_array($department, $parents, true)) continue;
        }
        if ($row['type'] === 'Department' && $department !== '' && $department !== $row['name']) continue;
        $categories[] = $row;
    }
    sendSuccessResponse('Categories retrieved', [
        'categories' => $categories, 'departments' => $departments, 'subjects' => $subjects,
        'item_types' => ['Textbook', 'Notes', 'Lab Manual'],
        'conditions' => ['New', 'Like New', 'Good', 'Fair', 'Poor'],
        'sort_fields' => ['created_at', 'price', 'title'], 'sort_directions' => ['asc', 'desc'],
    ]);
} catch (Throwable $error) { marketFailure($error); }
