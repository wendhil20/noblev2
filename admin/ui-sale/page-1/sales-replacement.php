<?php
// sales-replacement.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];
$allowedPositions = [POSITION_STAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

function fetchPendingReplacementRequests($conn)
{
    $sql = "
        SELECT
            rr.id            AS request_id,
            rr.order_id,
            rr.created_at,

            o.nhccreference,
            o.contact_name,
            o.contact_phone,
            o.delivery_method,

            db.status        AS booking_status,
            ot.current_step  AS pickup_step

        FROM noblereplacementrequest rr
        INNER JOIN noblepaidproductlist o ON o.id = rr.order_id
        LEFT JOIN nobledeliverybooking db ON db.id = rr.booking_id
        LEFT JOIN nobleordertracking ot ON ot.po_id = rr.po_id AND ot.order_id = rr.order_id
        WHERE rr.status = 'pending'
        ORDER BY rr.created_at ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();

    return $requests;
}

function getReplacementTrackingLabel(array $row): string
{
    if (!empty($row['booking_status'])) {
        $deliverySteps = [
            'scheduled'  => 'Scheduled',
            'loading'    => 'Loading',
            'in_transit' => 'In Transit',
            'delivered'  => 'Delivered',
        ];
        return $deliverySteps[$row['booking_status']] ?? 'Unknown';
    }

    if ($row['pickup_step'] !== null) {
        $pickupSteps = [0 => 'Order is Placed', 1 => 'Loading', 2 => 'Item Ready', 3 => 'Picked up'];
        return $pickupSteps[(int) $row['pickup_step']] ?? 'Unknown';
    }

    return 'Unknown';
}

$pendingReplacements = fetchPendingReplacementRequests($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales — Replacement Requests</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
    <style>
        .rr-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }

        .rr-modal-overlay.active {
            display: flex;
        }

        .rr-modal-box {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
        }

        .rr-row {
            cursor: pointer;
        }
    </style>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <h1 class="text-xl font-bold text-gray-900 mb-4">Replacement Requests — Pending Review</h1>

        <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3">Order #</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Tracking Status</th>
                        <th class="px-4 py-3">Date Requested</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($pendingReplacements)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                No pending replacement requests.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingReplacements as $r): ?>
                            <tr class="rr-row hover:bg-gray-50" onclick="openReplacementDetail(<?= (int) $r['request_id'] ?>)">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    #<?= htmlspecialchars($r['nhccreference'] ?: $r['order_id']) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <?= htmlspecialchars($r['contact_name']) ?>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($r['contact_phone']) ?></div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <?= htmlspecialchars(ucfirst($r['delivery_method'])) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <?= htmlspecialchars(getReplacementTrackingLabel($r)) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    <?= htmlspecialchars(date('M d, Y · h:i A', strtotime($r['created_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- ═══ DETAIL MODAL (content fetched via AJAX from replacement-detail.php) ═══ -->
    <div id="rrModalOverlay" class="rr-modal-overlay">
        <div class="rr-modal-box" id="rrModalBox">
            <p class="text-gray-500 text-sm">Loading...</p>
        </div>
    </div>

    <script>
        const rrModalOverlay = document.getElementById('rrModalOverlay');
        const rrModalBox = document.getElementById('rrModalBox');

        async function openReplacementDetail(requestId) {
            rrModalBox.innerHTML = '<p class="text-gray-500 text-sm">Loading...</p>';
            rrModalOverlay.classList.add('active');

            try {
                const res = await fetch('<?= BASE_URL ?>/sales-replacementdetail?id=' + requestId);
                const data = await res.json();

                if (!data.success) {
                    rrModalBox.innerHTML = '<p class="text-red-600 text-sm">' + (data.message || 'Failed to load.') + '</p>';
                    return;
                }

                rrModalBox.innerHTML = data.html;
            } catch (err) {
                rrModalBox.innerHTML = '<p class="text-red-600 text-sm">Network error.</p>';
            }
        }

        rrModalOverlay.addEventListener('click', (e) => {
            if (e.target === rrModalOverlay) rrModalOverlay.classList.remove('active');
        });

        async function approveReplacement(requestId) {
    if (!confirm('Approve this replacement request?')) return;

    const btn = document.getElementById('rrApproveBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = 'Approving...';
    }

    try {
        const res = await fetch('<?= BASE_URL ?>/sales-replacementdetail', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=approve&id=' + encodeURIComponent(requestId)
        });
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Failed to approve request.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Approve Replacement';
            }
            return;
        }

        rrModalOverlay.classList.remove('active');
        location.reload();
    } catch (err) {
        alert('Network error.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Approve Replacement';
        }
    }
}
    </script>

</body>
</html>