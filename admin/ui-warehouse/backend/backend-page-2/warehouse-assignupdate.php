<?php
// warehouse-assignupdate.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/notification-helper.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$poId = isset($data['po_id']) ? (int) $data['po_id'] : 0;
$newStep = isset($data['step']) ? (int) $data['step'] : -1;
$notes = isset($data['notes']) ? trim($data['notes']) : '';
$pickupLocation = isset($data['pickup_location']) ? trim($data['pickup_location']) : null;
$pickupDriver = isset($data['pickup_driver_name']) ? trim($data['pickup_driver_name']) : null;
$pickupPlate = isset($data['pickup_plate_number']) ? trim($data['pickup_plate_number']) : null;
$pickupTruck = isset($data['pickup_truck_details']) ? trim($data['pickup_truck_details']) : null;
$supplierAddress = isset($data['supplier_address']) ? trim($data['supplier_address']) : null;
$supplierLatitude = isset($data['supplier_latitude']) ? $data['supplier_latitude'] : null;
$supplierLongitude = isset($data['supplier_longitude']) ? $data['supplier_longitude'] : null;
$updatedBy = $_SESSION['account_id'] ?? 0;

if (!$poId || $newStep < 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate pickup_location value kung pinasa
if ($pickupLocation !== null && !in_array($pickupLocation, ['office', 'supplier'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid pickup location value.']);
    exit;
}

// Required na lagi ang driver name at plate number kapag may pickup_location
if ($pickupLocation && (!$pickupDriver || !$pickupPlate)) {
    echo json_encode(['success' => false, 'message' => 'Driver name and plate number are required.']);
    exit;
}

// Get current tracking by po_id
$trackStmt = $conn->prepare("SELECT id, order_id, current_step, delivery_method FROM nobleordertracking WHERE po_id = ?");
$trackStmt->bind_param("i", $poId);
$trackStmt->execute();
$tracking = $trackStmt->get_result()->fetch_assoc();
$trackStmt->close();

if (!$tracking) {
    echo json_encode(['success' => false, 'message' => 'No tracking record found for this PO.']);
    exit;
}

$orderId = $tracking['order_id'];
$isDelivery = $tracking['delivery_method'] === 'delivery';

// Step labels
$deliveryStepLabels = [
    0 => 'Passed to supplier',
    1 => 'Processing',
    2 => 'Expected delivery',
    3 => 'Out for delivery',
    4 => 'Assign receiving',
    5 => 'Completed',
];

$pickupStepLabels = [
    0 => 'Passed to supplier',
    1 => 'Processing',
    2 => 'Item ready',
    3 => 'Picked up',
];

$stepLabels = $isDelivery ? $deliveryStepLabels : $pickupStepLabels;
$stepLabel = $stepLabels[$newStep] ?? 'Unknown step';

// Update tracking
if ($isDelivery && $newStep === 2) {
    // Delivery method — Expected delivery date range
    $expectedFrom = $data['expected_from'] ?? null;
    $expectedTo = $data['expected_to'] ?? null;

    $dateStmt = $conn->prepare("
        UPDATE nobleordertracking 
        SET current_step = ?, expected_delivery_from = ?, expected_delivery_to = ?, step_updated_at = NOW()
        WHERE po_id = ?
    ");
    $dateStmt->bind_param("issi", $newStep, $expectedFrom, $expectedTo, $poId);
    $dateStmt->execute();
    $dateStmt->close();
} elseif (!$isDelivery && $newStep === 2 && $pickupLocation) {
    // Pickup method — Item ready, save pickup_location + driver info + address/coords
    // (address/coords ay laging napasa na ngayon mula front-end, office man o supplier)
    $updateStmt = $conn->prepare("
        UPDATE nobleordertracking 
        SET current_step = ?, pickup_location = ?, 
            pickup_driver_name = ?, pickup_plate_number = ?, pickup_truck_details = ?,
            supplier_address = ?, supplier_latitude = ?, supplier_longitude = ?,
            step_updated_at = NOW()
        WHERE po_id = ?
    ");
    $updateStmt->bind_param(
        "isssssddi",
        $newStep,
        $pickupLocation,
        $pickupDriver,
        $pickupPlate,
        $pickupTruck,
        $supplierAddress,
        $supplierLatitude,
        $supplierLongitude,
        $poId
    );
    $updateStmt->execute();
    $updateStmt->close();
} else {
    $updateStmt = $conn->prepare("
        UPDATE nobleordertracking 
        SET current_step = ?, step_updated_at = NOW()
        WHERE po_id = ?
    ");
    $updateStmt->bind_param("ii", $newStep, $poId);
    $updateStmt->execute();
    $updateStmt->close();
}

// Log the step change
$logNote = $notes;
if (!$isDelivery && $newStep === 2 && $pickupLocation) {
    $locLabel = $pickupLocation === 'office' ? 'Office (Warehouse)' : 'Supplier Location';
    $logNote = "Pickup location: {$locLabel}. Kumuha mula sa supplier: {$pickupDriver}, Plate: {$pickupPlate}"
        . ($pickupTruck ? ", Truck: {$pickupTruck}" : '')
        . ($notes ? " — {$notes}" : '');
}

$logStmt = $conn->prepare("
    INSERT INTO nobletrackinglog (order_id, step_number, step_label, changed_by, notes, changed_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$logStmt->bind_param("iisss", $orderId, $newStep, $stepLabel, $updatedBy, $logNote);
$logStmt->execute();
$logStmt->close();

// ── Notify pagka-abot ng final step ──
$maxStep = $isDelivery ? 5 : 3;

if ($newStep === $maxStep) {
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

    $poNumber = $poDetails['po_number'] ?? "PO #{$poId}";
    $reference = $poDetails['nhccreference'] ?? '';
    $notifTitle = "PO Completed";
    $notifMessage = "Purchase Order {$poNumber}" . ($reference ? " ({$reference})" : "") . " tracking has been marked as completed.";

    $targets = [
        [POSITION_WAREHOUSESTAFF, BASE_URL . '/warehousestaff'],
        [POSITION_WAREHOUSERECEIVER, BASE_URL . '/warehousereceiver'],
    ];

    foreach ($targets as [$pos, $link]) {
        sendNotification($conn, ROLE_WAREHOUSE, $pos, null, $notifTitle, $notifMessage, $link);
    }
}

// ── Bonus: Notify pagka-abot ng "Item ready" para sa pickup, may driver/location info ──
if (!$isDelivery && $newStep === 2 && $pickupLocation) {
    $poInfo2 = $conn->prepare("
        SELECT npo.po_number, ppl.nhccreference
        FROM noblepurchaseorder npo
        JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
        WHERE npo.id = ?
        LIMIT 1
    ");
    $poInfo2->bind_param("i", $poId);
    $poInfo2->execute();
    $poDetails2 = $poInfo2->get_result()->fetch_assoc();
    $poInfo2->close();

    $poNumber2 = $poDetails2['po_number'] ?? "PO #{$poId}";
    $reference2 = $poDetails2['nhccreference'] ?? '';
    $locLabel = $pickupLocation === 'office' ? 'sa office (warehouse)' : 'sa supplier location';

    $readyTitle = "Item Ready for Pickup";
    $readyMessage = "Purchase Order {$poNumber2}" . ($reference2 ? " ({$reference2})" : "") . " ay ready na for pickup {$locLabel}.";

    sendNotification($conn, ROLE_WAREHOUSE, POSITION_WAREHOUSESTAFF, null, $readyTitle, $readyMessage, BASE_URL . '/warehousestaff');
}

echo json_encode([
    'success' => true,
    'message' => 'Step updated successfully.',
    'step' => $newStep,
    'step_label' => $stepLabel,
    'pickup_location' => $pickupLocation,
    'pickup_driver_name' => $pickupDriver,
    'pickup_plate_number' => $pickupPlate,
]);