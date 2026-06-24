<?php
// index-heartbeat.php

include ROOT_PATH . '/network/connect.php';

$user_id = intval($_SESSION['account_id'] ?? 0);
if ($user_id) {
    $conn->query("UPDATE noblerole SET last_active = NOW() WHERE id = $user_id");
}
echo json_encode(['ok' => true]);