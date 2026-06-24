<?php
// warehousereceiver-storage.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSERECEIVER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all slots grouped by location
$stmt = $conn->query("
    SELECT 
        wl.id,
        wl.location_code,
        wl.slot_number,
        wl.capacity,
        wl.order_id,
        wl.po_id,
        wl.assigned_at,
        ppl.nhccreference,
        ppl.contact_name,
        npo.po_number,
        rr.status AS receiver_status
    FROM noblewarehouselocation wl
    LEFT JOIN noblepaidproductlist ppl ON ppl.id = wl.order_id
    LEFT JOIN noblepurchaseorder npo ON npo.id = wl.po_id
    LEFT JOIN noblereceivingreceiver rr ON rr.order_id = wl.order_id AND rr.po_id = wl.po_id AND rr.status = 'received'
    ORDER BY wl.location_code ASC, wl.slot_number ASC
");
$allSlots = $stmt->fetch_all(MYSQLI_ASSOC);

// Group by location_code
$locations = [];
foreach ($allSlots as $slot) {
    $locations[$slot['location_code']][] = $slot;
}

// Stats
$totalSlots    = count($allSlots);
$occupiedSlots = count(array_filter($allSlots, fn($s) => $s['order_id'] !== null));
$emptySlots    = $totalSlots - $occupiedSlots;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Storage Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-slate-800">Storage Management</h1>
            <p class="text-sm text-slate-500 mt-1">Warehouse slot occupancy overview</p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Total Slots</p>
                <p class="text-2xl font-bold text-slate-800"><?= $totalSlots ?></p>
            </div>
            <div class="bg-white rounded-xl border border-emerald-200 p-4 shadow-sm">
                <p class="text-xs text-emerald-600 uppercase tracking-wide mb-1">Available</p>
                <p class="text-2xl font-bold text-emerald-600"><?= $emptySlots ?></p>
            </div>
            <div class="bg-white rounded-xl border border-indigo-200 p-4 shadow-sm">
                <p class="text-xs text-indigo-600 uppercase tracking-wide mb-1">Occupied</p>
                <p class="text-2xl font-bold text-indigo-600"><?= $occupiedSlots ?></p>
            </div>
        </div>

        <!-- Location grids -->
        <div class="space-y-6">
            <?php foreach ($locations as $locationCode => $slots): 
                $occupied = count(array_filter($slots, fn($s) => $s['order_id'] !== null));
                $total    = count($slots);
                $pct      = $total > 0 ? round(($occupied / $total) * 100) : 0;
            ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Location header -->
                    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars($locationCode) ?></span>
                            <span class="text-xs text-slate-400"><?= $occupied ?>/<?= $total ?> occupied</span>
                        </div>
                        <!-- Progress bar -->
                        <div class="flex items-center gap-2">
                            <div class="w-32 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full <?= $pct >= 80 ? 'bg-red-400' : ($pct >= 50 ? 'bg-amber-400' : 'bg-emerald-400') ?>"
                                    style="width: <?= $pct ?>%"></div>
                            </div>
                            <span class="text-xs text-slate-500"><?= $pct ?>%</span>
                        </div>
                    </div>

                    <!-- Slots grid -->
                    <div class="p-4 grid grid-cols-5 gap-2">
                        <?php foreach ($slots as $slot): 
                            $isEmpty = $slot['order_id'] === null;
                        ?>
                            <div class="rounded-lg border p-2.5 text-xs cursor-pointer transition-all
                                <?= $isEmpty 
                                    ? 'border-slate-200 bg-slate-50 hover:bg-slate-100' 
                                    : 'border-indigo-200 bg-indigo-50 hover:bg-indigo-100' ?>"
                                <?= !$isEmpty ? "onclick=\"openSlotModal(" . json_encode($slot) . ")\"" : "" ?>>
                                
                                <!-- Slot number -->
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="font-bold text-slate-600">
                                        Slot <?= $slot['slot_number'] ?>
                                    </span>
                                    <?php if ($isEmpty): ?>
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    <?php else: ?>
                                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isEmpty): ?>
                                    <p class="text-slate-400 text-xs">Available</p>
                                <?php else: ?>
                                    <p class="font-medium text-indigo-700 truncate">
                                        <?= htmlspecialchars($slot['nhccreference'] ?? '—') ?>
                                    </p>
                                    <p class="text-slate-500 truncate text-xs mt-0.5">
                                        <?= htmlspecialchars($slot['contact_name'] ?? '—') ?>
                                    </p>
                                    <?php if ($slot['po_number']): ?>
                                        <p class="font-mono text-xs text-slate-400 mt-0.5 truncate">
                                            <?= htmlspecialchars($slot['po_number']) ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Slot Detail Modal -->
    <div id="slotModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 relative">
            <button onclick="closeSlotModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Slot Details</p>
            <p id="modalSlotLabel" class="text-lg font-bold text-slate-800 mb-4"></p>

            <div class="space-y-2 text-sm mb-5">
                <div class="flex justify-between">
                    <span class="text-slate-500">Reference</span>
                    <span id="modalRef" class="font-medium text-slate-800"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Contact</span>
                    <span id="modalContact" class="text-slate-700"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">PO Number</span>
                    <span id="modalPO" class="font-mono text-xs text-slate-600"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Status</span>
                    <span id="modalStatus" class="font-medium"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Assigned</span>
                    <span id="modalAssigned" class="text-slate-600 text-xs"></span>
                </div>
            </div>

            <button onclick="clearSlot()" id="clearBtn"
                class="w-full px-4 py-2.5 text-sm bg-red-50 hover:bg-red-100 text-red-600 font-medium rounded-lg border border-red-200 transition-colors">
                Clear this slot
            </button>
        </div>
    </div>

    <script>
        let currentSlotId = null;

        function openSlotModal(slot) {
            currentSlotId = slot.id;
            document.getElementById('modalSlotLabel').textContent = slot.location_code + ' — Slot ' + slot.slot_number;
            document.getElementById('modalRef').textContent     = slot.nhccreference  ?? '—';
            document.getElementById('modalContact').textContent = slot.contact_name   ?? '—';
            document.getElementById('modalPO').textContent      = slot.po_number      ?? '—';
            document.getElementById('modalStatus').textContent  = slot.receiver_status ? slot.receiver_status.replace('_', ' ') : '—';

            const assigned = slot.assigned_at
                ? new Date(slot.assigned_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
                : '—';
            document.getElementById('modalAssigned').textContent = assigned;

            document.getElementById('slotModal').classList.remove('hidden');
            document.getElementById('slotModal').classList.add('flex');
        }

        function closeSlotModal() {
            document.getElementById('slotModal').classList.add('hidden');
            document.getElementById('slotModal').classList.remove('flex');
            currentSlotId = null;
        }

        function clearSlot() {
            if (!currentSlotId) return;
            if (!confirm('Are you sure you want to clear this slot?')) return;

            fetch('<?= BASE_URL ?>/warehousereceiver-clearslot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slot_id: currentSlotId }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeSlotModal();
                    window.location.reload();
                } else {
                    alert(data.message ?? 'Failed to clear slot.');
                }
            })
            .catch(() => alert('Network error.'));
        }

        document.getElementById('slotModal').addEventListener('click', function(e) {
            if (e.target === this) closeSlotModal();
        });
    </script>
</body>
</html>