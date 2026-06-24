<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSERECEIVER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$poId = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0; // ✅ ilipat dito, bago ang validation
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$receiverId = isset($_GET['rid']) ? (int) $_GET['rid'] : 0;

if (!$orderId || !$poId || !$token || !$receiverId) { // ✅ idagdag !$poId
    header('Location: ' . BASE_URL . '/warehousereceiver');
    exit;
}

$staffId = $_SESSION['account_id'] ?? 0;

// ✅ Formula kasama na ang po_id
$expected = hash_hmac('sha256', $orderId . '_' . $poId . '_' . $receiverId, defined('QR_SECRET') ? QR_SECRET : 'warehouse_secret');
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    exit('Unauthorized QR code.');
}

// FIX: confirm the logged-in user IS the intended receiver
if ($staffId !== $receiverId) {
    http_response_code(403);
    exit('This QR code is not assigned to your account.');
}

// ✅ Fetch — scoped sa po_id
$stmt = $conn->prepare("
    SELECT
        rr.id           AS assignment_id,
        rr.status       AS receiver_status,
        rr.location,
        npo.po_type,
        ppl.nhccreference,
        ppl.contact_name,
        ppl.delivery_method,
        ot.current_step
    FROM noblereceivingreceiver rr
    JOIN noblepaidproductlist ppl ON ppl.id = rr.order_id
    JOIN noblepurchaseorder npo ON npo.id = rr.po_id
    LEFT JOIN nobleordertracking ot ON ot.po_id = rr.po_id
    WHERE rr.order_id = ? AND rr.po_id = ? AND rr.assigned_staff_id = ?
    LIMIT 1
");
$stmt->bind_param("iii", $orderId, $poId, $staffId);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header('Location: ' . BASE_URL . '/warehousereceiver');
    exit;
}

// Sa locStmt query sa warehousereceiverscan.php
$locStmt = $conn->prepare("
    SELECT
        location_code,
        COUNT(*) AS total_slots,
        SUM(CASE WHEN order_id IS NULL THEN 1 ELSE 0 END) AS available_slots
    FROM noblewarehouselocation
    GROUP BY location_code
    ORDER BY location_code ASC
");
$locStmt->execute();
$locations = $locStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$locStmt->close();

$isCompleted = $data['receiver_status'] === 'received'; // ✅

$statusMap = [
    'pending' => ['Pending', 'bg-amber-50 text-amber-700 border-amber-200'],
    'received' => ['Received', 'bg-emerald-50 text-emerald-700 border-emerald-200'], // ✅
];
$needsLocation = in_array($data['receiver_status'], ['pending', 'in_transit']) && empty($data['location']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Order — <?= htmlspecialchars($data['nhccreference'] ?? '') ?></title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6 flex items-start justify-center pt-20">
        <div class="w-full max-w-md">

            <!-- Order card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-4">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Order reference</p>
                <p class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                    <?= htmlspecialchars($data['nhccreference'] ?? '—') ?>
                    <?php if (($data['po_type'] ?? 'normal') === 'replacement'): ?>
                        <span
                            class="text-[8px] px-2 py-1 rounded-full font-semibold uppercase bg-rose-100 text-rose-700 border border-rose-200">
                            Replacement
                        </span>
                    <?php endif; ?>
                </p>
                <p class="text-sm text-slate-500 mt-0.5"><?= htmlspecialchars($data['contact_name']) ?></p>

                <div class="mt-4 flex items-center gap-3">
                    <span
                        class="capitalize text-xs px-2.5 py-1 rounded-full border
                        <?= $data['delivery_method'] === 'delivery' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                        <?= htmlspecialchars($data['delivery_method']) ?>
                    </span>
                    <?php
                    $statusMap = [
                        'pending' => ['Pending', 'bg-amber-50 text-amber-700 border-amber-200'],
                        'in_transit' => ['In transit', 'bg-blue-50 text-blue-700 border-blue-200'],
                        'in_warehouse' => ['In warehouse', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        'completed' => ['Completed', 'bg-slate-100 text-slate-500 border-slate-200'],
                    ];
                    $sm = $statusMap[$data['receiver_status']] ?? [ucfirst($data['receiver_status']), 'bg-slate-100 text-slate-500 border-slate-200'];
                    ?>
                    <span class="text-xs px-2.5 py-1 rounded-full border <?= $sm[1] ?>"><?= $sm[0] ?></span>
                </div>

                <?php if ($data['location']): ?>
                    <div class="mt-4 bg-slate-50 rounded-lg px-4 py-3 text-sm text-slate-600 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Current location: <strong
                                class="font-medium text-slate-800"><?= htmlspecialchars($data['location']) ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isCompleted): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 text-center">
                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-emerald-700">Item is already marked in warehouse.</p>
                    <?php if ($data['location']): ?>
                        <p class="text-xs text-emerald-600 mt-1">
                            <strong>Location:</strong> <?= htmlspecialchars($data['location']) ?>
                        </p>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/warehousereceiver"
                        class="mt-3 inline-flex text-xs text-emerald-600 hover:text-emerald-700 underline underline-offset-2">
                        Back to assignments
                    </a>
                </div>

            <?php elseif (!empty($data['location'])): ?>
                <!-- Already has a location set but not yet in_warehouse — show info only, no modal -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-blue-700">Warehouse location already set.</p>
                    <p class="text-xs text-blue-600 mt-1"><strong><?= htmlspecialchars($data['location']) ?></strong></p>
                    <a href="<?= BASE_URL ?>/warehousereceiver"
                        class="mt-3 inline-flex text-xs text-blue-600 hover:text-blue-700 underline underline-offset-2">
                        Back to assignments
                    </a>
                </div>

            <?php else: ?>
                <!-- No location yet — show the assign button -->
                <button onclick="openModal()"
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Assign Warehouse Location
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="actionModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 relative">

            <button onclick="closeModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Main panel -->
            <div id="panelMain">
                <p class="text-base font-semibold text-slate-800 mb-1">Assign to warehouse</p>
                <p class="text-sm text-slate-500 mb-5">Select a location for this item.</p>

                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Warehouse location</label>
                    <select id="locationSelect" onchange="onLocationChange()"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-indigo-400 bg-white">
                        <option value="">— Select a location —</option>
                        <?php foreach ($locations as $loc):
                            $isFull = $loc['available_slots'] == 0;
                            ?>
                            <option value="<?= htmlspecialchars($loc['location_code']) ?>" <?= $isFull ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($loc['location_code']) ?>
                                — <?= $loc['available_slots'] ?>/<?= $loc['total_slots'] ?> slots available
                                <?= $isFull ? '(FULL)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="occupantPreview" class="hidden mb-4 rounded-lg px-4 py-3 text-sm border"></div>

                <div class="mb-5">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes <span
                            class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="locationNotes" rows="2" placeholder="Any additional notes…"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-indigo-400 resize-none placeholder:text-slate-300"></textarea>
                </div>

                <div class="flex gap-2">
                    <button onclick="closeModal()"
                        class="flex-1 px-4 py-2.5 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                        Cancel
                    </button>
                    <button id="confirmBtn" onclick="submitLocation()" disabled
                        class="flex-1 px-4 py-2.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        Confirm & mark received
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div id="panelLoading" class="hidden flex-col items-center py-6 gap-3">
                <div class="w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <p class="text-sm text-slate-500">Saving…</p>
            </div>

            <!-- Success -->
            <div id="panelSuccess" class="hidden flex-col items-center py-6 gap-3 text-center">
                <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p id="successMsg" class="text-sm font-medium text-slate-800"></p>
                <button onclick="window.location.reload()"
                    class="mt-1 px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                    Done
                </button>
            </div>
        </div>
    </div>

    <script>
        const ORDER_ID = <?= $orderId ?>;
        const PO_ID = <?= $poId ?>;

        function openModal() {
            showPanel('panelMain');
            document.getElementById('actionModal').classList.remove('hidden');
            document.getElementById('actionModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('actionModal').classList.add('hidden');
            document.getElementById('actionModal').classList.remove('flex');
        }

        function showPanel(id) {
            ['panelMain', 'panelLoading', 'panelSuccess'].forEach(p => {
                const el = document.getElementById(p);
                el.classList.add('hidden');
                el.classList.remove('flex');
            });
            const target = document.getElementById(id);
            target.classList.remove('hidden');
            if (id !== 'panelMain') target.classList.add('flex');
        }

        function onLocationChange() {
            const select = document.getElementById('locationSelect');
            const preview = document.getElementById('occupantPreview');
            const btn = document.getElementById('confirmBtn');
            const opt = select.options[select.selectedIndex];

            if (!select.value) {
                preview.classList.add('hidden');
                btn.disabled = true;
                return;
            }

            const occupied = opt.dataset.occupied === '1';
            const ref = opt.dataset.ref;
            const contact = opt.dataset.contact;
            const occupantId = parseInt(opt.dataset.orderId);
            const isSelf = occupantId === ORDER_ID;

            if (occupied && !isSelf) {
                preview.className = 'mb-4 rounded-lg px-4 py-3 text-sm border bg-amber-50 border-amber-200 text-amber-800';
                preview.innerHTML = `
                    <p class="font-medium mb-0.5">⚠ Currently occupied</p>
                    <p class="text-xs text-amber-700">${ref} — ${contact}</p>
                    <p class="text-xs text-amber-600 mt-1">Assigning here will move the current item out.</p>
                `;
                preview.classList.remove('hidden');
                btn.disabled = false;
            } else if (isSelf) {
                preview.className = 'mb-4 rounded-lg px-4 py-3 text-sm border bg-blue-50 border-blue-200 text-blue-800';
                preview.innerHTML = `<p class="font-medium">This order is already assigned here.</p>`;
                preview.classList.remove('hidden');
                btn.disabled = false;
            } else {
                preview.className = 'mb-4 rounded-lg px-4 py-3 text-sm border bg-emerald-50 border-emerald-200 text-emerald-800';
                preview.innerHTML = `<p class="font-medium">✓ Available</p>`;
                preview.classList.remove('hidden');
                btn.disabled = false;
            }
        }

        function submitLocation() {
            const loc = document.getElementById('locationSelect').value;
            if (!loc) return;

            showPanel('panelLoading');

            fetch('<?= BASE_URL ?>/warehousereceiverscanupdate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: ORDER_ID,
                    po_id: PO_ID,
                    action: 'mark_in_warehouse',
                    location: loc,
                    notes: document.getElementById('locationNotes').value.trim(),
                }),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('successMsg').textContent = data.message ?? 'Saved successfully.';
                        showPanel('panelSuccess');
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        showPanel('panelMain');
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                    showPanel('panelMain');
                });
        }

        document.getElementById('actionModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        window.addEventListener('DOMContentLoaded', () => {
            <?php if (!$isCompleted && empty($data['location'])): ?>
                setTimeout(() => openModal(), 400);
            <?php endif; ?>
        });
    </script>
</body>

</html>