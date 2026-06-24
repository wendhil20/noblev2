<?php
// user/auth/logout.php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

if (!defined('BASE_URL')) {
    // Minimal BASE_URL fallback if accessed directly
    $isLocalhost = (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
    );
    define('BASE_URL', $isLocalhost ? 'http://localhost/noblev2' : '');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('nobleuser');
    session_start();
}

// Destroy session
$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL);
exit;