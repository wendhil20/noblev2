<?php
//paymongo.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret    = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');


// ── Verify signature ──────────────────────────────────────────────────────────
$parts = [];
foreach (explode(',', $sigHeader) as $part) {
    $kv = explode('=', $part, 2);
    if (count($kv) === 2) {
        $parts[$kv[0]] = $kv[1];
    }
}

$timestamp = $parts['t'] ?? '';
$toSign    = $timestamp . '.' . $payload;
$expected  = hash_hmac('sha256', $toSign, $secret);
$received  = $parts['li'] ?? $parts['te'] ?? '';

if (!hash_equals($expected, $received)) {
    error_log('PayMongo webhook: invalid signature. Header was: ' . $sigHeader);
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
$type  = $event['data']['attributes']['type'] ?? '';

// Only handle successful payment
if ($type !== 'checkout_session.payment.paid') {
    http_response_code(200);
    exit('Ignored');
}

// ── Metadata extraction ───────────────────────────────────────────────────────
$metadata = $event['data']['attributes']['data']['attributes']['metadata']
    ?? $event['data']['attributes']['metadata']
    ?? [];

$userId         = intval($metadata['user_id'] ?? 0);
$pendingOrderId = intval($metadata['pending_order_id'] ?? 0);

if (!$userId || !$pendingOrderId) {
    error_log('PayMongo webhook: missing metadata. Payload: ' . $payload);
    http_response_code(200);
    exit('Missing user_id or pending_order_id in metadata');
}

// ── Fetch pending order ───────────────────────────────────────────────────────
$s = $conn->prepare("SELECT * FROM noblependingorder WHERE id = ? AND user_id = ? AND used = 0 LIMIT 1");
if (!$s) {
    error_log('PayMongo webhook: prepare failed (noblependingorder select): ' . $conn->error);
    http_response_code(500);
    exit('DB prepare error');
}
$s->bind_param("ii", $pendingOrderId, $userId);
$s->execute();
$pending = $s->get_result()->fetch_assoc();
$s->close();

if (!$pending) {
    error_log("PayMongo webhook: no pending order found for id $pendingOrderId, user $userId");
    http_response_code(200);
    exit('No pending order found for id ' . $pendingOrderId);
}

$cartItems = json_decode($pending['cart_items_json'], true);

if (!is_array($cartItems) || count($cartItems) === 0) {
    error_log("PayMongo webhook: empty or invalid cart_items_json for pending order $pendingOrderId");
    http_response_code(200);
    exit('Invalid cart items');
}

// ── Generate NHCC reference ───────────────────────────────────────────────────
$year        = date('Y');
$likePattern = "NHCC-{$year}-%";
$refCheck    = $conn->prepare("SELECT nhccreference FROM noblepaidproductlist WHERE nhccreference LIKE ? ORDER BY id DESC LIMIT 1");
if (!$refCheck) {
    error_log('PayMongo webhook: prepare failed (nhcc ref check): ' . $conn->error);
    http_response_code(500);
    exit('DB prepare error');
}
$refCheck->bind_param("s", $likePattern);
$refCheck->execute();
$lastRef = $refCheck->get_result()->fetch_assoc();
$refCheck->close();

$lastNum       = $lastRef ? intval(substr($lastRef['nhccreference'], -3)) : 0;
$nhccReference = 'NHCC-' . $year . '-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);

// ── Transaction ───────────────────────────────────────────────────────────────
$conn->begin_transaction();
try {
    // ── 1. Reserve stock ──────────────────────────────────────────────────────
    $stockStmt = $conn->prepare("UPDATE nobleproductvariant SET stock = stock - ? WHERE id = ? AND stock >= ?");
    if (!$stockStmt) {
        throw new \RuntimeException('Prepare failed (stock update): ' . $conn->error);
    }
    foreach ($cartItems as $item) {
        $vId = intval($item['variant_id']);
        $qty = intval($item['quantity']);
        $stockStmt->bind_param("iii", $qty, $vId, $qty);
        $stockStmt->execute();
        if ($stockStmt->affected_rows === 0) {
            $conn->rollback();
            error_log("PayMongo webhook: out of stock for variant $vId, pending order $pendingOrderId");
            http_response_code(200);
            exit('Out of stock for variant ' . $vId);
        }
    }
    $stockStmt->close();

    // ── 2. Insert order ───────────────────────────────────────────────────────
    $ins = $conn->prepare("
        INSERT INTO noblepaidproductlist
          (nhccreference, user_id, contact_name, contact_email, contact_phone,
           address_id, address_full, address_barangay, address_city, address_postalcode,
           address_lat, address_lng,
           delivery_method, truck_id, truck_name, truck_max_cubic_meter, truck_max_weight_capacity,
           delivery_distance_km, delivery_fee,
           subtotal, vat_amount, grand_total,
           payment_status, payment_method)
        VALUES
          (?,?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,  'paid', ?)
    ");
    if (!$ins) {
        throw new \RuntimeException('Prepare failed (noblepaidproductlist insert): ' . $conn->error);
    }

    $truckMaxVol   = floatval($pending['truck_max_vol'] ?? 0);
    $truckMaxWt    = floatval($pending['truck_max_wt']  ?? 0);
    $paymentMethod = $pending['payment_type'] ?? 'paymongo';

    // Explicit variables for bind_param (no expression references)
    $bNhcc        = $nhccReference;
    $bUserId      = $userId;
    $bCname       = $pending['contact_name']    ?? '';
    $bCemail      = $pending['contact_email']   ?? '';
    $bCphone      = $pending['contact_phone']   ?? '';
    $bAddrId      = intval($pending['address_id'] ?? 0);
    $bAddrFull    = $pending['address_full']    ?? '';
    $bAddrBgy     = $pending['address_barangay'] ?? '';
    $bAddrCity    = $pending['address_city']    ?? '';
    $bAddrPostal  = $pending['address_postal']  ?? '';
    $bAddrLat     = floatval($pending['address_lat'] ?? 0);
    $bAddrLng     = floatval($pending['address_lng'] ?? 0);
    $bMethod      = $pending['method']          ?? 'pickup';
    $bTruckId     = intval($pending['truck_id'] ?? 0);
    $bTruckName   = $pending['truck_name']      ?? '';
    $bTruckVol    = $truckMaxVol;
    $bTruckWt     = $truckMaxWt;
    $bDistKm      = floatval($pending['distance_km']   ?? 0);
    $bDelivFee    = floatval($pending['delivery_fee']  ?? 0);
    $bSubtotal    = floatval($pending['subtotal']      ?? 0);
    $bVat         = floatval($pending['vat_amount']    ?? 0);
    $bGrand       = floatval($pending['grand_total']   ?? 0);
    $bPayMethod   = $paymentMethod;

    $ins->bind_param(
        "sissssissssddsisdddddds",
        $bNhcc,
        $bUserId,
        $bCname,
        $bCemail,
        $bCphone,
        $bAddrId,
        $bAddrFull,
        $bAddrBgy,
        $bAddrCity,
        $bAddrPostal,
        $bAddrLat,
        $bAddrLng,
        $bMethod,
        $bTruckId,
        $bTruckName,
        $bTruckVol,
        $bTruckWt,
        $bDistKm,
        $bDelivFee,
        $bSubtotal,
        $bVat,
        $bGrand,
        $bPayMethod
    );

    if (!$ins->execute()) {
        throw new \RuntimeException('Execute failed (noblepaidproductlist insert): ' . $ins->error);
    }
    $orderId = $conn->insert_id;
    $ins->close();

    // ── 3. Insert line items ──────────────────────────────────────────────────
    $insItem = $conn->prepare("
        INSERT INTO noblepaidproductitems
          (order_id, product_id, product_name, color_id, colorname, variant_id, sizename, unit,
           quantity, unit_price, discount_pct, line_total)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$insItem) {
        throw new \RuntimeException('Prepare failed (noblepaidproductitems insert): ' . $conn->error);
    }
    foreach ($cartItems as $idx => $item) {
        $pid       = intval($item['product_id']       ?? 0);
        $pname     = $item['product_name']             ?? '';
        $cid       = intval($item['color_id']         ?? 0);
        $cname     = $item['colorname']                ?? '';
        $vid       = intval($item['variant_id']       ?? 0);
        $sname     = $item['sizename']                 ?? '';
        $punit     = $item['product_unit']             ?? '';
        $qty       = intval($item['quantity']         ?? 1);
        $price     = floatval($item['pricesize']      ?? 0);
        $disc      = floatval($item['discountvariant'] ?? 0);
        $unitPrice = $disc > 0 ? round($price * (1 - $disc / 100), 2) : $price;
        $lineTotal = round($unitPrice * $qty, 2);

        $insItem->bind_param(
            "iisisissiddd",
            $orderId,
            $pid,
            $pname,
            $cid,
            $cname,
            $vid,
            $sname,
            $punit,
            $qty,
            $unitPrice,
            $disc,
            $lineTotal
        );
        if (!$insItem->execute()) {
            throw new \RuntimeException("Execute failed (noblepaidproductitems, item index $idx): " . $insItem->error);
        }
    }
    $insItem->close();

    // ── 4. Clear cart ─────────────────────────────────────────────────────────
    $conn->query("DELETE FROM noblecart WHERE user_id = " . intval($userId));

    // ── 5. Mark pending order as used ─────────────────────────────────────────
    $conn->query("
        UPDATE noblependingorder
        SET used = 1,
            order_id = " . intval($orderId) . ",
            payment_status = 'paid',
            final_order_id = " . intval($orderId) . "
        WHERE id = " . intval($pending['id'])
    );

    // ── 6. Notify Accounting ──────────────────────────────────────────────────
    $notifTitle  = 'New Order ' . $nhccReference;
    $notifMsg    = 'Order from ' . ($pending['contact_name'] ?? 'Customer') . ' — ₱' . number_format(floatval($pending['grand_total'] ?? 0), 2);
    $notifLink   = BASE_URL . '/accounting';
    $forRole     = ROLE_ACCOUNTING;
    $forPosition = POSITION_HEAD;
    $forUserId   = null;

    $ns = $conn->prepare("INSERT INTO noblenotification (for_role, for_position, for_user_id, title, message, link) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$ns) {
        throw new \RuntimeException('Prepare failed (noblenotification insert): ' . $conn->error);
    }
    $ns->bind_param("ssisss", $forRole, $forPosition, $forUserId, $notifTitle, $notifMsg, $notifLink);
    if (!$ns->execute()) {
        throw new \RuntimeException('Execute failed (noblenotification insert): ' . $ns->error);
    }
    $ns->close();

    $conn->commit();
    http_response_code(200);
    exit('OK');

} catch (\Throwable $e) {
    $conn->rollback();
    error_log('PayMongo webhook error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
}