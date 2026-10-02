<?php
require_once __DIR__ . '/_common.php';
marketGet();
if (!isset($_GET['id'])) marketInvalid('id');
$id = marketInt('id', 1, 2147483647);
try {
    $db = getDbConnection();
    $statement = $db->prepare('SELECT ' . marketFields() . ", l.description, l.seller_id, u.avatar_url AS seller_avatar_url FROM listings l JOIN users u ON u.id=l.seller_id WHERE l.id=? AND l.status='available'");
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) sendErrorResponse('Listing not found.', 404);
    $row = marketRow($row);
    $row['seller_id'] = (int)$row['seller_id'];
    sendSuccessResponse('Listing retrieved', ['listing' => $row]);
} catch (Throwable $error) { marketFailure($error); }
