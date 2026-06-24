<?php
// cartremove.php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

// Must be logged in
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized.']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$cartId = intval($_POST['cart_id'] ?? 0);

if (!$cartId) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid input.']);
    exit;
}

// Delete — scoped to user so they can't delete others' items
$stmt = $conn->prepare("DELETE FROM noblecart WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $cartId, $userId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if (!$affected) {
    echo json_encode(['ok' => false, 'msg' => 'Item not found.']);
    exit;
}

// Recompute subtotal
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

// Count remaining items
$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM noblecart WHERE user_id = ?");
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$cartCount = intval($countStmt->get_result()->fetch_assoc()['cnt']);
$countStmt->close();

echo json_encode([
    'ok'         => true,
    'subtotal'   => $subtotal,
    'cart_count' => $cartCount,
]);