<?php
//warehouse-assignreceiver.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$staffId = isset($data['staff_id']) ? (int)$data['staff_id'] : 0;
$poId    = isset($data['po_id'])    ? (int)$data['po_id']    : 0;
$doneBy  = $_SESSION['account_id'] ?? 0;

if (!$orderId || !$staffId || !$poId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Check if already assigned for this specific PO
$check = $conn->prepare("SELECT id FROM noblereceivingreceiver WHERE order_id = ? AND po_id = ?");
$check->bind_param("ii", $orderId, $poId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Receiver already assigned for this PO.']);
    exit;
}

// Kunin lahat ng PO ng same order
$allPoStmt = $conn->prepare("SELECT id FROM noblepurchaseorder WHERE order_id = ?");
$allPoStmt->bind_param("i", $orderId);
$allPoStmt->execute();
$allPoResult = $allPoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allPoStmt->close();

foreach ($allPoResult as $poRow) {
    $currentPoId = $poRow['id'];

    $dupCheck = $conn->prepare("SELECT id FROM noblereceivingreceiver WHERE order_id = ? AND po_id = ?");
    $dupCheck->bind_param("ii", $orderId, $currentPoId);
    $dupCheck->execute();
    $dupExisting = $dupCheck->get_result()->fetch_assoc();
    $dupCheck->close();

    if ($dupExisting) continue;

    $stmt = $conn->prepare("
        INSERT INTO noblereceivingreceiver (order_id, po_id, assigned_staff_id, status, assigned_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("iii", $orderId, $currentPoId, $staffId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to assign receiver for PO #' . $currentPoId]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    $updateStep = $conn->prepare("
        UPDATE nobleordertracking 
        SET current_step = 5, step_updated_at = NOW()
        WHERE po_id = ?
    ");
    $updateStep->bind_param("i", $currentPoId);
    $updateStep->execute();
    $updateStep->close();

    $stepLabel = 'Assign receiving';
    $log = $conn->prepare("
        INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, changed_at)
        VALUES (?, 4, ?, ?, NOW())
    ");
    $log->bind_param("isi", $orderId, $stepLabel, $doneBy);
    $log->execute();
    $log->close();
}

// === SEND NOTIFICATIONS — isang beses lang, kahit maraming PO ===
$poInfo = $conn->prepare("
    SELECT npo.po_number, ppl.nhccreference
    FROM noblepurchaseorder npo
    JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
    WHERE npo.id = ?
    LIMIT 1
");
$poInfo->bind_param("i", $poId);
$poInfo->execute();
$poDetails = $poInfo->get_result()->fetch_assoc();
$poInfo->close();

$poNumber     = $poDetails['po_number']     ?? "PO #{$poId}";
$reference    = $poDetails['nhccreference'] ?? '';
$notifTitle   = "PO Completed – Ready for Receiving";
$notifMessage = "Purchase Order {$poNumber}" . ($reference ? " ({$reference})" : "") . " has been completed and a receiver has been assigned.";

$targets = [
    [POSITION_WAREHOUSESTAFF,    BASE_URL . '/warehousestaff'],
    [POSITION_WAREHOUSERECEIVER, BASE_URL . '/warehousereceiver'],
];

foreach ($targets as [$pos, $link]) {
    sendNotification(
        $conn,
        ROLE_WAREHOUSE,   // dati: hardcoded 'warehouse' — MALI, hindi tugma sa fetch query
        $pos,
        null,             // broadcast
        $notifTitle,
        $notifMessage,
        $link
    );
}

echo json_encode(['success' => true, 'message' => 'Receiver assigned successfully.']);