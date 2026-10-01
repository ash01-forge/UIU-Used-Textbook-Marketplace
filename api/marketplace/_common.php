<?php
/** Private marketplace helpers; public scripts only perform reads. */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';

function marketGet(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        sendErrorResponse('Method not allowed.', 405);
    }
}
function marketInvalid(string $key): void {
    sendErrorResponse('Invalid query parameter.', 422, [$key => 'Invalid value or outside allowed bounds.']);
}
function marketText(string $key, int $max = 100, string $default = ''): string {
    if (!isset($_GET[$key])) return $default;
    if (!is_string($_GET[$key])) marketInvalid($key);
    $value = trim($_GET[$key]);
    if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max) marketInvalid($key);
    return $value;
}
function marketInt(string $key, int $default, int $max): int {
    $value = marketText($key, 12, (string)$default);
    if (!preg_match('/^[1-9][0-9]*$/D', $value) || (float)$value > $max) marketInvalid($key);
    return (int)$value;
}
function marketChoice(string $key, array $choices, string $default = ''): string {
    $value = marketText($key, 100, $default);
    if (!in_array($value, $choices, true)) marketInvalid($key);
    return $value;
}
function marketPrice(string $key): ?string {
    $value = marketText($key, 12);
    if ($value === '') return null;
    if (!preg_match('/^[0-9]{1,8}(\.[0-9]{1,2})?$/D', $value)) marketInvalid($key);
    return $value;
}
function marketFields(): string {
    return 'l.id, l.category_id, l.title, l.author, l.edition, l.course_code,
        l.department, l.subject, l.item_type, l.condition_type, l.price,
        l.image_url, l.created_at, u.full_name AS seller_name,
        (SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.seller_id = l.seller_id) AS seller_rating';
}
function marketRow(array $row): array {
    $row['id'] = (int)$row['id'];
    $row['category_id'] = $row['category_id'] === null ? null : (int)$row['category_id'];
    $row['price'] = (float)$row['price'];
    $row['seller_rating'] = $row['seller_rating'] === null ? null : (float)$row['seller_rating'];
    return $row;
}
function marketFailure(Throwable $error): void {
    error_log('Marketplace: ' . $error->getMessage());
    sendErrorResponse('Marketplace is temporarily unavailable.', 500);
}
