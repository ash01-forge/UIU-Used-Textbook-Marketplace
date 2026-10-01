<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * CSRF Protection Helpers (includes/csrf.php)
 *
 * Beginner-friendly helpers for Cross-Site Request Forgery (CSRF) token
 * generation, retrieval, and validation for fetch() API requests and forms.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/response.php';

if (!function_exists('getCsrfToken')) {

    /**
     * Generates a new cryptographically secure CSRF token.
     *
     * @return string
     */
    function generateCsrfToken(): string {
        initSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Retrieves the current session CSRF token, generating one if it does not exist.
     *
     * @return string
     */
    function getCsrfToken(): string {
        initSession();
        if (empty($_SESSION['csrf_token'])) {
            return generateCsrfToken();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validates a provided CSRF token against the session token.
     *
     * @param string|null $token The candidate token from header or request body
     * @return bool
     */
    function validateCsrfToken(?string $token): bool {
        initSession();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Enforces CSRF token check on state-changing HTTP methods (POST, PUT, PATCH, DELETE).
     * Bypasses safe HTTP methods (GET, HEAD, OPTIONS).
     * Halts with 403 JSON error if validation fails.
     *
     * Token is checked from:
     * 1. HTTP header 'X-CSRF-Token' (recommended for fetch() requests)
     * 2. Request body 'csrf_token' field
     *
     * @return void
     */
    function requireCsrfToken() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

        if (in_array($method, $safeMethods, true)) {
            return;
        }

        // 1. Check HTTP header: X-CSRF-Token
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        // 2. Check JSON request body or POST field
        $bodyToken = null;
        $body = getJsonRequestBody();
        if (!empty($body['csrf_token'])) {
            $bodyToken = (string) $body['csrf_token'];
        }

        $candidate = $headerToken ?? $bodyToken;

        if (!validateCsrfToken($candidate)) {
            sendErrorResponse('CSRF token validation failed. Please refresh and try again.', 403);
        }
    }
}
