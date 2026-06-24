<?php
// superadmin-approve.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];
$allowedPositions = [POSITION_HEAD];
// Walang position restriction para sa superadmin
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$poId = (int) ($data['po_id'] ?? 0);

if (!$poId) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO']);
    exit;
}

$approvedBy = strtoupper(trim($_SESSION['username'] ?? ''));
$adminId = $_SESSION['account_id'] ?? 0;

// Get active signature ng superadmin
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
$approvedSig = $sig['image_path'] ?? '';

// Check PO — must be acknowledged muna bago ma-approve
$checkStmt = $conn->prepare("
    SELECT id, noted_by, acknowledged_by, approved_by, po_number, po_type 
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

if (empty($po['noted_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO must be noted first before approving']);
    exit;
}

if (empty($po['acknowledged_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO must be acknowledged first before approving']);
    exit;
}

if (!empty($po['approved_by'])) {
    echo json_encode(['success' => false, 'message' => 'PO already approved']);
    exit;
}

$isReplacement = ($po['po_type'] ?? 'normal') === 'replacement';

// I-update ang approved_by
$updateStmt = $conn->prepare("
    UPDATE noblepurchaseorder 
    SET approved_by = ?, approved_by_signature = ?
    WHERE id = ?
");
$updateStmt->bind_param("ssi", $approvedBy, $approvedSig, $poId);
$updateStmt->execute();
$updateStmt->close();

$poNumber = $po['po_number'];
$poLabel = $isReplacement ? 'Replacement Purchase Order' : 'Purchase Order';

// ── Notify warehouse staff + accounting roles (broadcast) ──
$notifTitle = $isReplacement ? 'Replacement Purchase Order Approved' : 'Purchase Order Approved';
$notifMessage = $poLabel . ' ' . $poNumber . ' has been approved by ' . $approvedBy . '.';
$notifLink = null;

$roles = [
    [ROLE_WAREHOUSE, POSITION_WAREHOUSESTAFF],
    [ROLE_ACCOUNTING, POSITION_HEAD],
    [ROLE_ACCOUNTING, POSITION_STAFF],
    [ROLE_ACCOUNTING, POSITION_CUSTODIAN],
];

foreach ($roles as [$role, $position]) {
    sendNotification(
        $conn,
        $role,
        $position,
        null,    // broadcast — hindi specific user
        $notifTitle,
        $notifMessage,
        $notifLink
    );
}

echo json_encode(['success' => true, 'approved_by' => $approvedBy, 'is_replacement' => $isReplacement]);