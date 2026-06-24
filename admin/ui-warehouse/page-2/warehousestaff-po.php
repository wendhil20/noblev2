<?php
// warehouse-po.php
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

// ✅✅✅ FIX: Preview-only PO number — based sa pinakamataas na actual sequence,
// hindi bilang ng rows (na nagdodoble dahil may suffix gaya ng -A, -B, -C).
// NOTE: Ito'y PREVIEW lang. Ang TUNAY/authoritative na po_number ay kino-compute
// muli (gamit ang FOR UPDATE lock) sa loob ng warehouse-po-save.php bago mag-insert,
// kaya kahit magbago ang state ng DB sa pagitan ng pag-open ng page na ito at ng
// pag-Save, hindi magkakaroon ng duplicate sa database.
$year = date('Y');
$poNumStmt = $conn->prepare("SELECT po_number FROM noblepurchaseorder WHERE YEAR(created_at) = ?");
$poNumStmt->bind_param("i", $year);
$poNumStmt->execute();
$existingPoNumbers = $poNumStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$poNumStmt->close();

$maxSeq = 0;
$pattern = '/^NHPO-' . preg_quote((string) $year, '/') . '-(\d+)/';
foreach ($existingPoNumbers as $row) {
    if (preg_match($pattern, $row['po_number'], $m)) {
        $maxSeq = max($maxSeq, (int) $m[1]);
    }
}
$poNumber = 'NHPO-' . $year . '-' . str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);
// ✅✅✅ END FIX ✅✅✅

$poDate = date('m/d/Y');

$stmt = $conn->prepare("SELECT ppl.id FROM noblepaidproductlist ppl WHERE ppl.id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: ' . BASE_URL . '/warehousestaff');
    exit;
}

// Detect kung replacement ang assignment
$isReplacement = false;
$repStmt = $conn->prepare("SELECT type FROM nobleorderassignment WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$repStmt->bind_param("i", $orderId);
$repStmt->execute();
$repRow = $repStmt->get_result()->fetch_assoc();
$repStmt->close();
if (!empty($repRow['type']) && $repRow['type'] === 'replacement') {
    $isReplacement = true;
}

// Check if PO already exists — skip redirect kung replacement
$checkStmt = $conn->prepare("SELECT id FROM noblepurchaseorder WHERE order_id = ? LIMIT 1");
$checkStmt->bind_param("i", $orderId);
$checkStmt->execute();
$existingPO = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existingPO && !$isReplacement) {
    header('Location: ' . BASE_URL . '/warehouse-polist?order_id=' . $orderId);
    exit;
}

$itemsStmt = $conn->prepare("
    SELECT 
        pi.id,
        pi.product_name,
        pi.colorname,
        pi.sizename,
        pi.unit,
        pi.quantity,
        p.id AS product_id
    FROM noblepaidproductitems pi
    LEFT JOIN nobleproduct p ON pi.product_id = p.id
    WHERE pi.order_id = ?
    ORDER BY pi.id ASC
");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

$suppliers = [];
$supRes = $conn->query("SELECT id, supname, supaddress, suppersonname, suppersonnumber, suppersonemail FROM noblecompanysupplier ORDER BY supname ASC");
while ($row = $supRes->fetch_assoc()) {
    $suppliers[$row['id']] = $row;
}

$linksStmt = $conn->query("
    SELECT psl.product_id, psl.supplier_id, psl.link_type, s.supname
    FROM nobleproductsupplierlink psl
    JOIN noblecompanysupplier s ON s.id = psl.supplier_id
    ORDER BY psl.product_id ASC, psl.link_type ASC
");
$productSuppliers = [];
while ($row = $linksStmt->fetch_assoc()) {
    $pid = $row['product_id'];
    if (!isset($productSuppliers[$pid])) {
        $productSuppliers[$pid] = ['primary' => [], 'secondary' => []];
    }
    $productSuppliers[$pid][$row['link_type']][] = [
        'supplier_id' => $row['supplier_id'],
        'supname' => $row['supname'],
    ];
}

$missingSupplier = false;
$itemsForJs = [];
foreach ($items as $item) {
    $pid = $item['product_id'];
    $links = $productSuppliers[$pid] ?? ['primary' => [], 'secondary' => []];
    $allOptions = array_merge($links['primary'], $links['secondary']);

    if (empty($allOptions)) {
        $missingSupplier = true;
    }

    $defaultSupplierId = $links['primary'][0]['supplier_id'] ?? ($links['secondary'][0]['supplier_id'] ?? null);

    $itemsForJs[] = [
        'id' => (int) $item['id'],
        'product_name' => $item['product_name'],
        'colorname' => $item['colorname'],
        'sizename' => $item['sizename'],
        'unit' => $item['unit'],
        'quantity' => (float) $item['quantity'],
        'primary' => $links['primary'],
        'secondary' => $links['secondary'],
        'default_supplier_id' => $defaultSupplierId,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Purchase Order</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            .ml-60 {
                margin-left: 0 !important;
            }

            body {
                background: white !important;
            }

            .po-page {
                display: none !important;
            }

            .po-page.print-active {
                display: block !important;
            }
        }
    </style>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Top bar -->
        <div class="flex items-center justify-between mb-6 no-print">
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/warehousestaff"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Orders
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-sm text-slate-700 font-medium">Generate Purchase Order</span>
            </div>

            <?php if (!$missingSupplier): ?>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-2 py-1.5 shadow-sm">
                        <button onclick="prevPage()" id="prevBtn"
                            class="p-1.5 rounded-md text-slate-500 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <span class="text-xs font-medium text-slate-600" id="pageIndicator">PO 1 of 1</span>
                        <button onclick="nextPage()" id="nextBtn"
                            class="p-1.5 rounded-md text-slate-500 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    <button onclick="window.print()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                    <button onclick="saveAllPO(event)"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Save All POs
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($missingSupplier): ?>
            <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-8 max-w-2xl mx-auto text-center">
                <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-slate-800 mb-2">Cannot Generate Purchase Order</h2>
                <p class="text-sm text-slate-500 mb-4">
                    Isa o higit pang produkto sa order na ito ay walang naka-assign na supplier (primary o secondary).
                    Pakitiyak na lahat ng produkto ay may supplier sa
                    <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">nobleproductsupplierlink</code>
                    bago mag-generate ng Purchase Order.
                </p>
                <div class="text-left bg-slate-50 rounded-lg p-4 mb-4">
                    <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">Mga produktong walang
                        supplier:</p>
                    <ul class="text-sm text-slate-700 space-y-1 list-disc list-inside">
                        <?php foreach ($itemsForJs as $it): ?>
                            <?php if (empty($it['primary']) && empty($it['secondary'])): ?>
                                <li>
                                    <?= htmlspecialchars($it['product_name']) ?>
                                    <?php if ($it['colorname']): ?> — <?= htmlspecialchars($it['colorname']) ?><?php endif; ?>
                                    <?php if ($it['sizename']): ?>, <?= htmlspecialchars($it['sizename']) ?><?php endif; ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a href="<?= BASE_URL ?>/warehousestaff"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Back to Orders
                </a>
            </div>
        <?php else: ?>

            <!-- Step 1: Per-item Supplier Assignment -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 max-w-5xl mx-auto mb-6 no-print">
                <h2 class="text-sm font-bold text-slate-800 mb-1">Step 1: Assign Supplier per Item</h2>
                <p class="text-xs text-slate-400 mb-4">
                    Default ang primary supplier. Kung walang stock, piliin ang secondary supplier — automatic
                    magre-group ang Purchase Order sa ibaba.
                </p>
                <table class="w-full text-sm border border-slate-200 rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="px-3 py-2.5 text-left font-medium text-xs w-8">No.</th>
                            <th class="px-3 py-2.5 text-left font-medium text-xs">Product Description</th>
                            <th class="px-3 py-2.5 text-center font-medium text-xs w-20">Quantity</th>
                            <th class="px-3 py-2.5 text-center font-medium text-xs w-20">Unit</th>
                            <th class="px-3 py-2.5 text-left font-medium text-xs w-64">Supplier</th>
                        </tr>
                    </thead>
                    <tbody id="assign-tbody">
                        <?php foreach ($itemsForJs as $idx => $it): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-500 text-center"><?= $idx + 1 ?></td>
                                <td class="px-3 py-2 text-slate-800">
                                    <?= htmlspecialchars($it['product_name']) ?>
                                    <?php if ($it['colorname']): ?>
                                        <span class="text-xs text-slate-500"> — <?= htmlspecialchars($it['colorname']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($it['sizename']): ?>
                                        <span class="text-xs text-slate-500">, <?= htmlspecialchars($it['sizename']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-center text-slate-700"><?= $it['quantity'] ?></td>
                                <td class="px-3 py-2 text-center text-slate-700"><?= htmlspecialchars($it['unit'] ?? '—') ?>
                                </td>
                                <td class="px-3 py-2">
                                    <select
                                        class="item-supplier-select w-full border border-slate-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        data-item-id="<?= $it['id'] ?>" onchange="onSupplierChange()">
                                        <?php if (!empty($it['primary'])): ?>
                                            <optgroup label="Primary">
                                                <?php foreach ($it['primary'] as $p): ?>
                                                    <option value="<?= $p['supplier_id'] ?>"
                                                        <?= $p['supplier_id'] == $it['default_supplier_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['supname']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if (!empty($it['secondary'])): ?>
                                            <optgroup label="Secondary (alternative)">
                                                <?php foreach ($it['secondary'] as $s): ?>
                                                    <option value="<?= $s['supplier_id'] ?>"
                                                        <?= $s['supplier_id'] == $it['default_supplier_id'] && empty($it['primary']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($s['supname']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Carousel container -->
            <div id="carousel-container"></div>

        <?php endif; ?>

    </div>

    <template id="po-page-template">
        <div class="po-page bg-white rounded-xl shadow-sm border border-slate-200 p-8 max-w-5xl mx-auto mb-4">

            <div class="flex justify-between items-start mb-6 border-b border-slate-200 pb-5">
                <div class="flex items-center gap-4">
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo" class="h-14">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 uppercase tracking-wide">Noblehome Construction
                            Corporation</h2>
                        <p class="text-xs text-slate-500">1181 MC Premiere Bidg, EDSA Bidg, EDSA Balintawak Quezon City
                        </p>
                        <p class="text-xs text-slate-500">noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell No.
                            0968-591-6544</p>
                    </div>
                </div>
                <div class="text-right">
                    <h1 class="text-xl font-bold text-slate-800 uppercase tracking-widest mb-3">Purchase Order</h1>
                    <table class="border border-slate-300 text-xs ml-auto" style="min-width:260px;">
                        <thead>
                            <tr class="bg-slate-700 text-white">
                                <th
                                    class="px-4 py-2 font-semibold uppercase tracking-wider border-r border-slate-500 text-center">
                                    P.O. Number</th>
                                <th class="px-4 py-2 font-semibold uppercase tracking-wider text-center">P.O. Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-white">
                                <td
                                    class="px-4 py-2 font-bold text-slate-800 border-r border-slate-200 text-center po-number-cell">
                                </td>
                                <td class="px-4 py-2 font-bold text-slate-800 text-center po-date-cell"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-2 rounded-lg overflow-hidden mb-6 text-sm gap-4">

                <!-- CUSTOMER -->
                <div class="border border-slate-300">
                    <div class="bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider px-4 py-2">
                        Customer</div>
                    <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Name</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="cust_name w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Company Name</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="cust_company w-full px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"
                                value="Noblehome Construction Corporation" readonly></div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Address</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="cust_address w-full px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"
                                value="1181 MC Premiere Bidg, EDSA Bidg, EDSA Balintawak Quezon City" readonly></div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Phone No.</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="cust_phone w-full px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"
                                value="02-88221295 / 0968-591-6544" readonly></div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Email Address</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="cust_email w-full px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"
                                value="noblehomeconsl.ph@gmail.com" readonly></div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                            Start Date</div>
                        <div><input type="date"
                                class="cust_start_date w-full px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"
                                readonly></div>
                    </div>
                </div>

                <!-- VENDOR -->
                <div class="border border-slate-300">
                    <div class="bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider px-4 py-2">Vendor
                        / Sales Person</div>
                    <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Supplier</div>
                        <div
                            class="border-b border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800 supplier-name-cell">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Name / Sales Person</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="vendor_name w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Company Name</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="vendor_company w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Address</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="vendor_address w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Phone No.</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="vendor_phone w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Email Address</div>
                        <div class="border-b border-slate-200"><input type="text"
                                class="vendor_email w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-[130px_1fr]">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                            Start Date</div>
                        <div><input type="date"
                                class="vendor_start_date w-full px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"
                                readonly></div>
                    </div>
                </div>

            </div>

            <!-- Items Table -->
            <div class="mb-6">
                <table class="w-full text-sm border border-slate-200 rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="px-3 py-2.5 text-left font-medium text-xs w-8">No.</th>
                            <th class="px-3 py-2.5 text-left font-medium text-xs">Product Description</th>
                            <th class="px-3 py-2.5 text-center font-medium text-xs w-20">Quantity</th>
                            <th class="px-3 py-2.5 text-center font-medium text-xs w-20">Unit</th>
                            <th class="px-3 py-2.5 text-right font-medium text-xs w-32">Unit Price (PHP)</th>
                            <th class="px-3 py-2.5 text-right font-medium text-xs w-32">Amount (PHP)</th>
                        </tr>
                    </thead>
                    <tbody class="items-tbody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6"
                                class="px-3 py-2 text-center text-xs text-slate-400 italic border-t border-slate-200">
                                ***** NOTHING FOLLOWS *****</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Totals + Notes -->
            <div class="grid grid-cols-2 gap-6 mb-8">
                <div class="space-y-3">
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">Note:</label>
                        <textarea
                            class="po_note w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"
                            rows="3"></textarea>
                    </div>
                </div>
                <div class="border border-slate-200 rounded-lg overflow-hidden text-sm">
                    <table class="w-full">
                        <tbody>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500 font-medium">Total Amount:</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-800">PHP <span
                                        class="total-amount">—</span></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">12% VAT Inclusive:</td>
                                <td class="px-4 py-2 text-right text-slate-700">PHP <span class="vat-amount">—</span>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Discount (%):</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number"
                                        class="discount-pct w-20 border border-slate-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        min="0" max="100" step="0.01" placeholder="0">
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Amount After Discount:</td>
                                <td class="px-4 py-2 text-right text-slate-700">PHP <span
                                        class="after-discount">—</span></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Add'l Discount:</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number"
                                        class="addl-discount w-28 border border-slate-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        min="0" step="0.01" placeholder="0">
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200 bg-amber-50">
                                <td class="px-4 py-2 text-slate-700 font-semibold">Final Amount:</td>
                                <td class="px-4 py-2 text-right font-bold text-slate-900">PHP <span
                                        class="final-amount">—</span></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Down Payment:</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number"
                                        class="down-payment w-28 border border-slate-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        min="0" step="0.01" placeholder="0">
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Down Payment Date:</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="date"
                                        class="dp-date border border-slate-200 rounded px-2 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                </td>
                            </tr>
                            <tr class="bg-amber-50">
                                <td class="px-4 py-2 text-slate-700 font-semibold">Current Balance:</td>
                                <td class="px-4 py-2 text-right font-bold text-slate-900">PHP <span
                                        class="current-balance">—</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Signature Section -->
            <div class="border border-slate-200 rounded-lg overflow-hidden mt-6">
                <div class="grid grid-cols-5 border-b border-slate-200">
                    <div
                        class="px-3 py-2 bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider text-center border-r border-slate-500">
                        Prepared By</div>
                    <div
                        class="px-3 py-2 bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider text-center border-r border-slate-500">
                        Noted By:</div>
                    <div
                        class="px-3 py-2 bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider text-center border-r border-slate-500">
                        Approved By</div>
                    <div
                        class="px-3 py-2 bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider text-center border-r border-slate-500">
                        Acknowledged By:</div>
                    <div
                        class="px-3 py-2 bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider text-center">
                        Received By</div>
                </div>
                <div class="grid grid-cols-5 border-b border-slate-100">
                    <div
                        class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px] prepared-sig-box">
                    </div>
                    <div class="px-3 py-6 border-r border-slate-200"></div>
                    <div class="px-3 py-6 border-r border-slate-200"></div>
                    <div class="px-3 py-6 border-r border-slate-200"></div>
                    <div class="px-3 py-6"></div>
                </div>
                <div class="grid grid-cols-5">
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800 prepared-by-name">—</p>
                        <p class="text-xs text-slate-500">Warehouse Staff</p>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800 noted-by-name">—</p>
                        <p class="text-xs text-slate-500">Staff</p>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800 approved-by-name">—</p>
                        <p class="text-xs text-slate-500">General Manager</p>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800 acknowledged-by-name">—</p>
                        <p class="text-xs text-slate-500">Acknowledged</p>
                    </div>
                    <div class="px-3 py-2 text-center">
                        <p class="text-xs font-semibold text-slate-800 received-by-name">—</p>
                        <p class="text-xs text-slate-500">Accounting Head</p>
                    </div>
                </div>
            </div>

        </div>
    </template>

    <template id="item-row-template">
        <tr class="border-b border-slate-100 hover:bg-slate-50">
            <td class="px-3 py-2 text-slate-500 text-center row-num"></td>
            <td class="px-3 py-2 text-slate-800 row-desc"></td>
            <td class="px-3 py-2 text-center text-slate-700 row-qty"></td>
            <td class="px-3 py-2 text-center text-slate-700 row-unit"></td>
            <td class="px-3 py-2 text-right">
                <input type="number" step="0.01" min="0"
                    class="unit-price w-full border border-slate-200 rounded px-2 py-1 text-right text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    placeholder="Optional">
            </td>
            <td class="px-3 py-2 text-right text-slate-800 font-medium row-amount">—</td>
        </tr>
    </template>

    <script>
        const ITEMS = <?= json_encode($itemsForJs) ?>;
        const SUPPLIERS = <?= json_encode(array_values($suppliers)) ?>;
        const BASE_PO_NUMBER = '<?= $poNumber ?>'; // preview lang; server-side ulit kino-confirm sa save
        const SUFFIX_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const PO_DATE = '<?= $poDate ?>';

        let _preparedByName = '';
        let _preparedBySig = '';
        let currentPage = 0;
        let currentGroups = [];

        // BAGO:
        const itemSupplierMap = {};
        const savedSelections = JSON.parse(localStorage.getItem('po_supplier_map_<?= $orderId ?>') || '{}');
        ITEMS.forEach(it => {
            itemSupplierMap[it.id] = savedSelections[it.id] ?? it.default_supplier_id;
        });
        // BAGO:
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date().toISOString().split('T')[0];

            // ✅ Re-apply saved selections to dropdowns on load
            document.querySelectorAll('.item-supplier-select').forEach(sel => {
                const saved = itemSupplierMap[sel.dataset.itemId];
                if (saved) sel.value = saved;
            });

            fetch('<?= BASE_URL ?>/warehouse-backendpo-getuser')
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        _preparedByName = res.name;
                        if (res.signature_path) _preparedBySig = res.signature_path;
                    } else {
                        _preparedByName = '<?= strtoupper(addslashes($_SESSION["username"])) ?>';
                    }
                })
                .catch(() => {
                    _preparedByName = '<?= strtoupper(addslashes($_SESSION["username"])) ?>';
                })
                .finally(() => {
                    rebuildCarousel(today);
                });
        });

        // BAGO:
        function onSupplierChange() {
            document.querySelectorAll('.item-supplier-select').forEach(sel => {
                itemSupplierMap[sel.dataset.itemId] = sel.value;
            });

            // ✅ Save to localStorage
            localStorage.setItem('po_supplier_map_<?= $orderId ?>', JSON.stringify(itemSupplierMap));

            const today = new Date().toISOString().split('T')[0];
            rebuildCarousel(today, true);

            document.querySelectorAll('.item-supplier-select').forEach(sel => {
                const savedVal = itemSupplierMap[sel.dataset.itemId];
                if (savedVal) sel.value = savedVal;
            });
        }

        function findSupplierById(id) {
            return SUPPLIERS.find(s => String(s.id) === String(id)) || null;
        }

        function rebuildCarousel(today, preserveValues = false) {
            const preserved = { items: {}, groups: {} };
            if (preserveValues) {
                document.querySelectorAll('.po-page').forEach(page => {
                    const sid = page.dataset.supplierId;
                    page.querySelectorAll('.unit-price').forEach(input => {
                        preserved.items[input.dataset.itemId] = input.value;
                    });
                    preserved.groups[sid] = {
                        cust_name: page.querySelector('.cust_name')?.value || '',
                        vendor_name: page.querySelector('.vendor_name')?.value || '',
                        vendor_company: page.querySelector('.vendor_company')?.value || '',
                        vendor_address: page.querySelector('.vendor_address')?.value || '',
                        vendor_phone: page.querySelector('.vendor_phone')?.value || '',
                        vendor_email: page.querySelector('.vendor_email')?.value || '',
                        note: page.querySelector('.po_note')?.value || '',
                        discount_pct: page.querySelector('.discount-pct')?.value || '',
                        addl_discount: page.querySelector('.addl-discount')?.value || '',
                        down_payment: page.querySelector('.down-payment')?.value || '',
                        dp_date: page.querySelector('.dp-date')?.value || '',
                    };
                });
            }

            const groupsMap = {};
            ITEMS.forEach(it => {
                const sid = itemSupplierMap[it.id];
                if (!groupsMap[sid]) groupsMap[sid] = { supplier_id: sid, items: [] };
                groupsMap[sid].items.push(it);
            });

            currentGroups = Object.values(groupsMap);
            currentGroups.forEach(g => { g.supplierInfo = findSupplierById(g.supplier_id); });
            currentGroups.sort((a, b) => {
                const an = a.supplierInfo?.supname || '';
                const bn = b.supplierInfo?.supname || '';
                return an.localeCompare(bn);
            });
            currentGroups.forEach((g, idx) => {
                g.suffix = SUFFIX_LETTERS[idx] || ('X' + idx);
                g.po_number = BASE_PO_NUMBER + '-' + g.suffix; // preview lang
            });

            const container = document.getElementById('carousel-container');
            container.innerHTML = '';
            const pageTpl = document.getElementById('po-page-template');
            const rowTpl = document.getElementById('item-row-template');

            currentGroups.forEach((g, gIdx) => {
                const pageNode = pageTpl.content.cloneNode(true);
                const pageEl = pageNode.querySelector('.po-page');
                pageEl.dataset.supplierId = g.supplier_id;
                pageEl.id = 'po-page-' + gIdx;
                if (gIdx !== 0) pageEl.classList.add('hidden');
                else pageEl.classList.add('print-active');

                pageEl.querySelector('.po-number-cell').textContent = g.po_number;
                pageEl.querySelector('.po-date-cell').textContent = PO_DATE;
                pageEl.querySelector('.supplier-name-cell').textContent = g.supplierInfo?.supname || '(Unknown Supplier)';

                const prev = preserved.groups[g.supplier_id];
                const sup = g.supplierInfo || {};
                pageEl.querySelector('.cust_name').value = prev?.cust_name
                    || localStorage.getItem('po_cust_name_<?= $orderId ?>')
                    || '';
                pageEl.querySelector('.cust_start_date').value = today;
                pageEl.querySelector('.vendor_name').value = prev?.vendor_name ?? (sup.suppersonname || '');
                pageEl.querySelector('.vendor_company').value = prev?.vendor_company ?? (sup.supname || '');
                pageEl.querySelector('.vendor_address').value = prev?.vendor_address ?? (sup.supaddress || '');
                pageEl.querySelector('.vendor_phone').value = prev?.vendor_phone ?? (sup.suppersonnumber || '');
                pageEl.querySelector('.vendor_email').value = prev?.vendor_email ?? (sup.suppersonemail || '');
                pageEl.querySelector('.vendor_start_date').value = today;
                pageEl.querySelector('.po_note').value = prev?.note || '';
                pageEl.querySelector('.discount-pct').value = prev?.discount_pct || '';
                pageEl.querySelector('.addl-discount').value = prev?.addl_discount || '';
                pageEl.querySelector('.down-payment').value = prev?.down_payment || '';
                pageEl.querySelector('.dp-date').value = prev?.dp_date || '';

                pageEl.querySelector('.prepared-by-name').textContent = _preparedByName;
                if (_preparedBySig) {
                    pageEl.querySelector('.prepared-sig-box').innerHTML =
                        `<img src="<?= BASE_URL ?>${_preparedBySig}" class="max-h-14 max-w-full object-contain" alt="signature">`;
                }

                const tbody = pageEl.querySelector('.items-tbody');
                g.items.forEach((it, idx) => {
                    const rowNode = rowTpl.content.cloneNode(true);
                    const tr = rowNode.querySelector('tr');
                    tr.querySelector('.row-num').textContent = idx + 1;

                    let desc = it.product_name;
                    if (it.colorname) desc += ` — ${it.colorname}`;
                    if (it.sizename) desc += `, ${it.sizename}`;
                    tr.querySelector('.row-desc').textContent = desc;
                    tr.querySelector('.row-qty').textContent = it.quantity;
                    tr.querySelector('.row-unit').textContent = it.unit || '—';

                    const priceInput = tr.querySelector('.unit-price');
                    priceInput.dataset.itemId = it.id;
                    priceInput.dataset.qty = it.quantity;
                    priceInput.value = preserved.items[it.id] || '';
                    priceInput.addEventListener('input', () => calcRow(priceInput));

                    tbody.appendChild(rowNode);
                });

                for (let e = g.items.length; e < 5; e++) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-slate-100 h-9';
                    tr.innerHTML = `<td class="px-3 py-2 text-slate-300 text-center">${e + 1}</td><td colspan="5"></td>`;
                    tbody.appendChild(tr);
                }

                pageEl.querySelectorAll('.discount-pct, .addl-discount, .down-payment').forEach(el => {
                    el.addEventListener('input', () => recalcTotals(pageEl));
                });

                container.appendChild(pageNode);
                recalcTotals(document.getElementById('po-page-' + gIdx));

                document.getElementById('po-page-' + gIdx).querySelector('.cust_name')
                    .addEventListener('input', function () {
                        localStorage.setItem('po_cust_name_<?= $orderId ?>', this.value);
                    });

            });

            currentPage = Math.min(currentPage, currentGroups.length - 1);
            if (currentPage < 0) currentPage = 0;
            showPage(currentPage);

            document.querySelectorAll('.item-supplier-select').forEach(sel => {
        const saved = savedSupplierSelections[sel.dataset.itemId];  // ❌ ito ang mali
        if (saved) {
            sel.value = saved;
            itemSupplierMap[sel.dataset.itemId] = saved;
        }
    });
}

        function showPage(idx) {
            const pages = document.querySelectorAll('.po-page');
            pages.forEach((el, i) => {
                el.classList.toggle('hidden', i !== idx);
                el.classList.toggle('print-active', i === idx);
            });
            currentPage = idx;
            const indicator = document.getElementById('pageIndicator');
            if (indicator) indicator.textContent = `PO ${idx + 1} of ${pages.length}`;
            updateNavButtons();
        }

        function nextPage() {
            const total = document.querySelectorAll('.po-page').length;
            if (currentPage < total - 1) showPage(currentPage + 1);
        }

        function prevPage() {
            if (currentPage > 0) showPage(currentPage - 1);
        }

        function updateNavButtons() {
            const total = document.querySelectorAll('.po-page').length;
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            if (!prevBtn || !nextBtn) return;
            prevBtn.disabled = currentPage === 0;
            nextBtn.disabled = currentPage === total - 1;
        }

        function calcRow(input) {
            const qty = parseFloat(input.dataset.qty) || 0;
            const price = parseFloat(input.value) || 0;
            const row = input.closest('tr');
            const amountCell = row.querySelector('.row-amount');
            if (price > 0) {
                const total = qty * price;
                amountCell.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                amountCell.textContent = '—';
            }
            recalcTotals(input.closest('.po-page'));
        }

        function recalcTotals(page) {
            let subtotal = 0;
            page.querySelectorAll('.unit-price').forEach(input => {
                const qty = parseFloat(input.dataset.qty) || 0;
                const price = parseFloat(input.value) || 0;
                subtotal += qty * price;
            });

            const fmt = v => v > 0 ? v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';

            page.querySelector('.total-amount').textContent = subtotal > 0 ? fmt(subtotal) : '—';

            const vat = subtotal > 0 ? subtotal * 0.12 : 0;
            page.querySelector('.vat-amount').textContent = vat > 0 ? fmt(vat) : '—';

            const discPct = parseFloat(page.querySelector('.discount-pct').value) || 0;
            const afterDiscount = subtotal > 0 ? subtotal - (subtotal * discPct / 100) : 0;
            page.querySelector('.after-discount').textContent = afterDiscount > 0 ? fmt(afterDiscount) : '—';

            const addlDisc = parseFloat(page.querySelector('.addl-discount').value) || 0;
            const finalAmt = afterDiscount > 0 ? afterDiscount - addlDisc : 0;
            page.querySelector('.final-amount').textContent = finalAmt > 0 ? fmt(finalAmt) : '—';

            const dp = parseFloat(page.querySelector('.down-payment').value) || 0;
            const balance = finalAmt > 0 ? finalAmt - dp : 0;
            page.querySelector('.current-balance').textContent = finalAmt > 0 ? fmt(balance) : '—';
        }

        function saveAllPO(e) {
            const groupsData = [];

            document.querySelectorAll('.po-page').forEach((page, idx) => {
                const g = currentGroups[idx];
                const unitPrices = [];
                page.querySelectorAll('.unit-price').forEach(input => {
                    unitPrices.push({
                        item_id: input.dataset.itemId,
                        quantity: parseFloat(input.dataset.qty) || 0,
                        unit_price: parseFloat(input.value) || 0
                    });
                });

                groupsData.push({
                    supplier_id: g.supplier_id,
                    // NOTE: po_number na ito ay preview lang; sa save script ang
                    // tunay/authoritative number ang gagamitin (server-generated),
                    // kaya po_suffix lang ang aktwal na kailangan dito.
                    po_number: g.po_number,
                    po_suffix: g.suffix,
                    prepared_by: _preparedByName,
                    prepared_by_signature: _preparedBySig,
                    vendor_name: page.querySelector('.vendor_name').value,
                    vendor_company: page.querySelector('.vendor_company').value,
                    vendor_address: page.querySelector('.vendor_address').value,
                    vendor_phone: page.querySelector('.vendor_phone').value,
                    vendor_email: page.querySelector('.vendor_email').value,
                    vendor_start_date: page.querySelector('.vendor_start_date').value,
                    cust_name: page.querySelector('.cust_name').value,
                    cust_company: page.querySelector('.cust_company').value,
                    cust_address: page.querySelector('.cust_address').value,
                    cust_phone: page.querySelector('.cust_phone').value,
                    cust_email: page.querySelector('.cust_email').value,
                    cust_start_date: page.querySelector('.cust_start_date').value,
                    note: page.querySelector('.po_note').value,
                    discount_pct: page.querySelector('.discount-pct').value,
                    addl_discount: page.querySelector('.addl-discount').value,
                    down_payment: page.querySelector('.down-payment').value,
                    dp_date: page.querySelector('.dp-date').value,
                    unit_prices: unitPrices
                });
            });

            const data = {
                order_id: <?= $orderId ?>,
                groups: groupsData
            };

            const btn = e.currentTarget;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            fetch('<?= BASE_URL ?>/warehouse-backendposave', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        localStorage.removeItem('po_supplier_map_<?= $orderId ?>'); // ✅
                        localStorage.removeItem('po_cust_name_<?= $orderId ?>'); // ✅
                        alert('Purchase Order(s) saved successfully!');
                        window.location.href = '<?= BASE_URL ?>/warehousestaff';
                    } else {
                        alert('Error saving PO: ' + (res.message || 'Unknown error'));
                        btn.disabled = false;
                        btn.textContent = 'Save All POs';
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Save All POs';
                });
        }
    </script>
</body>

</html>