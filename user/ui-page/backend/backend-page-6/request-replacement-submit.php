<?php
// user/ui-page/page-6/request-replacement-submit.php
header('Content-Type: application/json');
include ROOT_PATH . '/network/connect.php';

function respond($success, $message = '', $extra = [])
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

$userId    = (int) $_SESSION['user_id'];
$orderId   = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$poId      = isset($_POST['po_id']) && $_POST['po_id'] !== '' ? (int) $_POST['po_id'] : null;
$bookingId = isset($_POST['booking_id']) && $_POST['booking_id'] !== '' ? (int) $_POST['booking_id'] : null;
$reason    = trim($_POST['reason'] ?? '');

if (!$orderId || $reason === '') {
    respond(false, 'Missing required fields.');
}

// ─── Verify the order belongs to this user ─────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM noblepaidproductlist WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    respond(false, 'Order not found or does not belong to you.');
}

// ─── Handle multiple photo uploads ─────────────────────────────────────────
$uploadedPaths = [];
$maxPhotos = 5;
$maxSizeBytes = 5 * 1024 * 1024; // 5MB per photo
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {

    $fileCount = count($_FILES['photos']['name']);
    if ($fileCount > $maxPhotos) {
        respond(false, "You can only upload up to {$maxPhotos} photos.");
    }

    // ─── Build upload directory: /uploads/replacements/YYYY/MM/ ───────────
    $yearMonth = date('Y/m');
    $uploadDir = ROOT_PATH . '/uploads/replacements/' . $yearMonth . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    for ($i = 0; $i < $fileCount; $i++) {

        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
            continue; // skip failed individual file
        }

        $tmpName  = $_FILES['photos']['tmp_name'][$i];
        $size     = $_FILES['photos']['size'][$i];
        $origName = $_FILES['photos']['name'][$i];

        $mimeType = mime_content_type($tmpName);
        if (!in_array($mimeType, $allowedTypes, true)) {
            continue; // skip invalid file type
        }

        if ($size > $maxSizeBytes) {
            continue; // skip oversized file
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';

        $newFileName = 'rr_' . $orderId . '_' . uniqid() . '_' . $i . '.' . $safeExt;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($tmpName, $destPath)) {
            // ─── Relative path na isasave sa DB / gagamitin sa <img src> ──
            $uploadedPaths[] = 'uploads/replacements/' . $yearMonth . '/' . $newFileName;
        }
    }
}

// ─── Insert replacement request ────────────────────────────────────────────
$photosJson = !empty($uploadedPaths) ? json_encode($uploadedPaths) : null;

$stmt = $conn->prepare("
    INSERT INTO noblereplacementrequest
        (order_id, user_id, po_id, booking_id, reason, photos, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
");
$stmt->bind_param('iiiiss', $orderId, $userId, $poId, $bookingId, $reason, $photosJson);

if ($stmt->execute()) {
    respond(true, 'Replacement request submitted successfully.', ['request_id' => $stmt->insert_id]);
} else {
    respond(false, 'Failed to save your request. Please try again.');
}

$stmt->close();