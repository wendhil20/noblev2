<?php
header('Content-Type: application/json');
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id        = intval($input['id'] ?? 0);
$productId = intval($input['product_id'] ?? 0);
$discount  = floatval($input['discount_percent'] ?? 0);
$start     = $input['start_date'] ?? '';
$end       = $input['end_date'] ?? '';
$sizename  = trim($input['sizename'] ?? '');
$sizename  = $sizename === '' ? null : $sizename;
$colorId   = !empty($input['color_id']) ? intval($input['color_id']) : null;

if (!$productId || $discount <= 0 || $discount > 100 || !$start || !$end) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid input.']);
    exit;
}
if (strtotime($end) <= strtotime($start)) {
    echo json_encode(['ok' => false, 'msg' => 'End date must be after start date.']);
    exit;
}

// Validate size belongs to product
if ($sizename !== null) {
    $chk = $conn->prepare("
        SELECT 1 FROM nobleproductvariant v
        INNER JOIN nobleproductcolor c ON c.id = v.color_id
        WHERE c.product_id = ? AND v.sizename = ? LIMIT 1
    ");
    $chk->bind_param('is', $productId, $sizename);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['ok' => false, 'msg' => 'Selected size does not exist for this product.']);
        exit;
    }
    $chk->close();
}

// Validate color belongs to product
if ($colorId !== null) {
    $chk2 = $conn->prepare("SELECT 1 FROM nobleproductcolor WHERE id = ? AND product_id = ? LIMIT 1");
    $chk2->bind_param('ii', $colorId, $productId);
    $chk2->execute();
    if (!$chk2->get_result()->fetch_assoc()) {
        echo json_encode(['ok' => false, 'msg' => 'Selected color does not belong to this product.']);
        exit;
    }
    $chk2->close();
}

$startSql = date('Y-m-d H:i:s', strtotime($start));
$endSql   = date('Y-m-d H:i:s', strtotime($end));
$userId   = $_SESSION['user_id'] ?? null;

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE nobleproductpromo SET product_id=?, color_id=?, sizename=?, discount_percent=?, start_date=?, end_date=? WHERE id=?");
    $stmt->bind_param('iisdssi', $productId, $colorId, $sizename, $discount, $startSql, $endSql, $id);
} else {
    $stmt = $conn->prepare("INSERT INTO nobleproductpromo (product_id, color_id, sizename, discount_percent, start_date, end_date, created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('iisdssi', $productId, $colorId, $sizename, $discount, $startSql, $endSql, $userId);
}

$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Saved.' : 'Database error.']);