<?php
// handler/stock-reserve-helper.php

function reserveStockForCartItems(mysqli $conn, array $cartItems): array
{
    $conn->begin_transaction();
    try {
        $stockStmt = $conn->prepare("
            UPDATE nobleproductvariant
            SET stock = stock - ?
            WHERE id = ? AND stock >= ?
        ");
        if (!$stockStmt) {
            throw new \RuntimeException('Prepare failed (stock reserve): ' . $conn->error);
        }

        foreach ($cartItems as $item) {
            $vId = intval($item['variant_id']);
            $qty = intval($item['quantity']);

            $stockStmt->bind_param("iii", $qty, $vId, $qty);
            $stockStmt->execute();

            if ($stockStmt->affected_rows === 0) {
                $conn->rollback();

                $itemLabel = ($item['product_name'] ?? 'Item')
                    . (!empty($item['colorname']) ? ' — ' . $item['colorname'] : '')
                    . (!empty($item['sizename']) ? ' / ' . $item['sizename'] : '');

                return [
                    false,
                    [
                        'ok' => false,
                        'error' => 'Sorry, "' . $itemLabel . '" no longer has enough stock.',
                        'out_of_stock' => true,
                        'variant_id' => $vId,
                    ]
                ];
            }
        }
        $stockStmt->close();
        $conn->commit();
        return [true, null];

    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('Stock reserve error: ' . $e->getMessage());
        return [
            false,
            [
                'ok' => false,
                'error' => 'Failed to reserve stock. Please try again.',
            ]
        ];
    }
}


function restoreStockForCartItems(mysqli $conn, array $cartItems): void
{
    $restore = $conn->prepare("UPDATE nobleproductvariant SET stock = stock + ? WHERE id = ?");
    if (!$restore) {
        error_log('Prepare failed (restoreStockForCartItems): ' . $conn->error);
        return;
    }
    foreach ($cartItems as $item) {
        $qty = intval($item['quantity']);
        $vId = intval($item['variant_id']);
        $restore->bind_param("ii", $qty, $vId);
        $restore->execute();
    }
    $restore->close();
}

function restoreStockForPendingOrder(mysqli $conn, int $pendingOrderId): void
{
    $conn->begin_transaction();
    try {
        $s = $conn->prepare("
            SELECT cart_items_json, stock_reserved
            FROM noblependingorder
            WHERE id = ? AND used = 0
            FOR UPDATE
        ");
        if (!$s) {
            throw new \RuntimeException('Prepare failed (select pending order): ' . $conn->error);
        }
        $s->bind_param("i", $pendingOrderId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$row || intval($row['stock_reserved']) !== 1) {
            $conn->commit();
            return;
        }

        $cartItems = json_decode($row['cart_items_json'], true) ?: [];

        $restore = $conn->prepare("UPDATE nobleproductvariant SET stock = stock + ? WHERE id = ?");
        if (!$restore) {
            throw new \RuntimeException('Prepare failed (restore stock): ' . $conn->error);
        }
        foreach ($cartItems as $item) {
            $qty = intval($item['quantity']);
            $vId = intval($item['variant_id']);
            $restore->bind_param("ii", $qty, $vId);
            $restore->execute();
        }
        $restore->close();

        $upd = $conn->prepare("
            UPDATE noblependingorder
            SET stock_reserved = 0
            WHERE id = ?
        ");
        if (!$upd) {
            throw new \RuntimeException('Prepare failed (update pending order): ' . $conn->error);
        }
        $upd->bind_param("i", $pendingOrderId);
        $upd->execute();
        $upd->close();

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('Restore stock error (pending order ' . $pendingOrderId . '): ' . $e->getMessage());
    }
}