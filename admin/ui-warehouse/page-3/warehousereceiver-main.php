<?php
// warehousereceiver-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSERECEIVER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$staffId = $_SESSION['account_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT
        rr.id               AS assignment_id,
        rr.order_id,
        rr.po_id,
        rr.status           AS receiver_status,
        rr.ready_for_booking,
        rr.suggested_date_from,
        rr.suggested_date_to,
        rr.assigned_at,
        rr.qr_path,
        rr.location,
        npo.po_number,
        npo.po_type,
        ppl.nhccreference,
        ppl.contact_name,
        ppl.delivery_method,
        ot.current_step,
        ot.expected_delivery_from,
        ot.expected_delivery_to
    FROM noblereceivingreceiver rr
    JOIN noblepaidproductlist ppl ON ppl.id = rr.order_id
    JOIN noblepurchaseorder npo ON npo.id = rr.po_id
    LEFT JOIN nobleordertracking ot ON ot.po_id = rr.po_id
    WHERE rr.assigned_staff_id = ?
    ORDER BY rr.assigned_at DESC
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group by nhccreference to check ready for booking eligibility
$refGroups = [];
foreach ($assignments as $row) {
    $ref = $row['nhccreference'];
    if (!isset($refGroups[$ref])) {
        $refGroups[$ref] = ['total' => 0, 'received_with_location' => 0, 'ready_for_booking' => 0, 'order_id' => $row['order_id']];
    }
    $refGroups[$ref]['total']++;
    if ($row['receiver_status'] === 'received' && !empty($row['location'])) {
        $refGroups[$ref]['received_with_location']++;
    }
    if ($row['ready_for_booking']) {
        $refGroups[$ref]['ready_for_booking']++;
    }
}

$statusLabels = [
    'pending' => ['label' => 'Pending', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
    'received' => ['label' => 'Received', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
];

$qrData = [];
foreach ($assignments as $row) {
    $token = hash_hmac('sha256', $row['order_id'] . '_' . $row['po_id'] . '_' . $staffId, defined('QR_SECRET') ? QR_SECRET : 'warehouse_secret');
    $scanUrl = BASE_URL . '/warehousereceiverscan?order_id=' . $row['order_id'] . '&po_id=' . $row['po_id'] . '&rid=' . $staffId . '&token=' . $token;
    $qrData[$row['po_id']] = [
        'url' => $scanUrl,
        'ref' => $row['nhccreference'] ?? 'order-' . $row['order_id'],
        'token' => $token,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Receiving Assignments</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800">My Assignments</h1>
                <p class="text-sm text-slate-500 mt-1">Orders assigned to you for receiving</p>
            </div>
            <!-- Real-time indicator -->
            <div class="flex items-center gap-2 text-xs text-slate-400" id="realtimeIndicator">
                <span class="relative flex h-2 w-2">
                    <span id="rtPulse"
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span id="rtDot" class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span id="rtLabel">Live</span>
            </div>
        </div>

        <?php if (empty($assignments)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <p class="text-slate-500 text-sm">No assignments yet.</p>
            </div>

        <?php else: ?>

            <?php
            // Group assignments by nhccreference for display
            $grouped = [];
            foreach ($assignments as $row) {
                $grouped[$row['nhccreference']][] = $row;
            }
            ?>

            <div class="space-y-6" id="assignmentsContainer">
                <?php foreach ($grouped as $ref => $rows):
                    $refInfo = $refGroups[$ref];
                    $allReady = $refInfo['total'] > 0 && $refInfo['total'] === $refInfo['received_with_location'];
                    $isBooked = $refInfo['ready_for_booking'] > 0;
                    $firstRow = $rows[0];

                    // Build scheduled rows JSON for modal (rows that already have a schedule)
                    $scheduledRows = array_filter(
                        $rows,
                        fn($r) =>
                        $r['ready_for_booking'] == 1 &&
                        !empty($r['suggested_date_from'])
                    );
                    $scheduledJson = htmlspecialchars(json_encode(array_values(array_map(fn($r) => [
                        'po_number' => $r['po_number'],
                        'po_type' => $r['po_type'] ?? 'normal',
                        'date_from' => $r['suggested_date_from'],
                        'date_to' => $r['suggested_date_to'],
                    ], $scheduledRows))), ENT_QUOTES);
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

                        <!-- Reference header -->
                        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50"
                            data-ref="<?= htmlspecialchars($ref, ENT_QUOTES) ?>">
                            <div>
                                <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars($ref) ?></span>
                                <span
                                    class="ml-2 text-xs text-slate-400"><?= htmlspecialchars($firstRow['contact_name']) ?></span>
                            </div>
                            <div class="ref-action-area flex items-center gap-2">
                                <?php if ($isBooked): ?>
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        Ready for Booking
                                    </span>
                                <?php elseif ($allReady): ?>
                                    <button
                                        onclick="markReadyForBooking(<?= $refInfo['order_id'] ?>, '<?= htmlspecialchars($ref, ENT_QUOTES) ?>', <?= $scheduledJson ?>)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Mark as Ready for Booking
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">
                                        <?= $refInfo['received_with_location'] ?>/<?= $refInfo['total'] ?> items received
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- POs table -->
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100">
                                    <th
                                        class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-2.5">
                                        PO Number</th>
                                    <th
                                        class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-2.5">
                                        Method</th>
                                    <th
                                        class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-2.5">
                                        Status</th>
                                    <th
                                        class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-2.5">
                                        Location</th>
                                    <th
                                        class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-2.5">
                                        Assigned</th>
                                    <th class="px-5 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($rows as $row):
                                    $badge = $statusLabels[$row['receiver_status']] ?? ['label' => ucfirst($row['receiver_status']), 'class' => 'bg-slate-100 text-slate-500 border-slate-200'];
                                    $qr = $qrData[$row['po_id']];
                                    $isReplacementPO = ($row['po_type'] ?? 'normal') === 'replacement';
                                    ?>
                                    <tr class="hover:bg-slate-50 transition-colors"
                                        data-assignment-id="<?= $row['assignment_id'] ?>" data-po-id="<?= $row['po_id'] ?>"
                                        data-order-id="<?= $row['order_id'] ?>">
                                        <td class="px-5 py-3 font-mono text-xs text-slate-600 po-number-cell">
                                            <?= htmlspecialchars($row['po_number']) ?>
                                            <?php if ($isReplacementPO): ?>
                                                <span
                                                    class="replacement-badge ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">
                                                    Replacement
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 capitalize">
                                            <?= htmlspecialchars($row['delivery_method']) ?>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                class="status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= $badge['class'] ?>">
                                                <?= $badge['label'] ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-slate-500 location-cell">
                                            <?= $row['location'] ? htmlspecialchars($row['location']) : '<span class="text-slate-300">—</span>' ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-400 text-xs">
                                            <?= date('M j, Y', strtotime($row['assigned_at'])) ?>
                                        </td>
                                        <td class="px-5 py-3 action-cell">
                                            <?php if ($row['receiver_status'] !== 'received'): ?>
                                                <div class="flex flex-col gap-1.5">
                                                    <button
                                                        onclick="openQRModal(<?= $row['order_id'] ?>, <?= $row['po_id'] ?>, '<?= addslashes($qr['url']) ?>', '<?= htmlspecialchars($qr['ref'], ENT_QUOTES) ?>')"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 3.5V16m-4-8h.01M4 4h4v4H4V4zm12 0h4v4h-4V4zM4 16h4v4H4v-4z" />
                                                        </svg>
                                                        QR Code
                                                    </button>
                                                    <?php if (!empty($row['qr_path'])): ?>
                                                        <span
                                                            class="qr-saved-indicator inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            QR saved
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="qr-saved-indicator text-xs text-slate-400">Not yet saved</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="done-label text-xs text-emerald-600 font-medium">✓ Done</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Modal -->
    <div id="qrModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-96 flex flex-col items-center gap-4 relative">
            <button onclick="closeQRModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div id="panelGenerate" class="w-full flex flex-col items-center gap-3">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">QR Code</p>
                <p id="qrRefLabel" class="text-sm font-semibold text-slate-800"></p>

                <div id="qrCanvas"
                    class="w-52 h-52 flex items-center justify-center bg-slate-50 rounded-xl border border-slate-200">
                </div>

                <div class="w-full flex flex-col gap-2 mt-1">
                    <button onclick="saveQRToUploads()"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Generate & Save QR
                    </button>
                    <button onclick="switchToScan()"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ready to Scan
                    </button>
                </div>

                <div id="saveStatus" class="hidden text-xs text-emerald-600 font-medium flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    QR saved successfully
                </div>
            </div>

            <div id="panelScan" class="hidden w-full flex-col items-center gap-3">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Scan QR Code</p>
                <p class="text-xs text-slate-400 text-center">Point your camera at the QR code</p>

                <div class="relative w-full rounded-xl overflow-hidden bg-black" style="aspect-ratio: 4/3;">
                    <video id="scanVideo" class="w-full h-full object-cover" playsinline autoplay></video>
                    <canvas id="scanCanvas" class="hidden"></canvas>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-48 h-48 border-2 border-white/70 rounded-xl relative">
                            <div
                                class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-indigo-400 rounded-tl-lg">
                            </div>
                            <div
                                class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-indigo-400 rounded-tr-lg">
                            </div>
                            <div
                                class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-indigo-400 rounded-bl-lg">
                            </div>
                            <div
                                class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-indigo-400 rounded-br-lg">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="scanResult"
                    class="hidden w-full rounded-lg px-4 py-3 text-sm border bg-emerald-50 border-emerald-200 text-emerald-800 text-center font-medium">
                </div>
                <div id="scanError"
                    class="hidden w-full rounded-lg px-4 py-3 text-sm border bg-red-50 border-red-200 text-red-700 text-center">
                </div>

                <button onclick="switchToGenerate()"
                    class="text-xs text-slate-500 hover:text-slate-700 underline underline-offset-2">
                    Back to QR code
                </button>
            </div>
        </div>
    </div>

    <!-- Ready for Booking Modal -->
    <div id="rfbModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <!-- wider modal to accommodate side-by-side layout -->
        <div class="bg-white rounded-2xl shadow-xl p-6 flex gap-5 relative" style="width: 680px; max-width: 95vw;">
            <button onclick="closeRfbModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- LEFT: date inputs -->
            <div class="flex flex-col gap-4 flex-shrink-0" style="width: 260px;">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Mark as Ready for Booking</p>
                    <p id="rfb-ref-label" class="text-xs text-slate-400 mt-0.5"></p>
                </div>

                <p class="text-xs text-slate-500">
                    Set the window when this order can be delivered. Logistics will use this to schedule the shipment.
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">
                            Available From <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="rfb-date-from"
                            onchange="document.getElementById('rfb-date-to').min = this.value"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">
                            Available Until <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="rfb-date-to"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>

                <p id="rfb-error" class="hidden text-xs text-red-500"></p>

                <div class="flex gap-2">
                    <button onclick="closeRfbModal()"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-slate-600 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">
                        Cancel
                    </button>
                    <button onclick="submitReadyForBooking()" id="rfb-submit"
                        class="flex-1 px-4 py-2.5 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                        Confirm
                    </button>
                </div>
            </div>

            <!-- DIVIDER -->
            <div class="w-px bg-slate-100 flex-shrink-0"></div>

            <!-- RIGHT: already-scheduled POs list -->
            <div class="flex-1 flex flex-col gap-3 min-w-0">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Scheduled POs</p>

                <!-- empty state -->
                <div id="rfb-scheduled-empty"
                    class="hidden flex-1 flex flex-col items-center justify-center text-center py-6">
                    <svg class="w-8 h-8 text-slate-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-xs text-slate-400">No schedules set yet.</p>
                </div>

                <!-- list -->
                <div id="rfb-scheduled-wrap"
                    class="hidden flex-col gap-0 rounded-lg border border-slate-100 overflow-hidden text-xs">
                    <div class="grid grid-cols-3 px-3 py-2 bg-slate-50 border-b border-slate-100 sticky top-0">
                        <span class="font-medium text-slate-400 uppercase tracking-wide" style="font-size:10px;">PO
                            Number</span>
                        <span class="font-medium text-slate-400 uppercase tracking-wide"
                            style="font-size:10px;">From</span>
                        <span class="font-medium text-slate-400 uppercase tracking-wide"
                            style="font-size:10px;">Until</span>
                    </div>
                    <div id="rfb-scheduled-list" class="divide-y divide-slate-100 overflow-y-auto"
                        style="max-height: 220px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ─── State ────────────────────────────────────────────────────────────────
        let currentScanUrl = '';
        let currentRef = '';
        let currentOrderId = 0;
        let currentPoId = 0;
        let scanStream = null;
        let scanAnimFrame = null;
        let rfbOrderId = 0;
        let rfbRef = '';
        let pollTimer = null;
        let consecutiveFails = 0;

        // ─── Real-time Polling ────────────────────────────────────────────────────

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function fmtDate(d) {
            if (!d) return '—';
            const dt = new Date(d);
            return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function setRtStatus(online) {
            const pulse = document.getElementById('rtPulse');
            const dot = document.getElementById('rtDot');
            const label = document.getElementById('rtLabel');
            if (!pulse) return;

            if (online) {
                pulse.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75';
                dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-emerald-500';
                label.textContent = 'Live';
            } else {
                pulse.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75';
                dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-red-500';
                label.textContent = 'Reconnecting…';
            }
        }

        function pollAssignments() {
            fetch('<?= BASE_URL ?>/warehousereceiver-pollstatus')
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    if (!data.success) return;

                    consecutiveFails = 0;
                    setRtStatus(true);

                    // ── Update each row ──────────────────────────────────────────
                    data.assignments.forEach(a => {
                        const row = document.querySelector(`tr[data-po-id="${a.po_id}"]`);
                        if (!row) return;

                        // Status badge
                        const badge = row.querySelector('.status-badge');
                        if (badge) {
                            badge.className = `status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${a.badge_class}`;
                            badge.textContent = a.badge_label;
                        }

                        // Location cell
                        const locationCell = row.querySelector('.location-cell');
                        if (locationCell) {
                            locationCell.innerHTML = a.location
                                ? escapeHtml(a.location)
                                : '<span class="text-slate-300">—</span>';
                        }

                        // Replacement badge (insert once, in the PO Number cell)
                        const poCell = row.querySelector('.po-number-cell');
                        if (poCell && a.po_type === 'replacement' && !poCell.querySelector('.replacement-badge')) {
                            poCell.insertAdjacentHTML('beforeend',
                                ` <span class="replacement-badge ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>`);
                        }

                        // QR saved indicator
                        const qrInd = row.querySelector('.qr-saved-indicator');
                        if (qrInd && a.qr_path) {
                            qrInd.className = 'qr-saved-indicator inline-flex items-center gap-1 text-xs text-emerald-600 font-medium';
                            qrInd.innerHTML = `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> QR saved`;
                        }

                        // If newly received — swap action cell to "Done"
                        if (a.receiver_status === 'received') {
                            const actionCell = row.querySelector('.action-cell');
                            if (actionCell && !actionCell.querySelector('.done-label')) {
                                actionCell.innerHTML = '<span class="done-label text-xs text-emerald-600 font-medium">✓ Done</span>';
                            }
                        }
                    });

                    // ── Update reference group headers ───────────────────────────
                    if (data.refSummaries) {
                        Object.entries(data.refSummaries).forEach(([ref, info]) => {
                            const headerEl = document.querySelector(`[data-ref="${CSS.escape(ref)}"]`);
                            if (!headerEl) return;

                            const btnArea = headerEl.querySelector('.ref-action-area');
                            if (!btnArea) return;

                            const allReady = info.total > 0 && info.total === info.received_with_location;
                            const isBooked = info.ready_for_booking > 0;

                            // Only re-render if something changed (avoid flicker)
                            const currentState = btnArea.dataset.state;
                            const newState = isBooked ? 'booked' : allReady ? 'ready' : `progress-${info.received_with_location}-${info.total}`;

                            if (currentState === newState) return;
                            btnArea.dataset.state = newState;

                            if (isBooked) {
                                btnArea.innerHTML = `
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Ready for Booking
                                    </span>`;
                            } else if (allReady) {
                                btnArea.innerHTML = `
                                    <button onclick="markReadyForBooking(${info.order_id}, '${escapeHtml(ref)}', [])"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Mark as Ready for Booking
                                    </button>`;
                            } else {
                                btnArea.innerHTML = `<span class="text-xs text-slate-400">${info.received_with_location}/${info.total} items received</span>`;
                            }
                        });
                    }
                })
                .catch(() => {
                    consecutiveFails++;
                    if (consecutiveFails >= 2) setRtStatus(false);
                });
        }

        // Poll every 5 seconds
        pollTimer = setInterval(pollAssignments, 5000);

        // Pause polling when tab is hidden, resume on visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(pollTimer);
            } else {
                pollAssignments(); // immediate refresh on tab focus
                pollTimer = setInterval(pollAssignments, 5000);
            }
        });

        // ─── Ready for Booking ────────────────────────────────────────────────────

        function markReadyForBooking(orderId, ref, scheduledRows = []) {
            rfbOrderId = orderId;
            rfbRef = ref;

            const today = new Date().toISOString().split('T')[0];
            document.getElementById('rfb-date-from').min = today;
            document.getElementById('rfb-date-to').min = today;
            document.getElementById('rfb-date-from').value = '';
            document.getElementById('rfb-date-to').value = '';
            document.getElementById('rfb-ref-label').textContent = ref;
            document.getElementById('rfb-error').classList.add('hidden');

            // Show modal first
            document.getElementById('rfbModal').classList.remove('hidden');
            document.getElementById('rfbModal').classList.add('flex');

            // Show loading state on right panel
            const wrap = document.getElementById('rfb-scheduled-wrap');
            const emptyEl = document.getElementById('rfb-scheduled-empty');
            const list = document.getElementById('rfb-scheduled-list');

            wrap.classList.add('hidden');
            wrap.classList.remove('flex');
            emptyEl.classList.remove('hidden');
            emptyEl.classList.add('flex');
            emptyEl.innerHTML = `
        <svg class="w-5 h-5 text-slate-300 animate-spin mb-2" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <p class="text-xs text-slate-400">Loading schedules…</p>`;

            // Fetch all already-booked POs
            fetch('<?= BASE_URL ?>/warehousereceiver-scheduledpos')
                .then(r => r.json())
                .then(data => {
                    emptyEl.innerHTML = `
                <svg class="w-8 h-8 text-slate-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-xs text-slate-400">No schedules set yet.</p>`;

                    const booked = data.scheduled ?? [];

                    if (booked.length > 0) {
                        list.innerHTML = booked.map(r => `
                    <div class="grid grid-cols-4 px-3 py-2.5 items-center hover:bg-slate-50">
                        <span class="font-semibold text-slate-600" style="font-size:10px;">${escapeHtml(r.nhccreference)}</span>
                        <span class="font-mono text-slate-700 flex items-center gap-1" style="font-size:11px;">
                            ${escapeHtml(r.po_number)}
                            ${r.po_type === 'replacement'
                                ? `<span class="px-1 py-0.5 rounded-full text-[8px] font-semibold uppercase bg-rose-100 text-rose-700">R</span>`
                                : ''}
                        </span>
                        <span class="text-slate-500" style="font-size:11px;">${fmtDate(r.date_from)}</span>
                        <span class="text-slate-500" style="font-size:11px;">${fmtDate(r.date_to)}</span>
                    </div>`).join('');

                        // Update header to 4 cols
                        document.querySelector('#rfb-scheduled-wrap .grid').innerHTML = `
                    <span class="font-medium text-slate-400 uppercase tracking-wide" style="font-size:10px;">Ref</span>
                    <span class="font-medium text-slate-400 uppercase tracking-wide" style="font-size:10px;">PO Number</span>
                    <span class="font-medium text-slate-400 uppercase tracking-wide" style="font-size:10px;">From</span>
                    <span class="font-medium text-slate-400 uppercase tracking-wide" style="font-size:10px;">Until</span>`;
                        document.querySelector('#rfb-scheduled-wrap .grid').className = 'grid grid-cols-4 px-3 py-2 bg-slate-50 border-b border-slate-100';

                        wrap.classList.remove('hidden');
                        wrap.classList.add('flex');
                        emptyEl.classList.add('hidden');
                        emptyEl.classList.remove('flex');
                    } else {
                        emptyEl.classList.remove('hidden');
                        emptyEl.classList.add('flex');
                    }
                })
                .catch(() => {
                    emptyEl.innerHTML = '<p class="text-xs text-red-400">Failed to load schedules.</p>';
                });
        }

        function closeRfbModal() {
            document.getElementById('rfbModal').classList.add('hidden');
            document.getElementById('rfbModal').classList.remove('flex');
        }

        function submitReadyForBooking() {
            const dateFrom = document.getElementById('rfb-date-from').value;
            const dateTo = document.getElementById('rfb-date-to').value;
            const errEl = document.getElementById('rfb-error');

            if (!dateFrom || !dateTo) {
                errEl.textContent = 'Please select both dates.';
                errEl.classList.remove('hidden');
                return;
            }
            if (dateTo < dateFrom) {
                errEl.textContent = '"Available Until" must be after "Available From".';
                errEl.classList.remove('hidden');
                return;
            }
            errEl.classList.add('hidden');

            const btn = document.getElementById('rfb-submit');
            btn.disabled = true;
            btn.textContent = 'Saving…';

            fetch('<?= BASE_URL ?>/warehousereceiver-readyforbooking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: rfbOrderId,
                    suggested_date_from: dateFrom,
                    suggested_date_to: dateTo,
                }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeRfbModal();
                        window.location.reload();
                    } else {
                        errEl.textContent = data.message ?? 'Something went wrong.';
                        errEl.classList.remove('hidden');
                        btn.disabled = false;
                        btn.textContent = 'Confirm';
                    }
                })
                .catch(() => {
                    errEl.textContent = 'Network error.';
                    errEl.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = 'Confirm';
                });
        }

        document.getElementById('rfbModal').addEventListener('click', function (e) {
            if (e.target === this) closeRfbModal();
        });

        // ─── QR Modal ─────────────────────────────────────────────────────────────

        function openQRModal(orderId, poId, scanUrl, ref) {
            currentOrderId = orderId;
            currentPoId = poId;
            currentScanUrl = scanUrl;
            currentRef = ref;

            document.getElementById('qrRefLabel').textContent = ref;
            document.getElementById('saveStatus').classList.add('hidden');

            const canvas = document.getElementById('qrCanvas');
            canvas.innerHTML = '';
            new QRCode(canvas, {
                text: scanUrl,
                width: 208,
                height: 208,
                colorDark: '#312e81',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });

            showPanel('panelGenerate');
            document.getElementById('qrModal').classList.remove('hidden');
            document.getElementById('qrModal').classList.add('flex');
        }

        function closeQRModal() {
            stopCamera();
            document.getElementById('qrModal').classList.add('hidden');
            document.getElementById('qrModal').classList.remove('flex');
            document.getElementById('scanResult').classList.add('hidden');
            document.getElementById('scanError').classList.add('hidden');
        }

        function showPanel(id) {
            ['panelGenerate', 'panelScan'].forEach(p => {
                document.getElementById(p).classList.add('hidden');
                document.getElementById(p).classList.remove('flex');
            });
            const el = document.getElementById(id);
            el.classList.remove('hidden');
            el.classList.add('flex');
            el.style.flexDirection = 'column';
        }

        function saveQRToUploads() {
            const qrEl = document.querySelector('#qrCanvas canvas') || document.querySelector('#qrCanvas img');
            if (!qrEl) return;

            const dataUrl = qrEl.tagName === 'CANVAS' ? qrEl.toDataURL('image/png') : qrEl.src;

            fetch('<?= BASE_URL ?>/warehousereceiver-saveqr', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: currentOrderId,
                    po_id: currentPoId,
                    image: dataUrl,
                    ref: currentRef,
                }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('saveStatus').classList.remove('hidden');
                        const row = document.querySelector(`tr[data-po-id="${currentPoId}"]`);
                        if (row) {
                            const ind = row.querySelector('.qr-saved-indicator');
                            if (ind) {
                                ind.className = 'qr-saved-indicator inline-flex items-center gap-1 text-xs text-emerald-600 font-medium';
                                ind.innerHTML = `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> QR saved`;
                            }
                        }
                    } else {
                        alert(data.message ?? 'Failed to save QR.');
                    }
                })
                .catch(() => alert('Network error.'));
        }

        // ─── Camera / Scanner ─────────────────────────────────────────────────────

        function switchToScan() {
            showPanel('panelScan');
            document.getElementById('scanResult').classList.add('hidden');
            document.getElementById('scanError').classList.add('hidden');
            startCamera();
        }

        function switchToGenerate() {
            stopCamera();
            showPanel('panelGenerate');
        }

        function startCamera() {
            const video = document.getElementById('scanVideo');
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(stream => {
                    scanStream = stream;
                    video.srcObject = stream;
                    video.play();
                    requestAnimationFrame(scanFrame);
                })
                .catch(err => {
                    document.getElementById('scanError').textContent = 'Camera access denied: ' + err.message;
                    document.getElementById('scanError').classList.remove('hidden');
                });
        }

        function stopCamera() {
            if (scanStream) {
                scanStream.getTracks().forEach(t => t.stop());
                scanStream = null;
            }
            if (scanAnimFrame) {
                cancelAnimationFrame(scanAnimFrame);
                scanAnimFrame = null;
            }
        }

        function scanFrame() {
            const video = document.getElementById('scanVideo');
            const canvas = document.getElementById('scanCanvas');

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert',
                });
                if (code) {
                    stopCamera();
                    handleScanResult(code.data);
                    return;
                }
            }
            scanAnimFrame = requestAnimationFrame(scanFrame);
        }

        function handleScanResult(url) {
            const resultEl = document.getElementById('scanResult');
            const errorEl = document.getElementById('scanError');

            if (url.includes('/warehousereceiverscan')) {
                resultEl.textContent = '✓ QR detected! Redirecting…';
                resultEl.classList.remove('hidden');
                setTimeout(() => { window.location.href = url; }, 800);
            } else {
                errorEl.textContent = 'Invalid QR code. Please scan the correct order QR.';
                errorEl.classList.remove('hidden');
                setTimeout(() => {
                    errorEl.classList.add('hidden');
                    startCamera();
                }, 2000);
            }
        }

        document.getElementById('qrModal').addEventListener('click', function (e) {
            if (e.target === this) closeQRModal();
        });
    </script>
</body>

</html>