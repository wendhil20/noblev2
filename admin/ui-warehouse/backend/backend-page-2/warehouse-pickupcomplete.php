<?php
// warehouse-pickupcomplete.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$poId      = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;
$notes     = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$updatedBy = $_SESSION['account_id'] ?? 0;

if (!$poId) {
    echo json_encode(['success' => false, 'message' => 'Missing PO ID.']);
    exit;
}

if (empty($_FILES['proof_of_pickup']) || $_FILES['proof_of_pickup']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please attach a proof of pickup photo.']);
    exit;
}

// Validate tracking record
$trackStmt = $conn->prepare("SELECT id, order_id, current_step, delivery_method FROM nobleordertracking WHERE po_id = ?");
$trackStmt->bind_param("i", $poId);
$trackStmt->execute();
$tracking = $trackStmt->get_result()->fetch_assoc();
$trackStmt->close();

if (!$tracking) {
    echo json_encode(['success' => false, 'message' => 'No tracking record found for this PO.']);
    exit;
}

if ($tracking['delivery_method'] !== 'pickup') {
    echo json_encode(['success' => false, 'message' => 'This PO is not a pickup order.']);
    exit;
}

if ((int)$tracking['current_step'] !== 2) {
    echo json_encode(['success' => false, 'message' => 'PO must be at "Item ready" step before confirming pickup.']);
    exit;
}

$orderId = $tracking['order_id'];

// Validate file type/size
$file = $_FILES['proof_of_pickup'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.']);
    exit;
}
if ($file['size'] > 8 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image is too large. Max size is 8MB.']);
    exit;
}

// Save file
$uploadDir = ROOT_PATH . '/uploads/pickup-proof/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = match ($file['type']) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$filename = 'pickup_' . $poId . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;
$relativePath = 'uploads/pickup-proof/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
}

// Update tracking — advance to "Picked up" (step 3)
$updateStmt = $conn->prepare("
    UPDATE nobleordertracking
    SET current_step = 3, proof_of_pickup_path = ?, step_updated_at = NOW()
    WHERE po_id = ?
");
$updateStmt->bind_param("si", $relativePath, $poId);
$updateStmt->execute();
$updateStmt->close();

// Log
$logNote = 'Proof of pickup uploaded.' . ($notes ? " {$notes}" : '');
$logStmt = $conn->prepare("
    INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
    VALUES (?, 3, 'Picked up', ?, ?, NOW())
");
$logStmt->bind_param("iis", $orderId, $updatedBy, $logNote);
$logStmt->execute();
$logStmt->close();

// Notify
$poInfo = $conn->prepare("
    SELECT npo.po_number, ppl.nhccreference
    FROM noblepurchaseorder npo
    JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
    WHERE npo.id = ?
    LIMIT 1
");
$poInfo->bind_param("i", $poId);
$poInfo->execute();
$poDetails = $poInfo->get_result()->fetch_assoc();
$poInfo->close();

$poNumber  = $poDetails['po_number']     ?? "PO #{$poId}";
$reference = $poDetails['nhccreference'] ?? '';

$notifTitle   = "Order Picked Up";
$notifMessage = "Purchase Order {$poNumber}" . ($reference ? " ({$reference})" : "") . " has been picked up by the customer.";

sendNotification($conn, ROLE_WAREHOUSE, POSITION_WAREHOUSESTAFF, null, $notifTitle, $notifMessage, BASE_URL . '/warehousestaff');

echo json_encode([
    'success' => true,
    'message' => 'Pickup confirmed successfully.',
]);