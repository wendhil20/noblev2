<?php
// checkout-cancel.php

include ROOT_PATH . '/network/connect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/google');
    exit;
}

$userId  = intval($_SESSION['user_id']);
$orderId = intval($_GET['order'] ?? 0);

if ($orderId > 0) {
    $conn->begin_transaction();
    try {
        $s = $conn->prepare("
            SELECT id, payment_status
            FROM noblepaidproductlist
            WHERE id = ? AND user_id = ?
            FOR UPDATE
        ");
        $s->bind_param("ii", $orderId, $userId);
        $s->execute();
        $order = $s->get_result()->fetch_assoc();
        $s->close();

        if ($order && $order['payment_status'] === 'pending') {
            // Restore stock for each item in this order
            $items = $conn->prepare("SELECT variant_id, quantity FROM noblepaidproductitems WHERE order_id = ?");
            $items->bind_param("i", $orderId);
            $items->execute();
            $itemRows = $items->get_result()->fetch_all(MYSQLI_ASSOC);
            $items->close();

            $restore = $conn->prepare("UPDATE nobleproductvariant SET stock = stock + ? WHERE id = ?");
            foreach ($itemRows as $row) {
                $qty = intval($row['quantity']);
                $vid = intval($row['variant_id']);
                $restore->bind_param("ii", $qty, $vid);
                $restore->execute();
            }
            $restore->close();

            $upd = $conn->prepare("UPDATE noblepaidproductlist SET payment_status = 'cancelled' WHERE id = ?");
            $upd->bind_param("i", $orderId);
            $upd->execute();
            $upd->close();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}

header('Location: ' . BASE_URL . '/checkout?cancelled=1');
exit;