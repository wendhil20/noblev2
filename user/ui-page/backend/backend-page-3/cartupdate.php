<?php
// cartupdate.php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

// Must be logged in
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized.']);
    exit;
}

$userId  = intval($_SESSION['user_id']);
$cartId  = intval($_POST['cart_id'] ?? 0);
$qty     = intval($_POST['quantity'] ?? 1);

if (!$cartId || $qty < 1 || $qty > 99) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid input.']);
    exit;
}

// Verify cart item belongs to this user and get variant price
$stmt = $conn->prepare("
    SELECT nc.id, v.pricesize, v.discountvariant
    FROM noblecart nc
    JOIN nobleproductvariant v ON v.id = nc.variant_id
    WHERE nc.id = ? AND nc.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $cartId, $userId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    echo json_encode(['ok' => false, 'msg' => 'Cart item not found.']);
    exit;
}

// Update quantity
$upd = $conn->prepare("UPDATE noblecart SET quantity = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
$upd->bind_param("iii", $qty, $cartId, $userId);
$upd->execute();
$upd->close();

// Compute final price for this variant
$price      = floatval($item['pricesize']);
$discount   = floatval($item['discountvariant']);
$finalPrice = $discount > 0 ? $price * (1 - $discount / 100) : $price;

// Compute new subtotal for all cart items of this user
$subStmt = $conn->prepare("
    SELECT SUM(
        CASE WHEN v.discountvariant > 0
             THEN v.pricesize * (1 - v.discountvariant / 100) * nc.quantity
             ELSE v.pricesize * nc.quantity
        END
    ) AS subtotal
    FROM noblecart nc
    JOIN nobleproductvariant v ON v.id = nc.variant_id
    WHERE nc.user_id = ?
");
$subStmt->bind_param("i", $userId);
$subStmt->execute();
$subtotal = floatval($subStmt->get_result()->fetch_assoc()['subtotal'] ?? 0);
$subStmt->close();

echo json_encode([
    'ok'          => true,
    'final_price' => $finalPrice,
    'subtotal'    => $subtotal,
]);