<?php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = intval($data['id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM noblesubcategory WHERE id = ?");
$stmt->bind_param("i", $id);
echo json_encode(['success' => $stmt->execute()]);