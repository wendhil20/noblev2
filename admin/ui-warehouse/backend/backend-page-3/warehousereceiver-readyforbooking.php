<?php
// warehousereceiver-readyforbooking.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$orderId  = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$dateFrom = isset($data['suggested_date_from']) ? $data['suggested_date_from'] : null;
$dateTo   = isset($data['suggested_date_to'])   ? $data['suggested_date_to']   : null;
$staffId  = $_SESSION['account_id'] ?? 0;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID.']);
    exit;
}

if (!$dateFrom || !$dateTo || $dateTo < $dateFrom) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid delivery date range.']);
    exit;
}

$refStmt = $conn->prepare("
    SELECT nhccreference, contact_name FROM noblepaidproductlist WHERE id = ? LIMIT 1
");
$refStmt->bind_param("i", $orderId);
$refStmt->execute();
$refData = $refStmt->get_result()->fetch_assoc();
$refStmt->close();

if (!$refData) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

$nhccreference = $refData['nhccreference'];
$contactName   = $refData['contact_name'];

$checkStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_pos,
        SUM(CASE WHEN rr.status = 'received' AND rr.location IS NOT NULL THEN 1 ELSE 0 END) AS ready_pos
    FROM noblepurchaseorder npo
    JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
    JOIN noblereceivingreceiver rr ON rr.po_id = npo.id
    WHERE ppl.nhccreference = ?
");
$checkStmt->bind_param("s", $nhccreference);
$checkStmt->execute();
$checkData = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$checkData || $checkData['total_pos'] == 0) {
    echo json_encode(['success' => false, 'message' => 'No POs found for this reference.']);
    exit;
}

if ($checkData['total_pos'] != $checkData['ready_pos']) {
    echo json_encode(['success' => false, 'message' => 'Not all items are received and stored in warehouse yet.']);
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE noblereceivingreceiver rr
    JOIN noblepurchaseorder npo ON npo.id = rr.po_id
    JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
    SET rr.ready_for_booking = 1,
        rr.ready_for_booking_at = NOW(),
        rr.suggested_date_from = ?,
        rr.suggested_date_to = ?,
        rr.updated_at = NOW()
    WHERE ppl.nhccreference = ? AND (rr.ready_for_booking = 0 OR rr.ready_for_booking IS NULL)
");
$updateStmt->bind_param("sss", $dateFrom, $dateTo, $nhccreference);
$ok = $updateStmt->execute();
$updateStmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    exit;
}

// ── Notify logistics ──
$rbTitle   = "Ready for Booking";
$rbMessage = "All items for {$nhccreference}" . ($contactName ? " ({$contactName})" : "") . " have been received in the warehouse and are now ready for booking.";
$rbLink    = BASE_URL . '/logisticstaff';

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

// Log
$log = $conn->prepare("
    INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
    VALUES (?, 6, 'Ready for Booking', ?, ?, NOW())
");
$logNote = "Marked as ready for booking: {$nhccreference}";
$log->bind_param("iis", $orderId, $staffId, $logNote);
$log->execute();
$log->close();

echo json_encode([
    'success' => true,
    'message' => 'Marked as ready for booking. Logistics has been notified.',
]);