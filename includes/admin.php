<?php
/** Shared input validation helpers for admin JSON endpoints. */

require_once __DIR__ . '/response.php';

function adminRequireMethod(array $allowedMethods): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, $allowedMethods, true)) {
        header('Allow: ' . implode(', ', $allowedMethods));
        sendErrorResponse('Method not allowed.', 405, [
            'method' => 'Allowed methods: ' . implode(', ', $allowedMethods) . '.',
        ]);
    }
}

function adminQueryInt(string $name, int $default, int $minimum, int $maximum): int
{
    if (!array_key_exists($name, $_GET)) {
        return $default;
    }

    $value = $_GET[$name];
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]+$/', (string) $value)) {
        sendErrorResponse('Invalid query parameter.', 422, [$name => 'Must be an integer.']);
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT);
    if ($integer === false || $integer < $minimum || $integer > $maximum) {
        sendErrorResponse('Invalid query parameter.', 422, [
            $name => "Must be between {$minimum} and {$maximum}.",
        ]);
    }

    return $integer;
}

function adminQueryText(string $name, int $maximumLength): string
{
    if (!array_key_exists($name, $_GET)) {
        return '';
    }

    $value = $_GET[$name];
    if (!is_string($value)) {
        sendErrorResponse('Invalid query parameter.', 422, [$name => 'Must be a string.']);
    }

    $value = trim($value);
    if (mb_strlen($value) > $maximumLength) {
        sendErrorResponse('Invalid query parameter.', 422, [
            $name => "Must not exceed {$maximumLength} characters.",
        ]);
    }

    return $value;
}

function adminPositiveId($value, string $field = 'id'): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]+$/', (string) $value)) {
        sendErrorResponse('Invalid request data.', 422, [$field => 'Must be a positive integer.']);
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT);
    if ($integer === false || $integer < 1) {
        sendErrorResponse('Invalid request data.', 422, [$field => 'Must be a positive integer.']);
    }

    return $integer;
}