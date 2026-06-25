<?php
// user/ui-page/backend/backend-page-2/submit-review.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/navigation/system-notifications-functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not logged in.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$orderId = (int) ($input['order_id'] ?? 0);
$orderItemId = (int) ($input['order_item_id'] ?? 0);
$productId = (int) ($input['product_id'] ?? 0);
$rating = (int) ($input['rating'] ?? 0);
$comment = trim($input['comment'] ?? '');

if (!$orderId || !$orderItemId || !$productId || $rating < 1 || $rating > 5) {
    echo json_encode(['ok' => false, 'message' => 'Invalid review data.']);
    exit;
}

// ─── Security check: yung order_item dapat talagang pag-aari ng user na ito ───
$check = $conn->prepare("
    SELECT pi.id
    FROM noblepaidproductitems pi
    INNER JOIN noblepaidproductlist pl ON pl.id = pi.order_id
    WHERE pi.id = ? AND pl.id = ? AND pl.user_id = ?
");
$check->bind_param('iii', $orderItemId, $orderId, $userId);
$check->execute();
$valid = $check->get_result()->fetch_assoc();
$check->close();

if (!$valid) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Item not found in your orders.']);
    exit;
}

$ok = saveReview($conn, $userId, $orderId, $orderItemId, $productId, $rating, $comment);

echo json_encode(['ok' => $ok]);