<?php
// tracklist-main.php
// File: admin/ui-productspecialist/page-4/tracklist-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all trucks
$trucks = [];
$result = $conn->query("SELECT * FROM nobletrucklist ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $trucks[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracklist Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Truck List</h1>
                <p class="text-sm text-slate-500 mt-0.5">Manage truck records — add, update, or remove entries.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/ps-warehousebase"
                    class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg shadow transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Warehouse Base
                </a>
                <button onclick="openModal()"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Truck
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead
                        class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Variant</th>
                            <th class="px-5 py-3">Base Fare</th>
                            <th class="px-5 py-3">Add/km</th>
                            <th class="px-5 py-3">Per km Rate</th>
                            <th class="px-5 py-3">L × W × H (ft)</th>
                            <th class="px-5 py-3">Max m³</th>
                            <th class="px-5 py-3">Max kg</th>
                            <th class="px-5 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($trucks)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-12 text-slate-400">
                                    No trucks found. Click <strong>Add Truck</strong> to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trucks as $i => $t): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-5 py-3 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-5 py-3 font-medium text-slate-800"><?= htmlspecialchars($t['nametruck']) ?>
                                    </td>
                                    <td class="px-5 py-3"><?= htmlspecialchars($t['trucktype']) ?></td>
                                    <td class="px-5 py-3"><?= htmlspecialchars($t['truckvariant']) ?></td>
                                    <td class="px-5 py-3">₱<?= number_format($t['basefare'], 2) ?></td>
                                    <td class="px-5 py-3">₱<?= number_format($t['addperkm'], 2) ?></td>
                                    <td class="px-5 py-3">₱<?= number_format($t['perkmrate'], 2) ?></td>
                                    <td class="px-5 py-3"><?= $t['length'] ?> × <?= $t['width'] ?> × <?= $t['height'] ?></td>
                                    <td class="px-5 py-3"><?= $t['maxcubicmeter'] ?></td>
                                    <td class="px-5 py-3"><?= number_format($t['maxweightcapacity'], 2) ?></td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='openModal(<?= json_encode($t) ?>)'
                                                class="text-blue-600 hover:text-blue-800 text-xs font-semibold px-3 py-1 rounded-lg border border-blue-200 hover:bg-blue-50 transition">
                                                Edit
                                            </button>
                                            <button onclick="deleteTruck(<?= $t['id'] ?>)"
                                                class="text-red-500 hover:text-red-700 text-xs font-semibold px-3 py-1 rounded-lg border border-red-200 hover:bg-red-50 transition">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Modal ── -->
    <div id="truck-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden animate-fade-up">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-800" id="modal-title">Add Truck</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-5 overflow-y-auto max-h-[75vh]">
                <input type="hidden" id="truck-id">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Truck Name</label>
                        <input type="text" id="f-nametruck" placeholder="e.g. Elf Dropside"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Truck Type</label>
                        <input type="text" id="f-trucktype" placeholder="e.g. Light Truck"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Truck Variant</label>
                        <input type="text" id="f-truckvariant" placeholder="e.g. Open"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Base Fare (₱)</label>
                        <input type="number" id="f-basefare" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Add per km (₱)</label>
                        <input type="number" id="f-addperkm" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Per km Rate (₱)</label>
                        <input type="number" id="f-perkmrate" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Dimensions in feet -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Length (ft)</label>
                        <input type="number" id="f-length" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Width (ft)</label>
                        <input type="number" id="f-width" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Height (ft)</label>
                        <input type="number" id="f-height" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Auto-computed cubic meter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">
                            Max Cubic Meter (m³)
                            <span class="text-blue-400 font-normal ml-1">auto-computed</span>
                        </label>
                        <input type="number" id="f-maxcubicmeter" step="0.01" placeholder="Auto-computed" readonly
                            class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-500 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Max Weight Capacity (kg)</label>
                        <input type="number" id="f-maxweightcapacity" step="0.01" placeholder="0.00"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50">
                <button onclick="closeModal()"
                    class="text-sm font-medium text-slate-600 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button onclick="saveTruck()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-lg shadow transition">
                    Save Truck
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes fade-up {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-up {
            animation: fade-up 0.25s ease forwards;
        }
    </style>

    <script>
        const modal = document.getElementById('truck-modal');
        const FIELDS = ['nametruck', 'trucktype', 'truckvariant', 'basefare', 'addperkm', 'perkmrate', 'length', 'width', 'height', 'maxcubicmeter', 'maxweightcapacity'];

        // ── Auto-compute cubic meter (feet → m³) ─────────────────
        function computeCubicMeter() {
            const l = parseFloat(document.getElementById('f-length').value) || 0;
            const w = parseFloat(document.getElementById('f-width').value) || 0;
            const h = parseFloat(document.getElementById('f-height').value) || 0;

            if (l > 0 && w > 0 && h > 0) {
                const cubic = (l * w * h) / 35.3147;
                document.getElementById('f-maxcubicmeter').value = cubic.toFixed(2);
            } else {
                document.getElementById('f-maxcubicmeter').value = '';
            }
        }

        ['f-length', 'f-width', 'f-height'].forEach(id => {
            document.getElementById(id).addEventListener('input', computeCubicMeter);
        });

        // ── Modal ─────────────────────────────────────────────────
        function openModal(truck = null) {
            document.getElementById('truck-id').value = '';
            FIELDS.forEach(f => document.getElementById('f-' + f).value = '');
            document.getElementById('modal-title').textContent = 'Add Truck';

            if (truck) {
                document.getElementById('modal-title').textContent = 'Edit Truck';
                document.getElementById('truck-id').value = truck.id;
                FIELDS.forEach(f => {
                    const el = document.getElementById('f-' + f);
                    if (el) el.value = truck[f] ?? '';
                });
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

        // ── Save (insert or update) ───────────────────────────────
        async function saveTruck() {
            const id = document.getElementById('truck-id').value;

            const data = { id };
            FIELDS.forEach(f => data[f] = document.getElementById('f-' + f).value.trim());

            if (!data.nametruck || !data.trucktype) {
                alert('Truck Name and Type are required.');
                return;
            }

            const route = id ? 'ps-backendtrucklist-update' : 'ps-backendtrucklist-insert';

            try {
                const res = await fetch(`<?= BASE_URL ?>/${route}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Something went wrong.'));
                }
            } catch (e) {
                alert('Failed to connect. Please try again.');
            }
        }

        // ── Delete ────────────────────────────────────────────────
        async function deleteTruck(id) {
            if (!confirm('Delete this truck? This cannot be undone.')) return;

            try {
                const res = await fetch(`<?= BASE_URL ?>/ps-backendtrucklist-delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await res.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Something went wrong.'));
                }
            } catch (e) {
                alert('Failed to connect. Please try again.');
            }
        }
    </script>

</body>

</html>