<?php
// logisticdispatcher-getbookings.php
// Realtime polling endpoint — returns all active (non-cancelled) bookings as JSON
// so the dispatcher dashboard can re-render without a full page reload.
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICDISPATCHER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$stmt = $conn->prepare("
    SELECT db.*, ppl.nhccreference, ppl.contact_name, ppl.delivery_method
    FROM nobledeliverybooking db
    JOIN noblepaidproductlist ppl ON ppl.id = db.order_id
    WHERE db.status NOT IN ('cancelled')
    ORDER BY db.delivery_date ASC, db.scheduled_time_from ASC
");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'bookings' => $bookings, 'today' => (new DateTime())->format('Y-m-d')]);