<?php

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

// Check kung saved na
$stmt = $conn->prepare("SELECT id FROM noblesavedproduct WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $userId, $productId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    // Unsave
    $stmt = $conn->prepare("DELETE FROM noblesavedproduct WHERE id = ?");
    $stmt->bind_param("i", $existing['id']);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'saved' => false]);
} else {
    // Save
    $stmt = $conn->prepare("INSERT INTO noblesavedproduct (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'saved' => true]);
}