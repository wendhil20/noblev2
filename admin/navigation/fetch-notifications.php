<?php
// fetch-notifications.php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

$role      = $_SESSION['role'] ?? '';
$position  = $_SESSION['position'] ?? '';
$accountId = intval($_SESSION['account_id'] ?? 0);

if (!$role) { echo json_encode([]); exit; }

// Cleanup old notifications
$conn->query("DELETE FROM noblenotification WHERE created_at < NOW() - INTERVAL 7 DAY");

$s = $conn->prepare("
    SELECT * FROM noblenotification 
    WHERE
        for_user_id = ?
        OR (
            for_user_id IS NULL
            AND for_role = ?
            AND (for_position IS NULL OR for_position = ?)
        )
    ORDER BY created_at DESC 
    LIMIT 30
");
$s->bind_param("iss", $accountId, $role, $position);
$s->execute();
$result = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

echo json_encode($result);