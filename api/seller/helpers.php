<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Module Input & Validation Helpers (api/seller/helpers.php)
 *
 * Procedural helper functions for seller JSON API endpoints.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../config/db.php';

if (!function_exists('sellerRequireMethod')) {

    /**
     * Enforce allowed HTTP methods on the endpoint.
     *
     * @param array $allowedMethods
     * @return void
     */
    function sellerRequireMethod(array $allowedMethods): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, $allowedMethods, true)) {
            header('Allow: ' . implode(', ', $allowedMethods));
            sendErrorResponse('Method not allowed.', 405, [
                'method' => 'Allowed methods: ' . implode(', ', $allowedMethods) . '.',
            ]);
        }
    }

    /**
     * Parse positive integer from query parameter or request body.
     *
     * @param mixed $value
     * @param string $field
     * @return int
     */
    function sellerPositiveId($value, string $field = 'id'): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]+$/', (string) $value)) {
            sendErrorResponse('Invalid identifier provided.', 422, [
                $field => 'Must be a positive integer.',
            ]);
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1) {
            sendErrorResponse('Invalid identifier provided.', 422, [
                $field => 'Must be a positive integer.',
            ]);
        }

        return $integer;
    }

    /**
     * Parse an optional integer query parameter with bounds.
     *
     * @param string $name
     * @param int $default
     * @param int $min
     * @param int $max
     * @return int
     */
    function sellerQueryInt(string $name, int $default, int $min, int $max): int
    {
        if (!array_key_exists($name, $_GET)) {
            return $default;
        }

        $val = $_GET[$name];
        if ((!is_string($val) && !is_int($val)) || !preg_match('/^[0-9]+$/', (string) $val)) {
            sendErrorResponse('Invalid query parameter.', 422, [
                $name => 'Must be an integer.',
            ]);
        }

        $intVal = filter_var($val, FILTER_VALIDATE_INT);
        if ($intVal === false || $intVal < $min || $intVal > $max) {
            sendErrorResponse('Invalid query parameter.', 422, [
                $name => "Must be between {$min} and {$max}.",
            ]);
        }

        return $intVal;
    }

    /**
     * Parse an optional trimmed string query parameter with max length.
     *
     * @param string $name
     * @param int $maxLen
     * @return string
     */
    function sellerQueryText(string $name, int $maxLen = 100): string
    {
        if (!array_key_exists($name, $_GET)) {
            return '';
        }

        $val = $_GET[$name];
        if (!is_string($val)) {
            sendErrorResponse('Invalid query parameter.', 422, [
                $name => 'Must be a string.',
            ]);
        }

        $trimmed = trim($val);
        if (mb_strlen($trimmed) > $maxLen) {
            sendErrorResponse('Invalid query parameter.', 422, [
                $name => "Must not exceed {$maxLen} characters.",
            ]);
        }

        return $trimmed;
    }

    /**
     * Format a listing row for API responses.
     *
     * @param array $row
     * @return array
     */
    function sellerFormatListing(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'seller_id'      => (int) $row['seller_id'],
            'category_id'    => isset($row['category_id']) && $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'title'          => $row['title'],
            'author'         => $row['author'] ?? null,
            'edition'        => $row['edition'] ?? null,
            'course_code'    => $row['course_code'],
            'department'     => $row['department'],
            'subject'        => $row['subject'] ?? null,
            'item_type'      => $row['item_type'],
            'condition_type' => $row['condition_type'],
            'price'          => (float) $row['price'],
            'description'    => $row['description'],
            'image_url'      => $row['image_url'] ?? null,
            'status'         => $row['status'],
            'admin_feedback' => $row['admin_feedback'] ?? null,
            'reviewed_by'    => isset($row['reviewed_by']) && $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at'    => $row['reviewed_at'] ?? null,
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'] ?? null,
        ];
    }
}
