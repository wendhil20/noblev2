<?php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$result = $conn->query("SELECT id, name, email, role, position, created_at FROM noblerole ORDER BY created_at DESC");
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);