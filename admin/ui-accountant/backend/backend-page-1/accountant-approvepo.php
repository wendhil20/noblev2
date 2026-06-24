<?php
// accountant-approvepo.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$poId = (int) ($data['po_id'] ?? 0);

if (!$poId) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO']);
    exit;
}

$receivedBy = strtoupper(trim($_SESSION['username'] ?? ''));
$adminId = $_SESSION['account_id'] ?? 0;

// Get accounting head's active signature
$sigStmt = $conn->prepare("
    SELECT ns.image_path 
    FROM noblerole nr
    LEFT JOIN noblesignature ns ON ns.id = nr.active_signature_id
    WHERE nr.id = ?
    LIMIT 1
");
$sigStmt->bind_param("i", $adminId);
$sigStmt->execute();
$sig = $sigStmt->get_result()->fetch_assoc();
$sigStmt->close();
$receivedSig = $sig['image_path'] ?? '';

// Validate PO state — also fetch po_type
$checkStmt = $conn->prepare("
    SELECT id, approved_by, received_by, po_number, po_type
    FROM noblepurchaseorder WHERE id = ?
");
$checkStmt->bind_param("i", $poId);
$checkStmt->execute();
$po = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$po) {
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit;
}
if (empty($po['approved_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO must be approved by the General Manager first']);
    exit;
}
if (!empty($po['received_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO already received']);
    exit;
}

$isReplacement = ($po['po_type'] ?? 'normal') === 'replacement';

// Mark as received by Accounting Head
$updateStmt = $conn->prepare("
    UPDATE noblepurchaseorder 
    SET received_by = ?, received_by_signature = ?
    WHERE id = ?
");
$updateStmt->bind_param("ssi", $receivedBy, $receivedSig, $poId);
$updateStmt->execute();
$updateStmt->close();

// ── Notifications ──
$poNumber = $po['po_number'];
$poLabel = $isReplacement ? 'Replacement Purchase Order' : 'Purchase Order';
$notifTitle = $isReplacement
    ? 'Replacement Purchase Order Received'
    : 'Purchase Order Received';
$notifMessage = $isReplacement
    ? 'Replacement Purchase Order ' . $poNumber . ' has been received by Accounting Head ' . $receivedBy . '.'
    : 'Purchase Order ' . $poNumber . ' has been received by Accounting Head ' . $receivedBy . '.';
$notifLink = null;

$roles = [
    [ROLE_WAREHOUSE, POSITION_WAREHOUSESTAFF],
    [ROLE_ACCOUNTING, POSITION_STAFF],
    [ROLE_ACCOUNTING, POSITION_CUSTODIAN],
];

foreach ($roles as [$role, $position]) {
    sendNotification(
        $conn,
        $role,
        $position,
        null,
        $notifTitle,
        $notifMessage,
        $notifLink
    );
}

echo json_encode([
    'success' => true,
    'received_by' => $receivedBy,
    'is_replacement' => $isReplacement,
]);