<?php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = intval($body['id'] ?? 0);
$position = $body['position'] ?? '';

// debug muna
error_log("ID: $id | Position: $position");

if ($id === 0 || !in_array($position, ['head', 'staff','custodian','custoassistant','warehousestaff','warehousereceiver','logisticstaff','logisticdispatcher','productspecialiststaff'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data', 'id' => $id, 'position' => $position]);
    exit;
}

$stmt = $conn->prepare("UPDATE noblerole SET position = ? WHERE id = ?");
$stmt->bind_param("si", $position, $id);
$result = $stmt->execute();

echo json_encode([
    'success' => $result,
    'affected' => $stmt->affected_rows,
    'id' => $id,
    'position' => $position,
    'db_error' => $conn->error
]);