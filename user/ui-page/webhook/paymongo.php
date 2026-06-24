<?php
// handler/paymongo-webhook.php
// Register this URL in PayMongo Dashboard → Webhooks
// URL: https://yourdomain.com/paymongo-webhook
// Events: payment.paid, checkout_session.payment.paid

include ROOT_PATH . '/network/connect.php';

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret    = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');

// ── Verify signature ──────────────────────────────────────────────────────────
// PayMongo sends: t=timestamp,te=test_sig,li=live_sig
$parts = [];
foreach (explode(',', $sigHeader) as $part) {
    [$k, $v] = explode('=', $part, 2);
    $parts[$k] = $v;
}

$timestamp = $parts['t'] ?? '';
$toSign    = $timestamp . '.' . $payload;
$expected  = hash_hmac('sha256', $toSign, $secret);
$received  = $parts['li'] ?? $parts['te'] ?? '';

if (!hash_equals($expected, $received)) {
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

$metadata = $event['data']['attributes']['data']['attributes']['metadata'] ?? [];
$userId   = intval($metadata['user_id'] ?? 0);

if (!$userId) {
    http_response_code(200);
    exit('No user_id in metadata');
}

// ── Fetch pending order from DB temp table ────────────────────────────────────
// (see step 3 below — we use a temp table instead of session for webhooks)
$s = $conn->prepare("SELECT * FROM noblependingorder WHERE user_id = ? AND used = 0 ORDER BY created_at DESC LIMIT 1");
$s->bind_param("i", $userId);
$s->execute();
$pending = $s->get_result()->fetch_assoc();
$s->close();

if (!$pending) {
    http_response_code(200);
    exit('No pending order found');
}

$cartItems = json_decode($pending['cart_items_json'], true);

// Generate NHCC reference
$year = date('Y');
$refCheck = $conn->prepare("SELECT nhccreference FROM noblepaidproductlist WHERE nhccreference LIKE ? ORDER BY id DESC LIMIT 1");
$likePattern = "NHCC-{$year}-%";
$refCheck->bind_param("s", $likePattern);
$refCheck->execute();
$lastRef = $refCheck->get_result()->fetch_assoc();
$refCheck->close();

$lastNum      = $lastRef ? intval(substr($lastRef['nhccreference'], -3)) : 0;
$nhccReference = 'NHCC-' . $year . '-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);

$conn->begin_transaction();
try {
    // Reserve stock
    $stockStmt = $conn->prepare("UPDATE nobleproductvariant SET stock = stock - ? WHERE id = ? AND stock >= ?");
    foreach ($cartItems as $item) {
        $vId = intval($item['variant_id']);
        $qty = intval($item['quantity']);
        $stockStmt->bind_param("iii", $qty, $vId, $qty);
        $stockStmt->execute();
        if ($stockStmt->affected_rows === 0) {
            $conn->rollback();
            http_response_code(200);
            exit('Out of stock for variant ' . $vId);
        }
    }
    $stockStmt->close();

    // Insert order
    $ins = $conn->prepare("
        INSERT INTO noblepaidproductlist
          (nhccreference, user_id, contact_name, contact_email, contact_phone,
           address_id, address_full, address_barangay, address_city, address_postalcode, address_lat, address_lng,
           delivery_method, truck_id, truck_name, truck_max_cubic_meter, truck_max_weight_capacity,
           delivery_distance_km, delivery_fee,
           subtotal, vat_amount, grand_total, payment_status)
        VALUES
          (?,?,?,?,?,  ?,?,?,?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,  'paid')
    ");

    $truckMaxVol = floatval($pending['truck_max_vol'] ?? 0);
    $truckMaxWt  = floatval($pending['truck_max_wt']  ?? 0);

    $ins->bind_param(
        "sisssissssddsisddddddd",
        $nhccReference,
        $userId,
        $pending['contact_name'],
        $pending['contact_email'],
        $pending['contact_phone'],
        $pending['address_id'],
        $pending['address_full'],
        $pending['address_barangay'],
        $pending['address_city'],
        $pending['address_postal'],
        $pending['address_lat'],
        $pending['address_lng'],
        $pending['method'],
        $pending['truck_id'],
        $pending['truck_name'],
        $truckMaxVol,
        $truckMaxWt,
        $pending['distance_km'],
        $pending['delivery_fee'],
        $pending['subtotal'],
        $pending['vat_amount'],
        $pending['grand_total']
    );
    $ins->execute();
    $orderId = $conn->insert_id;
    $ins->close();

    // Insert line items
    $insItem = $conn->prepare("
        INSERT INTO noblepaidproductitems
          (order_id, product_id, product_name, color_id, colorname, variant_id, sizename, unit,
           quantity, unit_price, discount_pct, line_total)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    foreach ($cartItems as $item) {
        $pid       = intval($item['product_id']);
        $pname     = $item['product_name'];
        $cid       = intval($item['color_id']);
        $cname     = $item['colorname'];
        $vid       = intval($item['variant_id']);
        $sname     = $item['sizename'];
        $punit     = $item['product_unit'] ?? '';
        $qty       = intval($item['quantity']);
        $price     = floatval($item['pricesize']);
        $disc      = floatval($item['discountvariant']);
        $unitPrice = $disc > 0 ? round($price * (1 - $disc / 100), 2) : $price;
        $lineTotal = round($unitPrice * $qty, 2);

        $insItem->bind_param("iisisissiddd", $orderId, $pid, $pname, $cid, $cname, $vid, $sname, $punit, $qty, $unitPrice, $disc, $lineTotal);
        $insItem->execute();
    }
    $insItem->close();

    // Clear cart
    $conn->query("DELETE FROM noblecart WHERE user_id = " . intval($userId));

    // Mark pending order as used
    $conn->query("UPDATE noblependingorder SET used = 1, order_id = " . intval($orderId) . " WHERE id = " . intval($pending['id']));

    // Notify Accounting
    $notifTitle  = 'New Order ' . $nhccReference;
    $notifMsg    = 'Order from ' . $pending['contact_name'] . ' — ₱' . number_format(floatval($pending['grand_total']), 2);
    $notifLink   = BASE_URL . '/accounting';
    $forRole     = ROLE_ACCOUNTING;
    $forPosition = POSITION_HEAD;
    $forUserId   = null;
    $ns = $conn->prepare("INSERT INTO noblenotification (for_role, for_position, for_user_id, title, message, link) VALUES (?, ?, ?, ?, ?, ?)");
    $ns->bind_param("ssisss", $forRole, $forPosition, $forUserId, $notifTitle, $notifMsg, $notifLink);
    $ns->execute();
    $ns->close();

    $conn->commit();
    http_response_code(200);
    exit('OK');

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
}