<?php
$staffId = $_SESSION['account_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT 
        oa.id AS assignment_id,
        oa.order_id,
        oa.status AS assignment_status,
        oa.type AS assignment_type,
        oa.notes,
        oa.assigned_at,
        ppl.nhccreference,
        ppl.contact_name,
        ppl.contact_phone,
        ppl.grand_total,
        ppl.payment_status,
        ppl.delivery_method,
        ppl.created_at AS order_date,
        rr.assigned_staff_id AS receiver_id,
        rr.po_id AS receiver_po_id,
        nr.name AS receiver_name
    FROM nobleorderassignment oa
    JOIN noblepaidproductlist ppl ON oa.order_id = ppl.id
    LEFT JOIN noblereceivingreceiver rr ON rr.order_id = ppl.id
    LEFT JOIN noblerole nr ON nr.id = rr.assigned_staff_id
    WHERE oa.staff_id = ?
    GROUP BY oa.id
    ORDER BY oa.assigned_at DESC
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$assignedOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$poTrackings = [];
if (!empty($assignedOrders)) {
    $orderIds = array_unique(array_column($assignedOrders, 'order_id'));
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));

    $ptStmt = $conn->prepare("
        SELECT 
            npo.id AS po_id,
            npo.order_id,
            npo.po_number,
            npo.po_type,
            CASE WHEN 
                npo.prepared_by_signature IS NOT NULL AND npo.prepared_by_signature != '' AND
                npo.noted_by_signature IS NOT NULL AND npo.noted_by_signature != '' AND
                npo.approved_by_signature IS NOT NULL AND npo.approved_by_signature != '' AND
                npo.acknowledged_by_signature IS NOT NULL AND npo.acknowledged_by_signature != '' AND
                npo.received_by_signature IS NOT NULL AND npo.received_by_signature != ''
            THEN 1 ELSE 0 END AS all_signed,
            (
                (CASE WHEN npo.prepared_by_signature IS NOT NULL AND npo.prepared_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.noted_by_signature IS NOT NULL AND npo.noted_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.approved_by_signature IS NOT NULL AND npo.approved_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.acknowledged_by_signature IS NOT NULL AND npo.acknowledged_by_signature != '' THEN 1 ELSE 0 END) +
                (CASE WHEN npo.received_by_signature IS NOT NULL AND npo.received_by_signature != '' THEN 1 ELSE 0 END)
            ) AS signed_count,
            ot.current_step,
            ot.expected_delivery_from,
            ot.expected_delivery_to,
            rr.assigned_staff_id AS receiver_id,
            rr.po_id AS receiver_po_id,
            nr.name AS receiver_name
        FROM noblepurchaseorder npo
        LEFT JOIN nobleordertracking ot ON ot.po_id = npo.id
        LEFT JOIN noblereceivingreceiver rr ON rr.po_id = npo.id
        LEFT JOIN noblerole nr ON nr.id = rr.assigned_staff_id
        WHERE npo.order_id IN ($placeholders)
        ORDER BY npo.id ASC
    ");
    $ptStmt->bind_param($types, ...$orderIds);
    $ptStmt->execute();
    $ptRows = $ptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ptStmt->close();

    foreach ($ptRows as $row) {
        $poTrackings[$row['order_id']][] = $row;
    }
}

$staffList = [];
$stmtStaff = $conn->prepare("
    SELECT id, name, position 
    FROM noblerole 
    WHERE position = 'warehousereceiver' 
    ORDER BY name ASC
");
$stmtStaff->execute();
$staffList = $stmtStaff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtStaff->close();

$deliverySteps = [
    ['label' => 'Passed to supplier'],
    ['label' => 'Processing'],
    ['label' => 'Expected delivery'],
    ['label' => 'Out for delivery'],
    ['label' => 'Assign receiving'],
    ['label' => 'Completed'],
];
$pickupSteps = [
    ['label' => 'Passed to supplier'],
    ['label' => 'Processing'],
    ['label' => 'Item ready'],
    ['label' => 'Picked up'],
];
?>

<!-- Header -->
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-800">Assigned Orders</h1>
    <p class="text-sm text-slate-500 mt-1">Select an order to generate a Purchase Order</p>
</div>

<!-- Orders Table -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-3 py-2 font-medium text-slate-600">#</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Reference No.</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Customer</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Delivery</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Grand Total</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Payment</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Tracking</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" id="orders-tbody">
                <?php if (empty($assignedOrders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12 text-slate-400">
                            <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            No assigned orders yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $expandedOrders = [];
                    foreach ($assignedOrders as $order) {
                        $oid = $order['order_id'];
                        $pos = $poTrackings[$oid] ?? [];
                        if (empty($pos)) {
                            $expandedOrders[] = ['order' => $order, 'po' => null];
                        } else {
                            foreach ($pos as $po) {
                                $expandedOrders[] = ['order' => $order, 'po' => $po];
                            }
                        }
                    }
                    ?>
                    <?php foreach ($expandedOrders as $i => $row):
                        $order = $row['order'];
                        $currentPO = $row['po'];
                        $isDelivery = $order['delivery_method'] === 'delivery';
                        $steps = $isDelivery ? $deliverySteps : $pickupSteps;
                        $totalSteps = count($steps);
                        $poId = $currentPO['po_id'] ?? null;
                        $poNumber = $currentPO['po_number'] ?? null;
                        $poType = $currentPO['po_type'] ?? 'normal';
                        $allSigned = $currentPO['all_signed'] ?? 0;
                        $signedCount = $currentPO['signed_count'] ?? 0;
                        $poCurrentStep = isset($currentPO['current_step']) ? (int) $currentPO['current_step'] : 0;
                        $receiverName = $currentPO['receiver_name'] ?? null;
                        $receiverPoId = $currentPO['receiver_po_id'] ?? null;
                        $isAssignmentReplacement = ($order['assignment_type'] ?? 'normal') === 'replacement';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors" data-row-index="<?= $i ?>"
                            data-po-id="<?= $poId ?? 'null' ?>" data-order-id="<?= $order['order_id'] ?>">
                            <td class="px-3 py-2 text-slate-500"><?= $i + 1 ?></td>
                            <td class="px-3 py-2 font-medium text-slate-800">
                                <div class="flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($order['nhccreference'] ?? '—') ?></span>
                                    <?php if ($isAssignmentReplacement): ?>
                                        <span
                                            class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">
                                         Replacement
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-slate-400 font-normal mt-0.5"><?= htmlspecialchars($order['contact_phone']) ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($order['contact_name']) ?></td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    <?= $isDelivery ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' ?>">
                                    <?= ucfirst($order['delivery_method']) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-800 font-medium">₱<?= number_format($order['grand_total'], 2) ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php
                                $ps = $order['payment_status'];
                                $psColor = match ($ps) {
                                    'paid' => 'bg-green-100 text-green-700',
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                                ?>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $psColor ?>">
                                    <?= ucfirst($ps) ?>
                                </span>
                            </td>

                            <!-- Tracking Column -->
                            <td class="px-3 py-2 tracking-cell" style="min-width: 300px;">
                                <?php if (!$currentPO): ?>
                                    <span class="text-slate-400 text-xs italic">No tracking yet</span>
                                <?php else: ?>
                                    <div>
                                        <p class="text-xs font-mono text-slate-500 mb-1.5">
                                            <?= htmlspecialchars($poNumber) ?>
                                            <?php if ($poType === 'replacement'): ?>
                                                <span
                                                    class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">
                                                     Replacement
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <div class="flex items-center mb-1.5">
                                            <?php foreach ($steps as $si => $step):
                                                $done = $si < $poCurrentStep;
                                                $active = $si === $poCurrentStep;
                                                ?>
                                                <?php if ($si > 0): ?>
                                                    <div class="h-0.5 flex-1 <?= $done ? 'bg-emerald-400' : 'bg-slate-200' ?>"></div>
                                                <?php endif; ?>
                                                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-medium
                                                    <?php
                                                    if ($done)
                                                        echo 'bg-emerald-500 text-white';
                                                    elseif ($active)
                                                        echo 'bg-indigo-500 text-white';
                                                    else
                                                        echo 'bg-slate-200 text-slate-400';
                                                    ?>">
                                                    <?php if ($done): ?>
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                                d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    <?php else: ?>
                                                        <?= $si + 1 ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-xs text-indigo-600 font-medium">
                                            <?= htmlspecialchars($steps[$poCurrentStep]['label'] ?? '') ?>
                                            <?php if ($isDelivery && $poCurrentStep === 2 && !empty($currentPO['expected_delivery_from'])): ?>
                                                <span class="text-slate-400 font-normal ml-1">
                                                    <?= date('M d', strtotime($currentPO['expected_delivery_from'])) ?>
                                                    – <?= date('M d, Y', strtotime($currentPO['expected_delivery_to'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($isDelivery && $poCurrentStep === 4): ?>
                                            <?php if (!empty($receiverName) && $receiverPoId == $poId): ?>
                                                <div
                                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <?= htmlspecialchars($receiverName) ?>
                                                </div>
                                            <?php else: ?>
                                                <button
                                                    onclick="openAssignModal(<?= (int) $order['order_id'] ?>, '<?= htmlspecialchars($order['nhccreference'], ENT_QUOTES) ?>', <?= (int) $poId ?>)"
                                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium rounded-lg transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Assign Receiver
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($poCurrentStep < ($totalSteps - 1)): ?>
                                            <?php if ($allSigned): ?>
                                                <a href="<?= BASE_URL ?>/warehousestaff-trackpo?po_id=<?= (int) $poId ?>"
                                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 bg-slate-600 hover:bg-slate-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 5l7 7-7 7" />
                                                    </svg>
                                                    Update Step
                                                </a>
                                            <?php else: ?>
                                                <div class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 bg-slate-200 text-slate-400 text-xs font-medium rounded-lg cursor-not-allowed"
                                                    title="All 5 signatures required (<?= $signedCount ?>/5 signed)">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5a4 4 0 100-8 4 4 0 000 8z" />
                                                    </svg>
                                                    Update Step (<?= $signedCount ?>/5)
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div
                                                class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                                Completed
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Action Column -->
                            <td class="px-3 py-2 action-cell">
                                <div class="flex flex-col gap-1.5">
                                    <?php if ($poId): ?>
                                        <a href="<?= BASE_URL ?>/warehouse-poview?po_id=<?= $poId ?>"
                                            class="inline-flex items-center gap-1 px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View PO
                                        </a>
                                        <span
                                            class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($poNumber ?? '—') ?></span>

                                        <?php if ($allSigned): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                                All Signed
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <?= $signedCount ?>/5 Signed
                                            </span>
                                        <?php endif; ?>

                                        <?php
                                        // Only show Generate Replacement PO if:
                                        // 1. It's a replacement assignment
                                        // 2. AND there is NO existing replacement PO yet (po_type !== 'replacement')
                                        $hasReplacementPO = false;
                                        foreach (($poTrackings[$order['order_id']] ?? []) as $existingPO) {
                                            if ($existingPO['po_type'] === 'replacement') {
                                                $hasReplacementPO = true;
                                                break;
                                            }
                                        }
                                        ?>
                                        <?php if ($isAssignmentReplacement && !$hasReplacementPO): ?>
                                            <a href="<?= BASE_URL ?>/warehousestaffpo?order_id=<?= $order['order_id'] ?>"
                                                class="inline-flex items-center gap-1 px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Generate Replacement PO
                                            </a>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/warehousestaffpo?order_id=<?= $order['order_id'] ?>"
                                            class="inline-flex items-center gap-1 px-2 py-1 <?= $isAssignmentReplacement ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700' ?> text-white text-xs font-medium rounded-lg transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <?= $isAssignmentReplacement ? 'Generate Replacement PO' : 'Generate PO' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Receiving Staff Modal -->
<div id="assignModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Assign Receiving Staff</h3>
                <p class="text-xs text-slate-500 mt-0.5" id="assignModalRef"></p>
            </div>
            <button onclick="closeAssignModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="px-5 py-4">
            <input type="hidden" id="assignOrderId">
            <input type="hidden" id="assignPoId">
            <p class="text-xs text-slate-500 mb-3">Select a warehouse staff to receive this delivery:</p>
            <div class="space-y-2 max-h-60 overflow-y-auto">
                <?php if (empty($staffList)): ?>
                    <p class="text-xs text-slate-400 text-center py-4">No warehouse staff found.</p>
                <?php else: ?>
                    <?php foreach ($staffList as $staff): ?>
                        <label
                            class="flex items-center gap-3 p-2.5 rounded-lg border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 cursor-pointer transition-colors">
                            <input type="radio" name="selectedStaff" value="<?= (int) $staff['id'] ?>" class="text-indigo-600">
                            <div>
                                <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($staff['name']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars(ucfirst($staff['position'])) ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex justify-end gap-2">
            <button onclick="closeAssignModal()"
                class="px-3 py-1.5 text-xs text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">Cancel</button>
            <button onclick="submitAssign()"
                class="px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">Confirm
                Assignment</button>
        </div>
    </div>
</div>

<script>
    const DELIVERY_STEPS = ['Passed to supplier', 'Processing', 'Expected delivery', 'Out for delivery', 'Assign receiving', 'Completed'];
    const PICKUP_STEPS = ['Passed to supplier', 'Processing', 'Item ready', 'Picked up'];

    function renderStepper(steps, currentStep) {
        return steps.map((label, si) => {
            const done = si < currentStep;
            const active = si === currentStep;
            const line = si > 0 ? `<div class="h-0.5 flex-1 ${done ? 'bg-emerald-400' : 'bg-slate-200'}"></div>` : '';
            const circle = done
                ? `<div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center bg-emerald-500 text-white"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>`
                : `<div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-medium ${active ? 'bg-indigo-500 text-white' : 'bg-slate-200 text-slate-400'}">${si + 1}</div>`;
            return line + circle;
        }).join('');
    }

    function renderTrackingCell(order, po, isDelivery) {
        if (!po) return `<span class="text-slate-400 text-xs italic">No tracking yet</span>`;

        const steps = isDelivery ? DELIVERY_STEPS : PICKUP_STEPS;
        const totalSteps = steps.length;
        const currentStep = parseInt(po.current_step) || 0;
        const allSigned = po.all_signed == 1;
        const signedCount = po.signed_count || 0;

        let html = `<div>`;
        html += `<p class="text-xs font-mono text-slate-500 mb-1.5">${po.po_number}`;
        if (po.po_type === 'replacement') {
            html += ` <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">↺ Replacement</span>`;
        }
        html += `</p>`;
        html += `<div class="flex items-center mb-1.5">${renderStepper(steps, currentStep)}</div>`;
        html += `<div class="text-xs text-indigo-600 font-medium">${steps[currentStep] || ''}`;
        if (isDelivery && currentStep === 2 && po.expected_delivery_from) {
            const from = new Date(po.expected_delivery_from).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            const to = new Date(po.expected_delivery_to).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            html += `<span class="text-slate-400 font-normal ml-1">${from} – ${to}</span>`;
        }
        html += `</div>`;

        if (isDelivery && currentStep === 4) {
            if (po.receiver_name && po.receiver_po_id == po.po_id) {
                html += `<div class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                ${po.receiver_name}</div>`;
            } else {
                html += `<button onclick="openAssignModal(${order.order_id}, '${order.nhccreference}', ${po.po_id})"
                class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Assign Receiver</button>`;
            }
        }

        if (currentStep < totalSteps - 1) {
            if (allSigned) {
                html += `<a href="${BASE_URL}/warehousestaff-trackpo?po_id=${po.po_id}"
                class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 bg-slate-600 hover:bg-slate-700 text-white text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                Update Step</a>`;
            } else {
                html += `<div class="mt-1.5 inline-flex items-center gap-1 px-2 py-1 bg-slate-200 text-slate-400 text-xs font-medium rounded-lg cursor-not-allowed"
                title="All 5 signatures required (${signedCount}/5 signed)">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5a4 4 0 100-8 4 4 0 000 8z"/></svg>
                Update Step (${signedCount}/5)</div>`;
            }
        } else {
            html += `<div class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Completed</div>`;
        }

        html += `</div>`;
        return html;
    }

    function renderActionCell(order, po, allPOs) {
        const isReplacement = order.assignment_type === 'replacement';

        if (!po) {
            const btnColor = isReplacement ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700';
            const btnLabel = isReplacement ? 'Generate Replacement PO' : 'Generate PO';
            return `<div class="flex flex-col gap-1.5">
            <a href="${BASE_URL}/warehousestaffpo?order_id=${order.order_id}"
                class="inline-flex items-center gap-1 px-2 py-1 ${btnColor} text-white text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                ${btnLabel}</a>
        </div>`;
        }

        const allSigned = po.all_signed == 1;
        const signedCount = po.signed_count || 0;

        // Check if a replacement PO already exists among all POs for this order
        const hasReplacementPO = (allPOs || []).some(p => p.po_type === 'replacement');

        let html = `<div class="flex flex-col gap-1.5">
        <a href="${BASE_URL}/warehouse-poview?po_id=${po.po_id}"
            class="inline-flex items-center gap-1 px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            View PO</a>
        <span class="text-xs text-slate-500 font-mono">${po.po_number}</span>`;

        if (allSigned) {
            html += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            All Signed</span>`;
        } else {
            html += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            ${signedCount}/5 Signed</span>`;
        }

        // Only show Generate Replacement PO if replacement assignment AND no replacement PO exists yet
        if (isReplacement && !hasReplacementPO) {
            html += `<a href="${BASE_URL}/warehousestaffpo?order_id=${order.order_id}"
            class="inline-flex items-center gap-1 px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded-lg transition-colors">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Generate Replacement PO</a>`;
        }

        html += `</div>`;
        return html;
    }

    function refreshOrders() {
        fetch(`${BASE_URL}/warehouse-backendstaff-orders`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const orders = data.orders;
                const poTrackings = data.po_trackings;

                const expanded = [];
                orders.forEach(order => {
                    const pos = poTrackings[order.order_id] || [];
                    if (pos.length === 0) {
                        expanded.push({ order, po: null, allPOs: [] });
                    } else {
                        pos.forEach(po => expanded.push({ order, po, allPOs: pos }));
                    }
                });

                expanded.forEach(({ order, po, allPOs }) => {
                    let tr;
                    if (po) {
                        tr = document.querySelector(`tr[data-po-id="${po.po_id}"]`);
                        // If not found by po_id, find the null row and update it
                        if (!tr) {
                            tr = document.querySelector(`tr[data-order-id="${order.order_id}"][data-po-id="null"]`);
                            if (tr) tr.setAttribute('data-po-id', po.po_id);
                        }
                    } else {
                        tr = document.querySelector(`tr[data-order-id="${order.order_id}"][data-po-id="null"]`);
                    }
                    if (!tr) return;

                    const isDelivery = order.delivery_method === 'delivery';
                    const trackingCell = tr.querySelector('.tracking-cell');
                    const actionCell = tr.querySelector('.action-cell');

                    if (trackingCell) trackingCell.innerHTML = renderTrackingCell(order, po, isDelivery);
                    if (actionCell) actionCell.innerHTML = renderActionCell(order, po, allPOs);
                });
            })
            .catch(() => { });
    }

    // Poll every 10 seconds
    setInterval(refreshOrders, 10000);

    function openAssignModal(orderId, ref, poId) {
        document.getElementById('assignOrderId').value = orderId;
        document.getElementById('assignPoId').value = poId;
        document.getElementById('assignModalRef').textContent = ref;
        document.querySelectorAll('input[name="selectedStaff"]').forEach(r => r.checked = false);
        document.getElementById('assignModal').classList.remove('hidden');
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.add('hidden');
    }

    function submitAssign() {
        const orderId = document.getElementById('assignOrderId').value;
        const poId = document.getElementById('assignPoId').value;
        const staffId = document.querySelector('input[name="selectedStaff"]:checked')?.value;

        if (!staffId) {
            alert('Please select a staff member.');
            return;
        }

        fetch(`${BASE_URL}/warehouseassignreceiver`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, staff_id: staffId, po_id: poId })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeAssignModal();
                    refreshOrders();
                } else {
                    alert(data.message ?? 'Something went wrong.');
                }
            })
            .catch(() => alert('Network error. Please try again.'));
    }
</script>