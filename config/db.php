<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Shared PDO Database Connection (config/db.php)
 *
 * Beginner-friendly procedural helper function using PDO with prepared statements.
 */

// Prevent multiple declarations
if (!function_exists('getDbConnection')) {

    /**
     * Loads application configuration.
     *
     * @return array
     */
    function getAppConfig() {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $localConfigFile = __DIR__ . '/config.php';
        $exampleConfigFile = __DIR__ . '/config.example.php';

        if (file_exists($localConfigFile)) {
            $config = require $localConfigFile;
        } elseif (file_exists($exampleConfigFile)) {
            $config = require $exampleConfigFile;
        } else {
            throw new RuntimeException('Configuration file not found in config/ directory.');
        }

        return $config;
    }

    /**
     * Returns a shared PDO database connection instance.
     *
     * @return PDO
     * @throws RuntimeException If database connection cannot be established
     */
    function getDbConnection() {
        static $pdo = null;

        if ($pdo !== null) {
            return $pdo;
        }

        $config = getAppConfig();
        $dbConfig = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['dbname'],
            $dbConfig['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            return $pdo;
        } catch (PDOException $e) {
            // Log raw error internally for debugging
            error_log('Database connection error: ' . $e->getMessage());

            // Throw a safe exception without exposing credentials or internal paths
            throw new RuntimeException('Unable to connect to the database server.');
        }
    }

    /**
     * Safe database connection test for health checks.
     * Does not expose credentials or throw unhandled exceptions.
     *
     * @return array ['connected' => bool, 'error' => string|null]
     */
    function testDbConnection() {
        try {
            $conn = getDbConnection();
            $stmt = $conn->query('SELECT 1');
            $connected = ($stmt !== false);
            return [
                'connected' => $connected,
                'error'     => null,
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'error'     => 'Database connection failed.',
            ];
        }
    }
}
