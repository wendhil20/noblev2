<?php
// productqtydiscount-helper.php

function getProductQtyLimit(mysqli $conn, int $productId): int
{
    $stmt = $conn->prepare("SELECT max_qty_per_order FROM nobleproductlimit WHERE product_id = ? LIMIT 1");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['max_qty_per_order'] : 0;
}

/**
 * Kunin lahat ng discount tiers ng isang product, naka-sort by min_qty ascending.
 */
function getProductQtyTiers(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare("
        SELECT min_qty, max_qty, discount_percent
        FROM nobleproductqtytier
        WHERE product_id = ?
        ORDER BY min_qty ASC
    ");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $res = $stmt->get_result();

    $tiers = [];
    while ($row = $res->fetch_assoc()) {
        $tiers[] = $row;
    }
    $stmt->close();
    return $tiers;
}

/**
 * Hanapin ang discount_percent na applicable sa given qty, base sa list ng tiers.
 * Returns 0.0 kung walang tier na match (no discount).
 */
function resolveQtyDiscountPercent(array $tiers, int $qty): float
{
    foreach ($tiers as $t) {
        if ($qty >= (int) $t['min_qty'] && $qty <= (int) $t['max_qty']) {
            return (float) $t['discount_percent'];
        }
    }
    return 0.0;
}

/**
 * I-compute ang final total para sa given unit price + quantity,
 * isasama ang anumang applicable na qty-tier discount (percent off sa subtotal).
 *
 * Halimbawa: unitPrice=50.00, qty=5, tiers may match na discount_percent=1.2
 *   subtotal = 250.00
 *   discount_amount = 250.00 * (1.2 / 100) = 3.00
 *   total = 247.00
 *
 * Returns: ['subtotal', 'discount_percent', 'discount_amount', 'total']
 */
function computeQtyDiscountedTotal(float $unitPrice, int $qty, array $tiers): array
{
    $subtotal = $unitPrice * $qty;
    $discountPercent = resolveQtyDiscountPercent($tiers, $qty);
    $discountAmount = round($subtotal * ($discountPercent / 100), 2);
    $total = round($subtotal - $discountAmount, 2);

    return [
        'subtotal' => round($subtotal, 2),
        'discount_percent' => $discountPercent,
        'discount_amount' => $discountAmount,
        'total' => $total,
    ];
}

/**
 * I-validate kung pasok ang requested qty sa per-order limit ng product.
 * Gamitin ito bago i-insert sa cart o sa checkout, para hindi malusutan
 * kahit i-bypass ng user ang frontend JS.
 */
function isWithinProductQtyLimit(mysqli $conn, int $productId, int $requestedQty): bool
{
    $max = getProductQtyLimit($conn, $productId);
    if ($max <= 0) {
        return true; // walang limit
    }
    return $requestedQty <= $max;
}

