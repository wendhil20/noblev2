<?php
// admin/ui-warehouse/page-1/warehouse-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$headId = intval($_SESSION['account_id'] ?? 0);

$orders = [];
$res = $conn->query("
    SELECT o.id, o.nhccreference, o.contact_name, o.delivery_method,
           o.grand_total, o.created_at, COUNT(i.id) AS item_count,
           o.order_status,
           MAX(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) AS is_assigned
    FROM noblepaidproductlist o
    LEFT JOIN noblepaidproductitems i ON i.order_id = o.id
    LEFT JOIN nobleorderassignment a ON a.order_id = o.id
    WHERE o.payment_status = 'approved'
    GROUP BY o.id
    ORDER BY o.created_at ASC
");
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}

$staffList = [];
$res2 = $conn->query("
 SELECT id, name, email 
FROM noblerole 
WHERE role = '" . ROLE_WAREHOUSE . "' 
AND position = '" . POSITION_WAREHOUSESTAFF . "'
");
while ($row = $res2->fetch_assoc()) {
    $staffList[] = $row;
}

$staffSummary = [];
$res3 = $conn->query("
    SELECT a.staff_id, nr.name,
           COUNT(a.id) AS total_assigned,
           SUM(a.status = 'assigned') AS pending,
           SUM(a.status = 'in_progress') AS in_progress,
           SUM(a.status = 'done') AS done,
           SUM(a.type = 'replacement') AS replacement_count
    FROM nobleorderassignment a
    JOIN noblerole nr ON nr.id = a.staff_id
    GROUP BY a.staff_id
");
while ($row = $res3->fetch_assoc()) {
    $staffSummary[$row['staff_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen p-6">

        <h1 class="text-lg font-bold text-gray-800 mb-1">Warehouse Orders</h1>
        <p class="text-xs text-gray-400 mb-6">Assign approved orders to your warehouse staff.</p>

        <div class="grid grid-cols-3 gap-6">

            <div class="col-span-2 space-y-3">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">
                    Approved Orders (<?= count($orders) ?>)
                </p>

                <?php if (empty($orders)): ?>
                    <div class="bg-white rounded-xl border border-gray-100 px-6 py-10 text-center">
                        <i class="fa-solid fa-box-open text-gray-200 text-3xl mb-3"></i>
                        <p class="text-xs text-gray-400">No approved orders yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <?php $isReplacement = ($o['order_status'] ?? '') === 'replacement'; ?>
                        <div class="bg-white rounded-xl border <?= $isReplacement ? 'border-rose-100' : 'border-gray-100' ?> px-4 py-3 flex items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="font-mono text-xs font-bold text-gray-800">
                                        <?= htmlspecialchars($o['nhccreference'] ?? '—') ?>
                                    </span>
                                    <span
                                        class="text-[10px] px-2 py-0.5 rounded-full capitalize
                                    <?= $o['delivery_method'] === 'delivery' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= htmlspecialchars($o['delivery_method']) ?>
                                    </span>
                                    <?php if ($isReplacement): ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-semibold uppercase tracking-wide">
                                            ↺ Replacement
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-600"><?= htmlspecialchars($o['contact_name']) ?></p>
                                <p class="text-[10px] text-gray-400">
                                    <?= intval($o['item_count']) ?> item<?= $o['item_count'] != 1 ? 's' : '' ?> ·
                                    ₱<?= number_format(floatval($o['grand_total']), 2) ?> ·
                                    <?= date('M d, Y', strtotime($o['created_at'])) ?>
                                </p>
                            </div>

                            <?php if ($o['is_assigned'] && !$isReplacement): ?>
                                <button disabled
                                    class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold bg-green-500 text-white cursor-not-allowed opacity-80">
                                    <i class="fa-solid fa-check mr-1"></i> Assigned
                                </button>
                            <?php else: ?>
                                <div class="flex flex-col items-end gap-1">
                                    <button id="assign-btn-<?= $o['id'] ?>"
                                        onclick="openAssign(<?= $o['id'] ?>, '<?= htmlspecialchars($o['nhccreference'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($o['contact_name'], ENT_QUOTES) ?>')"
                                        class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-all duration-150
                                            <?= $isReplacement ? 'bg-rose-500 hover:bg-rose-600' : 'bg-amber-500 hover:bg-amber-600' ?>">
                                        <i class="fa-solid <?= $isReplacement ? 'fa-rotate' : 'fa-user-plus' ?> mr-1"></i>
                                        <?= $isReplacement ? 'Re-assign' : 'Assign' ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="space-y-3">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">
                    Staff Workload
                </p>

                <?php if (empty($staffList)): ?>
                    <div class="bg-white rounded-xl border border-gray-100 px-4 py-6 text-center">
                        <p class="text-xs text-gray-400">No staff found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($staffList as $staff): ?>
                        <?php $s = $staffSummary[$staff['id']] ?? null; ?>
                        <div class="bg-white rounded-xl border border-gray-100 px-4 py-3">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded-full bg-amber-400 flex items-center justify-center
                                        text-white text-xs font-bold shrink-0">
                                    <?= strtoupper(substr($staff['name'], 0, 1)) ?>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 truncate">
                                        <?= htmlspecialchars($staff['name']) ?>
                                    </p>
                                    <p class="text-[10px] text-gray-400 truncate"><?= htmlspecialchars($staff['email']) ?></p>
                                </div>
                            </div>

                            <?php if ($s): ?>
                                <div class="grid grid-cols-3 gap-1 mt-2">
                                    <div class="text-center bg-amber-50 rounded-lg py-1.5">
                                        <p class="text-sm font-bold text-amber-600"><?= $s['pending'] ?></p>
                                        <p class="text-[9px] text-amber-500">Assigned</p>
                                    </div>
                                    <div class="text-center bg-blue-50 rounded-lg py-1.5">
                                        <p class="text-sm font-bold text-blue-600"><?= $s['in_progress'] ?></p>
                                        <p class="text-[9px] text-blue-500">In Progress</p>
                                    </div>
                                    <div class="text-center bg-green-50 rounded-lg py-1.5">
                                        <p class="text-sm font-bold text-green-600"><?= $s['done'] ?></p>
                                        <p class="text-[9px] text-green-500">Done</p>
                                    </div>
                                </div>
                                <?php if (intval($s['replacement_count']) > 0): ?>
                                    <p class="text-[9px] text-rose-500 text-center mt-1.5 font-semibold">
                                        ↺ <?= intval($s['replacement_count']) ?> replacement<?= $s['replacement_count'] != 1 ? 's' : '' ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-[10px] text-gray-400 text-center mt-2">No assignments yet</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Assign Modal -->
    <div id="assign-modal" class="fixed inset-0 z-50 hidden items-center justify-center"
        style="background: rgba(0,0,0,0.4);">

        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <p class="text-sm font-bold text-gray-800" id="assign-modal-title">Assign Order</p>
                    <p class="text-[10px] text-gray-400" id="assign-modal-ref"></p>
                </div>
                <button onclick="closeAssign()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">
                        Select Staff
                    </label>
                    <select id="assign-staff-select"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400">
                        <option value="">— Choose staff —</option>
                        <?php foreach ($staffList as $staff): ?>
                            <option value="<?= $staff['id'] ?>">
                                <?= htmlspecialchars($staff['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">
                        Notes <span class="text-gray-300">(optional)</span>
                    </label>
                    <textarea id="assign-notes" rows="2" placeholder="e.g. Handle with care, priority order…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700
                           focus:outline-none focus:border-amber-400 resize-none"></textarea>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeAssign()"
                    class="flex-1 px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button id="submit-assign-btn" onclick="submitAssign()"
                    class="flex-1 px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition">
                    <i class="fa-solid fa-user-plus mr-1"></i> Assign
                </button>
            </div>
        </div>
    </div>

    <script>
        let assignOrderId = null;
        let assignIsReplacement = false;

        // Build a map of order_id => is_replacement from PHP
        const replacementOrders = {
            <?php foreach ($orders as $o): ?>
                <?= $o['id'] ?>: <?= ($o['order_status'] === 'replacement') ? 'true' : 'false' ?>,
            <?php endforeach; ?>
        };

        function openAssign(orderId, ref, customer) {
            assignOrderId = orderId;
            assignIsReplacement = replacementOrders[orderId] ?? false;

            document.getElementById('assign-modal-ref').textContent = ref + ' · ' + customer;
            document.getElementById('assign-modal-title').textContent = assignIsReplacement ? 'Re-assign Order' : 'Assign Order';
            document.getElementById('assign-staff-select').value = '';
            document.getElementById('assign-notes').value = '';

            const btn = document.getElementById('submit-assign-btn');
            if (assignIsReplacement) {
                btn.className = 'flex-1 px-4 py-2 rounded-lg text-xs font-semibold bg-rose-500 hover:bg-rose-600 text-white transition';
                btn.innerHTML = '<i class="fa-solid fa-rotate mr-1"></i> Re-assign';
            } else {
                btn.className = 'flex-1 px-4 py-2 rounded-lg text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white transition';
                btn.innerHTML = '<i class="fa-solid fa-user-plus mr-1"></i> Assign';
            }

            const modal = document.getElementById('assign-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeAssign() {
            document.getElementById('assign-modal').classList.add('hidden');
            document.getElementById('assign-modal').classList.remove('flex');
            assignOrderId = null;
            assignIsReplacement = false;
        }

        document.getElementById('assign-modal').addEventListener('click', function(e) {
            if (e.target === this) closeAssign();
        });

        function submitAssign() {
            const staffId = document.getElementById('assign-staff-select').value;
            const notes = document.getElementById('assign-notes').value.trim();

            if (!staffId) {
                alert('Please select a staff member.');
                return;
            }

            const btn = document.getElementById('submit-assign-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> ' + (assignIsReplacement ? 'Re-assigning…' : 'Assigning…');

            fetch('<?= BASE_URL ?>/warehouseassign', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `order_id=${assignOrderId}&staff_id=${staffId}&notes=${encodeURIComponent(notes)}`
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closeAssign();
                        const ordBtn = document.getElementById('assign-btn-' + assignOrderId);
                        if (ordBtn) {
                            ordBtn.disabled = true;
                            ordBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Assigned';
                            ordBtn.className = 'shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold bg-green-500 text-white cursor-not-allowed opacity-80';
                        }
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false;
                        if (assignIsReplacement) {
                            btn.innerHTML = '<i class="fa-solid fa-rotate mr-1"></i> Re-assign';
                        } else {
                            btn.innerHTML = '<i class="fa-solid fa-user-plus mr-1"></i> Assign';
                        }
                        alert(res.error ?? 'Something went wrong.');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    if (assignIsReplacement) {
                        btn.innerHTML = '<i class="fa-solid fa-rotate mr-1"></i> Re-assign';
                    } else {
                        btn.innerHTML = '<i class="fa-solid fa-user-plus mr-1"></i> Assign';
                    }
                });
        }
    </script>

</body>

</html>