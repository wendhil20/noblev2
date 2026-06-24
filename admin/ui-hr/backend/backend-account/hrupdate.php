
<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$id   = intval($data['id'] ?? 0);
$name = trim($data['name'] ?? '');

if (!$id || !$name) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

if (!empty($data['password'])) {
    $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE noblerole SET name = ?, password = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $hashed, $id);
} else {
    $stmt = $conn->prepare("UPDATE noblerole SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}