<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$userId = $_SESSION['account_id'];
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

// Get position + active_signature_id from noblerole
$stmt = $conn->prepare("SELECT position, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if PO already exists for this order
$sigPath = null;
if ($orderId) {
    $checkStmt = $conn->prepare("SELECT id FROM noblepurchaseorder WHERE order_id = ? LIMIT 1");
    $checkStmt->bind_param("i", $orderId);
    $checkStmt->execute();
    $existingPO = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    // Only load signature if PO already saved
    if ($existingPO && !empty($user['active_signature_id'])) {
        $sigStmt = $conn->prepare("SELECT image_path FROM noblesignature WHERE id = ? LIMIT 1");
        $sigStmt->bind_param("i", $user['active_signature_id']);
        $sigStmt->execute();
        $sig = $sigStmt->get_result()->fetch_assoc();
        $sigStmt->close();
        $sigPath = $sig['image_path'] ?? null;
    }
}

echo json_encode([
    'success' => true,
    'name' => strtoupper(trim($_SESSION['username'])),
    'position' => ucwords(str_replace('_', ' ', $user['position'] ?? 'Staff')),
    'signature_path' => $sigPath
]);