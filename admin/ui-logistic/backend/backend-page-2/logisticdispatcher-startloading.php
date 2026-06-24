<?php
// logisticdispatcher-startloading.php
// Marks a booking as 'loading' once items are being loaded onto the truck.
// Only allowed once delivery_date has arrived (today or earlier).
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICDISPATCHER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$bookingId = (int) ($input['booking_id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking id.']);
    exit;
}

$fetchStmt = $conn->prepare("
    SELECT id, status, delivery_date, driver_name, truck_details, plate_number, delivery_address
    FROM nobledeliverybooking
    WHERE id = ?
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

if (!in_array($booking['status'], ['scheduled', 'rescheduled'], true)) {
    echo json_encode(['success' => false, 'message' => 'This booking is not in a loadable state.']);
    exit;
}

if (empty($booking['driver_name']) || empty($booking['truck_details']) || empty($booking['plate_number']) || empty($booking['delivery_address'])) {
    echo json_encode(['success' => false, 'message' => 'Delivery details (driver, truck, plate, address) must be completed before loading can start.']);
    exit;
}

$today = (new DateTime())->setTime(0, 0, 0)->format('Y-m-d');
if ($booking['delivery_date'] > $today) {
    echo json_encode(['success' => false, 'message' => 'Loading can only start on or after the delivery date.']);
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE nobledeliverybooking
    SET status = 'loading', loading_at = NOW()
    WHERE id = ? AND status IN ('scheduled', 'rescheduled')
");
$updateStmt->bind_param("i", $bookingId);

if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update booking. It may have already changed status.']);
}
$updateStmt->close();