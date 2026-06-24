<?php
// warehouse-po-save.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$userId = $_SESSION['account_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$orderId = (int) ($input['order_id'] ?? 0);
$groups = $input['groups'] ?? [];

if (!$orderId || empty($groups)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// ✅ Detect kung replacement ang assignment ng order na ito
$poType = 'normal';
$typeStmt = $conn->prepare("SELECT type FROM nobleorderassignment WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$typeStmt->bind_param("i", $orderId);
$typeStmt->execute();
$typeRow = $typeStmt->get_result()->fetch_assoc();
$typeStmt->close();
if (!empty($typeRow['type']) && $typeRow['type'] === 'replacement') {
    $poType = 'replacement';
}

// Check if PO already exists for this order
$checkStmt = $conn->prepare("SELECT id FROM noblepurchaseorder WHERE order_id = ?");
$checkStmt->bind_param("i", $orderId);
$checkStmt->execute();
$existingPOs = $checkStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$checkStmt->close();

if (!empty($existingPOs) && $poType !== 'replacement') {
    // Normal order pero may PO na — block (dating behavior)
    echo json_encode(['success' => false, 'message' => 'A PO for this order already exists.']);
    exit;
}

// Get delivery method once
$dmStmt = $conn->prepare("SELECT delivery_method FROM noblepaidproductlist WHERE id = ? LIMIT 1");
$dmStmt->bind_param("i", $orderId);
$dmStmt->execute();
$dmRow = $dmStmt->get_result()->fetch_assoc();
$dmStmt->close();
$deliveryMethod = $dmRow['delivery_method'] ?? 'delivery';

$conn->begin_transaction();

try {
    // ✅ Kung replacement at may existing PO(s) na para sa order na ito,
    // i-RESET/CLEAR muna ang lumang PO, PO items, at tracking records
    if ($poType === 'replacement' && !empty($existingPOs)) {
        foreach ($existingPOs as $oldPO) {
            $oldPoId = $oldPO['id'];

            // Delete old PO items
            $delItems = $conn->prepare("DELETE FROM noblepurchaseorderitems WHERE po_id = ?");
            $delItems->bind_param("i", $oldPoId);
            $delItems->execute();
            $delItems->close();

            // Delete old tracking
            $delTrack = $conn->prepare("DELETE FROM nobleordertracking WHERE po_id = ?");
            $delTrack->bind_param("i", $oldPoId);
            $delTrack->execute();
            $delTrack->close();

            // Delete old receiving receiver assignment (kung meron)
            $delReceiver = $conn->prepare("DELETE FROM noblereceivingreceiver WHERE po_id = ?");
            $delReceiver->bind_param("i", $oldPoId);
            $delReceiver->execute();
            $delReceiver->close();
        }

        // Delete old PO records mismo
        $delPO = $conn->prepare("DELETE FROM noblepurchaseorder WHERE order_id = ?");
        $delPO->bind_param("i", $orderId);
        $delPO->execute();
        $delPO->close();
    }

    // ✅✅✅ AUTHORITATIVE PO NUMBER GENERATION (FIX) ✅✅✅
    // Kunin ang pinakahuling actual sequence number ngayong taon, hindi basta bilang ng rows.
    // Gamit ang FOR UPDATE para ma-lock ang mga matching rows habang nasa transaction tayo,
    // kaya kung dalawang request ang sabay-sabay tumatakbo, sequential silang
    // makakakuha ng tama at hindi nagbabanggaan ang po_number.
    $year = date('Y');
    $likePattern = 'NHPO-' . $year . '-%';

    $seqStmt = $conn->prepare("
        SELECT po_number 
        FROM noblepurchaseorder 
        WHERE po_number LIKE ? 
        FOR UPDATE
    ");
    $seqStmt->bind_param("s", $likePattern);
    $seqStmt->execute();
    $existingNumbers = $seqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $seqStmt->close();

    $maxSeq = 0;
    $pattern = '/^NHPO-' . preg_quote($year, '/') . '-(\d+)/';
    foreach ($existingNumbers as $row) {
        if (preg_match($pattern, $row['po_number'], $m)) {
            $maxSeq = max($maxSeq, (int) $m[1]);
        }
    }

    // Itong serverPoNumber na ito ang TUNAY na susundan, hindi yung galing sa client/JS.
    $serverPoNumber = 'NHPO-' . $year . '-' . str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);
    // ✅✅✅ END FIX ✅✅✅

    foreach ($groups as $group) {
        $supplierId = !empty($group['supplier_id']) ? (int) $group['supplier_id'] : null;

        // ✅ Gamitin ang server-generated base number + suffix na galing sa client
        // (yung suffix letter — A, B, C — ay okay lang manggaling sa client dahil
        // ito lang ang nagdidetermine kung anong grupo/supplier sa loob ng PARESONG batch ito)
        $poSuffix = trim($group['po_suffix'] ?? '');
        $poNumber = $poSuffix !== '' ? ($serverPoNumber . '-' . $poSuffix) : $serverPoNumber;

        $preparedBy = trim($group['prepared_by'] ?? '');
        $preparedBySig = trim($group['prepared_by_signature'] ?? '');

        if (empty($preparedBySig)) {
            $sigFetch = $conn->prepare("
                SELECT ns.image_path 
                FROM noblerole nr
                JOIN noblesignature ns ON ns.id = nr.active_signature_id
                WHERE nr.id = ? AND ns.is_active = 1
                LIMIT 1
            ");
            $sigFetch->bind_param("i", $userId);
            $sigFetch->execute();
            $sigRow = $sigFetch->get_result()->fetch_assoc();
            $sigFetch->close();
            $preparedBySig = $sigRow['image_path'] ?? '';
        }
        $vendorName = trim($group['vendor_name'] ?? '');
        $vendorCompany = trim($group['vendor_company'] ?? '');
        $vendorAddress = trim($group['vendor_address'] ?? '');
        $vendorPhone = trim($group['vendor_phone'] ?? '');
        $vendorEmail = trim($group['vendor_email'] ?? '');
        $vendorStart = $group['vendor_start_date'] ?: null;
        $custName = trim($group['cust_name'] ?? '');
        $custCompany = trim($group['cust_company'] ?? '');
        $custAddress = trim($group['cust_address'] ?? '');
        $custPhone = trim($group['cust_phone'] ?? '');
        $custEmail = trim($group['cust_email'] ?? '');
        $custStart = $group['cust_start_date'] ?: null;
        $note = trim($group['note'] ?? '');
        $discountPct = (float) ($group['discount_pct'] ?? 0);
        $addlDiscount = (float) ($group['addl_discount'] ?? 0);
        $downPayment = (float) ($group['down_payment'] ?? 0);
        $dpDate = $group['dp_date'] ?: null;
        $unitPrices = $group['unit_prices'] ?? [];

        // (Hindi na kailangan i-check ang $poNumber dito kasi server-generated na siya,
        // pero panatilihin natin ang check sa suffix kung gusto mong sigurado pa rin.)
        if (!$poNumber) {
            throw new Exception('Missing PO number for one of the groups.');
        }

        // Compute totals
        $subtotal = 0;
        foreach ($unitPrices as $item) {
            $subtotal += (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
        }
        $afterDiscount = $subtotal - ($subtotal * $discountPct / 100);
        $finalAmount = $afterDiscount - $addlDiscount;
        $balance = $finalAmount - $downPayment;

        $stmt = $conn->prepare("
            INSERT INTO noblepurchaseorder 
            (order_id, supplier_id, po_number, po_suffix, po_type, po_date,
             prepared_by, prepared_by_signature,
             noted_by, noted_by_signature,
             vendor_name, vendor_company, vendor_address, vendor_phone, vendor_email, vendor_start_date,
             cust_name, cust_company, cust_address, cust_phone, cust_email, cust_start_date,
             note, discount_pct, addl_discount, subtotal, final_amount, down_payment, dp_date, current_balance,
             created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->bind_param(
            "iissssssssssssssssssdddddsd",  // 27 characters
            $orderId,        // i
            $supplierId,     // i
            $poNumber,       // s
            $poSuffix,       // s
            $poType,         // s
            $preparedBy,     // s
            $preparedBySig,  // s
            $vendorName,     // s
            $vendorCompany,  // s
            $vendorAddress,  // s
            $vendorPhone,    // s
            $vendorEmail,    // s
            $vendorStart,    // s
            $custName,       // s
            $custCompany,    // s
            $custAddress,    // s
            $custPhone,      // s
            $custEmail,      // s
            $custStart,      // s
            $note,           // s
            $discountPct,    // d
            $addlDiscount,   // d
            $subtotal,       // d
            $finalAmount,    // d
            $downPayment,    // d
            $dpDate,         // s
            $balance         // d
        );

        if (!$stmt->execute()) {
            throw new Exception('DB error inserting PO: ' . $conn->error);
        }
        $poId = $conn->insert_id;
        $stmt->close();

        // Insert PO items
        foreach ($unitPrices as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $lineTotal = $qty * $price;

            if (!$itemId)
                continue;

            $iStmt = $conn->prepare("
                INSERT INTO noblepurchaseorderitems (po_id, paid_item_id, unit_price, line_total)
                VALUES (?, ?, ?, ?)
            ");
            $iStmt->bind_param("iidd", $poId, $itemId, $price, $lineTotal);
            $iStmt->execute();
            $iStmt->close();
        }

        // ✅ INSERT TRACKING per PO (fresh start, current_step = 0)
        $trackCheck = $conn->prepare("SELECT id FROM nobleordertracking WHERE po_id = ? LIMIT 1");
        $trackCheck->bind_param("i", $poId);
        $trackCheck->execute();
        $existingTrack = $trackCheck->get_result()->fetch_assoc();
        $trackCheck->close();

        if (!$existingTrack) {
            $trackStmt = $conn->prepare("
                INSERT INTO nobleordertracking (order_id, po_id, delivery_method, current_step, expected_delivery_from, expected_delivery_to, created_at)
                VALUES (?, ?, ?, 0, NULL, NULL, NOW())
            ");
            $trackStmt->bind_param("iis", $orderId, $poId, $deliveryMethod);
            $trackStmt->execute();
            $trackStmt->close();
        }

    } // end foreach groups

    // Notify accounting staff
    $notifTitle = ($poType === 'replacement' ? '[Replacement] ' : '') . 'New Purchase Order(s) Generated';
    $notifMessage = 'Purchase Order(s) for order #' . $orderId . ' have been generated by warehouse staff and are ready for review.';
    $notifLink = BASE_URL . '/accountantstaff';
    $forRole = ROLE_ACCOUNTING;
    $forPosition = POSITION_STAFF;

    $notifStmt = $conn->prepare("
        INSERT INTO noblenotification (for_role, for_position, title, message, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $notifStmt->bind_param("sssss", $forRole, $forPosition, $notifTitle, $notifMessage, $notifLink);
    $notifStmt->execute();
    $notifStmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'po_number' => $serverPoNumber]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}