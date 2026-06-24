<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$typeName = trim($_POST['type_name'] ?? '');

if (empty($typeName)) {
    echo json_encode(['success' => false, 'error' => 'Type name is required.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO noblecompanysuppliertype (type_name) VALUES (?)");
$stmt->bind_param("s", $typeName);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}

$stmt->close();