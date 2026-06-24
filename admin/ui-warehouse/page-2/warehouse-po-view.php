<?php
// warehouse-po-view.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$poId = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if (!$poId) {
    header('Location: ' . BASE_URL . '/warehousestaff');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM noblepurchaseorder WHERE id = ?");
$stmt->bind_param("i", $poId);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$po) {
    header('Location: ' . BASE_URL . '/warehousestaff');
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

$allSigned = !empty($po['prepared_by_signature'])
    && !empty($po['noted_by_signature'])
    && !empty($po['approved_by_signature'])
    && !empty($po['acknowledged_by_signature'])
    && !empty($po['received_by_signature']);

$signedCount = array_sum([
    !empty($po['prepared_by_signature']) ? 1 : 0,
    !empty($po['noted_by_signature']) ? 1 : 0,
    !empty($po['approved_by_signature']) ? 1 : 0,
    !empty($po['acknowledged_by_signature']) ? 1 : 0,
    !empty($po['received_by_signature']) ? 1 : 0,
]);
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
    #sidebar,
    aside,
    nav,
    .no-print,
    #global-toast-container,
    #notif-panel,
    #notif-backdrop {
        display: none !important;
    }

    @page {
        size: A4 portrait;
        margin: 12mm 10mm;
    }

    html, body {
        font-size: 9px !important;
        background: white !important;
        margin: 0 !important;
    }

    .ml-60 { margin-left: 0 !important; }
    .p-6 { padding: 0 !important; }
    .max-w-5xl { max-width: 100% !important; }
    .bg-slate-100 { background: white !important; }

    .bg-white.rounded-xl {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 8mm !important;
    }

    table {
        font-size: 8px !important;
    }

    th, td {
        padding: 3px 6px !important;
    }

    img.h-14 {
        height: 36px !important;
    }

    h1, h2 {
        font-size: 11px !important;
    }

    .text-xl { font-size: 11px !important; }
    .text-base { font-size: 10px !important; }
    .text-sm { font-size: 8.5px !important; }
    .text-xs { font-size: 7.5px !important; }

    .px-4 { padding-left: 8px !important; padding-right: 8px !important; }
    .py-2 { padding-top: 4px !important; padding-bottom: 4px !important; }
    .px-3 { padding-left: 6px !important; padding-right: 6px !important; }
    .py-3 { padding-top: 4px !important; padding-bottom: 4px !important; }

    .mb-6 { margin-bottom: 8px !important; }
    .mb-8 { margin-bottom: 8px !important; }
    .mt-6 { margin-top: 8px !important; }
    .pb-5 { padding-bottom: 8px !important; }
    .gap-4 { gap: 6px !important; }
    .gap-6 { gap: 6px !important; }

    .min-h-\[60px\] { min-height: 40px !important; }
    .max-h-14 { max-height: 34px !important; }

    input, textarea {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        -webkit-appearance: none !important;
        font-size: 8.5px !important;
        padding: 2px 4px !important;
    }

    input[type="date"]:not([value]),
    input[type="date"][value=""] {
        visibility: hidden !important;
    }

    .avoid-break {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .print-dash-show {
        display: none !important;
    }

    .print-dash {
        display: inline !important;
    }
}

@media screen {
    .print-dash {
        display: none;
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
                <span class="text-sm text-slate-700 font-medium">View Purchase Order</span>

                <?php if ($allSigned): ?>
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        All Signatures Completed
                    </span>
                <?php else: ?>
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Signatures: <?= $signedCount ?>/5
                    </span>
                <?php endif; ?>
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
                <button onclick="updatePO()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Update PO
                </button>
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

            <!-- Customer + Vendor -->
            <div class="grid grid-cols-2 gap-4 mb-6 text-sm">

                <!-- CUSTOMER -->
                <div class="border border-slate-300">
                    <div class="bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider px-4 py-2">
                        Customer</div>
                    <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-b border-slate-200">
                            Name</div>
                        <div class="border-b border-slate-200">
                            <input type="text" id="cust_name" value="<?= htmlspecialchars($po['cust_name'] ?? '') ?>"
                                class="w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>
                    <?php
                    $custReadonly = [
                        'Company Name' => $po['cust_company'],
                        'Address' => $po['cust_address'],
                        'Phone No.' => $po['cust_phone'],
                        'Email Address' => $po['cust_email'],
                        'Start Date' => $po['cust_start_date'],
                    ];
                    foreach ($custReadonly as $label => $value): ?>
                        <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                            <div
                                class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                                <?= $label ?>
                            </div>
                            <div class="px-3 py-2 text-slate-500 text-sm bg-slate-50"><?= htmlspecialchars($value ?? '—') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- VENDOR -->
                <div class="border border-slate-300">
                    <div class="bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider px-4 py-2">Vendor
                        / Sales Person</div>
                    <?php
                    $vendorEditable = [
                        ['id' => 'vendor_name', 'label' => 'Name / Sales Person', 'value' => $po['vendor_name']],
                        ['id' => 'vendor_company', 'label' => 'Company Name', 'value' => $po['vendor_company']],
                        ['id' => 'vendor_address', 'label' => 'Address', 'value' => $po['vendor_address']],
                        ['id' => 'vendor_phone', 'label' => 'Phone No.', 'value' => $po['vendor_phone']],
                        ['id' => 'vendor_email', 'label' => 'Email Address', 'value' => $po['vendor_email']],
                    ];
                    foreach ($vendorEditable as $field): ?>
                        <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                            <div
                                class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                                <?= $field['label'] ?>
                            </div>
                            <div>
                                <input type="text" id="<?= $field['id'] ?>"
                                    value="<?= htmlspecialchars($field['value'] ?? '') ?>"
                                    class="w-full px-3 py-2 text-sm bg-transparent focus:outline-none focus:ring-1 focus:ring-indigo-400">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="grid grid-cols-[130px_1fr] border-t border-slate-200">
                        <div
                            class="bg-slate-50 text-xs font-bold uppercase text-slate-600 px-3 py-2 border-r border-slate-200">
                            Start Date</div>
                        <div class="px-3 py-2 text-slate-500 text-sm bg-slate-50">
                            <?= htmlspecialchars($po['vendor_start_date'] ?? '—') ?>
                        </div>
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
                                    <?= htmlspecialchars($item['unit'] ?? '—') ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" min="0"
                                        class="unit-price w-full border border-slate-200 rounded px-2 py-1 text-right text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        value="<?= $item['unit_price'] ?>" data-qty="<?= $item['quantity'] ?>"
                                        data-item-id="<?= $item['po_item_id'] ?>" oninput="calcRow(this)">
                                </td>
                                <td class="px-3 py-2 text-right text-slate-800 font-medium row-amount">
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

            <!-- Totals + Note -->
            <div class="grid grid-cols-2 gap-6 mb-8 avoid-break">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">Note:</label>
                    <textarea id="po_note" rows="3"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"><?= htmlspecialchars($po['note'] ?? '') ?></textarea>
                </div>
                <div class="border border-slate-200 rounded-lg overflow-hidden text-sm">
                    <table class="w-full">
                        <tbody>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500 font-medium">Total Amount:</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-800">PHP <span
                                        id="total-amount"><?= number_format($po['subtotal'], 2) ?></span></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">12% VAT Inclusive:</td>
                                <td class="px-4 py-2 text-right text-slate-700">PHP <span
                                        id="vat-amount"><?= number_format($po['subtotal'] * 0.12, 2) ?></span></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Discount (%):</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number" id="discount-pct" min="0" max="100" step="0.01"
                                        value="<?= $po['discount_pct'] ?>"
                                        class="w-20 border border-slate-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        oninput="recalcTotals()">
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Amount After Discount:</td>
                                <td class="px-4 py-2 text-right text-slate-700">PHP <span
                                        id="after-discount"><?= number_format($po['subtotal'] - ($po['subtotal'] * $po['discount_pct'] / 100), 2) ?></span>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Add'l Discount:</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number" id="addl-discount" min="0" step="0.01"
                                        value="<?= $po['addl_discount'] ?>"
                                        class="w-28 border border-slate-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        oninput="recalcTotals()">
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200 bg-amber-50">
                                <td class="px-4 py-2 text-slate-700 font-semibold">Final Amount:</td>
                                <td class="px-4 py-2 text-right font-bold text-slate-900">PHP <span
                                        id="final-amount"><?= number_format($po['final_amount'], 2) ?></span></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Down Payment:</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number" id="down-payment" min="0" step="0.01"
                                        value="<?= $po['down_payment'] ?>"
                                        class="w-28 border border-slate-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        oninput="recalcTotals()">
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2 text-slate-500">Down Payment Date:</td>
                                <td class="px-4 py-2 text-right">
                                    <input type="date" id="dp-date"
                                        value="<?= htmlspecialchars($po['dp_date'] ?? '') ?>"
                                        class="print-dash-show border border-slate-200 rounded px-2 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                    <?php if (empty($po['dp_date'])): ?>
                                        <span class="print-dash text-slate-500 text-sm">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="bg-amber-50">
                                <td class="px-4 py-2 text-slate-700 font-semibold">Current Balance:</td>
                                <td class="px-4 py-2 text-right font-bold text-slate-900">PHP <span
                                        id="current-balance"><?= number_format($po['current_balance'], 2) ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border <?= $sigBorder ?> rounded-lg overflow-hidden mt-6 avoid-break">
                <!-- Header Row -->
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

                <!-- Signature Images Row -->
                <div class="grid grid-cols-5 border-b border-slate-100">
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['prepared_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['prepared_by_signature']) ?>" alt="signature"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['noted_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['noted_by_signature']) ?>" alt="signature"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['approved_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['approved_by_signature']) ?>" alt="signature"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-3 border-r border-slate-200 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['acknowledged_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['acknowledged_by_signature']) ?>" alt="signature"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                    <div class="px-3 py-3 flex items-center justify-center min-h-[60px]">
                        <?php if (!empty($po['received_by_signature'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($po['received_by_signature']) ?>" alt="signature"
                                class="max-h-14 max-w-full object-contain">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Names Row -->
                <div class="grid grid-cols-5">
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800">
                            <?= htmlspecialchars($po['prepared_by'] ?? '—') ?>
                        </p>
                        <p class="text-xs text-slate-500">Warehouse Staff</p>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <p class="text-xs font-semibold text-slate-800">
                            <?= !empty($po['noted_by']) ? htmlspecialchars($po['noted_by']) : '—' ?>
                        </p>
                        <p class="text-xs text-slate-500">Accounting Staff</p>
                    </div>
                    <div class="px-3 py-2 border-r border-slate-200 text-center">
                        <?php if (!empty($po['approved_by'])): ?>
                            <p class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($po['approved_by']) ?></p>
                            <p class="text-xs text-slate-500">General Manager</p>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Pending</p>
                        <?php endif; ?>
                    </div>
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
                        <?php if (!empty($po['received_by'])): ?>
                            <p class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($po['received_by']) ?></p>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Pending</p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-500">Accounting Head</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', recalcTotals);

        function calcRow(input) {
            const qty = parseFloat(input.dataset.qty) || 0;
            const price = parseFloat(input.value) || 0;
            const row = input.closest('tr');
            const amountCell = row.querySelector('.row-amount');
            const total = qty * price;
            amountCell.textContent = price > 0 ? '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—';
            recalcTotals();
        }

        function recalcTotals() {
            let subtotal = 0;
            document.querySelectorAll('.unit-price').forEach(input => {
                subtotal += (parseFloat(input.dataset.qty) || 0) * (parseFloat(input.value) || 0);
            });

            const fmt = v => v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            document.getElementById('total-amount').textContent = fmt(subtotal);
            document.getElementById('vat-amount').textContent = fmt(subtotal * 0.12);

            const discPct = parseFloat(document.getElementById('discount-pct').value) || 0;
            const afterDiscount = subtotal - (subtotal * discPct / 100);
            document.getElementById('after-discount').textContent = fmt(afterDiscount);

            const addlDisc = parseFloat(document.getElementById('addl-discount').value) || 0;
            const finalAmt = afterDiscount - addlDisc;
            document.getElementById('final-amount').textContent = fmt(finalAmt);

            const dp = parseFloat(document.getElementById('down-payment').value) || 0;
            document.getElementById('current-balance').textContent = fmt(finalAmt - dp);
        }

        function updatePO() {
            const unitPrices = [];
            document.querySelectorAll('.unit-price').forEach(input => {
                unitPrices.push({
                    item_id: input.dataset.itemId,
                    unit_price: parseFloat(input.value) || 0,
                    quantity: parseFloat(input.dataset.qty) || 0
                });
            });

            const data = {
                po_id: <?= $poId ?>,
                cust_name: document.getElementById('cust_name').value,
                vendor_name: document.getElementById('vendor_name').value,
                vendor_company: document.getElementById('vendor_company').value,
                vendor_address: document.getElementById('vendor_address').value,
                vendor_phone: document.getElementById('vendor_phone').value,
                vendor_email: document.getElementById('vendor_email').value,
                note: document.getElementById('po_note').value,
                discount_pct: document.getElementById('discount-pct').value,
                addl_discount: document.getElementById('addl-discount').value,
                down_payment: document.getElementById('down-payment').value,
                dp_date: document.getElementById('dp-date').value,
                unit_prices: unitPrices
            };

            fetch('<?= BASE_URL ?>/warehouse-backendpoupdate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Purchase Order updated successfully!');
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