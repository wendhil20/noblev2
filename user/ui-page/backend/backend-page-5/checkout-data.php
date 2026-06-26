<?php
// handler/checkout-data.php
// All backend logic for checkout — include this at the top of checkout.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-2/productqtydiscount-helper.php';


$userId   = intval($_SESSION['user_id']);
$uploadUrl = BASE_URL . '/uploads/';

// ── 1. Cart items ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        nc.id           AS cart_id,
        nc.quantity,

        p.id            AS product_id,
        p.name          AS product_name,
        p.category      AS product_category,
        p.unit          AS product_unit,
        p.imageproduct,

        c.id            AS color_id,
        c.colorname,
        c.imagecolor,

        v.id            AS variant_id,
        v.sizename,
        v.pricesize,
        v.discountvariant,
        v.stock         AS variant_stock,
        v.width,
        v.height,
        v.leght         AS length,
        v.dimension_unit,
        v.weight,
        v.weight_unit

    FROM noblecart nc
    JOIN nobleproduct        p ON p.id  = nc.product_id
    JOIN nobleproductcolor   c ON c.id  = nc.color_id
    JOIN nobleproductvariant v ON v.id  = nc.variant_id

    WHERE nc.user_id = ?
    ORDER BY nc.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Redirect to cart if empty
if (empty($cartItems)) {
    header('Location: ' . BASE_URL . '/cart');
    exit;
}

// ── 2a. Group total qty per product (for tier discount resolution) ─────────────
// Same logic as cartview.php — quantity tier discounts are resolved against the
// TOTAL quantity of a product across all cart rows (e.g. different colors/sizes),
// not just the quantity of a single row.
$productQtyMap = []; // product_id => total qty in cart
foreach ($cartItems as $item) {
    $pid = $item['product_id'];
    $productQtyMap[$pid] = ($productQtyMap[$pid] ?? 0) + intval($item['quantity']);
}

$tiersPerProduct = []; // product_id => tiers array
foreach (array_unique(array_column($cartItems, 'product_id')) as $pid) {
    $tiersPerProduct[$pid] = getProductQtyTiers($conn, (int) $pid);
}

// ── 2b. Subtotal + cart volume/weight totals + stock validation ────────────────
$subtotal        = 0;
$totalVolumeCbm  = 0;  // cubic meters
$totalWeightKg   = 0;  // kg
$hasStockIssue   = false; // true if any item is out of stock or qty exceeds stock

foreach ($cartItems as $item) {
    $price      = floatval($item['pricesize']);
    $discount   = floatval($item['discountvariant']);
    $unitPrice  = $discount > 0 ? $price * (1 - $discount / 100) : $price;
    $qty        = intval($item['quantity']);
    $pid        = $item['product_id'];

    // Apply quantity tier discount on top of variant discount (matches cartview.php)
    $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
    $tiers              = $tiersPerProduct[$pid] ?? [];
    $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);
    $finalPrice         = $unitPrice * (1 - $tierDiscount / 100);

    $subtotal  += $finalPrice * $qty;

    // ── Stock validation ────────────────────────────────────────────────────
    $stock = intval($item['variant_stock']);
    if ($stock <= 0 || $qty > $stock) {
        $hasStockIssue = true;
    }

    // Convert dimensions to meters then compute volume per unit
    $unit = strtolower(trim($item['dimension_unit'] ?? 'mm'));
    $w    = floatval($item['width']);
    $h    = floatval($item['height']);
    $l    = floatval($item['length']);

    $factor = match ($unit) {
        'cm'     => 0.01,
        'm'      => 1.0,
        'inches' => 0.0254,
        default  => 0.001, // mm
    };

    $volCbm       = ($w * $factor) * ($h * $factor) * ($l * $factor);
    $totalVolumeCbm += $volCbm * $qty;
    // Convert weight to kg
    $wUnit = strtolower(trim($item['weight_unit'] ?? 'kg'));
    $wVal  = floatval($item['weight'] ?? 0);
    $wKg   = match ($wUnit) {
        'g'   => $wVal / 1000,
        'lbs' => $wVal * 0.453592,
        default => $wVal, // kg
    };
    $totalWeightKg += $wKg * $qty;
}

// ── VAT 12% (VAT-exclusive — VAT is added on top of prices) ─────────────────
// Formula: VAT = subtotal * 0.12   |   Grand total = subtotal + VAT
$vatRate    = 0.12;
$vatAmount  = round($subtotal * $vatRate, 2);
$grandTotal = round($subtotal + $vatAmount, 2);

// ── 3. User account info ───────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT name, email FROM nobleuseraccount WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userAccount = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── 4. Saved addresses ────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, contact_number, age, address, barangay, city, postalcode, latitude, longitude, updated_at
    FROM nobleuserinformation
    WHERE user_id = ?
    ORDER BY updated_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$savedAddresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 5. Warehouse / store base location ────────────────────────────────────────
$stmt = $conn->prepare("SELECT placename, latitude, longitude FROM noblewarehousebase LIMIT 1");
$stmt->execute();
$warehouse = $stmt->get_result()->fetch_assoc();
$stmt->close();

$storeLat  = floatval($warehouse['latitude']  ?? 14.6571900);
$storeLng  = floatval($warehouse['longitude'] ?? 121.0034486);
$storeName = $warehouse['placename'] ?? 'Store';

// ── 6. Available trucks ────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, nametruck, trucktype, truckvariant,
           basefare, addperkm, perkmrate,
           length, width, height,
           maxcubicmeter, maxweightcapacity
    FROM nobletrucklist
    ORDER BY maxcubicmeter ASC
");
$stmt->execute();
$allTrucks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Auto-assign the smallest truck that fits the cart
$assignedTruck = null;
foreach ($allTrucks as $truck) {
    if (
        floatval($truck['maxcubicmeter'])    >= $totalVolumeCbm &&
        floatval($truck['maxweightcapacity']) >= $totalWeightKg
    ) {
        $assignedTruck = $truck;
        break;
    }
}
// Fallback: largest available truck
if (!$assignedTruck && !empty($allTrucks)) {
    $assignedTruck = end($allTrucks);
}