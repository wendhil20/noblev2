<?php
// admin/ui-warehouse/backend/backend-page-1/warehouse-assign.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$orderId = intval($_POST['order_id'] ?? 0);
$staffId = intval($_POST['staff_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$assignedBy = intval($_SESSION['account_id'] ?? 0);

if (!$orderId || !$staffId) {
    echo json_encode(['success' => false, 'error' => 'Missing fields.']);
    exit;
}

// ─── Fetch order info ──────────────────────────────────────────────────────
$oq = $conn->prepare("SELECT nhccreference, contact_name, order_status FROM noblepaidproductlist WHERE id = ?");
$oq->bind_param("i", $orderId);
$oq->execute();
$order = $oq->get_result()->fetch_assoc();
$oq->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

$isReplacement = ($order['order_status'] ?? '') === 'replacement';
$assignType = $isReplacement ? 'replacement' : 'normal';

// ─── Assignment logic ──────────────────────────────────────────────────────
if ($isReplacement) {
    // Replacement: update existing assignment or insert kung wala pa
    $checkExisting = $conn->prepare("SELECT id FROM nobleorderassignment WHERE order_id = ?");
    $checkExisting->bind_param("i", $orderId);
    $checkExisting->execute();
    $existingRow = $checkExisting->get_result()->fetch_assoc();
    $checkExisting->close();

    if ($existingRow) {
        $u = $conn->prepare("
            UPDATE nobleorderassignment
            SET staff_id = ?, assigned_by = ?, notes = ?, status = 'assigned', type = ?, updated_at = NOW()
            WHERE order_id = ?
        ");
        $u->bind_param("iissi", $staffId, $assignedBy, $notes, $assignType, $orderId);
        $u->execute();
        $u->close();
    } else {
        $s = $conn->prepare("
            INSERT INTO nobleorderassignment (order_id, staff_id, assigned_by, notes, type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $s->bind_param("iiiss", $orderId, $staffId, $assignedBy, $notes, $assignType);
        $s->execute();
        $s->close();
    }

    // ─── Hanapin ang approved replacement request para sa order ───────────
    $rrCheck = $conn->prepare("
        SELECT id FROM noblereplacementrequest
        WHERE order_id = ? AND status = 'approved'
        ORDER BY id DESC LIMIT 1
    ");
    $rrCheck->bind_param("i", $orderId);
    $rrCheck->execute();
    $rrRow = $rrCheck->get_result()->fetch_assoc();
    $rrCheck->close();

    if ($rrRow) {
        // I-mark ang replacement request as in_progress
        $rrUpdate = $conn->prepare("
            UPDATE noblereplacementrequest
            SET status = 'in_progress', updated_at = NOW()
            WHERE id = ?
        ");
        $rrUpdate->bind_param("i", $rrRow['id']);
        $rrUpdate->execute();
        $rrUpdate->close();

        // Kung may existing replacement PO na pero NULL pa ang replacement_request_id, i-update
        $existingPOStmt = $conn->prepare("
            SELECT id, replacement_request_id FROM noblepurchaseorder
            WHERE order_id = ? AND po_type = 'replacement'
            ORDER BY id DESC LIMIT 1
        ");
        $existingPOStmt->bind_param("i", $orderId);
        $existingPOStmt->execute();
        $existingPORow = $existingPOStmt->get_result()->fetch_assoc();
        $existingPOStmt->close();

        if ($existingPORow && empty($existingPORow['replacement_request_id'])) {
            $updatePO = $conn->prepare("
                UPDATE noblepurchaseorder
                SET replacement_request_id = ?
                WHERE id = ?
            ");
            $updatePO->bind_param("ii", $rrRow['id'], $existingPORow['id']);
            $updatePO->execute();
            $updatePO->close();
        }
    }

} else {
    // Normal order — check kung naka-assign na sa same staff
    $check = $conn->prepare("SELECT id FROM nobleorderassignment WHERE order_id = ? AND staff_id = ?");
    $check->bind_param("ii", $orderId, $staffId);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Already assigned to this staff.']);
        exit;
    }
    $check->close();

    $s = $conn->prepare("
        INSERT INTO nobleorderassignment (order_id, staff_id, assigned_by, notes, type)
        VALUES (?, ?, ?, ?, ?)
    ");
    $s->bind_param("iiiss", $orderId, $staffId, $assignedBy, $notes, $assignType);
    $s->execute();
    $s->close();
}

// ─── Notify staff ──────────────────────────────────────────────────────────
$notifTitle = ($isReplacement ? '[Replacement] ' : '') . 'New Order Assigned: ' . ($order['nhccreference'] ?? '');
$notifMsg = ($isReplacement ? '[Replacement] ' : '') . 'Order from ' . ($order['contact_name'] ?? '') . ' has been assigned to you.';
$notifLink = BASE_URL . '/warehousestaff';

sendNotification(
    $conn,
    ROLE_WAREHOUSE,
    POSITION_WAREHOUSESTAFF,
    $staffId,
    $notifTitle,
    $notifMsg,
    $notifLink
);

echo json_encode(['success' => true]);