<?php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId    = intval($data['product_id'] ?? 0);
$subcategoryId = intval($data['subcategory_id'] ?? 0);
$linked       = (bool)($data['linked'] ?? false);

if (!$productId || !$subcategoryId) {
    echo json_encode(['success' => false]);
    exit;
}

if ($linked) {
    // Add link
    $stmt = $conn->prepare("INSERT IGNORE INTO nobleproduct_subcategory (product_id, subcategory_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $productId, $subcategoryId);
} else {
    // Remove link
    $stmt = $conn->prepare("DELETE FROM nobleproduct_subcategory WHERE product_id = ? AND subcategory_id = ?");
    $stmt->bind_param("ii", $productId, $subcategoryId);
}

echo json_encode(['success' => $stmt->execute()]);