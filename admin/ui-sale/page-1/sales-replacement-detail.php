<?php
// admin/ui-page/sales/replacement-detail.php
header('Content-Type: application/json');
include ROOT_PATH . '/network/connect.php';

function respond($success, $message = '', $extra = [])
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ─── Handle "Approve" action (POST) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $requestId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if (!$requestId) {
        respond(false, 'Missing request id.');
    }

    $stmt = $conn->prepare("SELECT status, order_id FROM noblereplacementrequest WHERE id = ?");
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        respond(false, 'Replacement request not found.');
    }

    if ($existing['status'] !== 'pending') {
        respond(false, 'This request has already been processed.');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE noblereplacementrequest SET status = 'approved' WHERE id = ?");
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->close();

        // Mark the order as a replacement order (payment_status stays untouched)
        $stmt = $conn->prepare("UPDATE noblepaidproductlist SET order_status = 'replacement' WHERE id = ?");
        $stmt->bind_param('i', $existing['order_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, 'Failed to approve request.');
    }

    respond(true, 'Replacement request approved.');
}

// ─── Fetch detail (GET) — existing behavior ───────────────────────
$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$requestId) {
    respond(false, 'Missing request id.');
}

$stmt = $conn->prepare("
    SELECT
        rr.id, rr.order_id, rr.reason, rr.photos, rr.status, rr.created_at,
        o.nhccreference, o.contact_name, o.contact_email, o.contact_phone,
        o.delivery_method, o.address_full, o.grand_total
    FROM noblereplacementrequest rr
    INNER JOIN noblepaidproductlist o ON o.id = rr.order_id
    WHERE rr.id = ?
");
$stmt->bind_param('i', $requestId);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
    respond(false, 'Replacement request not found.');
}

$photos = $r['photos'] ? json_decode($r['photos'], true) : [];

ob_start();
?>
<div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-bold text-gray-900">
        Order #<?= htmlspecialchars($r['nhccreference'] ?: $r['order_id']) ?>
    </h2>
    <button type="button" onclick="document.getElementById('rrModalOverlay').classList.remove('active')"
        class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-xmark text-lg"></i>
    </button>
</div>

<div class="space-y-3 text-sm">
    <div class="flex justify-between gap-4">
        <span class="text-gray-500 shrink-0">Customer</span>
        <span class="text-gray-900 font-medium text-right">
            <?= htmlspecialchars($r['contact_name']) ?><br>
            <span class="text-xs text-gray-500"><?= htmlspecialchars($r['contact_phone']) ?></span>
        </span>
    </div>

    <div class="flex justify-between gap-4">
        <span class="text-gray-500 shrink-0">Delivery Method</span>
        <span class="text-gray-900 font-medium"><?= htmlspecialchars(ucfirst($r['delivery_method'])) ?></span>
    </div>

    <?php if (!empty($r['address_full'])): ?>
        <div class="flex justify-between gap-4">
            <span class="text-gray-500 shrink-0">Address</span>
            <span class="text-gray-900 font-medium text-right"><?= htmlspecialchars($r['address_full']) ?></span>
        </div>
    <?php endif; ?>

    <div class="flex justify-between gap-4">
        <span class="text-gray-500 shrink-0">Date Requested</span>
        <span class="text-gray-900 font-medium">
            <?= htmlspecialchars(date('M d, Y · h:i A', strtotime($r['created_at']))) ?>
        </span>
    </div>

    <div class="flex justify-between gap-4">
        <span class="text-gray-500 shrink-0">Status</span>
        <?php
        $statusColors = [
            'pending'  => 'bg-yellow-100 text-yellow-700',
            'approved' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700',
        ];
        $statusClass = $statusColors[$r['status']] ?? 'bg-gray-100 text-gray-600';
        ?>
        <span id="rrStatusBadge" class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>">
            <?= htmlspecialchars(ucfirst($r['status'])) ?>
        </span>
    </div>

    <div class="border-t border-gray-100 pt-3">
        <span class="text-gray-500 block mb-1">Reason</span>
        <p class="text-gray-900"><?= nl2br(htmlspecialchars($r['reason'])) ?></p>
    </div>

    <?php if (!empty($photos)): ?>
        <div class="border-t border-gray-100 pt-3">
            <span class="text-gray-500 block mb-2">Photo Proof</span>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($photos as $photo): ?>
                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($photo) ?>" target="_blank">
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($photo) ?>"
                            class="w-20 h-20 object-cover rounded-md border border-gray-200">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($r['status'] === 'pending'): ?>
        <div class="border-t border-gray-100 pt-4 flex justify-end">
            <button type="button" id="rrApproveBtn" onclick="approveReplacement(<?= (int) $r['id'] ?>)"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-md transition disabled:opacity-60">
                <i class="fa-solid fa-check mr-1"></i> Approve Replacement
            </button>
        </div>
    <?php endif; ?>
</div>
<?php
$html = ob_get_clean();

respond(true, '', ['html' => $html]);