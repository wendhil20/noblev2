<?php
// logisticdispatcher-delivered.php
// Marks a booking as 'delivered' once the driver confirms the order reached the customer.
// Requires a proof-of-delivery image upload (jpg/jpeg/png), which is converted to .webp
// and stored under /uploads/pod/, with the path saved to proof_of_delivery_path.
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICDISPATCHER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

// This endpoint receives multipart/form-data (because of the file upload),
// not JSON — booking_id comes from $_POST, the file from $_FILES.
$bookingId = (int) ($_POST['booking_id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking id.']);
    exit;
}

if (empty($_FILES['proof_of_delivery']) || $_FILES['proof_of_delivery']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Proof of delivery photo is required.']);
    exit;
}

$fetchStmt = $conn->prepare("
    SELECT id, status
    FROM nobledeliverybooking
    WHERE id = ?
    LIMIT 1
");
$fetchStmt->bind_param("i", $bookingId);
$fetchStmt->execute();
$booking = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if ($booking['status'] !== 'in_transit') {
    echo json_encode(['success' => false, 'message' => 'This booking must be "in transit" first.']);
    exit;
}

// ── Validate the uploaded image ─────────────────────────────────────────────
$file = $_FILES['proof_of_delivery'];

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$detectedMime = mime_content_type($file['tmp_name']);

if (!in_array($detectedMime, $allowedMimes, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed (no GIFs).']);
    exit;
}

$maxBytes = 8 * 1024 * 1024; // 8MB
if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'Image is too large. Max size is 8MB.']);
    exit;
}

// ── Convert to .webp ─────────────────────────────────────────────────────
$uploadDir = ROOT_PATH . '/uploads/pod';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'pod_' . $bookingId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.webp';
$destPath = $uploadDir . '/' . $filename;
$relativePath = 'uploads/pod/' . $filename;

$sourceImage = match ($detectedMime) {
    'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
    'image/png' => imagecreatefrompng($file['tmp_name']),
    'image/webp' => imagecreatefromwebp($file['tmp_name']),
    default => null,
};

if (!$sourceImage) {
    echo json_encode(['success' => false, 'message' => 'Could not process the uploaded image.']);
    exit;
}

// Preserve transparency for PNG sources when converting
imagepalettetotruecolor($sourceImage);
imagealphablending($sourceImage, true);
imagesavealpha($sourceImage, true);

$converted = imagewebp($sourceImage, $destPath, 85);
imagedestroy($sourceImage);

if (!$converted) {
    echo json_encode(['success' => false, 'message' => 'Failed to save converted image.']);
    exit;
}

// ── Update booking ───────────────────────────────────────────────────────
$updateStmt = $conn->prepare("
    UPDATE nobledeliverybooking
    SET status = 'delivered', delivered_at = NOW(), proof_of_delivery_path = ?
    WHERE id = ? AND status = 'in_transit'
");
$updateStmt->bind_param("si", $relativePath, $bookingId);

if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'proof_of_delivery_path' => $relativePath]);
} else {
    // Roll back the saved file if the DB update didn't actually apply
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    echo json_encode(['success' => false, 'message' => 'Could not update booking. It may have already changed status.']);
}
$updateStmt->close();