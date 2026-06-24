<?php
// accounting-poview.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$poId = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if (!$poId) {
    header('Location: ' . BASE_URL . '/accountantcustodian');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM noblepurchaseorder WHERE id = ?");
$stmt->bind_param("i", $poId);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$po) {
    header('Location: ' . BASE_URL . '/accountantcustodian');
    exit;
}

$itemsStmt = $conn->prepare("
    SELECT 
        poi.id AS po_item_id,
        poi.unit_price,
        poi.line_total,
        ppi.product_name,
        ppi.colorname,
        ppi.sizename,
        ppi.unit,
        ppi.quantity
    FROM noblepurchaseorderitems poi
    LEFT JOIN noblepaidproductitems ppi ON poi.paid_item_id = ppi.id
    WHERE poi.po_id = ?
    ORDER BY poi.id ASC
");
$itemsStmt->bind_param("i", $poId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

$isPending = empty($po['noted_by']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Purchase Order</title>
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
        }
    </style>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Top bar -->
        <div class="flex items-center justify-between mb-6 no-print">
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/accountantcustodian"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Purchase Orders
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-sm text-slate-700 font-medium">View Purchase Order</span>
            </div>
            <div class="flex gap-2">
                <button onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
                <?php if ($isPending): ?>
                    <button onclick="notePO()"
                        class="inline-flex items-center gap-2 px-2 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                        <i class="fa-solid fa-check"></i>
                        Note PO
                    </button>
                <?php else: ?>
                    <span
                        class="inline-flex items-center gap-2 px-2 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-lg">
                        <i class="fa-solid fa-check"></i>
                        Noted
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- PO Document -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 max-w-5xl mx-auto">

            <!-- PO Header -->
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
                                <td class="px-4 py-2 font-bold text-slate-800 border-r border-slate-200 text-center">
                                    <?= htmlspecialchars($po['po_number']) ?>
                                </td>
                                <td class="px-4 py-2 font-bold text-slate-800 text-center">
                                    <?= date('m/d/Y', strtotime($po['po_date'])) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Customer + Vendor — READ ONLY -->
            <div class="grid grid-cols-2 gap-4 mb-6 text-sm">

                <!-- CUSTOMER -->
                <div class="border border-slate-300">
                    <div class="bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider px-4 py-2">
                        Customer</div>
                    <?php
                    $custFields = [
                        'Name' => $po['cust_name'],
                        'Company Name' => $po['cust_company'],
                        'Address' => $po['cust_address'],
                        'Phone No.' => $po['cust_phone'],
                        'Email Address' => $po['cust_email'],
                        'Start Date' => $po['cust_start_date'],
                    ];
                    foreach ($custFields as $label => $value): ?>
                        <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                            <div
                                class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                                <?= $label ?>
                            </div>
                            <div class="px-3 py-2 text-slate-700 text-sm">
                                <?= htmlspecialchars($value ?? '—') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- VENDOR -->
                <div class="border border-slate-300">
                    <div class="bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider px-4 py-2">Vendor
                        / Sales Person</div>
                    <?php
                    $vendorFields = [
                        'Name / Sales Person' => $po['vendor_name'],
                        'Company Name' => $po['vendor_company'],
                        'Address' => $po['vendor_address'],
                        'Phone No.' => $po['vendor_phone'],
                        'Email Address' => $po['vendor_email'],
                        'Start Date' => $po['vendor_start_date'],
                    ];
                    foreach ($vendorFields as $label => $value): ?>
                        <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                            <div
                                class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                                <?= $label ?>
                            </div>
                            <div class="px-3 py-2 text-slate-700 text-sm">
                                <?= htmlspecialchars($value ?? '—') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Items Table — READ ONLY -->
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
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-500 text-center"><?= $idx + 1 ?></td>
                                <td class="px-3 py-2 text-slate-800">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                    <?php if ($item['colorname']): ?>
                                        <span class="text-xs text-slate-500"> —
                                            <?= htmlspecialchars($item['colorname']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['sizename']): ?>
                                        <span class="text-xs text-slate-500">, <?= htmlspecialchars($item['sizename']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-center text-slate-700"><?= $item['quantity'] ?></td>
                                <td class="px-3 py-2 text-center text-slate-700">
                                    <?= htmlspecialchars($item['unit'] ?? '—') ?></td>
                                <td class="px-3 py-2 text-right text-slate-700">
                                    ₱<?= number_format($item['unit_price'], 2) ?>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-800 font-medium">
                                    ₱<?= number_format($item['line_total'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php for ($e = count($items); $e < 5; $e++): ?>
                            <tr class="border-b border-slate-100 h-9">
                                <td class="px-3 py-2 text-slate-300 text-center"><?= $e + 1 ?></td>
                                <td colspan="5"></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6"
                                class="px-3 py-2 text-center text-xs text-slate-400 italic border-t border-slate-200">
                                ***** NOTHING FOLLOWS *****
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Totals + Note — READ ONLY -->
            <div class="grid grid-cols-2 gap-6 mb-8">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">Note:</label>
                    <div
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-slate-50 min-h-[72px]">
                        <?= nl2br(htmlspecialchars($po['note'] ?? '—')) ?>
                    </div>
                </div>
                <div class="border border-slate-200 rounded-lg overflow-hidden text-sm">
                    <table class="w-full">
                        <tbody>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500 font-medium">Total Amount:</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-800">
                                    PHP <?= number_format($po['subtotal'], 2) ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">12% VAT Inclusive:</td>
                                <td class="px-4 py-2 text-right text-slate-700">
                                    PHP <?= number_format($po['subtotal'] * 0.12, 2) ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Discount (%):</td>
                                <td class="px-4 py-2 text-right text-slate-700">
                                    <?= number_format($po['discount_pct'], 2) ?>%
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Amount After Discount:</td>
                                <td class="px-4 py-2 text-right text-slate-700">
                                    PHP
                                    <?= number_format($po['subtotal'] - ($po['subtotal'] * $po['discount_pct'] / 100), 2) ?>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Add'l Discount:</td>
                                <td class="px-4 py-2 text-right text-slate-700">
                                    PHP <?= number_format($po['addl_discount'], 2) ?></td>
                            </tr>
                            <tr class="border-b border-slate-200 bg-amber-50">
                                <td class="px-4 py-2 text-slate-700 font-semibold">Final Amount:</td>
                                <td class="px-4 py-2 text-right font-bold text-slate-900">
                                    PHP <?= number_format($po['final_amount'], 2) ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Down Payment:</td>
                                <td class="px-4 py-2 text-right text-slate-700">
                                    PHP <?= number_format($po['down_payment'], 2) ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Down Payment Date:</td>
                                <td class="px-4 py-2 text-right text-slate-700">
                                    <?= !empty($po['dp_date']) ? date('m/d/Y', strtotime($po['dp_date'])) : '—' ?>
                                </td>
                            </tr>
                            <tr class="bg-amber-50">
                                <td class="px-4 py-2 text-slate-700 font-semibold">Current Balance:</td>
                                <td class="px-4 py-2 text-right font-bold text-slate-900">
                                    PHP <?= number_format($po['current_balance'], 2) ?></td>
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
                <!-- Signatures row -->
                <div class="grid grid-cols-5 border-b border-slate-100">
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['prepared_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['prepared_by_signature']) ?>"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['noted_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['noted_by_signature']) ?>"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-6 border-r border-slate-200"></div>
                    <!-- Acknowledged By signature -->
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['acknowledged_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['acknowledged_by_signature']) ?>"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-6"></div>
                </div>

                <!-- Names row -->
                <div class="grid grid-cols-5">
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($po['prepared_by']) ?></p>
                        <p class="text-xs text-slate-500">Warehouse Staff</p>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <?php if (!empty($po['noted_by'])): ?>
                            <p class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($po['noted_by']) ?></p>
                            <p class="text-xs text-slate-500">Accounting Staff</p>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Pending</p>
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800">MR. KEN YANG</p>
                        <p class="text-xs text-slate-500">General Manager</p>
                    </div>
                    <!-- Acknowledged By name -->
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <?php if (!empty($po['acknowledged_by'])): ?>
                            <p class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($po['acknowledged_by']) ?>
                            </p>
                            <p class="text-xs text-slate-500">Custodian</p>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Pending</p>
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-2 text-center">
                        <p class="text-xs font-semibold text-slate-800">MS. MARY GRACE RIVERA</p>
                        <p class="text-xs text-slate-500">Accounting Head</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function acknowledgePO() {
            if (!confirm('Confirm acknowledging this Purchase Order?')) return;

            fetch('<?= BASE_URL ?>/accounting-backendpoacknowledge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ po_id: <?= $poId ?> })
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('PO noted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
        }
    </script>
</body>

</html>