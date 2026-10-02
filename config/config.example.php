<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Example Configuration File (config.example.php)
 *
 * HOW TO USE:
 * 1. Copy this file to "config/config.php" in the same folder.
 * 2. Update the credentials if your local XAMPP setup differs from defaults.
 * 3. Never commit "config/config.php" to Git (it is ignored via .gitignore).
 */

return [
    // Database Configuration (Default XAMPP MariaDB/MySQL settings)
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'bookbridge_db',
        'username' => 'root',
        'password' => '', // XAMPP default is empty
        'charset'  => 'utf8mb4',
    ],

    // Application Configuration
    'app' => [
        'name'        => 'BookBridge - UIU Used Textbook Marketplace',
        'env'         => 'development', // 'development' or 'production'
        'debug'       => true,
        'base_url'    => 'http://localhost/UIU-Used-Textbook-Marketplace',
        'timezone'    => 'Asia/Dhaka',
    ],

    // Session Configuration
    'session' => [
        'cookie_name'     => 'bookbridge_session',
        'lifetime'        => 86400 * 7, // 7 days in seconds
        'cookie_path'     => '/UIU-Used-Textbook-Marketplace',
        'cookie_httponly' => true,
        'cookie_secure'   => false, // Set to true if running under HTTPS
        'cookie_samesite' => 'Lax', // Protects against standard CSRF
    ],

    // Marketplace Policy Constants
    'policy' => [
        'payment_method'      => 'cash_on_meet', // Cash on Meet only
        'allowed_domains'     => ['uiu.ac.bd', 'bscse.uiu.ac.bd', 'bba.uiu.ac.bd', 'eee.uiu.ac.bd'],
    ]
];
