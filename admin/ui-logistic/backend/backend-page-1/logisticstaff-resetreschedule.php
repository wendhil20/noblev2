<?php
// logisticstaff-resetreschedule.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICSTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$bookingId    = (int) ($input['booking_id'] ?? 0);
$schedDate    = trim($input['scheduled_date'] ?? '');
$deliveryDate = trim($input['delivery_date'] ?? '');
$schedTimeFrom = trim($input['scheduled_time_from'] ?? '');

if (!$bookingId || !$schedDate || !$deliveryDate || !$schedTimeFrom) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate scheduled date is not a Sunday
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

// Fetch booking — must exist and must be a replacement PO
$checkStmt = $conn->prepare("SELECT po_type, status FROM nobledeliverybooking WHERE id = ? LIMIT 1");
$checkStmt->bind_param("i", $bookingId);
$checkStmt->execute();
$booking = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if (($booking['po_type'] ?? 'normal') !== 'replacement') {
    echo json_encode(['success' => false, 'message' => 'Reset & Reschedule is only available for replacement POs.']);
    exit;
}

// Only lock when the truck is actually moving. "Delivered" is intentionally
// allowed here for replacement POs — that's the whole point of a replacement:
// the original was already delivered, and a new schedule is needed for the
// replacement item itself.
if ($booking['status'] === 'in_transit') {
    echo json_encode(['success' => false, 'message' => 'Cannot reset a booking that is currently in transit.']);
    exit;
}

// Reset delivery details, apply the new schedule, send it back to "needs details"
$updateStmt = $conn->prepare("
    UPDATE nobledeliverybooking
    SET scheduled_date = ?, delivery_date = ?, scheduled_time_from = ?,
        truck_details = NULL, plate_number = NULL, driver_name = NULL,
        delivery_address = NULL, notes = NULL,
        status = 'scheduled', updated_at = NOW()
    WHERE id = ?
");
$updateStmt->bind_param("sssi", $schedDate, $deliveryDate, $schedTimeFrom, $bookingId);

if ($updateStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Booking reset. Please add new delivery details.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error while resetting the booking.']);
}
$updateStmt->close();