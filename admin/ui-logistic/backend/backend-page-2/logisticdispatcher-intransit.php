<?php
// logisticdispatcher-intransit.php
// Marks a booking as 'in_transit' once all items are loaded and the truck departs.
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
    SELECT id, status
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

if ($booking['status'] !== 'loading') {
    echo json_encode(['success' => false, 'message' => 'This booking must be in "loading" status first.']);
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE nobledeliverybooking
    SET status = 'in_transit', in_transit_at = NOW()
    WHERE id = ? AND status = 'loading'
");
$updateStmt->bind_param("i", $bookingId);

if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update booking. It may have already changed status.']);
}
$updateStmt->close();