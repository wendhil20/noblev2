<?php
header('Content-Type: application/json');
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$id = intval($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['ok' => false]); exit; }

$stmt = $conn->prepare("DELETE FROM nobleproductpromo WHERE id = ?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);