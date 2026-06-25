<?php
// handler/check-qrph.php
// Polled by the frontend every 5 s to check whether a QR Ph payment was received.
//
// Static QR Ph has no per-transaction PayMongo ID to poll directly.
// Confirmation comes via PayMongo webhook (payment.paid) → your webhook handler
// marks noblependingorder.payment_status = 'paid' and sets final_order_id.
//
// This endpoint simply checks that DB column and returns the result.
//
// GET params:
//   source_id  — the marker stored in paymongo_session_id (qrph_pending_{id})
//   order_id   — noblependingorder.id

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'error' => 'Not authenticated']);
    exit;
}

$userId  = intval($_SESSION['user_id']);
$orderId = intval($_GET['order_id'] ?? 0);

if (!$orderId) {
    echo json_encode(['status' => 'error', 'error' => 'Missing order_id']);
    exit;
}

include ROOT_PATH . '/network/connect.php';

// ── Poll the local DB row ─────────────────────────────────────────────────────
$s = $conn->prepare("
    SELECT payment_status, final_order_id
    FROM noblependingorder
    WHERE id = ? AND user_id = ?
");
$s->bind_param("ii", $orderId, $userId);
$s->execute();
$row = $s->get_result()->fetch_assoc();
$s->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'error' => 'Order not found']);
    exit;
}

switch ($row['payment_status']) {
    case 'paid':
        echo json_encode([
            'status'   => 'paid',
            'order_id' => intval($row['final_order_id']),
        ]);
        break;

    case 'cancelled':
    case 'expired':
        echo json_encode(['status' => 'failed']);
        break;

    default: // 'pending' — keep polling
        echo json_encode(['status' => 'pending']);
        break;
}
exit;