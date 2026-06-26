<?php
// connect.php

$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000']);

// Set database credentials based on environment
if ($isLocal) {
    // Local/XAMPP Configuration
    $host = getenv('DB_HOST_LOCAL') ?: 'localhost:3306';
    $username = getenv('DB_USER_LOCAL') ?: 'root';
    $password = getenv('DB_PASSWORD_LOCAL') ?: '';
    $database = getenv('DB_NAME_LOCAL') ?: 'noblev2';
} else {
    // Production Configuration
    $host = getenv('DB_HOST_PROD') ?: 'localhost';
    $username = getenv('DB_USER_PROD') ?: 'u318146187_noblev2';
    $password = getenv('DB_PASSWORD_PROD') ?: 'Noblev2123';
    $database = getenv('DB_NAME_PROD') ?: 'u318146187_noblev2';
}

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
$conn->query("SET time_zone = '+08:00'");
