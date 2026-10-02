<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Authentication & Session Helpers (includes/auth.php)
 *
 * Beginner-friendly procedural helpers for session management, login status,
 * and role-based access control (RBAC).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/activity.php';

if (!function_exists('initSession')) {

    /**
     * Initializes a safe, cookie-based session if one is not already active.
     *
     * @return void
     */
    function initSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = getAppConfig();
        $sessionConfig = $config['session'] ?? [];

        // Configure session cookie parameters
        $lifetime = $sessionConfig['lifetime'] ?? 86400 * 7;
        $path     = $sessionConfig['cookie_path'] ?? '/';
        $domain   = ''; // Localhost domain
        $secure   = $sessionConfig['cookie_secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httponly = $sessionConfig['cookie_httponly'] ?? true;
        $samesite = $sessionConfig['cookie_samesite'] ?? 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ]);
        } else {
            session_set_cookie_params($lifetime, $path . '; samesite=' . $samesite, $domain, $secure, $httponly);
        }

        if (!empty($sessionConfig['cookie_name'])) {
            session_name($sessionConfig['cookie_name']);
        }

        session_start();
    }

    /**
     * Checks whether a user is currently logged in.
     *
     * @return bool
     */
    function isLoggedIn(): bool {
        initSession();
        return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    /**
     * Retrieves the currently logged-in user profile from session.
     *
     * @return array|null Returns user data array or null if guest
     */
    function getCurrentUser(): ?array {
        initSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Retrieves the currently logged-in user's ID.
     *
     * @return int|null
     */
    function getCurrentUserId(): ?int {
        initSession();
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }

    /**
     * Retrieves the currently logged-in user's role ('buyer', 'seller', 'admin').
     *
     * @return string|null
     */
    function getCurrentUserRole(): ?string {
        initSession();
        return $_SESSION['user']['role'] ?? null;
    }

    /**
     * Sets the user in the session after successful authentication.
     * Excludes sensitive fields like password_hash.
     *
     * @param array $user User database record
     * @return void
     */
    function setSessionUser(array $user) {
        initSession();

        // Prevent session fixation attack
        session_regenerate_id(true);

        // Sanitize - never store password_hash in session
        unset($user['password_hash']);

        $_SESSION['user'] = [
            'id'         => (int) $user['id'],
            'full_name'  => $user['full_name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'student_id' => $user['student_id'] ?? null,
            'phone'      => $user['phone'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
        trackAuthenticatedUser((int)$user['id']);
    }

    /**
     * Clears all session data and destroys the user session.
     *
     * @return void
     */
    function destroySessionUser() {
        initSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Guards an endpoint: requires authentication.
     * Halts and returns 401 JSON error if not signed in.
     *
     * @return array The authenticated user's session profile
     */
    function requireLogin(): array {
        if (!isLoggedIn()) {
            sendErrorResponse('Authentication required. Please sign in.', 401);
        }
        $user = getCurrentUser();
        trackAuthenticatedUser((int)$user['id']);
        return $user;
    }

    /**
     * Guards an endpoint: requires one of the permitted roles.
     * Halts and returns 403 JSON error if unauthorized.
     *
     * @param string|array $allowedRoles Single role string or array of allowed roles
     * @return array The authenticated user's session profile
     */
    function requireRole($allowedRoles): array {
        $user = requireLogin();
        $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

        if (!in_array($user['role'], $allowed, true)) {
            sendErrorResponse('Access forbidden. You do not have permission to perform this action.', 403);
        }

        return $user;
    }
}
