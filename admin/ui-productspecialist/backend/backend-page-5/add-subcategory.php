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
    $tmpPath = $_FILES['image']['tmp_name'];

    // Validate: make sure this is actually an image
    $imageInfo = getimagesize($tmpPath);
    if ($imageInfo === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid image file']);
        exit;
    }

    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
    if (!in_array($imageInfo[2], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Unsupported image type']);
        exit;
    }

    $uploadDir = ROOT_PATH . '/uploads/subcategory/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'sub_' . time() . '_' . rand(1000, 9999) . '.webp';
    $destPath = $uploadDir . $filename;

    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($tmpPath);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($tmpPath);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case IMAGETYPE_WEBP:
            $src = imagecreatefromwebp($tmpPath);
            break;
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($tmpPath);
            break;
        default:
            $src = false;
    }

    if ($src === false) {
        echo json_encode(['success' => false, 'message' => 'Could not process image']);
        exit;
    }

    $maxWidth = 1600;
    $origWidth = imagesx($src);
    $origHeight = imagesy($src);

    if ($origWidth > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = intval($origHeight * ($maxWidth / $origWidth));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        $src = $resized;
    }

    $saved = imagewebp($src, $destPath, 80);
   

    if (!$saved) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        exit;
    }

    $imagePath = '/uploads/subcategory/' . $filename;
}

$stmt = $conn->prepare("INSERT INTO noblesubcategory (category_id, name, image) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $catId, $name, $imagePath);
echo json_encode(['success' => $stmt->execute()]);