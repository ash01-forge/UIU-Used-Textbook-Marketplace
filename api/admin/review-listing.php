<?php
/** POST /api/admin/review-listing.php — moderate one pending listing. */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin.php';

$admin = requireRole('admin');
adminRequireMethod(['POST']);
requireCsrfToken();

$body = getJsonRequestBody();
$listingId = adminPositiveId($body['listing_id'] ?? null, 'listing_id');
$action = $body['action'] ?? null;
$feedback = $body['admin_feedback'] ?? '';

$statusByAction = [
    'approve' => 'available',
    'reject' => 'rejected',
    'changes_requested' => 'changes_requested',
];
if (!is_string($action) || !isset($statusByAction[$action])) {
    sendErrorResponse('Invalid moderation action.', 422, [
        'action' => 'Allowed values are approve, reject, and changes_requested.',
    ]);
}
if (!is_string($feedback)) {
    sendErrorResponse('Invalid moderation feedback.', 422, ['admin_feedback' => 'Must be a string.']);
}
$feedback = trim($feedback);
if (in_array($action, ['reject', 'changes_requested'], true) && $feedback === '') {
    sendErrorResponse('Moderation feedback is required.', 422, [
        'admin_feedback' => 'Required when rejecting or requesting changes.',
    ]);
}
if (mb_strlen($feedback) > 2000) {
    sendErrorResponse('Moderation feedback is too long.', 422, [
        'admin_feedback' => 'Must not exceed 2000 characters.',
    ]);
}

try {
    $db = getDbConnection();
    $db->beginTransaction();

    $update = $db->prepare(
        "UPDATE listings
         SET status = ?, admin_feedback = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE id = ? AND status = 'pending_approval'"
    );
    $update->execute([
        $statusByAction[$action],
        $action === 'approve' ? null : $feedback,
        (int) $admin['id'],
        $listingId,
    ]);

    if ($update->rowCount() !== 1) {
        $current = $db->prepare('SELECT status FROM listings WHERE id = ?');
        $current->execute([$listingId]);
        $currentStatus = $current->fetchColumn();
        $db->rollBack();

        if ($currentStatus === false) {
            sendErrorResponse('Listing not found.', 404);
        }
        sendErrorResponse('Listing is no longer awaiting moderation.', 409, [
            'status' => (string) $currentStatus,
        ]);
    }

    $select = $db->prepare(
        'SELECT id, status, admin_feedback, reviewed_by, reviewed_at FROM listings WHERE id = ?'
    );
    $select->execute([$listingId]);
    $listing = $select->fetch();
    $db->commit();

    $listing['id'] = (int) $listing['id'];
    $listing['reviewed_by'] = (int) $listing['reviewed_by'];
    sendSuccessResponse('Listing moderation saved.', ['listing' => $listing]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin listing moderation failed: ' . $e->getMessage());
    sendErrorResponse('Unable to save listing moderation.', 500);
}