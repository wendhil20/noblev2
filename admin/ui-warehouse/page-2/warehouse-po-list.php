<?php
// warehouse-po-list.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
if (!$orderId) {
    header('Location: ' . BASE_URL . '/warehousestaff');
    exit;
}

// Fetch order reference for display
$stmt = $conn->prepare("SELECT nhccreference, contact_name FROM noblepaidproductlist WHERE id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: ' . BASE_URL . '/warehousestaff');
    exit;
}

// Fetch all POs for this order, with supplier name and signed count
$stmt = $conn->prepare("
    SELECT 
        po.id, po.po_number, po.po_suffix, po.po_date, po.final_amount,
        po.prepared_by_signature, po.noted_by_signature, po.approved_by_signature,
        po.acknowledged_by_signature, po.received_by_signature,
        s.supname AS supplier_name
    FROM noblepurchaseorder po
    LEFT JOIN noblecompanysupplier s ON s.id = po.supplier_id
    WHERE po.order_id = ?
    ORDER BY po.po_suffix ASC
");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$pos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($pos)) {
    header('Location: ' . BASE_URL . '/warehousestaffpo?order_id=' . $orderId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen p-6">

        <!-- Top bar -->
        <div class="flex items-center gap-3 mb-6">
            <a href="<?= BASE_URL ?>/warehousestaff"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Orders
            </a>
            <span class="text-slate-300">/</span>
            <span class="text-sm text-slate-700 font-medium">Purchase Orders</span>
        </div>

        <div class="mb-6">
            <h1 class="text-lg font-bold text-slate-800">
                Purchase Orders for <?= htmlspecialchars($order['nhccreference'] ?? '—') ?>
            </h1>
            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($order['contact_name']) ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($pos as $po):
                $signedCount = array_sum([
                    !empty($po['prepared_by_signature']) ? 1 : 0,
                    !empty($po['noted_by_signature']) ? 1 : 0,
                    !empty($po['approved_by_signature']) ? 1 : 0,
                    !empty($po['acknowledged_by_signature']) ? 1 : 0,
                    !empty($po['received_by_signature']) ? 1 : 0,
                ]);
                $allSigned = $signedCount === 5;
                ?>
                <a href="<?= BASE_URL ?>/warehouse-poview?po_id=<?= $po['id'] ?>"
                    class="bg-white rounded-xl border border-slate-200 p-4 hover:shadow-md hover:border-indigo-300 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono text-sm font-bold text-slate-800">
                            <?= htmlspecialchars($po['po_number']) ?>
                        </span>
                        <?php if ($allSigned): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                All Signed
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                <?= $signedCount ?>/5 Signed
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-700 font-medium mb-1">
                        <?= htmlspecialchars($po['supplier_name'] ?? 'No Supplier') ?>
                    </p>
                    <p class="text-xs text-slate-400 mb-2">
                        <?= date('M d, Y', strtotime($po['po_date'])) ?>
                    </p>
                    <p class="text-sm font-bold text-slate-800">
                        ₱<?= number_format($po['final_amount'], 2) ?>
                    </p>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

</body>

</html>