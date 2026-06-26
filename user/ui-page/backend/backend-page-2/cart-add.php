<?php
// cart-add.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-2/productqtydiscount-helper.php';

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

// Check how much of THIS VARIANT the user already has in cart (stock-level check)
$curStmt = $conn->prepare("SELECT quantity FROM noblecart WHERE user_id = ? AND variant_id = ? LIMIT 1");
$curStmt->bind_param("ii", $userId, $variantId);
$curStmt->execute();
$curRow = $curStmt->get_result()->fetch_assoc();
$curStmt->close();

$currentVariantQty = intval($curRow['quantity'] ?? 0);

// ─── Product-level limit check ──────────────────────────────────────────
// Ang limit ay PER PRODUCT, hindi per variant — kaya kailangan i-sum ang
// quantity ng LAHAT ng color/size ng product na ito na nasa cart na ng user.
$productQtyStmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM noblecart WHERE user_id = ? AND product_id = ?");
$productQtyStmt->bind_param("ii", $userId, $productId);
$productQtyStmt->execute();
$currentProductQty = intval($productQtyStmt->get_result()->fetch_assoc()['total'] ?? 0);
$productQtyStmt->close();

$productLimit = getProductQtyLimit($conn, $productId); // 0 = walang limit
// ─────────────────────────────────────────────────────────────────────────

// Cap qty base sa stock ng specific variant
$qty = min($qty, $availableStock - $currentVariantQty);

// Cap qty base sa product-level max quantity per order (kung may limit)
$limitReached = false;
if ($productLimit > 0) {
    $remainingByLimit = $productLimit - $currentProductQty;
    if ($qty > $remainingByLimit) {
        $qty = $remainingByLimit;
        $limitReached = true;
    }
}

if ($qty <= 0) {
    if ($availableStock <= $currentVariantQty) {
        echo json_encode([
            'ok'           => false,
            'msg'          => "Only {$availableStock} left in stock" . ($currentVariantQty > 0 ? " (you already have {$currentVariantQty} in your cart)" : "") . ".",
            'out_of_stock' => true
        ]);
    } else {
        // Naabot na ang max quantity per order ng product
        echo json_encode([
            'ok'            => false,
            'msg'           => "Max {$productLimit} pcs lang ang pwedeng bilhin per order para sa product na ito (already {$currentProductQty} in your cart).",
            'limit_reached' => true
        ]);
    }
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
    // Get total cart count (lahat ng products, para sa cart badge)
    $countStmt = $conn->prepare("SELECT SUM(quantity) as total FROM noblecart WHERE user_id = ?");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countRow  = $countStmt->get_result()->fetch_assoc();
    $cartCount = intval($countRow['total'] ?? 0);
    $countStmt->close();

    $remainingStock  = $availableStock - ($currentVariantQty + $qty);
    $newProductQty   = $currentProductQty + $qty;

    // ─── Qty-tier discount preview ───────────────────────────────────
    // Para magamit agad ng frontend sa pag-display ng "X% off" o updated total,
    // batay sa BAGONG total quantity ng product na ito sa cart ng user.
    $tiers           = getProductQtyTiers($conn, $productId);
    $discountPercent = resolveQtyDiscountPercent($tiers, $newProductQty);
    // ───────────────────────────────────────────────────────────────────

    $response = [
        'ok'                  => true,
        'msg'                 => $limitReached
            ? "Added to cart! (capped to max {$productLimit} pcs per order)"
            : 'Added to cart!',
        'cart_count'          => $cartCount,
        'remaining_stock'     => $remainingStock,
        'product_qty_in_cart' => $newProductQty,
        'qty_limit'           => $productLimit,            // 0 = walang limit
        'qty_discount_percent'=> $discountPercent,         // 0.0 = walang applicable discount
        'limit_reached'       => $limitReached,            // true kung na-cap dahil sa limit
    ];

    echo json_encode($response);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Failed to add to cart. Please try again.']);
}

$stmt->close();