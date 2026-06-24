<?php
// logisticstaff-reschedulebooking.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$staffId = $_SESSION['account_id'] ?? 0;
$data    = json_decode(file_get_contents('php://input'), true);

$bookingId    = isset($data['booking_id'])           ? (int) $data['booking_id']             : 0;
$schedDate    = isset($data['scheduled_date'])       ? trim($data['scheduled_date'])          : '';
$deliveryDate = isset($data['delivery_date'])        ? trim($data['delivery_date'])           : '';
$timeFrom     = isset($data['scheduled_time_from'])  ? trim($data['scheduled_time_from'])     : '';
$truckDetails = isset($data['truck_details'])        ? trim($data['truck_details'])           : '';
$plateNumber  = isset($data['plate_number'])         ? trim($data['plate_number'])            : '';
$driverName   = isset($data['driver_name'])          ? trim($data['driver_name'])             : '';
$address      = isset($data['delivery_address'])     ? trim($data['delivery_address'])        : '';
$reason       = isset($data['reschedule_reason'])    ? trim($data['reschedule_reason'])       : '';
$notes        = isset($data['notes'])                ? trim($data['notes'])                   : '';

if (!$bookingId || !$schedDate || !$deliveryDate || !$timeFrom || !$truckDetails || !$plateNumber || !$driverName || !$address || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate date
$dow = (int) date('w', strtotime($schedDate));
if ($dow === 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot schedule on Sundays.']);
    exit;
}

// Validate delivery date
$deliveryDow = (int) date('w', strtotime($deliveryDate));
if ($deliveryDow === 0) {
    echo json_encode(['success' => false, 'message' => 'Delivery date cannot fall on a Sunday. Please choose another date.']);
    exit;
}

$isSat  = $dow === 6;
$openH  = $isSat ? '08:00' : '07:00';
$closeH = $isSat ? '12:00' : '17:00';

if ($timeFrom < $openH || $timeFrom >= $closeH) {
    echo json_encode(['success' => false, 'message' => 'Time is outside company operating hours.']);
    exit;
}

// Fetch existing booking to get order_id + nhccref
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

// Append reason to notes
$fullNotes = $notes ? "Reason: {$reason}\n{$notes}" : "Reason: {$reason}";

$stmt = $conn->prepare("
    UPDATE nobledeliverybooking SET
        scheduled_date       = ?,
        delivery_date        = ?,
        scheduled_time_from  = ?,
        truck_details        = ?,
        plate_number         = ?,
        driver_name          = ?,
        delivery_address     = ?,
        notes                = ?,
        status               = 'rescheduled',
        updated_at           = NOW()
    WHERE id = ?
");
$stmt->bind_param("ssssssssi", $schedDate, $deliveryDate, $timeFrom, $truckDetails, $plateNumber, $driverName, $address, $fullNotes, $bookingId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to update booking.']);
    exit;
}

// Log
$log = $conn->prepare("
    INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
    VALUES (?, 7, 'Delivery Rescheduled', ?, ?, NOW())
");
$logNote = "Rescheduled to {$schedDate} {$timeFrom} (delivery date: {$deliveryDate}). Driver: {$driverName}. Plate: {$plateNumber}. Reason: {$reason}";
$log->bind_param("iis", $orderId, $staffId, $logNote);
$log->execute();
$log->close();

// ── Notify warehouse (gamit na ang helper, walang manual SQL) ──
$title   = "Delivery Rescheduled";
$message = "Delivery for {$nhccref} has been rescheduled to {$schedDate} ({$timeFrom}), delivery date {$deliveryDate}. Reason: {$reason}";
$link    = BASE_URL . '/warehousereceiver';

sendNotification(
    $conn,
    ROLE_WAREHOUSE,
    POSITION_WAREHOUSERECEIVER,
    null,           // broadcast — lahat ng warehouse receiver
    $title,
    $message,
    $link
);

echo json_encode(['success' => true, 'message' => 'Delivery rescheduled successfully.']);