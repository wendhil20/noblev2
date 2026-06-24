<?php
// warehouse-backendstaff-orders.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$staffId = $_SESSION['account_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT 
        oa.id AS assignment_id,
        oa.order_id,
        oa.status AS assignment_status,
        oa.type AS assignment_type,
        oa.assigned_at,
        ppl.nhccreference,
        ppl.contact_name,
        ppl.contact_phone,
        ppl.grand_total,
        ppl.payment_status,
        ppl.delivery_method
    FROM nobleorderassignment oa
    JOIN noblepaidproductlist ppl ON oa.order_id = ppl.id
    WHERE oa.staff_id = ?
    GROUP BY oa.id
    ORDER BY oa.assigned_at DESC
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$assignedOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$poTrackings = [];
if (!empty($assignedOrders)) {
    $orderIds = array_unique(array_column($assignedOrders, 'order_id'));
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));

    $ptStmt = $conn->prepare("
        SELECT 
            npo.id AS po_id,
            npo.order_id,
            npo.po_number,
            npo.po_type,
            CASE WHEN 
                npo.prepared_by_signature IS NOT NULL AND npo.prepared_by_signature != '' AND
                npo.noted_by_signature IS NOT NULL AND npo.noted_by_signature != '' AND
                npo.approved_by_signature IS NOT NULL AND npo.approved_by_signature != '' AND
                npo.acknowledged_by_signature IS NOT NULL AND npo.acknowledged_by_signature != '' AND
                npo.received_by_signature IS NOT NULL AND npo.received_by_signature != ''
            THEN 1 ELSE 0 END AS all_signed,
            (
                (CASE WHEN npo.prepared_by_signature IS NOT NULL AND npo.prepared_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.noted_by_signature IS NOT NULL AND npo.noted_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.approved_by_signature IS NOT NULL AND npo.approved_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.acknowledged_by_signature IS NOT NULL AND npo.acknowledged_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.received_by_signature IS NOT NULL AND npo.received_by_signature != '' THEN 1 ELSE 0 END)
            ) AS signed_count,
            ot.current_step,
            ot.expected_delivery_from,
            ot.expected_delivery_to,
            rr.assigned_staff_id AS receiver_id,
            rr.po_id AS receiver_po_id,
            nr.name AS receiver_name
        FROM noblepurchaseorder npo
        LEFT JOIN nobleordertracking ot ON ot.po_id = npo.id
        LEFT JOIN noblereceivingreceiver rr ON rr.po_id = npo.id
        LEFT JOIN noblerole nr ON nr.id = rr.assigned_staff_id
        WHERE npo.order_id IN ($placeholders)
        ORDER BY npo.id ASC
    ");
    $ptStmt->bind_param($types, ...$orderIds);
    $ptStmt->execute();
    $ptRows = $ptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ptStmt->close();

    foreach ($ptRows as $row) {
        $poTrackings[$row['order_id']][] = $row;
    }
}

echo json_encode([
    'success' => true,
    'orders' => $assignedOrders,
    'po_trackings' => $poTrackings
]);