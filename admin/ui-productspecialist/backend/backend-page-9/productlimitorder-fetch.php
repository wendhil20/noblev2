<?php
// productlimitorder-fetch.php
// GET ?product_id=123 -> ibabalik ang kasalukuyang limit + discount tiers ng product

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$productId = intval($_GET['product_id'] ?? 0);
if (!$productId) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid product.']);
    exit;
}

// Current limit
$limitStmt = $conn->prepare("SELECT max_qty_per_order FROM nobleproductlimit WHERE product_id = ? LIMIT 1");
$limitStmt->bind_param("i", $productId);
$limitStmt->execute();
$limitRow = $limitStmt->get_result()->fetch_assoc();
$limitStmt->close();

// Current tiers, naka-sort by min_qty para madaling tignan sa modal
$tierStmt = $conn->prepare("
    SELECT min_qty, max_qty, discount_percent
    FROM nobleproductqtytier
    WHERE product_id = ?
    ORDER BY min_qty ASC
");
$tierStmt->bind_param("i", $productId);
$tierStmt->execute();
$tierResult = $tierStmt->get_result();

$tiers = [];
while ($t = $tierResult->fetch_assoc()) {
    $tiers[] = [
        'min_qty' => (int) $t['min_qty'],
        'max_qty' => (int) $t['max_qty'],
        'discount_percent' => (float) $t['discount_percent'],
    ];
}
$tierStmt->close();

echo json_encode([
    'ok' => true,
    'max_qty_per_order' => $limitRow ? (int) $limitRow['max_qty_per_order'] : 0,
    'tiers' => $tiers,
]);