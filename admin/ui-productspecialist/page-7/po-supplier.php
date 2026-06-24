<?php
// admin/ui-productspecialist/page-7/po-supplier.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch supplier types for dropdown
$types = [];
$resTypes = $conn->query("SELECT * FROM noblecompanysuppliertype ORDER BY type_name ASC");
while ($row = $resTypes->fetch_assoc()) {
    $types[] = $row;
}

// Fetch all suppliers
$suppliers = [];
$resSuppliers = $conn->query("SELECT * FROM noblecompanysupplier ORDER BY created_at DESC");
while ($row = $resSuppliers->fetch_assoc()) {
    $suppliers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO Supplier</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen p-6">

        <div class="flex items-center justify-between mb-1">
            <h1 class="text-lg font-bold text-gray-800">Suppliers</h1>
            <button onclick="openAddModal()"
                class="px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition">
                <i class="fa-solid fa-plus mr-1"></i> Add Supplier
            </button>
        </div>
        <p class="text-xs text-gray-400 mb-6">Manage your company suppliers.</p>

        <!-- Supplier List -->
        <?php if (empty($suppliers)): ?>
            <div class="bg-white rounded-xl border border-gray-100 px-6 py-12 text-center">
                <i class="fa-solid fa-truck-ramp-box text-gray-200 text-4xl mb-3"></i>
                <p class="text-xs text-gray-400">No suppliers yet. Click <strong>Add Supplier</strong> to get started.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-3">
                <?php foreach ($suppliers as $s): ?>
                    <div class="bg-white rounded-xl border border-gray-100 px-5 py-4 flex items-center gap-4">
                        <!-- Logo -->
                        <div
                            class="w-14 h-14 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-center shrink-0 overflow-hidden">
                            <?php if (!empty($s['suplogoimagecompany'])): ?>
                                <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($s['suplogoimagecompany']) ?>"
                                    class="w-full h-full object-contain" alt="logo">
                            <?php else: ?>
                                <i class="fa-solid fa-building text-gray-300 text-xl"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-bold text-gray-800">
                                    <?= htmlspecialchars($s['supname']) ?>
                                </span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                    <?= htmlspecialchars($s['suptype'] ?? '—') ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 truncate">
                                <i class="fa-solid fa-location-dot mr-1 text-gray-300"></i>
                                <?= htmlspecialchars($s['supaddress'] ?? '—') ?> ·
                                <?= htmlspecialchars($s['countryregion'] ?? '—') ?>
                            </p>
                            <p class="text-[10px] text-gray-400 mt-0.5">
                                <i class="fa-solid fa-user mr-1"></i>
                                <?= htmlspecialchars($s['suppersonname'] ?? '—') ?>
                                <?php if (!empty($s['suppersonjobtitle'])): ?>
                                    · <?= htmlspecialchars($s['suppersonjobtitle']) ?>
                                <?php endif; ?>
                                <?php if (!empty($s['suppersonemail'])): ?>
                                    · <?= htmlspecialchars($s['suppersonemail']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="flex gap-2 shrink-0">
                            <a href="<?= BASE_URL ?>/ps-supplierlink?supplier_id=<?= $s['id'] ?>"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 border border-amber-200 text-amber-600 hover:bg-amber-100 transition">
                                <i class="fa-solid fa-boxes-stacked mr-1"></i> Products
                            </a>
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                                <i class="fa-solid fa-pen mr-1"></i> Edit
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── ADD MODAL ── -->
    <div id="add-modal" class="fixed inset-0 z-50 hidden items-center justify-center"
        style="background:rgba(0,0,0,0.4);">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div
                class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white z-10">
                <p class="text-sm font-bold text-gray-800">Add Supplier</p>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="add-form" enctype="multipart/form-data">
                <div class="px-6 py-5 space-y-4">

                    <!-- Logo Upload -->
                    <div>
                        <label
                            class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Company
                            Logo</label>
                        <div onclick="document.getElementById('add-logo-input').click()"
                            class="w-full h-28 border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:border-amber-400 transition"
                            id="add-logo-preview-wrap">
                            <img id="add-logo-preview" class="hidden w-full h-full object-contain rounded-xl" />
                            <div id="add-logo-placeholder">
                                <i class="fa-solid fa-image text-gray-300 text-2xl mb-1"></i>
                                <p class="text-[10px] text-gray-400">Click to upload logo</p>
                            </div>
                        </div>
                        <input type="file" id="add-logo-input" name="suplogoimagecompany" accept="image/*"
                            class="hidden" onchange="previewLogo(this, 'add-logo-preview', 'add-logo-placeholder')">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Supplier
                                Name *</label>
                            <input type="text" name="supname" required placeholder="e.g. ABC Trading Corp."
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Supplier
                                Type *</label>
                            <div class="flex gap-2">
                                <select name="suptype" required
                                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                                    <option value="">— Select type —</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= htmlspecialchars($t['type_name']) ?>">
                                            <?= htmlspecialchars($t['type_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="openTypeModal()"
                                    class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50 hover:text-amber-500 transition"
                                    title="Manage supplier types">
                                    <i class="fa-solid fa-gear text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Address</label>
                            <input type="text" name="supaddress" placeholder="Street, City, Province"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Country
                                / Region</label>
                            <input type="text" name="countryregion" placeholder="e.g. Philippines"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                    </div>

                    <p
                        class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest pt-1 border-t border-gray-100">
                        Contact Person</p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Name</label>
                            <input type="text" name="suppersonname" placeholder="Full name"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Job
                                Title</label>
                            <input type="text" name="suppersonjobtitle" placeholder="e.g. Sales Manager"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Phone
                                Number</label>
                            <input type="text" name="suppersonnumber" placeholder="+63 9XX XXX XXXX"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Email</label>
                            <input type="email" name="suppersonemail" placeholder="contact@supplier.com"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                    </div>

                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex gap-3 sticky bottom-0 bg-white">
                    <button type="button" onclick="closeAddModal()"
                        class="flex-1 px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="button" onclick="submitAdd()" id="add-submit-btn"
                        class="flex-1 px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition">
                        <i class="fa-solid fa-plus mr-1"></i> Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── EDIT MODAL ── -->
    <div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center"
        style="background:rgba(0,0,0,0.4);">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div
                class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white z-10">
                <p class="text-sm font-bold text-gray-800">Edit Supplier</p>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="edit-form" enctype="multipart/form-data">
                <input type="hidden" name="supplier_id" id="edit-supplier-id">
                <input type="hidden" name="existing_logo" id="edit-existing-logo">

                <div class="px-6 py-5 space-y-4">

                    <!-- Logo Upload -->
                    <div>
                        <label
                            class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Company
                            Logo</label>
                        <div onclick="document.getElementById('edit-logo-input').click()"
                            class="w-full h-28 border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:border-amber-400 transition">
                            <img id="edit-logo-preview" class="hidden w-full h-full object-contain rounded-xl" />
                            <div id="edit-logo-placeholder">
                                <i class="fa-solid fa-image text-gray-300 text-2xl mb-1"></i>
                                <p class="text-[10px] text-gray-400">Click to change logo</p>
                            </div>
                        </div>
                        <input type="file" id="edit-logo-input" name="suplogoimagecompany" accept="image/*"
                            class="hidden" onchange="previewLogo(this, 'edit-logo-preview', 'edit-logo-placeholder')">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Supplier
                                Name *</label>
                            <input type="text" name="supname" id="edit-supname" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Supplier
                                Type *</label>
                            <div class="flex gap-2">

                                <select name="suptype" id="edit-suptype" required
                                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                                    <option value="">— Select type —</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= htmlspecialchars($t['type_name']) ?>">
                                            <?= htmlspecialchars($t['type_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="openTypeModal()"
                                    class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50 hover:text-amber-500 transition"
                                    title="Manage supplier types">
                                    <i class="fa-solid fa-gear text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Address</label>
                            <input type="text" name="supaddress" id="edit-supaddress"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Country
                                / Region</label>
                            <input type="text" name="countryregion" id="edit-countryregion"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                    </div>

                    <p
                        class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest pt-1 border-t border-gray-100">
                        Contact Person</p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Name</label>
                            <input type="text" name="suppersonname" id="edit-suppersonname"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Job
                                Title</label>
                            <input type="text" name="suppersonjobtitle" id="edit-suppersonjobtitle"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Phone
                                Number</label>
                            <input type="text" name="suppersonnumber" id="edit-suppersonnumber"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Email</label>
                            <input type="email" name="suppersonemail" id="edit-suppersonemail"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        </div>
                    </div>

                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex gap-3 sticky bottom-0 bg-white">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="button" onclick="submitEdit()" id="edit-submit-btn"
                        class="flex-1 px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── TYPE MANAGER MODAL ── -->
    <div id="type-modal" class="fixed inset-0 z-[60] hidden items-center justify-center"
        style="background:rgba(0,0,0,0.4);">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <p class="text-sm font-bold text-gray-800">Manage Supplier Types</p>
                <button onclick="closeTypeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-3 max-h-60 overflow-y-auto" id="type-list">
                <?php foreach ($types as $t): ?>
                    <div class="flex items-center gap-2" id="type-row-<?= $t['id'] ?>">
                        <span class="flex-1 text-sm text-gray-700 truncate" id="type-label-<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['type_name']) ?>
                        </span>
                        <input type="text" id="type-input-<?= $t['id'] ?>" value="<?= htmlspecialchars($t['type_name']) ?>"
                            class="hidden flex-1 border border-gray-200 rounded-lg px-2 py-1 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        <button onclick="startEditType(<?= $t['id'] ?>)" id="type-edit-btn-<?= $t['id'] ?>"
                            class="text-xs px-2 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button onclick="saveEditType(<?= $t['id'] ?>)" id="type-save-btn-<?= $t['id'] ?>"
                            class="hidden text-xs px-2 py-1 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Add new type -->
            <div class="px-6 pb-4 pt-2 border-t border-gray-100 mt-2">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Add New Type</p>
                <div class="flex gap-2">
                    <input type="text" id="new-type-input" placeholder="e.g. Logistics / Delivery"
                        class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                    <button onclick="submitNewType()" id="new-type-btn"
                        class="shrink-0 px-3 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        // ── TYPE MODAL ──
        function openTypeModal() {
            const m = document.getElementById('type-modal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function closeTypeModal() {
            document.getElementById('type-modal').classList.add('hidden');
            document.getElementById('type-modal').classList.remove('flex');
            // Reload to refresh dropdowns with new types
            location.reload();
        }
        document.getElementById('type-modal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeTypeModal();
        });

        function startEditType(id) {
            document.getElementById('type-label-' + id).classList.add('hidden');
            document.getElementById('type-input-' + id).classList.remove('hidden');
            document.getElementById('type-edit-btn-' + id).classList.add('hidden');
            document.getElementById('type-save-btn-' + id).classList.remove('hidden');
            document.getElementById('type-input-' + id).focus();
        }

        function saveEditType(id) {
            const newName = document.getElementById('type-input-' + id).value.trim();
            if (!newName) return;

            fetch('<?= BASE_URL ?>/ps-backendsuppliertype-update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&type_name=${encodeURIComponent(newName)}`
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        document.getElementById('type-label-' + id).textContent = newName;
                        document.getElementById('type-label-' + id).classList.remove('hidden');
                        document.getElementById('type-input-' + id).classList.add('hidden');
                        document.getElementById('type-edit-btn-' + id).classList.remove('hidden');
                        document.getElementById('type-save-btn-' + id).classList.add('hidden');
                    } else {
                        alert(res.error ?? 'Something went wrong.');
                    }
                });
        }

        function submitNewType() {
            const input = document.getElementById('new-type-input');
            const name = input.value.trim();
            if (!name) return;

            const btn = document.getElementById('new-type-btn');
            btn.disabled = true;

            fetch('<?= BASE_URL ?>/ps-backendsuppliertype-insert', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type_name=${encodeURIComponent(name)}`
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // Add new row to list
                        const list = document.getElementById('type-list');
                        const div = document.createElement('div');
                        div.className = 'flex items-center gap-2';
                        div.id = 'type-row-' + res.id;
                        div.innerHTML = `
                <span class="flex-1 text-sm text-gray-700 truncate" id="type-label-${res.id}">${name}</span>
                <input type="text" id="type-input-${res.id}" value="${name}"
                    class="hidden flex-1 border border-gray-200 rounded-lg px-2 py-1 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                <button onclick="startEditType(${res.id})" id="type-edit-btn-${res.id}"
                    class="text-xs px-2 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button onclick="saveEditType(${res.id})" id="type-save-btn-${res.id}"
                    class="hidden text-xs px-2 py-1 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition">
                    <i class="fa-solid fa-check"></i>
                </button>
            `;
                        list.appendChild(div);
                        input.value = '';
                        btn.disabled = false;
                    } else {
                        btn.disabled = false;
                        alert(res.error ?? 'Something went wrong.');
                    }
                });
        }

        // ── Logo Preview ──
        function previewLogo(input, previewId, placeholderId) {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById(previewId);
                const ph = document.getElementById(placeholderId);
                img.src = e.target.result;
                img.classList.remove('hidden');
                ph.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        // ── ADD MODAL ──
        function openAddModal() {
            document.getElementById('add-form').reset();
            document.getElementById('add-logo-preview').classList.add('hidden');
            document.getElementById('add-logo-placeholder').classList.remove('hidden');
            const m = document.getElementById('add-modal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function closeAddModal() {
            document.getElementById('add-modal').classList.add('hidden');
            document.getElementById('add-modal').classList.remove('flex');
        }
        document.getElementById('add-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeAddModal(); });

        function submitAdd() {
            const btn = document.getElementById('add-submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving…';

            const formData = new FormData(document.getElementById('add-form'));

            fetch('<?= BASE_URL ?>/ps-backendsupplier-insert', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closeAddModal();
                        location.reload();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i> Add Supplier';
                        alert(res.error ?? 'Something went wrong.');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i> Add Supplier';
                });
        }

        // ── EDIT MODAL ──
        function openEditModal(data) {
            document.getElementById('edit-supplier-id').value = data.id;
            document.getElementById('edit-existing-logo').value = data.suplogoimagecompany ?? '';
            document.getElementById('edit-supname').value = data.supname ?? '';
            document.getElementById('edit-suptype').value = data.suptype ?? '';
            document.getElementById('edit-supaddress').value = data.supaddress ?? '';
            document.getElementById('edit-countryregion').value = data.countryregion ?? '';
            document.getElementById('edit-suppersonname').value = data.suppersonname ?? '';
            document.getElementById('edit-suppersonjobtitle').value = data.suppersonjobtitle ?? '';
            document.getElementById('edit-suppersonnumber').value = data.suppersonnumber ?? '';
            document.getElementById('edit-suppersonemail').value = data.suppersonemail ?? '';

            // Show existing logo
            const img = document.getElementById('edit-logo-preview');
            const ph = document.getElementById('edit-logo-placeholder');
            if (data.suplogoimagecompany) {
                img.src = '<?= BASE_URL ?>/uploads/' + data.suplogoimagecompany;
                img.classList.remove('hidden');
                ph.classList.add('hidden');
            } else {
                img.classList.add('hidden');
                ph.classList.remove('hidden');
            }

            // Reset file input
            document.getElementById('edit-logo-input').value = '';

            const m = document.getElementById('edit-modal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('flex');
        }
        document.getElementById('edit-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeEditModal(); });

        function submitEdit() {
            const btn = document.getElementById('edit-submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving…';

            const formData = new FormData(document.getElementById('edit-form'));

            fetch('<?= BASE_URL ?>/ps-backendsupplier-update', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closeEditModal();
                        location.reload();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes';
                        alert(res.error ?? 'Something went wrong.');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes';
                });
        }
    </script>

</body>

</html>