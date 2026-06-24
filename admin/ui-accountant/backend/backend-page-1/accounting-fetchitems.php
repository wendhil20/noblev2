<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) { echo json_encode([]); exit; }

$s = $conn->prepare("
    SELECT id, nhccreference, contact_name, contact_email, contact_phone,
           delivery_method, truck_name, delivery_fee, subtotal, vat_amount,
           grand_total, payment_status, created_at, address_full,
           address_barangay, address_city, address_postalcode
    FROM noblepaidproductlist WHERE id = ?
");
$s->bind_param("i", $orderId);
$s->execute();
$order = $s->get_result()->fetch_assoc();
$s->close();

if (!$order) { echo json_encode([]); exit; }

$s2 = $conn->prepare("
    SELECT product_name, colorname, sizename, quantity, unit_price, discount_pct, line_total
    FROM noblepaidproductitems WHERE order_id = ? ORDER BY id ASC
");
$s2->bind_param("i", $orderId);
$s2->execute();
$items = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
$s2->close();

echo json_encode(['order' => $order, 'items' => $items]);