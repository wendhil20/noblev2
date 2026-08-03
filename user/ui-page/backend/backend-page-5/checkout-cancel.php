<?php
// checkout-cancel.php
// Hit when the user cancels/backs out of PayMongo's hosted checkout page.
// Releases any stock that was reserved for this pending order.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-5/helper/stock-reserve-helper.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/google');
    exit;
}

$userId         = intval($_SESSION['user_id']);
$pendingOrderId = intval($_GET['pending'] ?? 0);

if ($pendingOrderId > 0) {
    // Verify ownership before restoring
    $s = $conn->prepare("SELECT id FROM noblependingorder WHERE id = ? AND user_id = ? AND used = 0");
    $s->bind_param("ii", $pendingOrderId, $userId);
    $s->execute();
    $owned = $s->get_result()->fetch_assoc();
    $s->close();

    if ($owned) {
        restoreStockForPendingOrder($conn, $pendingOrderId);
    }
}

header('Location: ' . BASE_URL . '/checkout?cancelled=1');
exit;