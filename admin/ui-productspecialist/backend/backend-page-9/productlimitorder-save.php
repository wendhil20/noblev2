<?php
// productlimitorder-save.php
// POST JSON body: { product_id, max_qty_per_order, tiers: [{min_qty, max_qty, discount_percent}, ...] }

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$productId = intval($input['product_id'] ?? 0);
$maxQty = intval($input['max_qty_per_order'] ?? 0);
$tiersInput = is_array($input['tiers'] ?? null) ? $input['tiers'] : [];

if (!$productId) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid product.']);
    exit;
}
if ($maxQty < 0) {
    echo json_encode(['ok' => false, 'msg' => 'Max quantity per order cannot be negative.']);
    exit;
}

// Tiyakin existing ang product
$checkStmt = $conn->prepare("SELECT id FROM nobleproduct WHERE id = ? LIMIT 1");
$checkStmt->bind_param("i", $productId);
$checkStmt->execute();
$exists = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$exists) {
    echo json_encode(['ok' => false, 'msg' => 'Product not found.']);
    exit;
}

// ── Server-side validation ng tiers (huwag lang umasa sa client-side checks) ──
$cleanTiers = [];
foreach ($tiersInput as $t) {
    $min = intval($t['min_qty'] ?? 0);
    $max = intval($t['max_qty'] ?? 0);
    $disc = floatval($t['discount_percent'] ?? 0);

    if ($min <= 0 || $max <= 0 || $min > $max) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid tier range detected.']);
        exit;
    }
    if ($disc < 0 || $disc > 100) {
        echo json_encode(['ok' => false, 'msg' => 'Discount percent must be between 0 and 100.']);
        exit;
    }
    if ($maxQty > 0 && $max > $maxQty) {
        echo json_encode(['ok' => false, 'msg' => 'Tier max qty exceeds the order limit.']);
        exit;
    }

    $cleanTiers[] = ['min_qty' => $min, 'max_qty' => $max, 'discount_percent' => $disc];
}

// Tignan kung mag-overlap ang mga ranges
usort($cleanTiers, fn($a, $b) => $a['min_qty'] <=> $b['min_qty']);
for ($i = 1; $i < count($cleanTiers); $i++) {
    if ($cleanTiers[$i]['min_qty'] <= $cleanTiers[$i - 1]['max_qty']) {
        echo json_encode(['ok' => false, 'msg' => 'Tier ranges cannot overlap.']);
        exit;
    }
}

$conn->begin_transaction();
try {
    // Upsert sa limit table — isa lang dapat na row per product
    $upsert = $conn->prepare("
        INSERT INTO nobleproductlimit (product_id, max_qty_per_order)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE max_qty_per_order = VALUES(max_qty_per_order)
    ");
    $upsert->bind_param("ii", $productId, $maxQty);
    $upsert->execute();
    $upsert->close();

    // Palitan lahat ng tiers ng product na ito (delete then re-insert — simple at consistent)
    $del = $conn->prepare("DELETE FROM nobleproductqtytier WHERE product_id = ?");
    $del->bind_param("i", $productId);
    $del->execute();
    $del->close();

    if (!empty($cleanTiers)) {
        $insert = $conn->prepare("
            INSERT INTO nobleproductqtytier (product_id, min_qty, max_qty, discount_percent)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($cleanTiers as $t) {
            $insert->bind_param("iiid", $productId, $t['min_qty'], $t['max_qty'], $t['discount_percent']);
            $insert->execute();
        }
        $insert->close();
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'msg' => 'Saved successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'msg' => 'Failed to save. Please try again.']);
}