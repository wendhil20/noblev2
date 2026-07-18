<?php
// add-category.php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
if (!$name) { echo json_encode(['success' => false, 'message' => 'Name required']); exit; }

$imagePath = null;

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

    $uploadDir = ROOT_PATH . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'category_' . time() . '_' . rand(100, 999) . '.webp';
    $destPath = $uploadDir . $filename;

    // Load source image based on detected type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($tmpPath);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($tmpPath);
            // Preserve transparency
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

    // Optional: cap max dimensions to avoid huge uploads (e.g. 1600px wide max)
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

    // Save as WebP (quality 80 is a good balance of size vs quality)
    $saved = imagewebp($src, $destPath, 80);
    

    if (!$saved) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        exit;
    }

    $imagePath = 'uploads/' . $filename;
}

$stmt = $conn->prepare("INSERT INTO noblecategory (name, image) VALUES (?, ?)");
$stmt->bind_param("ss", $name, $imagePath);
echo json_encode(['success' => $stmt->execute()]);