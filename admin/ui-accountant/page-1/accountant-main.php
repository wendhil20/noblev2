<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$orders = [];
$res = $conn->query("
    SELECT o.id, o.nhccreference, o.contact_name, o.contact_email, o.contact_phone,
           o.delivery_method, o.truck_name, o.delivery_fee, o.subtotal, o.vat_amount,
           o.grand_total, o.payment_status, o.created_at, o.address_full,
           o.address_barangay, o.address_city, o.address_postalcode,
           COUNT(i.id) AS item_count
    FROM noblepaidproductlist o
    LEFT JOIN noblepaidproductitems i ON i.order_id = o.id
    WHERE o.payment_status IN ('pending', 'paid')
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>
<body class="bg-slate-100">

<div class="ml-60 min-h-screen p-6">

    <h1 class="text-lg font-bold text-gray-800 mb-1">Orders for Approval</h1>
    <p class="text-xs text-gray-400 mb-6">Review and approve paid/pending orders to forward to Warehouse.</p>

    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-xs text-gray-400 uppercase tracking-wider">
                    <th class="px-4 py-3 text-left font-semibold">Reference</th>
                    <th class="px-4 py-3 text-left font-semibold">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold">Items</th>
                    <th class="px-4 py-3 text-left font-semibold">Method</th>
                    <th class="px-4 py-3 text-left font-semibold">Total</th>
                    <th class="px-4 py-3 text-left font-semibold">Status</th>
                    <th class="px-4 py-3 text-left font-semibold">Date</th>
                    <th class="px-4 py-3 text-left font-semibold">Action</th>
                </tr>
            </thead>
            <tbody id="orders-tbody">
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-400">No orders yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr id="row-<?= $o['id'] ?>" class="border-b border-gray-50">
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-800">
                                <?= htmlspecialchars($o['nhccreference'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs font-semibold text-gray-800"><?= htmlspecialchars($o['contact_name']) ?></p>
                                <p class="text-[10px] text-gray-400"><?= htmlspecialchars($o['contact_email']) ?></p>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600"><?= intval($o['item_count']) ?></td>
                            <td class="px-4 py-3 text-xs text-gray-600 capitalize"><?= htmlspecialchars($o['delivery_method']) ?></td>
                            <td class="px-4 py-3 text-xs font-semibold text-gray-800">
                                ₱<?= number_format(floatval($o['grand_total']), 2) ?>
                            </td>
                            <td class="px-4 py-3" id="status-cell-<?= $o['id'] ?>">
                                <?php if ($o['payment_status'] === 'paid'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                        <i class="fa-solid fa-circle-check text-[9px]"></i> Paid
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                                        <i class="fa-solid fa-clock text-[9px]"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-[10px] text-gray-400">
                                <?= date('M d, Y h:i A', strtotime($o['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3 flex items-center gap-2">
                                <button onclick="openReceipt(<?= $o['id'] ?>)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200
                                           text-gray-600 hover:bg-gray-50 transition-all duration-150">
                                    <i class="fa-solid fa-receipt mr-1"></i> View
                                </button>
                                <button id="approve-btn-<?= $o['id'] ?>" onclick="approveOrder(<?= $o['id'] ?>, this)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600
                                           text-white transition-all duration-150">
                                    Approve
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receipt-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center"
     style="background: rgba(0,0,0,0.4);">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 flex flex-col max-h-[90vh]">

        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-receipt text-amber-500"></i>
                <span class="text-sm font-bold text-gray-800">Order Receipt</span>
            </div>
            <button onclick="closeReceipt()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div id="receipt-body" class="overflow-y-auto px-6 py-5 flex-1">
            <div class="flex items-center justify-center py-10">
                <i class="fa-solid fa-spinner fa-spin text-gray-300 text-2xl"></i>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-100 shrink-0 flex items-center justify-between gap-3">
            <button onclick="closeReceipt()" class="px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                Close
            </button>
            <button id="modal-approve-btn"
                class="px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition">
                <i class="fa-solid fa-circle-check mr-1"></i> Approve & Forward to Warehouse
            </button>
        </div>
    </div>
</div>

<script>
const loadedItems = {};
let currentOrderId = null;

function openReceipt(orderId) {
    currentOrderId = orderId;
    const modal = document.getElementById('receipt-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Reset approve button state
    const modalBtn = document.getElementById('modal-approve-btn');
    const tableBtn = document.getElementById('approve-btn-' + orderId);
    if (tableBtn && tableBtn.tagName === 'SPAN') {
        modalBtn.disabled = true;
        modalBtn.textContent = 'Already Approved';
        modalBtn.classList.replace('bg-amber-500', 'bg-gray-200');
        modalBtn.classList.add('text-gray-400');
        modalBtn.classList.remove('text-white');
    } else {
        modalBtn.disabled = false;
        modalBtn.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> Approve & Forward to Warehouse';
        modalBtn.className = 'px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition';
        modalBtn.onclick = () => approveOrder(orderId, modalBtn, true);
    }

    if (loadedItems[orderId]) {
        renderReceipt(orderId, loadedItems[orderId]);
        return;
    }

    document.getElementById('receipt-body').innerHTML = `
        <div class="flex items-center justify-center py-10">
            <i class="fa-solid fa-spinner fa-spin text-gray-300 text-2xl"></i>
        </div>`;

    fetch('<?= BASE_URL ?>/accountingfetchitems?order_id=' + orderId)
        .then(r => r.json())
        .then(data => {
            loadedItems[orderId] = data;
            renderReceipt(orderId, data);
        })
        .catch(() => {
            document.getElementById('receipt-body').innerHTML =
                '<p class="text-xs text-red-400 text-center py-8">Failed to load receipt.</p>';
        });
}

function renderReceipt(orderId, data) {
    const o = data.order;
    const items = data.items;

    document.getElementById('receipt-body').innerHTML = `
        <!-- Store Header -->
        <div class="text-center mb-5">
            <p class="text-base font-bold text-gray-800">Noble<span class="text-amber-500">Home</span></p>
            <p class="text-[10px] text-gray-400 mt-0.5">Order Receipt</p>
        </div>

        <!-- Reference & Date -->
        <div class="flex justify-between items-center mb-4 pb-4 border-b border-dashed border-gray-200">
            <div>
                <p class="text-[10px] text-gray-400">Reference No.</p>
                <p class="text-xs font-bold text-gray-800 font-mono">${o.nhccreference ?? '—'}</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] text-gray-400">Date</p>
                <p class="text-xs text-gray-700">${formatDate(o.created_at)}</p>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="mb-4 pb-4 border-b border-dashed border-gray-200">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Customer</p>
            <p class="text-xs font-semibold text-gray-800">${o.contact_name}</p>
            <p class="text-[10px] text-gray-500">${o.contact_email}</p>
            <p class="text-[10px] text-gray-500">${o.contact_phone}</p>
            ${o.address_full ? `<p class="text-[10px] text-gray-500 mt-1">${o.address_full}, ${o.address_barangay}, ${o.address_city} ${o.address_postalcode}</p>` : ''}
        </div>

        <!-- Delivery Info -->
        <div class="mb-4 pb-4 border-b border-dashed border-gray-200">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Delivery</p>
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Method</span>
                <span class="font-semibold text-gray-700 capitalize">${o.delivery_method}</span>
            </div>
            ${o.truck_name ? `
            <div class="flex justify-between text-xs mt-1">
                <span class="text-gray-500">Truck</span>
                <span class="font-semibold text-gray-700">${o.truck_name}</span>
            </div>` : ''}
        </div>

        <!-- Items -->
        <div class="mb-4 pb-4 border-b border-dashed border-gray-200">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-3">Items</p>
            <div class="space-y-2">
                ${items.map(i => `
                    <div class="flex justify-between items-start gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-800 leading-snug">${i.product_name}</p>
                            <p class="text-[10px] text-gray-400">
                                ${i.colorname ? i.colorname : ''}${i.colorname && i.sizename ? ' / ' : ''}${i.sizename ? i.sizename : ''}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs text-gray-700">x${i.quantity}</p>
                            ${parseFloat(i.discount_pct) > 0
                                ? `<p class="text-[10px] text-green-600">-${i.discount_pct}%</p>`
                                : ''}
                            <p class="text-xs font-semibold text-gray-800">₱${parseFloat(i.line_total).toFixed(2)}</p>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>

        <!-- Totals -->
        <div class="space-y-1.5">
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Subtotal</span>
                <span class="text-gray-700">₱${parseFloat(o.subtotal).toFixed(2)}</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">VAT (12%)</span>
                <span class="text-gray-700">₱${parseFloat(o.vat_amount).toFixed(2)}</span>
            </div>
            ${parseFloat(o.delivery_fee) > 0 ? `
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Delivery Fee</span>
                <span class="text-gray-700">₱${parseFloat(o.delivery_fee).toFixed(2)}</span>
            </div>` : ''}
            <div class="flex justify-between text-sm font-bold text-gray-900 pt-2 border-t border-gray-200 mt-2">
                <span>Grand Total</span>
                <span>₱${parseFloat(o.grand_total).toFixed(2)}</span>
            </div>
        </div>

        <!-- Payment Status -->
        <div class="mt-4 text-center">
            ${o.payment_status === 'paid'
                ? `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                       <i class="fa-solid fa-circle-check"></i> Payment Confirmed
                   </span>`
                : `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                       <i class="fa-solid fa-clock"></i> Payment Pending
                   </span>`
            }
        </div>
    `;
}

function closeReceipt() {
    const modal = document.getElementById('receipt-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    currentOrderId = null;
}

// Close on backdrop click
document.getElementById('receipt-modal').addEventListener('click', function(e) {
    if (e.target === this) closeReceipt();
});

function approveOrder(orderId, btn, fromModal = false) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Approving…';

    fetch('<?= BASE_URL ?>/accountingapprove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `order_id=${orderId}`
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            // Update table status badge
            document.getElementById('status-cell-' + orderId).innerHTML = `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-circle-check text-[9px]"></i> Approved
                </span>`;

            // Replace table approve button
            const tableBtn = document.getElementById('approve-btn-' + orderId);
            if (tableBtn) {
                tableBtn.outerHTML = '<span class="text-[10px] text-gray-400" id="approve-btn-' + orderId + '">Forwarded</span>';
            }

            // Update modal button
            btn.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> Approved';
            btn.className = 'px-4 py-2 rounded-lg text-xs font-semibold bg-gray-200 text-gray-400 cursor-default';

            if (fromModal) {
                setTimeout(() => closeReceipt(), 1200);
            }
        } else {
            btn.disabled = false;
            btn.innerHTML = fromModal
                ? '<i class="fa-solid fa-circle-check mr-1"></i> Approve & Forward to Warehouse'
                : 'Approve';
            alert(res.error ?? 'Something went wrong.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = fromModal
            ? '<i class="fa-solid fa-circle-check mr-1"></i> Approve & Forward to Warehouse'
            : 'Approve';
    });
}

function formatDate(ts) {
    const d = new Date(ts);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
           + ' ' + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}
</script>

</body>
</html>