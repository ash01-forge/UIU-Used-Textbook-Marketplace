<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * JSON Response Helpers (includes/response.php)
 *
 * Beginner-friendly helper functions to format and send JSON responses
 * for frontend JavaScript fetch() requests.
 */

if (!function_exists('sendJsonResponse')) {

    /**
     * Sends a raw JSON response and terminates execution.
     *
     * @param array $payload The associative array to JSON-encode
     * @param int $statusCode HTTP status code (e.g. 200, 400, 401, 403, 404, 500)
     * @return void
     */
    function sendJsonResponse(array $payload, int $statusCode = 200) {
        // Discard any accidental PHP warnings or output before sending JSON
        if (ob_get_level() > 0) {
            ob_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Sends a standardized JSON success response.
     *
     * Response shape:
     * {
     *   "success": true,
     *   "message": "...",
     *   "data": { ... }
     * }
     *
     * @param string $message User-friendly message
     * @param mixed $data Payload data (array or object)
     * @param int $statusCode HTTP status code (defaults to 200)
     * @return void
     */
    function sendSuccessResponse(string $message = 'Success', $data = [], int $statusCode = 200) {
        sendJsonResponse([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Sends a standardized JSON error response.
     *
     * Response shape:
     * {
     *   "success": false,
     *   "message": "...",
     *   "errors": [ ... ]
     * }
     *
     * @param string $message User-friendly error message
     * @param int $statusCode HTTP status code (defaults to 400)
     * @param array $errors Optional list of field-level errors
     * @return void
     */
    function sendErrorResponse(string $message = 'An error occurred', int $statusCode = 400, array $errors = []) {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        sendJsonResponse($payload, $statusCode);
    }

    /**
     * Reads and parses JSON payload sent via JavaScript fetch(..., { method: 'POST', body: JSON.stringify(...) }).
     * Falls back to $_POST for standard form data.
     *
     * @return array
     */
    function getJsonRequestBody(): array {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?? [];
    }
}
