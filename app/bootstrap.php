<?php
// Common bootstrap: session, config, database, markdown parser, CSRF token

// Ensure PHP errors get logged to a writable file for debugging
$errorLogPath = __DIR__ . '/../data/php_errors.log';
if (is_dir(dirname($errorLogPath)) && is_writable(dirname($errorLogPath))) {
    ini_set('log_errors', '1');
    ini_set('error_log', $errorLogPath);
}

// Session cookie params (30 days)
session_set_cookie_params([
    'lifetime' => 30 * 24 * 3600,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Polyfill for PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

// Core deps
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../lib/Parsedown.php';

// Markdown parser in safe mode
$parsedown = new Parsedown();
$parsedown->setSafeMode(true);
