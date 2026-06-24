<?php
// humanresource-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-800">Manage Accounts</h1>
            <p class="text-sm text-gray-400 mt-1">All registered department accounts</p>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <span class="text-sm font-semibold text-gray-700">Account List</span>
                <div class="flex items-center gap-2">
                    <span id="last-updated" class="text-[10px] text-gray-400"></span>
                    <div id="pulse" class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-[11px] font-semibold text-black uppercase">
                            <th class="px-5 py-3 text-left">ID</th>
                            <th class="px-5 py-3 text-left">Name</th>
                            <th class="px-5 py-3 text-left">Email</th>
                            <th class="px-5 py-3 text-left">Role / Department</th>
                            <th class="px-5 py-3 text-left">Position</th>
                            <th class="px-5 py-3 text-left">Created At</th>
                            <th class="px-5 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="accounts-tbody">
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">
                                <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-base font-bold text-gray-800">Edit Account</h2>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <form class="space-y-4" onsubmit="return false;">
                    <input type="hidden" id="edit-id">
                    <!-- Name -->
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Name</label>
                        <input id="edit-name" type="text"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-amber-400 transition"
                            placeholder="Full name">
                    </div>

                    <!-- New Password -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">New
                            Password</label>
                        <div class="relative">
                            <input id="edit-password" type="password"
                                class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-amber-400 transition pr-10"
                                placeholder="Leave blank to keep current">
                            <button type="button" onclick="togglePasswordVisibility()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i id="toggle-eye" class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1">Leave blank if you don't want to change the password.
                        </p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Confirm
                            Password</label>
                        <input id="edit-confirm-password" type="password"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-amber-400 transition"
                            placeholder="Repeat new password">
                    </div>

                    <!-- Error message -->
                    <p id="edit-error" class="text-xs text-red-500 hidden"></p>
                </form>

                <div class="flex gap-3 mt-6">
                    <button onclick="closeEditModal()"
                        class="flex-1 border border-gray-200 text-gray-600 text-sm font-medium py-2 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button onclick="saveEdit()"
                        class="flex-1 bg-amber-400 hover:bg-amber-500 text-white text-sm font-semibold py-2 rounded-lg transition">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        let previousCount = 0;

        function renderAccounts(data) {
            const tbody = document.getElementById('accounts-tbody');

            tbody.innerHTML = data.map((row) => `
        <tr class="border-t border-gray-100 hover:bg-amber-50 transition-colors">
            <td class="px-5 py-3 text-gray-400 font-mono text-xs">${row.id}</td>
            <td class="px-5 py-3 font-medium text-gray-800">${row.name}</td>
            <td class="px-5 py-3 text-gray-500">${row.email}</td>
            <td class="px-5 py-3">
                <span class="bg-amber-100 text-amber-700 text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">
                    ${row.role}
                </span>
            </td>
            <td class="px-5 py-3">
                <select onchange="changePosition(${row.id}, this.value)"
                    class="${row.position === 'head' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'} 
                    text-[10px] font-semibold px-3 py-1 rounded-full uppercase tracking-wide border-none outline-none cursor-pointer transition-all">
                    <option value="staff" ${row.position === 'staff' ? 'selected' : ''}>Staff</option>
                    <option value="head" ${row.position === 'head' ? 'selected' : ''}>Head</option>
                    <option value="custodian" ${row.position === 'custodian' ? 'selected' : ''}>Custodian</option>
                    <option value="custoassistant" ${row.position === 'custoassistant' ? 'selected' : ''}>Custodian Assistant</option>
                    <option value="warehousestaff" ${row.position === 'warehousestaff' ? 'selected' : ''}>Warehouse Staff</option>
                    <option value="warehousereceiver" ${row.position === 'warehousereceiver' ? 'selected' : ''}>Warehouse Receiver</option>
                    <option value="logisticstaff" ${row.position === 'logisticstaff' ? 'selected' : ''}>Logistic Staff</option>
                    <option value="logisticdispatcher" ${row.position === 'logisticdispatcher' ? 'selected' : ''}>Logistic Dispatcher</option>
                    <option value="productspecialiststaff" ${row.position === 'productspecialiststaff' ? 'selected' : ''}>Product Specialist Staff</option>
                </select>
            </td>
            <td class="px-5 py-3 text-gray-400 text-xs font-mono">${row.created_at}</td>
            <td class="px-5 py-3">
                <button onclick="openEditModal(${row.id}, '${row.name.replace(/'/g, "\\'")}')"
                    class="bg-amber-100 hover:bg-amber-200 text-amber-700 text-[10px] font-semibold px-3 py-1 rounded-full uppercase tracking-wide transition-all">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                </button>
            </td>
        </tr>
    `).join('');

            const now = new Date();
            document.getElementById('last-updated').textContent =
                'Updated ' + now.toLocaleTimeString('en-PH');
        }

        function fetchAccounts(forceRender = false) {
            fetch('<?= BASE_URL ?>/hr-backendfetch')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('accounts-tbody');

                    if (!data.length) {
                        tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No accounts found.</td></tr>`;
                        previousCount = 0;
                        return;
                    }

                    if (forceRender || data.length !== previousCount) {
                        previousCount = data.length;
                        renderAccounts(data);
                    }
                })
                .catch(() => {
                    document.getElementById('accounts-tbody').innerHTML =
                        `<tr><td colspan="7" class="px-5 py-8 text-center text-red-400">Failed to load data.</td></tr>`;
                });
        }

        function changePosition(id, newPosition) {
            fetch('<?= BASE_URL ?>/hr-backendposition', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, position: newPosition })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const select = document.querySelector(`select[onchange="changePosition(${id}, this.value)"]`);
                        if (select) {
                            select.className = `${newPosition === 'head' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'} 
                    text-[10px] font-semibold px-3 py-1 rounded-full uppercase tracking-wide border-none outline-none cursor-pointer transition-all`;
                        }
                    }
                });
        }

        // ─── Edit Modal ───────────────────────────────────────────────

        function openEditModal(id, name) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-password').value = '';
            document.getElementById('edit-confirm-password').value = '';
            document.getElementById('edit-error').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('hidden');
            document.getElementById('edit-modal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('flex');
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('edit-password');
            const icon = document.getElementById('toggle-eye');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function saveEdit() {
            const id = document.getElementById('edit-id').value;
            const name = document.getElementById('edit-name').value.trim();
            const password = document.getElementById('edit-password').value;
            const confirmPassword = document.getElementById('edit-confirm-password').value;
            const errorEl = document.getElementById('edit-error');

            // Validation
            if (!name) {
                errorEl.textContent = 'Name is required.';
                errorEl.classList.remove('hidden');
                return;
            }

            if (password && password !== confirmPassword) {
                errorEl.textContent = 'Passwords do not match.';
                errorEl.classList.remove('hidden');
                return;
            }

            errorEl.classList.add('hidden');

            const payload = { id, name };
            if (password) payload.password = password;

            fetch('<?= BASE_URL ?>/hr-backendupdate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeEditModal();
                        fetchAccounts(true); // Re-render with updated data
                    } else {
                        errorEl.textContent = data.message || 'Update failed. Try again.';
                        errorEl.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    errorEl.textContent = 'Network error. Try again.';
                    errorEl.classList.remove('hidden');
                });
        }

        // Close modal on backdrop click
        document.getElementById('edit-modal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        // Initial load
        fetchAccounts(true);

        // Re-check on tab focus
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                fetchAccounts();
            }
        });
    </script>

</body>

</html>