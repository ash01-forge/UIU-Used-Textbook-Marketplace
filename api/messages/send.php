<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * POST /api/messages/send.php
 *
 * Sends a campus coordination message from the authenticated user to another user.
 *
 * Security & Validation:
 * - Sender identity is strictly derived from the active session.
 * - Recipient cannot be the sender themselves.
 * - Message text is validated (1 to 2000 characters).
 * - CSRF token enforced.
 * - Safe against XSS: raw input preserved in database, frontend must use textContent or htmlspecialchars.
 *
 * Access: Authenticated User (Buyer, Seller, Admin)
 * Requires valid CSRF token.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 405);
}

$currentUser = requireLogin();
$senderId = (int) $currentUser['id'];
requireCsrfToken();

$body = getJsonRequestBody();
$errors = [];

// 1. Validate receiver_id
$receiverId = isset($body['receiver_id']) ? (int) $body['receiver_id'] : 0;
if ($receiverId <= 0) {
    $errors['receiver_id'] = 'A valid receiver_id is required.';
} elseif ($receiverId === $senderId) {
    $errors['receiver_id'] = 'You cannot send a message to yourself.';
}

// 2. Validate message_text
$messageText = trim((string) ($body['message_text'] ?? ''));
if ($messageText === '') {
    $errors['message_text'] = 'Message text cannot be empty.';
} elseif (mb_strlen($messageText) > 2000) {
    $errors['message_text'] = 'Message text cannot exceed 2000 characters.';
}

// 3. Validate optional listing_id
$listingId = isset($body['listing_id']) && (int) $body['listing_id'] > 0 ? (int) $body['listing_id'] : null;

if (!empty($errors)) {
    sendErrorResponse('Validation failed. Please correct the errors.', 422, $errors);
}

$db = getDbConnection();

try {
    // Verify receiver exists
    $stmtReceiver = $db->prepare('SELECT id, full_name FROM users WHERE id = ?');
    $stmtReceiver->execute([$receiverId]);
    $receiver = $stmtReceiver->fetch();

    if (!$receiver) {
        sendErrorResponse('The recipient user does not exist.', 404);
    }

    // Verify listing if provided
    if ($listingId !== null) {
        $stmtListing = $db->prepare('SELECT id, title FROM listings WHERE id = ?');
        $stmtListing->execute([$listingId]);
        if (!$stmtListing->fetch()) {
            sendErrorResponse('The referenced listing does not exist.', 404);
        }
    }

    // Insert message
    $stmtInsert = $db->prepare('
        INSERT INTO messages (sender_id, receiver_id, listing_id, message_text, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ');
    $stmtInsert->execute([$senderId, $receiverId, $listingId, $messageText]);
    $newMsgId = (int) $db->lastInsertId();

    sendSuccessResponse('Message sent successfully', [
        'message' => [
            'id'           => $newMsgId,
            'sender_id'    => $senderId,
            'receiver_id'  => $receiverId,
            'listing_id'   => $listingId,
            'message_text' => $messageText,
            'is_read'      => false,
            'created_at'   => date('Y-m-d H:i:s'),
        ]
    ], 201);

} catch (PDOException $e) {
    error_log('Message send error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while sending the message.', 500);
}
