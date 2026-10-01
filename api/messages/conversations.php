<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET /api/messages/conversations.php
 *
 * Lists all active conversation threads for the logged-in user with unread counts
 * and latest message previews.
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

$db = getDbConnection();

try {
    // Find all distinct conversation partners for this user
    $sql = "
        SELECT 
            partner.id AS partner_id,
            partner.full_name AS partner_name,
            partner.role AS partner_role,
            partner.avatar_url AS partner_avatar,
            last_msg.id AS last_message_id,
            last_msg.message_text AS last_message,
            last_msg.created_at AS last_message_time,
            last_msg.sender_id AS last_sender_id,
            last_msg.listing_id,
            l.title AS listing_title,
            (
                SELECT COUNT(*) 
                FROM messages unread 
                WHERE unread.sender_id = partner.id 
                  AND unread.receiver_id = ? 
                  AND unread.is_read = 0
            ) AS unread_count
        FROM (
            SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END AS partner_id
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
        ) AS conversations
        JOIN users partner ON conversations.partner_id = partner.id
        JOIN messages last_msg ON last_msg.id = (
            SELECT m2.id 
            FROM messages m2 
            WHERE (m2.sender_id = ? AND m2.receiver_id = partner.id)
               OR (m2.sender_id = partner.id AND m2.receiver_id = ?)
            ORDER BY m2.created_at DESC, m2.id DESC
            LIMIT 1
        )
        LEFT JOIN listings l ON last_msg.listing_id = l.id
        ORDER BY last_msg.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
    $rows = $stmt->fetchAll();

    $conversations = array_map(function ($row) use ($userId) {
        return [
            'partner_id'        => (int) $row['partner_id'],
            'partner_name'      => $row['partner_name'],
            'partner_role'      => $row['partner_role'],
            'partner_avatar'    => $row['partner_avatar'],
            'last_message'      => $row['last_message'],
            'last_message_time' => $row['last_message_time'],
            'last_sender_id'    => (int) $row['last_sender_id'],
            'is_last_from_me'   => ((int) $row['last_sender_id'] === $userId),
            'listing_id'        => $row['listing_id'] ? (int) $row['listing_id'] : null,
            'listing_title'     => $row['listing_title'],
            'unread_count'      => (int) $row['unread_count'],
        ];
    }, $rows);

    sendSuccessResponse('Conversations retrieved successfully', [
        'total'         => count($conversations),
        'conversations' => $conversations,
    ]);

} catch (PDOException $e) {
    error_log('Conversations fetch error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve conversations.', 500);
}
