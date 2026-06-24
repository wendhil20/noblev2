<?php
// warehouse-scanupdate.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$poId = isset($data['po_id']) ? (int) $data['po_id'] : 0;
$action = isset($data['action']) ? trim($data['action']) : '';
$staffId = $_SESSION['account_id'] ?? 0;

if (!$orderId || !$poId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$check = $conn->prepare("
    SELECT id, status FROM noblereceivingreceiver
    WHERE order_id = ? AND po_id = ? AND assigned_staff_id = ?
    LIMIT 1
");
$check->bind_param("iii", $orderId, $poId, $staffId);
$check->execute();
$assignment = $check->get_result()->fetch_assoc();
$check->close();

if (!$assignment) {
    echo json_encode(['success' => false, 'message' => 'You are not assigned to this order.']);
    exit;
}

$location = isset($data['location']) ? trim($data['location']) : null;
$notes = isset($data['notes']) ? trim($data['notes']) : null;

if ($action === 'update_location') {
    if (!$location) {
        echo json_encode(['success' => false, 'message' => 'Location is required.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE noblereceivingreceiver
        SET location = ?, status = IF(status = 'pending', 'in_transit', status), updated_at = NOW()
        WHERE order_id = ? AND po_id = ? AND assigned_staff_id = ?
    ");
    $stmt->bind_param("siii", $location, $orderId, $poId, $staffId);
    $ok = $stmt->execute();
    $stmt->close();

    $log = $conn->prepare("
        INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
        VALUES (?, NULL, 'Location updated', ?, ?, NOW())
    ");
    $logLabel = 'Location updated: ' . $location . ($notes ? ' — ' . $notes : '');
    $log->bind_param("iis", $orderId, $staffId, $logLabel);
    $log->execute();
    $log->close();

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Location saved: ' . $location : 'Failed to save location.',
    ]);
    exit;
}

if ($action === 'mark_in_warehouse') {
    if ($assignment['status'] === 'received') {
        echo json_encode(['success' => false, 'message' => 'Order is already marked as received.']);
        exit;
    }

    if ($location) {
        $slotStmt = $conn->prepare("
            SELECT id, slot_number 
            FROM noblewarehouselocation 
            WHERE location_code = ? AND order_id IS NULL 
            ORDER BY slot_number ASC 
            LIMIT 1
        ");
        $slotStmt->bind_param("s", $location);
        $slotStmt->execute();
        $slot = $slotStmt->get_result()->fetch_assoc();
        $slotStmt->close();

        if (!$slot) {
            echo json_encode(['success' => false, 'message' => 'No available slots in ' . $location . '. Please choose another location.']);
            exit;
        }

        $assignSlot = $conn->prepare("
            UPDATE noblewarehouselocation 
            SET order_id = ?, po_id = ?, assigned_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $assignSlot->bind_param("iii", $orderId, $poId, $slot['id']);
        $assignSlot->execute();
        $assignSlot->close();

        $slotLocation = $location . ' — Slot ' . $slot['slot_number'];
    } else {
        $slotLocation = null;
    }

    $stmt = $conn->prepare("
        UPDATE noblereceivingreceiver
        SET status      = 'received',
            location    = COALESCE(NULLIF(?, ''), location),
            received_at = NOW(),
            updated_at  = NOW()
        WHERE order_id = ? AND po_id = ? AND assigned_staff_id = ?
    ");
    $stmt->bind_param("siii", $slotLocation, $orderId, $poId, $staffId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $logNote = 'Received in warehouse' . ($location ? ' at ' . $slotLocation : '') . ($notes ? ' — ' . $notes : '');
        $log = $conn->prepare("
            INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
            VALUES (?, 5, 'Received in warehouse', ?, ?, NOW())
        ");
        $log->bind_param("iis", $orderId, $staffId, $logNote);
        $log->execute();
        $log->close();

        // ── Notify warehouse staff/receiver ──
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

        $poNumber = $poDetails['po_number'] ?? "PO #{$poId}";
        $reference = $poDetails['nhccreference'] ?? '';
        $notifTitle = "Item Received in Warehouse";
        $notifMessage = "Purchase Order {$poNumber}" . ($reference ? " ({$reference})" : "") . " has been received and stored" . ($slotLocation ? " at {$slotLocation}" : "") . ".";

        $targets = [
            [POSITION_WAREHOUSESTAFF, BASE_URL . '/warehousestaff'],
            [POSITION_WAREHOUSERECEIVER, BASE_URL . '/warehousereceiver'],
        ];

        foreach ($targets as [$pos, $link]) {
            sendNotification(
                $conn,
                ROLE_WAREHOUSE,   // dati: hardcoded 'warehouse' — MALI
                $pos,
                null,
                $notifTitle,
                $notifMessage,
                $link
            );
        }

        // Check kung ALL POs under same nhccreference ay received na
        $checkAll = $conn->prepare("
            SELECT 
                COUNT(*) AS total_pos,
                SUM(CASE WHEN rr.status = 'received' THEN 1 ELSE 0 END) AS received_pos,
                ppl.nhccreference,
                ppl.contact_name
            FROM noblepurchaseorder npo
            JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
            LEFT JOIN noblereceivingreceiver rr ON rr.po_id = npo.id AND rr.status = 'received'
            WHERE npo.order_id = ?
            GROUP BY ppl.nhccreference, ppl.contact_name
            LIMIT 1
        ");
        $checkAll->bind_param("i", $orderId);
        $checkAll->execute();
        $allData = $checkAll->get_result()->fetch_assoc();
        $checkAll->close();

        if ($allData && $allData['total_pos'] > 0 && $allData['total_pos'] == $allData['received_pos']) {
            $ref = $allData['nhccreference'] ?? '';
            $contact = $allData['contact_name'] ?? '';
            $rbTitle = "Ready for Booking";
            $rbMessage = "All items for {$ref}" . ($contact ? " ({$contact})" : "") . " have been received in the warehouse and are now ready for booking.";
            $rbLink = BASE_URL . '/logisticstaff';

            foreach ([POSITION_LOGISTICSTAFF, POSITION_LOGISTICDISPATCHER] as $pos) {
                sendNotification(
                    $conn,
                    ROLE_LOGISTIC,
                    $pos,
                    null,
                    $rbTitle,
                    $rbMessage,
                    $rbLink
                );
            }
        }
    }

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Item received and stored in warehouse.' : 'Failed to update status.',
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);