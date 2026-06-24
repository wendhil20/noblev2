<?php
// warehouse-po-update.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$poId        = (int) ($input['po_id'] ?? 0);
$custName    = trim($input['cust_name'] ?? '');
$vendorName  = trim($input['vendor_name'] ?? '');
$vendorCo    = trim($input['vendor_company'] ?? '');
$vendorAddr  = trim($input['vendor_address'] ?? '');
$vendorPhone = trim($input['vendor_phone'] ?? '');
$vendorEmail = trim($input['vendor_email'] ?? '');
$note        = trim($input['note'] ?? '');
$discountPct = (float) ($input['discount_pct'] ?? 0);
$addlDisc    = (float) ($input['addl_discount'] ?? 0);
$downPayment = (float) ($input['down_payment'] ?? 0);
$dpDate      = $input['dp_date'] ?: null;
$unitPrices  = $input['unit_prices'] ?? [];

if (!$poId) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}

// Recompute totals
$subtotal = 0;
foreach ($unitPrices as $item) {
    $subtotal += (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0);
}

$afterDiscount = $subtotal - ($subtotal * $discountPct / 100);
$finalAmount   = $afterDiscount - $addlDisc;
$balance       = $finalAmount - $downPayment;

// Update main PO record
$stmt = $conn->prepare("
    UPDATE noblepurchaseorder SET
        cust_name       = ?,
        vendor_name     = ?,
        vendor_company  = ?,
        vendor_address  = ?,
        vendor_phone    = ?,
        vendor_email    = ?,
        note            = ?,
        discount_pct    = ?,
        addl_discount   = ?,
        subtotal        = ?,
        final_amount    = ?,
        down_payment    = ?,
        dp_date         = ?,
        current_balance = ?
    WHERE id = ?
");
$stmt->bind_param(
    "sssssssddddddsi",
    $custName,
    $vendorName,
    $vendorCo,
    $vendorAddr,
    $vendorPhone,
    $vendorEmail,
    $note,
    $discountPct,
    $addlDisc,
    $subtotal,
    $finalAmount,
    $downPayment,
    $dpDate,
    $balance,
    $poId
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    $stmt->close();
    exit;
}
$stmt->close();

// Update each item's unit_price and line_total
foreach ($unitPrices as $item) {
    $itemId    = (int) ($item['item_id'] ?? 0);
    $price     = (float) ($item['unit_price'] ?? 0);
    $qty       = (float) ($item['quantity'] ?? 0);
    $lineTotal = $price * $qty;

    if (!$itemId) continue;

    $iStmt = $conn->prepare("
        UPDATE noblepurchaseorderitems 
        SET unit_price = ?, line_total = ?
        WHERE id = ?
    ");
    $iStmt->bind_param("ddi", $price, $lineTotal, $itemId);
    $iStmt->execute();
    $iStmt->close();
}

echo json_encode(['success' => true]);