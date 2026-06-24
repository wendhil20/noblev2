<?php
// admin/ui-productspecialist/backend/backend-page-5/update-subcategory-image.php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

if (empty($_FILES['image']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit;
}

$ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filename = 'sub_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
$uploadDir = ROOT_PATH . '/uploads/subcategory/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
    exit;
}

$imagePath = 'uploads/subcategory/' . $filename;
$stmt = $conn->prepare("UPDATE noblesubcategory SET image = ? WHERE id = ?");
$stmt->bind_param("si", $imagePath, $id);
echo json_encode(['success' => $stmt->execute()]);