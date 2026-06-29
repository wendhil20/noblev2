<?php
// user/ui-page/page-5/success.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$userId = intval($_SESSION['user_id']);

// ── Localhost bypass: manually process pending order ─────────────────────────
$isLocalhost = (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);

if ($isLocalhost) {
    // Find the latest unused pending order for this user
    $s = $conn->prepare("
        SELECT * FROM noblependingorder
        WHERE user_id = ? AND used = 0
        ORDER BY created_at DESC LIMIT 1
    ");
    $s->bind_param("i", $userId);
    $s->execute();
    $pending = $s->get_result()->fetch_assoc();
    $s->close();

    if ($pending) {
        $cartItems = json_decode($pending['cart_items_json'], true);

        // Generate NHCC reference
        $year = date('Y');
        $refCheck = $conn->prepare("
            SELECT nhccreference FROM noblepaidproductlist
            WHERE nhccreference LIKE ? ORDER BY id DESC LIMIT 1
        ");
        $likePattern = "NHCC-{$year}-%";
        $refCheck->bind_param("s", $likePattern);
        $refCheck->execute();
        $lastRef = $refCheck->get_result()->fetch_assoc();
        $refCheck->close();

        $lastNum       = $lastRef ? intval(substr($lastRef['nhccreference'], -3)) : 0;
        $nhccReference = 'NHCC-' . $year . '-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);

        $conn->begin_transaction();
        try {
            // Deduct stock + increment sold count
            $stockStmt = $conn->prepare("
                UPDATE nobleproductvariant
                SET stock = stock - ?, sold = sold + ?
                WHERE id = ? AND stock >= ?
            ");
            foreach ($cartItems as $item) {
                $vId = intval($item['variant_id']);
                $qty = intval($item['quantity']);
                $stockStmt->bind_param("iiii", $qty, $qty, $vId, $qty);
                $stockStmt->execute();
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
                $pending['truck_max_vol'],
                $pending['truck_max_wt'],
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

                $insItem->bind_param(
                    "iisisissiddd",
                    $orderId, $pid, $pname, $cid, $cname, $vid, $sname, $punit,
                    $qty, $unitPrice, $disc, $lineTotal
                );
                $insItem->execute();
            }
            $insItem->close();

            // Clear cart
            $conn->query("DELETE FROM noblecart WHERE user_id = " . intval($userId));

            // Mark pending as used
            $conn->query("
                UPDATE noblependingorder SET used = 1, order_id = " . intval($orderId) . "
                WHERE id = " . intval($pending['id'])
            );

            // Notify Accounting
            $notifTitle  = 'New Order ' . $nhccReference;
            $notifMsg    = 'Order from ' . $pending['contact_name'] . ' — ₱' . number_format(floatval($pending['grand_total']), 2);
            $notifLink   = BASE_URL . '/accounting';
            $forRole     = ROLE_ACCOUNTING;
            $forPosition = POSITION_HEAD;
            $forUserId   = null;
            $ns = $conn->prepare("
                INSERT INTO noblenotification (for_role, for_position, for_user_id, title, message, link)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ns->bind_param("ssisss", $forRole, $forPosition, $forUserId, $notifTitle, $notifMsg, $notifLink);
            $ns->execute();
            $ns->close();

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

// ── Fetch the confirmed order ─────────────────────────────────────────────────
$order = null;
$s = $conn->prepare("
    SELECT o.*, COUNT(i.id) AS item_count
    FROM noblepaidproductlist o
    LEFT JOIN noblepaidproductitems i ON i.order_id = o.id
    WHERE o.user_id = ? AND o.payment_status = 'paid'
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT 1
");
$s->bind_param("i", $userId);
$s->execute();
$order = $s->get_result()->fetch_assoc();
$s->close();

if (!$order) {
    // Still processing (live webhook not yet fired) — show loading screen
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirming Order — NobleHome</title>
        <?php include ROOT_PATH . '/link/top.php'; ?>
        <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center">
        <div class="text-center p-8">
            <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-clock text-amber-500 text-2xl"></i>
            </div>
            <h1 class="text-lg font-bold text-gray-900 mb-2">Payment received!</h1>
            <p class="text-sm text-gray-500 mb-4">Your order is being confirmed. This usually takes a few seconds.</p>
            <p class="text-xs text-gray-400 mb-6">Page will refresh automatically…</p>
            <script>setTimeout(() => location.reload(), 3000);</script>
        </div>
        <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="max-w-md mx-auto px-4 py-16">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">

            <!-- Icon -->
            <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-check text-green-500 text-2xl"></i>
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-1">Order Confirmed!</h1>
            <p class="text-sm text-gray-500 mb-6">
                Thank you, <strong><?= htmlspecialchars($order['contact_name']) ?></strong>.<br>
                Your order has been received and is being prepared.
            </p>

            <!-- Order details -->
            <div class="text-left rounded-xl border border-gray-100 p-4 mb-6 space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Reference</span>
                    <span class="font-bold text-gray-800"><?= htmlspecialchars($order['nhccreference']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Items</span>
                    <span class="font-semibold text-gray-700"><?= intval($order['item_count']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Delivery</span>
                    <span class="font-semibold text-gray-700"><?= ucfirst($order['delivery_method']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Total paid</span>
                    <span class="font-bold text-gray-900">₱<?= number_format(floatval($order['grand_total']), 2) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Payment status</span>
                    <span class="text-green-600 font-semibold">
                        <i class="fa-solid fa-circle-check mr-1"></i>Paid
                    </span>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/"
                class="block w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-white
                       text-sm font-bold text-center transition">
                Continue Shopping
            </a>

            <p class="text-[10px] text-gray-400 mt-3">
                A confirmation email was sent to <?= htmlspecialchars($order['contact_email']) ?>
            </p>

        </div>
    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>
</html>