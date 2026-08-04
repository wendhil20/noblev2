<?php
// cron/expire-pending-orders.php
// Run this every few minutes via cron to release stock reserved by
// abandoned checkouts (user never completed or cancelled payment).
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-5/helper/stock-reserve-helper.php';

// Consider a pending order "abandoned" if reserved more than 5 minutes ago
$cutoffSeconds = 300;

$s = $conn->prepare("
    SELECT id
    FROM noblependingorder
    WHERE used = 0
      AND stock_reserved = 1
      AND reserved_at < (NOW() - INTERVAL ? SECOND)
");
$s->bind_param("i", $cutoffSeconds);
$s->execute();
$expiredOrders = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

foreach ($expiredOrders as $row) {
    restoreStockForPendingOrder($conn, intval($row['id']));
    error_log("Expired pending order restored: " . $row['id']);
}

echo "Processed " . count($expiredOrders) . " expired pending order(s).\n";