<?php
// accounting-backendponote.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_STAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$poId = (int) ($data['po_id'] ?? 0);

if (!$poId) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO']);
    exit;
}

$staffId = $_SESSION['account_id'] ?? 0;

$stmt = $conn->prepare("SELECT position FROM noblerole WHERE id = ?");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$notedBy = strtoupper(trim($_SESSION['username'] ?? ''));

$sigStmt = $conn->prepare("
    SELECT image_path FROM noblesignature 
    WHERE user_id = ? AND is_active = 1 
    LIMIT 1
");
$sigStmt->bind_param("i", $staffId);
$sigStmt->execute();
$sig = $sigStmt->get_result()->fetch_assoc();
$sigStmt->close();
$notedSig = $sig['image_path'] ?? '';

$checkStmt = $conn->prepare("SELECT id, noted_by, po_type FROM noblepurchaseorder WHERE id = ?");
$checkStmt->bind_param("i", $poId);
$checkStmt->execute();
$po = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$po) {
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit;
}

if (!empty($po['noted_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO already noted']);
    exit;
}

$isReplacement = ($po['po_type'] ?? 'normal') === 'replacement';

$updateStmt = $conn->prepare("
    UPDATE noblepurchaseorder 
    SET noted_by = ?, noted_by_signature = ?
    WHERE id = ?
");
$updateStmt->bind_param("ssi", $notedBy, $notedSig, $poId);
$updateStmt->execute();
$updateStmt->close();

$poStmt = $conn->prepare("SELECT po_number FROM noblepurchaseorder WHERE id = ?");
$poStmt->bind_param("i", $poId);
$poStmt->execute();
$poData = $poStmt->get_result()->fetch_assoc();
$poStmt->close();
$poNumber = $poData['po_number'] ?? 'N/A';

$poLabel = $isReplacement ? 'Replacement Purchase Order' : 'Purchase Order';

// ── Notify warehouse staff ──
$notifTitle = $isReplacement ? 'Replacement Purchase Order Noted' : 'Purchase Order Noted';
$notifMessage = $poLabel . ' ' . $poNumber . ' has been noted by accounting staff ' . $notedBy . '.';
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

// ── Notify accounting custodian ──
$notifTitleCust = $isReplacement
    ? 'Replacement Purchase Order Ready for Acknowledgment'
    : 'Purchase Order Ready for Acknowledgment';
$notifMessageCust = $poLabel . ' ' . $poNumber . ' has been noted and is now ready for your acknowledgment.';
$notifLinkCust = null;

sendNotification(
    $conn,
    ROLE_ACCOUNTING,
    POSITION_CUSTODIAN,
    null,
    $notifTitleCust,
    $notifMessageCust,
    $notifLinkCust
);

echo json_encode(['success' => true, 'noted_by' => $notedBy, 'is_replacement' => $isReplacement]);