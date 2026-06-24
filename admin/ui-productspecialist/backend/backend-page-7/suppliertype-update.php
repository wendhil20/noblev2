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

$id       = intval($_POST['id'] ?? 0);
$typeName = trim($_POST['type_name'] ?? '');

if (!$id || empty($typeName)) {
    echo json_encode(['success' => false, 'error' => 'ID and type name are required.']);
    exit;
}

$stmt = $conn->prepare("UPDATE noblecompanysuppliertype SET type_name = ? WHERE id = ?");
$stmt->bind_param("si", $typeName, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}

$stmt->close();