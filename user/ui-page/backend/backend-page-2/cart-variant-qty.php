<?php
// cart-variant-qty.php
// Ginagamit ng mainproductview.php para i-refresh ang stock-vs-cart-qty display
// nang hindi kailangan mag-full page reload. Basahin lang, walang mutation.

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Not logged in.']);
    exit;
}

$userId    = intval($_SESSION['user_id']);
$productId = intval($_GET['product_id'] ?? 0);

if (!$productId) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid product.']);
    exit;
}

// Cart qty per variant_id para sa product na ito (ng currently logged-in user)
$variantQty = [];
$total = 0;

$stmt = $conn->prepare("
    SELECT variant_id, SUM(quantity) as qty
    FROM noblecart
    WHERE user_id = ? AND product_id = ?
    GROUP BY variant_id
");
$stmt->bind_param("ii", $userId, $productId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $q = intval($row['qty']);
    $variantQty[intval($row['variant_id'])] = $q;
    $total += $q;
}
$stmt->close();

echo json_encode([
    'ok'          => true,
    'variant_qty' => $variantQty, // variant_id => qty na nasa cart
    'product_qty' => $total,      // total qty ng product na ito sa cart (lahat ng color/size)
]);