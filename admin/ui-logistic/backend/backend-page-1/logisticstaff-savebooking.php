<?php
// logisticstaff-savebooking.php
// STEP 1 — creates a schedule-only booking row (no driver/truck/address yet)
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICSTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$staffId = $_SESSION['account_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

$orderId = (int) ($input['order_id'] ?? 0);
$poId = (int) ($input['po_id'] ?? 0);
$nhccRef = trim($input['nhccreference'] ?? '');
$schedDate = trim($input['scheduled_date'] ?? '');
$deliveryDate = trim($input['delivery_date'] ?? '');
$schedTimeFrom = trim($input['scheduled_time_from'] ?? '');

if (!$orderId || !$schedDate || !$deliveryDate || !$schedTimeFrom) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate date is not a Sunday
$dow = (int) date('w', strtotime($schedDate));
if ($dow === 0) {
    echo json_encode(['success' => false, 'message' => 'Closed on Sundays. Please choose another date.']);
    exit;
}

// Validate delivery date is not a Sunday
$deliveryDow = (int) date('w', strtotime($deliveryDate));
if ($deliveryDow === 0) {
    echo json_encode(['success' => false, 'message' => 'Delivery date cannot fall on a Sunday. Please choose another date.']);
    exit;
}

// Fetch po_type for this PO so the booking row reflects normal vs replacement
$poType = 'normal';
if ($poId) {
    $poTypeStmt = $conn->prepare("SELECT po_type FROM noblepurchaseorder WHERE id = ? LIMIT 1");
    $poTypeStmt->bind_param("i", $poId);
    $poTypeStmt->execute();
    $poTypeRow = $poTypeStmt->get_result()->fetch_assoc();
    $poTypeStmt->close();
    $poType = $poTypeRow['po_type'] ?? 'normal';
}

// Prevent duplicate active bookings for the same order
$checkStmt = $conn->prepare("
    SELECT id FROM nobledeliverybooking
    WHERE order_id = ? AND status NOT IN ('cancelled')
    LIMIT 1
");
$checkStmt->bind_param("i", $orderId);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'This order already has an active booking.']);
    exit;
}

$insertStmt = $conn->prepare("
    INSERT INTO nobledeliverybooking
        (order_id, po_id, nhccreference, po_type, scheduled_date, delivery_date, scheduled_time_from, status, booked_by, created_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
");
$insertStmt->bind_param("iisssssi", $orderId, $poId, $nhccRef, $poType, $schedDate, $deliveryDate, $schedTimeFrom, $staffId);

if ($insertStmt->execute()) {
    echo json_encode(['success' => true, 'booking_id' => $insertStmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error while saving the schedule.']);
}
$insertStmt->close();