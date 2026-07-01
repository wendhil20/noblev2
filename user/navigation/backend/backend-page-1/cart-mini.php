<?php
// cart-mini.php — AJAX endpoint para sa mini cart dropdown sa navbar
header('Content-Type: application/json');

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-2/productqtydiscount-helper.php';

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
        c.id AS color_id,
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

// Kunin LAHAT ng cart rows (hindi lang yung LIMIT 5) para tama yung subtotal AT yung count ng distinct products.
// Kasama na ang product_id dito kasi kailangan ito para sa qty-tier discount resolution
// (ang tier ay base sa TOTAL quantity ng isang product sa buong cart, hindi lang sa isang row).
$allStmt = $conn->prepare("
    SELECT nc.quantity, nc.product_id, nc.color_id, v.sizename, v.pricesize, v.discountvariant
    FROM noblecart nc
    JOIN nobleproductvariant v ON v.id = nc.variant_id
    WHERE nc.user_id = ?
");
$allStmt->bind_param("i", $userId);
$allStmt->execute();
$allRows = $allStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allStmt->close();

// ── Active promos (date-based, per-color/size or general) para sa cart products ──
$promosByProduct = []; // product_id => [ {color_id, sizename, discount_percent}, ... ]
$cartProductIds = array_unique(array_column($allRows, 'product_id'));
if (!empty($cartProductIds)) {
    $idPh = implode(',', array_fill(0, count($cartProductIds), '?'));
    $idTypes = str_repeat('i', count($cartProductIds));
    $promoStmt = $conn->prepare("
        SELECT product_id, color_id, sizename, discount_percent
        FROM nobleproductpromo
        WHERE product_id IN ($idPh) AND NOW() BETWEEN start_date AND end_date
    ");
    $promoStmt->bind_param($idTypes, ...$cartProductIds);
    $promoStmt->execute();
    $promoRes = $promoStmt->get_result();
    while ($row = $promoRes->fetch_assoc()) {
        $promosByProduct[$row['product_id']][] = [
            'color_id'         => $row['color_id'] !== null ? intval($row['color_id']) : null,
            'sizename'         => $row['sizename'],
            'discount_percent' => floatval($row['discount_percent']),
        ];
    }
    $promoStmt->close();
}

function resolvePromoDiscount(array $promosByProduct, $productId, $colorId, $sizeName) {
    $best = 0;
    foreach ($promosByProduct[$productId] ?? [] as $promo) {
        $colorMatches = $promo['color_id'] === null || $promo['color_id'] === intval($colorId);
        $sizeMatches  = $promo['sizename'] === null || $promo['sizename'] === $sizeName;
        if ($colorMatches && $sizeMatches && $promo['discount_percent'] > $best) {
            $best = $promo['discount_percent'];
        }
    }
    return $best;
}

// ── Group total qty per product across the FULL cart (not just the 5 shown) ────
// Same logic as cartview.php / checkout-data.php
$productQtyMap = []; // product_id => total qty in cart
foreach ($allRows as $r) {
    $pid = $r['product_id'];
    $productQtyMap[$pid] = ($productQtyMap[$pid] ?? 0) + intval($r['quantity']);
}

$tiersPerProduct = []; // product_id => tiers array
foreach (array_unique(array_column($allRows, 'product_id')) as $pid) {
    $tiersPerProduct[$pid] = getProductQtyTiers($conn, (int) $pid);
}

// ── Subtotal across the FULL cart, with tier discount applied ──────────────────
$subtotal = 0;
foreach ($allRows as $r) {
    $price            = floatval($r['pricesize']);
    $variantDiscount  = floatval($r['discountvariant']);
    $promoDiscount    = resolvePromoDiscount($promosByProduct, $r['product_id'], $r['color_id'], $r['sizename']);
    $discount         = max($variantDiscount, $promoDiscount);
    $unitPrice        = $discount > 0 ? $price * (1 - $discount / 100) : $price;
    $qty       = intval($r['quantity']);
    $pid       = $r['product_id'];

    $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
    $tiers              = $tiersPerProduct[$pid] ?? [];
    $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);
    $final              = $unitPrice * (1 - $tierDiscount / 100);

    $subtotal += $final * $qty;
}

// Count = bilang ng distinct na cart rows (per product/variant), HINDI sum ng quantity
$count = count($allRows);

$items = array_map(function ($row) use ($productQtyMap, $tiersPerProduct, $promosByProduct) {
    $price            = floatval($row['pricesize']);
    $variantDiscount  = floatval($row['discountvariant']);
    $promoDiscount    = resolvePromoDiscount($promosByProduct, $row['product_id'], $row['color_id'], $row['sizename']);
    $discount         = max($variantDiscount, $promoDiscount);
    $unitPrice        = $discount > 0 ? $price * (1 - $discount / 100) : $price;
    $qty       = intval($row['quantity']);
    $pid       = $row['product_id'];

    $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
    $tiers              = $tiersPerProduct[$pid] ?? [];
    $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);
    $final              = $unitPrice * (1 - $tierDiscount / 100);

    $image   = !empty($row['imagecolor']) ? $row['imagecolor'] : $row['imageproduct'];
    $variant = trim(($row['colorname'] ?? '') . (!empty($row['sizename']) ? ' · ' . $row['sizename'] : ''));

    return [
        'cart_id'       => $row['cart_id'],
        'product_id'    => $pid,
        'name'          => $row['product_name'],
        'image'         => $image,
        'variant'       => $variant,
        'quantity'      => $qty,
        'price'         => $final,            // final unit price: variant discount + qty tier discount
        'orig_price'    => $price,             // original price before any discount
        'variant_disc'  => $discount,          // variant discount %
        'tier_discount' => $tierDiscount,       // qty tier discount %
        'stock'         => intval($row['variant_stock']),
    ];
}, $rows);

echo json_encode([
    'ok'       => true,
    'items'    => $items,
    'count'    => $count,
    'subtotal' => $subtotal,
]);