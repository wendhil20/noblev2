<?php
// handler/create-qrph.php
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = intval($_SESSION['user_id']);

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-5/checkout-data.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$contactName  = trim($body['contact_name']  ?? '');
$contactEmail = trim($body['contact_email'] ?? '');
$contactPhone = trim($body['contact_phone'] ?? '');
$addressId    = intval($body['address_id']  ?? 0);
$method       = in_array($body['method'] ?? '', ['pickup', 'delivery']) ? $body['method'] : null;
$truckId      = intval($body['truck_id']    ?? 0);
$deliveryFee  = floatval($body['delivery_fee'] ?? 0);
$distanceKm   = floatval($body['distance_km']  ?? 0);

if (!$contactName || !$contactEmail || !$contactPhone || !$method) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// ── 1. Quick stock check ──────────────────────────────────────────────────────
$stockCheck = $conn->prepare("SELECT id, stock FROM nobleproductvariant WHERE id = ?");
foreach ($cartItems as $item) {
    $vId = intval($item['variant_id']);
    $qty = intval($item['quantity']);

    $stockCheck->bind_param("i", $vId);
    $stockCheck->execute();
    $row = $stockCheck->get_result()->fetch_assoc();

    if (!$row || intval($row['stock']) < $qty) {
        $itemLabel = $item['product_name']
            . ($item['colorname'] ? ' — ' . $item['colorname'] : '')
            . ($item['sizename']  ? ' / '  . $item['sizename']  : '');

        echo json_encode([
            'ok'           => false,
            'error'        => 'Sorry, "' . $itemLabel . '" no longer has enough stock.',
            'out_of_stock' => true,
            'variant_id'   => $vId,
        ]);
        exit;
    }
}
$stockCheck->close();

// ── 2. Compute final total ────────────────────────────────────────────────────
$finalGrand     = round($grandTotal + $deliveryFee, 2);
$amountCentavos = intval(round($finalGrand * 100));

// PayMongo minimum amount is ₱20.00 (2000 centavos)
if ($amountCentavos < 100) {
    echo json_encode(['ok' => false, 'error' => 'Order total is below the minimum payable amount (₱20.00).']);
    exit;
}

// ── 3. Truck snapshot ─────────────────────────────────────────────────────────
$truckName = null; $truckMaxVol = 0.0; $truckMaxWt = 0.0;
if ($truckId > 0) {
    $s = $conn->prepare("SELECT nametruck, trucktype, maxcubicmeter, maxweightcapacity FROM nobletrucklist WHERE id = ?");
    $s->bind_param("i", $truckId);
    $s->execute();
    $t = $s->get_result()->fetch_assoc();
    $s->close();
    if ($t) {
        $truckName   = ucfirst($t['nametruck']) . ' — ' . $t['trucktype'];
        $truckMaxVol = floatval($t['maxcubicmeter']);
        $truckMaxWt  = floatval($t['maxweightcapacity']);
    }
}

// ── 4. Address snapshot ───────────────────────────────────────────────────────
$addrFull = $addrBarangay = $addrCity = $addrPostal = null;
$addrLat  = 0.0; $addrLng = 0.0;
if ($addressId > 0) {
    $s = $conn->prepare("
        SELECT address, barangay, city, postalcode, latitude, longitude
        FROM nobleuserinformation
        WHERE id = ? AND user_id = ?
    ");
    $s->bind_param("ii", $addressId, $userId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if ($row) {
        $addrFull     = $row['address'];
        $addrBarangay = $row['barangay'];
        $addrCity     = $row['city'];
        $addrPostal   = $row['postalcode'];
        $addrLat      = floatval($row['latitude']);
        $addrLng      = floatval($row['longitude']);
    }
}

// ── 5. Save pending order ─────────────────────────────────────────────────────
$cartJson = json_encode($cartItems);
$paymentType = 'qrph'; // ← FIX: explicitly mark this pending order as a QR Ph payment

$ins = $conn->prepare("
    INSERT INTO noblependingorder
      (user_id,
       contact_name, contact_email, contact_phone,
       address_id, address_full, address_barangay, address_city, address_postal,
       address_lat, address_lng,
       method,
       truck_id, truck_name, truck_max_vol, truck_max_wt,
       distance_km, delivery_fee,
       subtotal, vat_amount, grand_total,
       cart_items_json, payment_type)
    VALUES (?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,  ?,?,?,?,  ?,?,  ?,?,?,  ?,?)
");

$typeStr = 'i'   // user_id
    . 's'        // contact_name
    . 's'        // contact_email
    . 's'        // contact_phone
    . 'i'        // address_id
    . 's'        // address_full
    . 's'        // address_barangay
    . 's'        // address_city
    . 's'        // address_postal
    . 'd'        // address_lat
    . 'd'        // address_lng
    . 's'        // method
    . 'i'        // truck_id
    . 's'        // truck_name
    . 'd'        // truck_max_vol
    . 'd'        // truck_max_wt
    . 'd'        // distance_km
    . 'd'        // delivery_fee
    . 'd'        // subtotal
    . 'd'        // vat_amount
    . 'd'        // grand_total
    . 's'        // cart_items_json
    . 's';       // payment_type ← FIX: added type for new column

$ins->bind_param(
    $typeStr,
    $userId,
    $contactName,
    $contactEmail,
    $contactPhone,
    $addressId,
    $addrFull,
    $addrBarangay,
    $addrCity,
    $addrPostal,
    $addrLat,
    $addrLng,
    $method,
    $truckId,
    $truckName,
    $truckMaxVol,
    $truckMaxWt,
    $distanceKm,
    $deliveryFee,
    $subtotal,
    $vatAmount,
    $finalGrand,
    $cartJson,
    $paymentType // ← FIX: bind the new value
);

if (!$ins->execute()) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save pending order: ' . $ins->error]);
    exit;
}
$pendingOrderId = $conn->insert_id;
$ins->close();

// ── 6. Create PayMongo Checkout Session — QR Ph only ──────────────────────────
$secretKey  = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
$successUrl = BASE_URL . '/success';
$cancelUrl  = BASE_URL . '/checkout';

$payload = [
    'data' => [
        'attributes' => [
            'amount'   => $amountCentavos,
            'currency' => 'PHP',
            'line_items' => [
                [
                    'name'        => 'NobleHome Order',
                    'quantity'    => 1,
                    'amount'      => $amountCentavos,
                    'currency'    => 'PHP',
                    'description' => 'Subtotal: ₱' . number_format($subtotal, 2)
                        . ' + VAT: ₱' . number_format($vatAmount, 2)
                        . ($deliveryFee > 0 ? ' + Delivery: ₱' . number_format($deliveryFee, 2) : ''),
                ]
            ],
            // Limited to QR Ph only — PayMongo's hosted page generates/shows the QR itself
            'payment_method_types' => ['qrph'],
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'description' => 'NobleHome Order',
            'metadata' => [
                'user_id'          => strval($userId),
                'pending_order_id' => strval($pendingOrderId),
            ],
        ],
    ],
];

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($secretKey . ':'),
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$pmData = json_decode($response, true);

// ── 7. If PayMongo fails, roll back the pending order ─────────────────────────
if ($httpStatus !== 200 || empty($pmData['data']['id'])) {
    $conn->query("DELETE FROM noblependingorder WHERE id = " . intval($pendingOrderId));

    echo json_encode([
        'ok'    => false,
        'error' => 'PayMongo error: ' . ($pmData['errors'][0]['detail'] ?? 'Unknown error'),
    ]);
    exit;
}

// ── 8. Save PayMongo session ID ───────────────────────────────────────────────
$sessionId  = $pmData['data']['id'];
$sessionUrl = $pmData['data']['attributes']['checkout_url'];

$upd = $conn->prepare("UPDATE noblependingorder SET paymongo_session_id = ? WHERE id = ?");
$upd->bind_param("si", $sessionId, $pendingOrderId);
$upd->execute();
$upd->close();

// ── 9. Return checkout URL — frontend redirects here ──────────────────────────
echo json_encode([
    'ok'           => true,
    'checkout_url' => $sessionUrl,
]);
exit;





