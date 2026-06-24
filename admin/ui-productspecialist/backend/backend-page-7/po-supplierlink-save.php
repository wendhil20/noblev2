<?php
// admin/ui-productspecialist/backend/backend-page-7/po-supplierlink-save.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$productId  = intval($_POST['product_id']  ?? 0);
$supplierId = intval($_POST['supplier_id'] ?? 0);
$linkType   = trim($_POST['link_type']     ?? '');

if (!$productId || !$supplierId) {
    echo json_encode(['success' => false, 'error' => 'Missing product or supplier ID.']);
    exit;
}

// Remove link
if ($linkType === 'remove') {
    $stmt = $conn->prepare("DELETE FROM nobleproductsupplierlink WHERE product_id = ? AND supplier_id = ?");
    $stmt->bind_param("ii", $productId, $supplierId);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
    exit;
}

if (!in_array($linkType, ['primary', 'secondary'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid link type.']);
    exit;
}

// Insert or update (upsert)
$stmt = $conn->prepare("
    INSERT INTO nobleproductsupplierlink (product_id, supplier_id, link_type)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE link_type = VALUES(link_type)
");
$stmt->bind_param("iis", $productId, $supplierId, $linkType);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);