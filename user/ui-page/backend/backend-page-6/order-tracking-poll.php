<?php
// user/ui-page/backend/backend-page-6/order-tracking-poll.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/page-6/order-tracking-functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = (int) $_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_order_id']);
    exit;
}

$data = fetchOrderTrackingData($conn, $userId, $orderId);

if ($data === null) {
    http_response_code(404);
    echo json_encode(['error' => 'order_not_found']);
    exit;
}

$order = $data['order'];
$isPickup = $data['isPickup'];
$bookings = $data['bookings'];
$pickupTrackings = $data['pickupTrackings'];
$replacementRequest = $data['replacementRequest'];
$officeBase = $data['officeBase'];

ob_start();
include ROOT_PATH . '/user/ui-page/page-6/order-tracking-partial.php';
$html = ob_get_clean();

echo json_encode([
    'html' => $html,
    'isPickup' => $isPickup,
    'hasReplacementRequest' => !empty($replacementRequest),
    'version' => buildTrackingVersion($data),
    'serverTime' => date('c'),
]);