<?php
// logisticstaff-cancelbooking.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$staffId   = $_SESSION['account_id'] ?? 0;
$data      = json_decode(file_get_contents('php://input'), true);
$bookingId = isset($data['booking_id']) ? (int) $data['booking_id'] : 0;

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID.']);
    exit;
}

// Fetch to get order_id + nhccref for logging
$fetch = $conn->prepare("SELECT order_id, nhccreference FROM nobledeliverybooking WHERE id = ? LIMIT 1");
$fetch->bind_param("i", $bookingId);
$fetch->execute();
$existing = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$existing) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

$orderId = $existing['order_id'];
$nhccref = $existing['nhccreference'];

$stmt = $conn->prepare("UPDATE nobledeliverybooking SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $bookingId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel booking.']);
    exit;
}

// Log
$log = $conn->prepare("
    INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
    VALUES (?, 7, 'Booking Cancelled', ?, ?, NOW())
");
$logNote = "Delivery booking cancelled for {$nhccref}.";
$log->bind_param("iis", $orderId, $staffId, $logNote);
$log->execute();
$log->close();

echo json_encode(['success' => true, 'message' => 'Booking cancelled.']);