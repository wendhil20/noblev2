<?php
// user/ui-page/backend/backend-page-6/orders-poll.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/page-6/orders-functions.php';


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ─── Guard: must be logged in ──────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$orders = fetchUserOrders($conn, $userId);
$statusTabs = buildStatusTabs($orders);

ob_start();
include ROOT_PATH . '/user/ui-page/page-6/orders-list-partial.php';
$html = ob_get_clean();

echo json_encode([
    'html' => $html,
    'hasOrders' => !empty($orders),
    'version' => buildOrderVersionMap($orders),
    'serverTime' => date('c'),
]);