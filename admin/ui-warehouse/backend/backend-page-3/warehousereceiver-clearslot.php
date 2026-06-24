<?php
// warehousereceiver-clearslot.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$slotId = isset($data['slot_id']) ? (int) $data['slot_id'] : 0;

if (!$slotId) {
    echo json_encode(['success' => false, 'message' => 'Missing slot ID.']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE noblewarehouselocation 
    SET order_id = NULL, assigned_at = NULL, updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("i", $slotId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Slot cleared.' : 'Failed to clear slot.',
]);