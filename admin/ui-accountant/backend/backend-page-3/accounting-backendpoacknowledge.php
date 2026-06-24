<?php
// accounting-backendpoacknowledge.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$poId = (int) ($data['po_id'] ?? 0);

if (!$poId) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO']);
    exit;
}

$custodianId = $_SESSION['account_id'] ?? 0;
$acknowledgedBy = strtoupper(trim($_SESSION['username'] ?? ''));

// Get active signature
$sigStmt = $conn->prepare("
    SELECT ns.image_path 
    FROM noblerole nr
    LEFT JOIN noblesignature ns ON ns.id = nr.active_signature_id
    WHERE nr.id = ?
    LIMIT 1
");
$sigStmt->bind_param("i", $custodianId);
$sigStmt->execute();
$sig = $sigStmt->get_result()->fetch_assoc();
$sigStmt->close();
$acknowledgedSig = $sig['image_path'] ?? '';

// Check PO — must be noted first before can be acknowledged
$checkStmt = $conn->prepare("SELECT id, noted_by, acknowledged_by, po_number, po_type FROM noblepurchaseorder WHERE id = ?");
$checkStmt->bind_param("i", $poId);
$checkStmt->execute();
$po = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$po) {
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit;
}

if (empty($po['noted_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO must be noted first before acknowledging']);
    exit;
}

if (!empty($po['acknowledged_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO already acknowledged']);
    exit;
}

$isReplacement = ($po['po_type'] ?? 'normal') === 'replacement';

// Update acknowledged_by
$updateStmt = $conn->prepare("
    UPDATE noblepurchaseorder 
    SET acknowledged_by = ?, acknowledged_by_signature = ?
    WHERE id = ?
");
$updateStmt->bind_param("ssi", $acknowledgedBy, $acknowledgedSig, $poId);
$updateStmt->execute();
$updateStmt->close();

$poNumber = $po['po_number'];
$poLabel = $isReplacement ? 'Replacement Purchase Order' : 'Purchase Order';

// ── Notify warehouse staff ──
$notifTitle = $isReplacement ? 'Replacement Purchase Order Acknowledged' : 'Purchase Order Acknowledged';
$notifMessage = $poLabel . ' ' . $poNumber . ' has been acknowledged by custodian ' . $acknowledgedBy . '.';
$notifLink = null;

sendNotification(
    $conn,
    ROLE_WAREHOUSE,
    POSITION_WAREHOUSESTAFF,
    null,
    $notifTitle,
    $notifMessage,
    $notifLink
);

// ── Notify accounting staff din ──
$notifTitle2 = $isReplacement ? 'Replacement Purchase Order Acknowledged' : 'Purchase Order Acknowledged';
$notifMessage2 = $poLabel . ' ' . $poNumber . ' has been acknowledged by custodian ' . $acknowledgedBy . '.';
$notifLink2 = null;

sendNotification(
    $conn,
    ROLE_SUPERADMIN,
    POSITION_HEAD,
    null,
    $notifTitle2,
    $notifMessage2,
    $notifLink2
);

echo json_encode(['success' => true, 'acknowledged_by' => $acknowledgedBy, 'is_replacement' => $isReplacement]);