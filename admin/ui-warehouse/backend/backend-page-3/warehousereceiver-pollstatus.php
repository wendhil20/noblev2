<?php
// warehousereceiver-pollstatus.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

$staffId = $_SESSION['account_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT
        rr.id               AS assignment_id,
        rr.order_id,
        rr.po_id,
        rr.status           AS receiver_status,
        rr.ready_for_booking,
        rr.qr_path,
        rr.location,
        npo.po_number,
        npo.po_type,
        ppl.nhccreference
    FROM noblereceivingreceiver rr
    JOIN noblepaidproductlist ppl ON ppl.id = rr.order_id
    JOIN noblepurchaseorder npo ON npo.id = rr.po_id
    WHERE rr.assigned_staff_id = ?
    ORDER BY rr.assigned_at DESC
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statusLabels = [
    'pending'  => ['label' => 'Pending',  'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
    'received' => ['label' => 'Received', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
];

// Compute ref group summaries
$refGroups = [];
foreach ($rows as $row) {
    $ref = $row['nhccreference'];
    if (!isset($refGroups[$ref])) {
        $refGroups[$ref] = [
            'total'                  => 0,
            'received_with_location' => 0,
            'ready_for_booking'      => 0,
            'order_id'               => $row['order_id'],
        ];
    }
    $refGroups[$ref]['total']++;
    if ($row['receiver_status'] === 'received' && !empty($row['location'])) {
        $refGroups[$ref]['received_with_location']++;
    }
    if ($row['ready_for_booking']) {
        $refGroups[$ref]['ready_for_booking']++;
    }
}

// Build assignments array for JS
$assignments = [];
foreach ($rows as $row) {
    $badge = $statusLabels[$row['receiver_status']]
        ?? ['label' => ucfirst($row['receiver_status']), 'class' => 'bg-slate-100 text-slate-500 border-slate-200'];

    $assignments[] = [
        'assignment_id'     => (int) $row['assignment_id'],
        'po_id'             => (int) $row['po_id'],
        'order_id'          => (int) $row['order_id'],
        'nhccreference'     => $row['nhccreference'],
        'receiver_status'   => $row['receiver_status'],
        'ready_for_booking' => (bool) $row['ready_for_booking'],
        'qr_path'           => $row['qr_path'],
        'location'          => $row['location'],
        'po_type'           => $row['po_type'] ?? 'normal', // ✅ added
        'badge_label'       => $badge['label'],
        'badge_class'       => $badge['class'],
    ];
}

echo json_encode([
    'success'      => true,
    'assignments'  => $assignments,
    'refSummaries' => $refGroups,
]);