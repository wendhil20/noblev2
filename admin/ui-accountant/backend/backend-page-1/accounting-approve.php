<?php
// admin/ui-accountant/backend/accounting-approve.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$orderId = intval($_POST['order_id'] ?? 0);
if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'Invalid order.']);
    exit;
}

// Fetch order details for notification message
$s = $conn->prepare("SELECT nhccreference, contact_name, grand_total FROM noblepaidproductlist WHERE id = ?");
$s->bind_param("i", $orderId);
$s->execute();
$order = $s->get_result()->fetch_assoc();
$s->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

// Update status to approved
$u = $conn->prepare("UPDATE noblepaidproductlist SET payment_status = 'approved' WHERE id = ?");
$u->bind_param("i", $orderId);
$u->execute();
$u->close();

// ── Notify WAREHOUSE DEPARTMENT head (specific user) ──
$notifTitle = 'Order Approved: ' . $order['nhccreference'];
$notifMsg   = 'Order from ' . $order['contact_name'] . ' — ₱' . number_format(floatval($order['grand_total']), 2) . ' is ready for warehouse processing.';
$notifLink  = BASE_URL . '/warehousemain';
$notifRole  = ROLE_WAREHOUSE;
$notifPos   = POSITION_HEAD;

// Kunin ang specific Warehouse Head id
$headStmt = $conn->prepare("
    SELECT id FROM noblerole 
    WHERE role = ? AND position = ? 
    LIMIT 1
");
$headStmt->bind_param("ss", $notifRole, $notifPos);
$headStmt->execute();
$headRow = $headStmt->get_result()->fetch_assoc();
$headStmt->close();

$forUserId = $headRow['id'] ?? null;

sendNotification(
    $conn,
    $notifRole,
    $notifPos,
    $forUserId,   // specific Warehouse Head id
    $notifTitle,
    $notifMsg,
    $notifLink
);

echo json_encode(['success' => true]);