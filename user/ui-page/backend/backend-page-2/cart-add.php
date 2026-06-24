<?php
// cart-add.php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

// Must be logged in
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Please login first.']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request.']);
    exit;
}

$userId    = intval($_SESSION['user_id']);
$productId = intval($_POST['product_id'] ?? 0);
$colorId   = intval($_POST['color_id']   ?? 0);
$variantId = intval($_POST['variant_id'] ?? 0);
$qty       = max(1, intval($_POST['qty'] ?? 1)); // minimum 1, must be a positive integer

if (!$productId || !$colorId || !$variantId) {
    echo json_encode(['ok' => false, 'msg' => 'Please select a color and size.']);
    exit;
}

// Validate color belongs to product
$check = $conn->prepare("SELECT id FROM nobleproductcolor WHERE id = ? AND product_id = ? LIMIT 1");
$check->bind_param("ii", $colorId, $productId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid selection.']);
    exit;
}
$check->close();

// Validate variant belongs to color, and pull its stock
$check2 = $conn->prepare("SELECT stock FROM nobleproductvariant WHERE id = ? AND color_id = ? LIMIT 1");
$check2->bind_param("ii", $variantId, $colorId);
$check2->execute();
$variantRow = $check2->get_result()->fetch_assoc();
$check2->close();

if (!$variantRow) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid selection.']);
    exit;
}

$availableStock = intval($variantRow['stock']);

if ($availableStock <= 0) {
    echo json_encode([
        'ok'           => false,
        'msg'          => 'This item is currently out of stock.',
        'out_of_stock' => true
    ]);
    exit;
}

// Check how much of this variant the user already has in cart
$curStmt = $conn->prepare("SELECT quantity FROM noblecart WHERE user_id = ? AND variant_id = ? LIMIT 1");
$curStmt->bind_param("ii", $userId, $variantId);
$curStmt->execute();
$curRow = $curStmt->get_result()->fetch_assoc();
$curStmt->close();

$currentQty = intval($curRow['quantity'] ?? 0);

// Cap qty to what's actually available
$qty = min($qty, $availableStock - $currentQty);

if ($qty <= 0) {
    echo json_encode([
        'ok'           => false,
        'msg'          => "Only {$availableStock} left in stock" . ($currentQty > 0 ? " (you already have {$currentQty} in your cart)" : "") . ".",
        'out_of_stock' => $availableStock <= $currentQty
    ]);
    exit;
}

// Insert or increment quantity by $qty
$stmt = $conn->prepare("
    INSERT INTO noblecart (user_id, product_id, color_id, variant_id, quantity)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity = quantity + ?
");
$stmt->bind_param("iiiiii", $userId, $productId, $colorId, $variantId, $qty, $qty);

if ($stmt->execute()) {
    // Get total cart count
    $countStmt = $conn->prepare("SELECT SUM(quantity) as total FROM noblecart WHERE user_id = ?");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countRow  = $countStmt->get_result()->fetch_assoc();
    $cartCount = intval($countRow['total'] ?? 0);
    $countStmt->close();

    $remainingStock = $availableStock - ($currentQty + $qty);

    echo json_encode([
        'ok'              => true,
        'msg'             => 'Added to cart!',
        'cart_count'      => $cartCount,
        'remaining_stock' => $remainingStock
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Failed to add to cart. Please try again.']);
}

$stmt->close();