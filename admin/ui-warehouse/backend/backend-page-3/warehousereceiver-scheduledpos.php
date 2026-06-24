<?php
// warehousereceiver-scheduledpos.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

$staffId = $_SESSION['account_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT
        ppl.nhccreference,
        npo.po_number,
        npo.po_type,
        rr.suggested_date_from  AS date_from,
        rr.suggested_date_to    AS date_to
    FROM noblereceivingreceiver rr
    JOIN noblepaidproductlist ppl ON ppl.id = rr.order_id
    JOIN noblepurchaseorder npo ON npo.id = rr.po_id
    WHERE rr.assigned_staff_id = ?
      AND rr.ready_for_booking = 1
      AND rr.suggested_date_from IS NOT NULL
    ORDER BY rr.suggested_date_from ASC
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'scheduled' => $rows]);