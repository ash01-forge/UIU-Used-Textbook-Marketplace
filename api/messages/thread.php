<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/messages/thread.php?with_user_id={id}
 *
 * Retrieves the complete chat history between the logged-in user and another user.
 * Automatically marks all incoming unread messages in this thread as read.
 *
 * Access: Authenticated User (Buyer, Seller, Admin)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 405);
}

$currentUser = requireLogin();
$userId = (int) $currentUser['id'];

$withUserId = isset($_GET['with_user_id']) ? (int) $_GET['with_user_id'] : 0;

if ($withUserId <= 0) {
    sendErrorResponse('A valid with_user_id query parameter is required.', 422, [
        'with_user_id' => 'Valid partner user ID is required.'
    ]);
}

if ($withUserId === $userId) {
    sendErrorResponse('You cannot have a message thread with yourself.', 422, [
        'with_user_id' => 'Cannot view conversation with self.'
    ]);
}

$db = getDbConnection();

try {
    // 1. Verify partner user exists
    $stmtPartner = $db->prepare('SELECT id, full_name, email, role, avatar_url FROM users WHERE id = ?');
    $stmtPartner->execute([$withUserId]);
    $partner = $stmtPartner->fetch();

    if (!$partner) {
        sendErrorResponse('The specified user was not found.', 404);
    }

    // 2. Mark incoming unread messages as read
    $stmtMarkRead = $db->prepare('
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ');
    $stmtMarkRead->execute([$withUserId, $userId]);

    // 3. Fetch conversation messages
    $stmtMsgs = $db->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.receiver_id,
            m.listing_id,
            l.title AS listing_title,
            m.message_text,
            m.is_read,
            m.created_at
        FROM messages m
        LEFT JOIN listings l ON m.listing_id = l.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC, m.id ASC
    ");
    $stmtMsgs->execute([$userId, $withUserId, $withUserId, $userId]);
    $rawMessages = $stmtMsgs->fetchAll();

    $messages = array_map(function ($msg) use ($userId) {
        return [
            'id'            => (int) $msg['id'],
            'sender_id'     => (int) $msg['sender_id'],
            'receiver_id'   => (int) $msg['receiver_id'],
            'is_me'         => ((int) $msg['sender_id'] === $userId),
            'listing_id'    => $msg['listing_id'] ? (int) $msg['listing_id'] : null,
            'listing_title' => $msg['listing_title'],
            'message_text'  => $msg['message_text'],
            'is_read'       => (bool) $msg['is_read'],
            'created_at'    => $msg['created_at'],
        ];
    }, $rawMessages);

    sendSuccessResponse('Chat thread retrieved successfully', [
        'partner' => [
            'id'         => (int) $partner['id'],
            'full_name'  => $partner['full_name'],
            'role'       => $partner['role'],
            'avatar_url' => $partner['avatar_url'],
        ],
        'total'    => count($messages),
        'messages' => $messages,
    ]);

} catch (PDOException $e) {
    error_log('Thread fetch error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve chat thread.', 500);
}
