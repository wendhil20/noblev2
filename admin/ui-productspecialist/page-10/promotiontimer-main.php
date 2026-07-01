<?php
// promotiontimer-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo Timer Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-800">Promo / Discount Timer</h1>
            <button id="addPromoBtn"
                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg">
                <i class="fa-solid fa-plus mr-1"></i> Add Promo
            </button>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3">Product</th>
                        <th class="text-left px-4 py-3">Discount</th>
                        <th class="text-left px-4 py-3">Start</th>
                        <th class="text-left px-4 py-3">End</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="promoTableBody">
                    <tr><td colspan="6" class="text-center py-10 text-gray-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════ ADD/EDIT MODAL ═══════ -->
    <div id="promoModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-2xl w-full max-w-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 id="promoModalTitle" class="text-sm font-bold text-gray-900">Add Promo</h3>
                <button onclick="closePromoModal()" class="text-gray-300 hover:text-gray-500">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <input type="hidden" id="promoId" value="">

            <div class="mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</label>
                <select id="promoProductId"
                    class="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
                    <option value="">Select product…</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Color (optional)</label>
                <select id="promoColorId"
                    class="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
                    <option value="">All colors</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Size (optional)</label>
                <select id="promoSize"
                    class="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
                    <option value="">All sizes</option>
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Puedeng iwan ang dalawa sa "All" para sa buong product, o piliin ang specific color/size.</p>
            </div>

            <div class="mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Discount %</label>
                <input type="number" id="promoDiscount" min="1" max="100" step="0.01"
                    class="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
            </div>

            <div class="mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Start Date</label>
                <input type="datetime-local" id="promoStart"
                    class="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
            </div>

            <div class="mb-4">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">End Date</label>
                <input type="datetime-local" id="promoEnd"
                    class="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
            </div>

            <p id="promoError" class="text-xs text-red-500 mb-3 hidden"></p>

            <button onclick="savePromo()"
                class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg">
                Save
            </button>
        </div>
    </div>

    <script>
        const LIST_URL   = <?= json_encode(BASE_URL . '/ps-backend-promotiontimer-list') ?>;
        const SAVE_URL   = <?= json_encode(BASE_URL . '/ps-backend-promotiontimer-save') ?>;
        const DELETE_URL = <?= json_encode(BASE_URL . '/ps-backend-promotiontimer-delete') ?>;
        let sizesByProduct = {};
        let colorsByProduct = {};

        async function loadPromoTable() {
            const res = await fetch(LIST_URL);
            const data = await res.json();

            sizesByProduct = data.sizes_by_product || {};
            colorsByProduct = data.colors_by_product || {};

            const select = document.getElementById('promoProductId');
            if (select.options.length <= 1) {
                data.products.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    select.appendChild(opt);
                });
            }

            const body = document.getElementById('promoTableBody');
            if (data.promos.length === 0) {
                body.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400">No promos yet.</td></tr>`;
                return;
            }

            body.innerHTML = data.promos.map(p => {
                const statusColor = { active: 'text-green-600', upcoming: 'text-amber-600', expired: 'text-gray-400' }[p.status];
                const scope = [p.colorname || 'All colors', p.sizename || 'All sizes'].join(' / ');
                return `
                <tr class="border-t border-gray-100">
                    <td class="px-4 py-3 font-medium text-gray-800">${p.product_name}</td>
                    <td class="px-4 py-3 text-gray-500">${scope}</td>
                    <td class="px-4 py-3">${p.discount_percent}%</td>
                    <td class="px-4 py-3 text-gray-500">${p.start_date}</td>
                    <td class="px-4 py-3 text-gray-500">${p.end_date}</td>
                    <td class="px-4 py-3 font-semibold ${statusColor}">${p.status}</td>
                    <td class="px-4 py-3 text-right">
                        <button onclick='editPromo(${JSON.stringify(p)})' class="text-amber-500 hover:text-amber-600 mr-3"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="deletePromo(${p.id})" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`;
            }).join('');
        }

        function populateSizeOptions(productId, preselectSize = '') {
            const sizeSelect = document.getElementById('promoSize');
            sizeSelect.innerHTML = '<option value="">All sizes</option>';
            const sizes = sizesByProduct[productId] || [];
            sizes.forEach(sz => {
                const opt = document.createElement('option');
                opt.value = sz;
                opt.textContent = sz;
                if (sz === preselectSize) opt.selected = true;
                sizeSelect.appendChild(opt);
            });
        }

        function populateColorOptions(productId, preselectColorId = '') {
            const colorSelect = document.getElementById('promoColorId');
            colorSelect.innerHTML = '<option value="">All colors</option>';
            const colors = colorsByProduct[productId] || [];
            colors.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.colorname;
                if (String(c.id) === String(preselectColorId)) opt.selected = true;
                colorSelect.appendChild(opt);
            });
        }

        document.getElementById('promoProductId').addEventListener('change', (e) => {
            populateSizeOptions(e.target.value);
            populateColorOptions(e.target.value);
        });

        function openPromoModal() {
            document.getElementById('promoModal').classList.remove('hidden');
            document.getElementById('promoModal').classList.add('flex');
        }
        function closePromoModal() {
            document.getElementById('promoModal').classList.add('hidden');
            document.getElementById('promoModal').classList.remove('flex');
            document.getElementById('promoError').classList.add('hidden');
        }

        document.getElementById('addPromoBtn').addEventListener('click', () => {
            document.getElementById('promoModalTitle').textContent = 'Add Promo';
            document.getElementById('promoId').value = '';
            document.getElementById('promoProductId').value = '';
            document.getElementById('promoDiscount').value = '';
            document.getElementById('promoStart').value = '';
            document.getElementById('promoEnd').value = '';
            document.getElementById('promoSize').innerHTML = '<option value="">All sizes</option>';
            document.getElementById('promoColorId').innerHTML = '<option value="">All colors</option>';
            openPromoModal();
        });

        function editPromo(p) {
            document.getElementById('promoModalTitle').textContent = 'Edit Promo';
            document.getElementById('promoId').value = p.id;
            document.getElementById('promoProductId').value = p.product_id;
            document.getElementById('promoDiscount').value = p.discount_percent;
            document.getElementById('promoStart').value = p.start_date_raw;
            document.getElementById('promoEnd').value = p.end_date_raw;
            populateSizeOptions(p.product_id, p.sizename || '');
            populateColorOptions(p.product_id, p.color_id || '');
            openPromoModal();
        }

        async function savePromo() {
            const errEl = document.getElementById('promoError');
            errEl.classList.add('hidden');

            const payload = {
                id: document.getElementById('promoId').value,
                product_id: document.getElementById('promoProductId').value,
                discount_percent: document.getElementById('promoDiscount').value,
                start_date: document.getElementById('promoStart').value,
                end_date: document.getElementById('promoEnd').value,
                sizename: document.getElementById('promoSize').value || null,
                color_id: document.getElementById('promoColorId').value || null,
            };

            if (!payload.product_id || !payload.discount_percent || !payload.start_date || !payload.end_date) {
                errEl.textContent = 'Complete all fields.';
                errEl.classList.remove('hidden');
                return;
            }
            if (payload.end_date <= payload.start_date) {
                errEl.textContent = 'End date must be after start date.';
                errEl.classList.remove('hidden');
                return;
            }

            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) {
                errEl.textContent = data.msg || 'Failed to save.';
                errEl.classList.remove('hidden');
                return;
            }
            closePromoModal();
            loadPromoTable();
        }

        async function deletePromo(id) {
            if (!confirm('Delete this promo?')) return;
            const fd = new FormData();
            fd.append('id', id);
            await fetch(DELETE_URL, { method: 'POST', body: fd });
            loadPromoTable();
        }

        loadPromoTable();
    </script>

</body>
</html>