<?php
// productlimitorder-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$uploadUrl = BASE_URL . '/uploads/';

// ── Load products kasama ang current limit + tier count ──
$products = [];
$sql = "
    SELECT p.id, p.name, p.category, p.imageproduct,
           l.max_qty_per_order,
           (SELECT COUNT(*) FROM nobleproductqtytier t WHERE t.product_id = p.id) AS tier_count
    FROM nobleproduct p
    LEFT JOIN nobleproductlimit l ON l.product_id = p.id
    ORDER BY p.name ASC
";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Limit Order Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-lg font-bold text-slate-800">Product Limit & Quantity Discount</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Itakda ang max quantity per order, at ang tiered discount habang dumadami ang quantity na binili.
                </p>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 text-sm">
                <i class="fa-solid fa-box-open text-3xl mb-2 block"></i>
                Walang products na nahanap.
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="px-5 py-3 text-left">Product</th>
                            <th class="px-5 py-3 text-left">Category</th>
                            <th class="px-5 py-3 text-center">Max Qty / Order</th>
                            <th class="px-5 py-3 text-center">Discount Tiers</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="product-table-body">
                        <?php foreach ($products as $p): ?>
                            <tr data-product-id="<?= $p['id'] ?>">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($p['imageproduct'])): ?>
                                            <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                                class="w-9 h-9 rounded-lg object-cover border border-slate-100">
                                        <?php else: ?>
                                            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center text-slate-300">
                                                <i class="fa-solid fa-image text-xs"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="font-medium text-slate-700"><?= htmlspecialchars($p['name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($p['category']) ?></td>
                                <td class="px-5 py-3 text-center max-qty-cell">
                                    <?= ($p['max_qty_per_order'] !== null && $p['max_qty_per_order'] > 0)
                                        ? htmlspecialchars($p['max_qty_per_order'])
                                        : '<span class="text-slate-300 italic">No limit</span>' ?>
                                </td>
                                <td class="px-5 py-3 text-center tier-count-cell">
                                    <?php if ($p['tier_count'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 text-xs font-medium">
                                            <?= $p['tier_count'] ?> tier<?= $p['tier_count'] > 1 ? 's' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-300 italic text-xs">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button type="button"
                                        onclick="openManageModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 text-slate-600 hover:border-amber-300 hover:text-amber-600 transition">
                                        <i class="fa-solid fa-sliders mr-1"></i> Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- ════════ Manage Modal ════════ -->
    <div id="manage-modal" class="fixed inset-0 z-50 bg-black/40 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Limit & Discount Settings</h2>
                    <p id="modal-product-name" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closeManageModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5">

                <!-- Max Qty Per Order -->
                <div class="mb-5">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1.5">
                        Max Quantity per Order
                    </label>
                    <input type="number" id="input-max-qty" min="0" placeholder="0 = walang limit"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-amber-400">
                    <p class="text-[11px] text-slate-400 mt-1">Set to 0 kung walang limit sa quantity per order.</p>
                </div>

                <!-- Tiers -->
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        Quantity Discount Tiers
                    </label>
                    <button type="button" onclick="addTierRow()" class="text-xs font-medium text-amber-600 hover:text-amber-700">
                        <i class="fa-solid fa-plus mr-1"></i> Add Tier
                    </button>
                </div>

                <div id="tier-rows" class="space-y-2 mb-2"></div>
                <p class="text-[11px] text-slate-400 mb-4">
                    Halimbawa: Min 3 / Max 4 qty = 1% off, Min 5 / Max 5 qty = 1.2% off. Hindi dapat mag-overlap ang ranges,
                    at hindi dapat lumagpas sa Max Quantity per Order sa itaas.
                </p>

                <div id="modal-error" class="hidden text-xs text-red-500 bg-red-50 border border-red-100 rounded-lg px-3 py-2 mb-4"></div>

                <div class="flex justify-end gap-2">
                    <button onclick="closeManageModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button onclick="saveManageModal()" id="save-btn" class="px-4 py-2 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white">
                        <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const fetchUrl = <?= json_encode(BASE_URL . '/productlimitorder-fetch') ?>;
        const saveUrl = <?= json_encode(BASE_URL . '/productlimitorder-save') ?>;

        let currentProductId = null;
        let tierCounter = 0;

        function openManageModal(productId, productName) {
            currentProductId = productId;
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('tier-rows').innerHTML = '';
            document.getElementById('input-max-qty').value = '';
            tierCounter = 0;

            document.getElementById('manage-modal').classList.remove('hidden');
            document.getElementById('manage-modal').classList.add('flex');

            fetch(`${fetchUrl}?product_id=${productId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;
                    document.getElementById('input-max-qty').value = data.max_qty_per_order ?? 0;
                    (data.tiers || []).forEach(t => addTierRow(t.min_qty, t.max_qty, t.discount_percent));
                    if (!data.tiers || data.tiers.length === 0) addTierRow();
                })
                .catch(() => addTierRow());
        }

        function closeManageModal() {
            document.getElementById('manage-modal').classList.add('hidden');
            document.getElementById('manage-modal').classList.remove('flex');
            currentProductId = null;
        }

        function addTierRow(minQty = '', maxQty = '', discountPercent = '') {
            tierCounter++;
            const id = 'tier-' + tierCounter;
            const wrapper = document.createElement('div');
            wrapper.id = id;
            wrapper.className = 'flex items-center gap-2';
            wrapper.innerHTML = `
                <input type="number" min="1" placeholder="Min qty" value="${minQty}"
                    class="tier-min w-1/4 px-2.5 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-amber-400">
                <span class="text-slate-300 text-xs">to</span>
                <input type="number" min="1" placeholder="Max qty" value="${maxQty}"
                    class="tier-max w-1/4 px-2.5 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-amber-400">
                <div class="relative flex-1">
                    <input type="number" step="0.01" min="0" max="100" placeholder="Discount %" value="${discountPercent}"
                        class="tier-discount w-full px-2.5 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-amber-400 pr-6">
                    <span class="absolute right-2.5 top-1.5 text-[11px] text-slate-400">%</span>
                </div>
                <button type="button" onclick="document.getElementById('${id}').remove()"
                    class="text-slate-300 hover:text-red-400 transition">
                    <i class="fa-solid fa-trash-can text-xs"></i>
                </button>
            `;
            document.getElementById('tier-rows').appendChild(wrapper);
        }

        function collectTiers() {
            const rows = document.querySelectorAll('#tier-rows > div');
            const tiers = [];
            for (const row of rows) {
                const min = row.querySelector('.tier-min').value;
                const max = row.querySelector('.tier-max').value;
                const disc = row.querySelector('.tier-discount').value;
                if (min === '' && max === '' && disc === '') continue; // skip totally empty rows
                tiers.push({
                    min_qty: parseInt(min, 10),
                    max_qty: parseInt(max, 10),
                    discount_percent: parseFloat(disc) || 0
                });
            }
            return tiers;
        }

        function showModalError(msg) {
            const el = document.getElementById('modal-error');
            el.textContent = msg;
            el.classList.remove('hidden');
        }

        async function saveManageModal() {
            document.getElementById('modal-error').classList.add('hidden');

            const maxQty = parseInt(document.getElementById('input-max-qty').value, 10) || 0;
            const tiers = collectTiers();

            // Basic client-side validation bago i-send sa server
            for (const t of tiers) {
                if (!t.min_qty || !t.max_qty || t.min_qty > t.max_qty) {
                    showModalError('Mali ang Min/Max qty sa isa sa mga tier.');
                    return;
                }
                if (maxQty > 0 && t.max_qty > maxQty) {
                    showModalError('Hindi puedeng lumagpas ang tier sa Max Quantity per Order.');
                    return;
                }
            }

            const btn = document.getElementById('save-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Saving…';

            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: currentProductId,
                        max_qty_per_order: maxQty,
                        tiers
                    })
                });
                const data = await res.json();

                if (!data.ok) {
                    showModalError(data.msg || 'Failed to save.');
                } else {
                    updateRowDisplay(currentProductId, maxQty, tiers.length);
                    closeManageModal();
                }
            } catch (e) {
                showModalError('Something went wrong. Please try again.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1.5"></i> Save';
        }

        function updateRowDisplay(productId, maxQty, tierCount) {
            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (!row) return;
            row.querySelector('.max-qty-cell').innerHTML = maxQty > 0
                ? maxQty
                : '<span class="text-slate-300 italic">No limit</span>';
            row.querySelector('.tier-count-cell').innerHTML = tierCount > 0
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 text-xs font-medium">${tierCount} tier${tierCount > 1 ? 's' : ''}</span>`
                : '<span class="text-slate-300 italic text-xs">None</span>';
        }
    </script>

</body>

</html>