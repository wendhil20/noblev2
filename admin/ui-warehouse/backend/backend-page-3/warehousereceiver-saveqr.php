<?php
// warehousereceiver-saveqr.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$poId    = isset($data['po_id'])    ? (int) $data['po_id']    : 0; // ✅ idagdag
$image   = isset($data['image'])    ? $data['image']          : '';
$ref     = isset($data['ref'])      ? preg_replace('/[^a-z0-9\-]/i', '-', $data['ref']) : 'qr';
$staffId = $_SESSION['account_id']  ?? 0;

if (!$orderId || !$poId || !$image) { // ✅ kasama na ang !$poId
    echo json_encode(['success' => false, 'message' => 'Missing data.']);
    exit;
}

// ✅ Verify — scoped sa specific na po_id
$check = $conn->prepare("
    SELECT id FROM noblereceivingreceiver 
    WHERE order_id = ? AND po_id = ? AND assigned_staff_id = ? 
    LIMIT 1
");
$check->bind_param("iii", $orderId, $poId, $staffId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$check->close();

// Decode base64 image
$imageData = preg_replace('/^data:image\/\w+;base64,/', '', $image);
$imageData = base64_decode($imageData);

if (!$imageData) {
    echo json_encode(['success' => false, 'message' => 'Invalid image data.']);
    exit;
}

$uploadDir = ROOT_PATH . '/uploads/qrcodes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ✅ Unique filename per PO — kasama na ang po_id
$filename = 'qr-' . $ref . '-' . $orderId . '-' . $poId . '.png';
$filepath = $uploadDir . $filename;

if (file_put_contents($filepath, $imageData) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// ✅ Update — scoped sa specific na po_id
$relativePath = 'uploads/qrcodes/' . $filename;
$update = $conn->prepare("
    UPDATE noblereceivingreceiver 
    SET qr_path = ? 
    WHERE order_id = ? AND po_id = ? AND assigned_staff_id = ?
");
$update->bind_param("siii", $relativePath, $orderId, $poId, $staffId);
$update->execute();
$update->close();

echo json_encode([
    'success'  => true,
    'message'  => 'QR saved.',
    'filename' => $filename,
    'path'     => $relativePath,
]);