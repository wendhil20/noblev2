<?php
// cart-mini.php — AJAX endpoint para sa mini cart dropdown sa navbar
header('Content-Type: application/json');

include ROOT_PATH . '/network/connect.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Not logged in']);
    exit;
}

$userId = intval($_SESSION['user_id']);

// 5 latest items lang ipapakita sa dropdown (para hindi mahaba, "View Cart" na lang sa buong list)
$stmt = $conn->prepare("
    SELECT
        nc.id AS cart_id,
        nc.quantity,
        p.id AS product_id,
        p.name AS product_name,
        p.imageproduct,
        c.colorname,
        c.imagecolor,
        v.sizename,
        v.pricesize,
        v.discountvariant,
        v.stock AS variant_stock
    FROM noblecart nc
    JOIN nobleproduct p ON p.id = nc.product_id
    JOIN nobleproductcolor c ON c.id = nc.color_id
    JOIN nobleproductvariant v ON v.id = nc.variant_id
    WHERE nc.user_id = ?
    ORDER BY nc.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Kunin LAHAT ng cart rows (hindi lang yung LIMIT 5) para tama yung subtotal AT yung count ng distinct products
$allStmt = $conn->prepare("
    SELECT nc.quantity, v.pricesize, v.discountvariant
    FROM noblecart nc
    JOIN nobleproductvariant v ON v.id = nc.variant_id
    WHERE nc.user_id = ?
");
$allStmt->bind_param("i", $userId);
$allStmt->execute();
$allRows = $allStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allStmt->close();

$subtotal = 0;
foreach ($allRows as $r) {
    $price = floatval($r['pricesize']);
    $discount = floatval($r['discountvariant']);
    $final = $discount > 0 ? $price * (1 - $discount / 100) : $price;
    $qty = intval($r['quantity']);
    $subtotal += $final * $qty;
}

// Count = bilang ng distinct na cart rows (per product/variant), HINDI sum ng quantity
$count = count($allRows);

$items = array_map(function ($row) {
    $price = floatval($row['pricesize']);
    $discount = floatval($row['discountvariant']);
    $final = $discount > 0 ? $price * (1 - $discount / 100) : $price;
    $image = !empty($row['imagecolor']) ? $row['imagecolor'] : $row['imageproduct'];
    $variant = trim(($row['colorname'] ?? '') . (!empty($row['sizename']) ? ' · ' . $row['sizename'] : ''));

    return [
        'cart_id'    => $row['cart_id'],
        'product_id' => $row['product_id'],
        'name'       => $row['product_name'],
        'image'      => $image,
        'variant'    => $variant,
        'quantity'   => intval($row['quantity']),
        'price'      => $final,
        'stock'      => intval($row['variant_stock']),
    ];
}, $rows);

echo json_encode([
    'ok'       => true,
    'items'    => $items,
    'count'    => $count,
    'subtotal' => $subtotal,
]);