<?php
/** GET/POST/PUT/DELETE /api/admin/categories.php. */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin.php';

requireRole('admin');
adminRequireMethod(['GET', 'POST', 'PUT', 'DELETE']);

$method = strtoupper($_SERVER['REQUEST_METHOD']);
if ($method !== 'GET') {
    requireCsrfToken();
}

try {
    $db = getDbConnection();

    if ($method === 'GET') {
        $type = adminQueryText('type', 20);
        $department = adminQueryText('department', 100);
        $search = adminQueryText('search', 100);
        if ($type !== '' && !in_array($type, ['Department', 'Subject'], true)) {
            sendErrorResponse('Invalid category filter.', 422, [
                'type' => 'Allowed values are Department and Subject.',
            ]);
        }

        $conditions = ['1 = 1'];
        $parameters = [];
        if ($type !== '') {
            $conditions[] = 'type = ?';
            $parameters[] = $type;
        }
        if ($department !== '') {
            $conditions[] = 'department = ?';
            $parameters[] = $department;
        }
        if ($search !== '') {
            $conditions[] = '(name LIKE ? OR department LIKE ?)';
            $term = '%' . $search . '%';
            array_push($parameters, $term, $term);
        }

        $query = $db->prepare(
            'SELECT id, name, type, department, subject, created_at
             FROM categories WHERE ' . implode(' AND ', $conditions) . ' ORDER BY type, department, name'
        );
        $query->execute($parameters);
        $categories = $query->fetchAll();
        foreach ($categories as &$category) {
            $category['id'] = (int) $category['id'];
        }
        unset($category);

        sendSuccessResponse('Categories retrieved.', ['categories' => $categories]);
    }

    $body = getJsonRequestBody();
    if (!is_array($body)) {
        sendErrorResponse('Invalid JSON request body.', 400);
    }

    if ($method === 'POST') {
        $name = $body['name'] ?? null;
        $type = $body['type'] ?? null;
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100) {
            sendErrorResponse('Invalid category name.', 422, [
                'name' => 'Required; must contain 1 to 100 characters.',
            ]);
        }
        $name = trim($name);
        if (!is_string($type) || !in_array($type, ['Department', 'Subject'], true)) {
            sendErrorResponse('Invalid category type.', 422, [
                'type' => 'Allowed values are Department and Subject.',
            ]);
        }

        if ($type === 'Department') {
            $department = $name;
            $subject = null;
        } else {
            $department = $body['department'] ?? null;
            if (!is_string($department) || trim($department) === '' || mb_strlen(trim($department)) > 100) {
                sendErrorResponse('Invalid parent department.', 422, [
                    'department' => 'Required for a subject and must not exceed 100 characters.',
                ]);
            }
            $department = trim($department);
            $parent = $db->prepare("SELECT id FROM categories WHERE name = ? AND type = 'Department'");
            $parent->execute([$department]);
            if (!$parent->fetchColumn()) {
                sendErrorResponse('Parent department not found.', 422, [
                    'department' => 'Choose an existing department category.',
                ]);
            }
            $subject = $name;
        }

        try {
            $insert = $db->prepare(
                'INSERT INTO categories (name, type, department, subject) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$name, $type, $department, $subject]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                sendErrorResponse('A category with this name and type already exists.', 409);
            }
            throw $e;
        }

        sendSuccessResponse('Category created.', [
            'category' => [
                'id' => (int) $db->lastInsertId(),
                'name' => $name,
                'type' => $type,
                'department' => $department,
                'subject' => $subject,
            ],
        ], 201);
    }

    $categoryId = adminPositiveId($body['id'] ?? null, 'id');

    if ($method === 'PUT') {
        $name = $body['name'] ?? null;
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100) {
            sendErrorResponse('Invalid category name.', 422, [
                'name' => 'Required; must contain 1 to 100 characters.',
            ]);
        }
        $name = trim($name);

        $db->beginTransaction();
        $select = $db->prepare('SELECT id, name, type, department FROM categories WHERE id = ? FOR UPDATE');
        $select->execute([$categoryId]);
        $category = $select->fetch();
        if (!$category) {
            $db->rollBack();
            sendErrorResponse('Category not found.', 404);
        }

        $duplicate = $db->prepare('SELECT id FROM categories WHERE name = ? AND type = ? AND id <> ?');
        $duplicate->execute([$name, $category['type'], $categoryId]);
        if ($duplicate->fetchColumn()) {
            $db->rollBack();
            sendErrorResponse('A category with this name and type already exists.', 409);
        }

        if ($category['type'] === 'Department') {
            $rename = $db->prepare('UPDATE categories SET name = ?, department = ? WHERE id = ?');
            $rename->execute([$name, $name, $categoryId]);

            $children = $db->prepare("UPDATE categories SET department = ? WHERE type = 'Subject' AND department = ?");
            $children->execute([$name, $category['name']]);
            $listings = $db->prepare('UPDATE listings SET department = ? WHERE department = ?');
            $listings->execute([$name, $category['name']]);
            $users = $db->prepare('UPDATE users SET department = ? WHERE department = ?');
            $users->execute([$name, $category['name']]);
        } else {
            $rename = $db->prepare('UPDATE categories SET name = ?, subject = ? WHERE id = ?');
            $rename->execute([$name, $name, $categoryId]);

            if ($category['department'] !== null) {
                $listings = $db->prepare(
                    'UPDATE listings SET subject = ? WHERE department = ? AND subject = ?'
                );
                $listings->execute([$name, $category['department'], $category['name']]);
            } else {
                $listings = $db->prepare('UPDATE listings SET subject = ? WHERE subject = ?');
                $listings->execute([$name, $category['name']]);
            }
        }

        $updated = $db->prepare('SELECT id, name, type, department, subject, created_at FROM categories WHERE id = ?');
        $updated->execute([$categoryId]);
        $updatedCategory = $updated->fetch();
        $db->commit();

        $updatedCategory['id'] = (int) $updatedCategory['id'];
        sendSuccessResponse('Category updated and linked records preserved.', [
            'category' => $updatedCategory,
        ]);
    }

    $db->beginTransaction();
    $select = $db->prepare('SELECT id, name, type, department FROM categories WHERE id = ? FOR UPDATE');
    $select->execute([$categoryId]);
    $category = $select->fetch();
    if (!$category) {
        $db->rollBack();
        sendErrorResponse('Category not found.', 404);
    }

    $listingReferences = $db->prepare('SELECT COUNT(*) FROM listings WHERE category_id = ?');
    $listingReferences->execute([$categoryId]);
    $referenceCount = (int) $listingReferences->fetchColumn();

    if ($category['type'] === 'Department') {
        $children = $db->prepare("SELECT COUNT(*) FROM categories WHERE type = 'Subject' AND department = ?");
        $children->execute([$category['name']]);
        $referenceCount += (int) $children->fetchColumn();

        $listings = $db->prepare('SELECT COUNT(*) FROM listings WHERE department = ?');
        $listings->execute([$category['name']]);
        $referenceCount += (int) $listings->fetchColumn();

        $users = $db->prepare('SELECT COUNT(*) FROM users WHERE department = ?');
        $users->execute([$category['name']]);
        $referenceCount += (int) $users->fetchColumn();
    } else {
        if ($category['department'] !== null) {
            $listings = $db->prepare('SELECT COUNT(*) FROM listings WHERE department = ? AND subject = ?');
            $listings->execute([$category['department'], $category['name']]);
        } else {
            $listings = $db->prepare('SELECT COUNT(*) FROM listings WHERE subject = ?');
            $listings->execute([$category['name']]);
        }
        $referenceCount += (int) $listings->fetchColumn();
    }

    if ($referenceCount > 0) {
        $db->rollBack();
        sendErrorResponse('Category is referenced and cannot be deleted.', 409, [
            'id' => 'Remove or reassign dependent records before deleting this category.',
        ]);
    }

    $delete = $db->prepare('DELETE FROM categories WHERE id = ?');
    $delete->execute([$categoryId]);
    $db->commit();

    sendSuccessResponse('Category deleted.', ['id' => $categoryId]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        sendErrorResponse('Category conflicts with existing or referenced data.', 409);
    }
    error_log('Admin categories request failed: ' . $e->getMessage());
    sendErrorResponse('Unable to process category request.', 500);
}