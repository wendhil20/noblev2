<?php
// logisticstaff-getbookings.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICSTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = ["db.status NOT IN ('cancelled')"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(ppl.nhccreference LIKE ? OR ppl.contact_name LIKE ? OR db.driver_name LIKE ? OR db.plate_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if ($status !== '' && $status !== 'all') {
    $where[] = "db.status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = implode(' AND ', $where);

$sql = "
    SELECT
        db.id,
        db.order_id,
        db.scheduled_date,
        db.delivery_date,
        db.scheduled_time_from,
        db.truck_details,
        db.plate_number,
        db.driver_name,
        db.delivery_address,
        db.notes,
        db.status,
        db.po_type,
        db.created_at,
        ppl.nhccreference,
        ppl.contact_name,
        ppl.delivery_method,
        ppl.truck_name AS order_truck_name,
        ppl.truck_max_cubic_meter AS order_truck_max_cubic_meter,
        ppl.truck_max_weight_capacity AS order_truck_max_weight_capacity,
        ppl.address_full AS order_address_full,
        ppl.address_barangay AS order_address_barangay,
        ppl.address_city AS order_address_city,
        ppl.address_postalcode AS order_address_postalcode
    FROM nobledeliverybooking db
    JOIN noblepaidproductlist ppl ON ppl.id = db.order_id
    WHERE {$whereClause}
    ORDER BY db.scheduled_date DESC, db.scheduled_time_from DESC
";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'bookings' => $bookings]);