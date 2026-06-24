<?php
// trucklist-delete.php
// File: admin/ui-productspecialist/backend/backend-page-4/trucklist-delete.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid truck ID.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM nobletrucklist WHERE id = ?");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Truck not found.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Truck deleted successfully.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
}