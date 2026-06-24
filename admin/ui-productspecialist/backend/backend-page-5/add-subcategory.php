<?php
// admin/ui-productspecialist/backend/backend-page-5/add-subcategory.php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

$name     = trim($_POST['name'] ?? '');
$catId    = intval($_POST['category_id'] ?? 0);
$imagePath = null;

if (!$name || !$catId) { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

// Handle image upload
if (!empty($_FILES['image']['tmp_name'])) {
    $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = 'sub_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $uploadDir = ROOT_PATH . '/uploads/subcategory/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
    $imagePath = '/uploads/subcategory/' . $filename;
}

$stmt = $conn->prepare("INSERT INTO noblesubcategory (category_id, name, image) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $catId, $name, $imagePath);
echo json_encode(['success' => $stmt->execute()]);