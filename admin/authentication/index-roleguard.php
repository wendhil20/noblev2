<?php
// index-roleguard.php

// --- Role check ---
if (empty($allowedRoles) || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: ' . BASE_URL . '/unauthorized');
    exit;
}

// --- Position check (optional) ---
if (!empty($allowedPositions)) {
    $sessionPosition = strtolower(trim($_SESSION['position'] ?? ''));
    $normalizedAllowed = array_map('strtolower', $allowedPositions);
    if (!in_array($sessionPosition, $normalizedAllowed)) {
        header('Location: ' . BASE_URL . '/unauthorized');
        exit;
    }
}
?>