<?php
// add-category.php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
if (!$name) { echo json_encode(['success' => false, 'message' => 'Name required']); exit; }

$imagePath = null;

if (!empty($_FILES['image']['tmp_name'])) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = 'category_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $uploadDir = ROOT_PATH . '/uploads/';
    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
        $imagePath = 'uploads/' . $filename;
    }
}

$stmt = $conn->prepare("INSERT INTO noblecategory (name, image) VALUES (?, ?)");
$stmt->bind_param("ss", $name, $imagePath);
echo json_encode(['success' => $stmt->execute()]);