<?php
// logisticstaff-savebookingdetails.php
// STEP 2 — fills in driver/truck/plate/address on an existing scheduled booking,
// then notifies the logistics dispatcher.
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICSTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$bookingId = (int) ($input['booking_id'] ?? 0);
$truck = trim($input['truck_details'] ?? '');
$plate = trim($input['plate_number'] ?? '');
$driver = trim($input['driver_name'] ?? '');
$address = trim($input['delivery_address'] ?? '');
$notes = trim($input['notes'] ?? '');

if (!$bookingId || !$truck || !$plate || !$driver || !$address) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Fetch the booking + reference info for the notification message
$fetchStmt = $conn->prepare("
    SELECT db.id, db.scheduled_date, db.scheduled_time_from, db.status,
           ppl.nhccreference, ppl.contact_name
    FROM nobledeliverybooking db
    JOIN noblepaidproductlist ppl ON ppl.id = db.order_id
    WHERE db.id = ?
    LIMIT 1
");
$fetchStmt->bind_param("i", $bookingId);
$fetchStmt->execute();
$booking = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if ($booking['status'] === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'This booking has been cancelled.']);
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE nobledeliverybooking
    SET truck_details = ?, plate_number = ?, driver_name = ?, delivery_address = ?, notes = ?
    WHERE id = ?
");
$updateStmt->bind_param("sssssi", $truck, $plate, $driver, $address, $notes, $bookingId);

if (!$updateStmt->execute()) {
    $updateStmt->close();
    echo json_encode(['success' => false, 'message' => 'Database error while saving delivery details.']);
    exit;
}
$updateStmt->close();

// ── Notify the logistics dispatcher (broadcast — walang specific account) ──
$title = 'New Delivery Ready for Dispatch';
$message = sprintf(
    '%s (%s) has been scheduled for %s at %s with driver %s (plate %s).',
    $booking['nhccreference'],
    $booking['contact_name'],
    date('M j, Y', strtotime($booking['scheduled_date'])),
    date('g:ia', strtotime($booking['scheduled_time_from'])),
    $driver,
    $plate
);
$link = BASE_URL . '/logisticdispatcher';

sendNotification(
    $conn,
    ROLE_LOGISTIC,
    POSITION_LOGISTICDISPATCHER,
    null,
    $title,
    $message,
    $link
);

echo json_encode(['success' => true]);