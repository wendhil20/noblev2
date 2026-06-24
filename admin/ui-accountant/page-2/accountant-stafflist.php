<?php
// accounting-main.php

// ALL POs — both pending and noted
$stmt = $conn->prepare("
    SELECT npo.*, ppl.nhccreference, ppl.contact_name
    FROM noblepurchaseorder npo
    JOIN noblepaidproductlist ppl ON npo.order_id = ppl.id
    ORDER BY npo.created_at DESC
");
$stmt->execute();
$allPOs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-800">Purchase Orders</h1>
    <p class="text-sm text-slate-500 mt-1">Review and note Purchase Orders from Warehouse Staff</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-5 py-3 font-medium text-slate-600">#</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">PO Number</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">Reference</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">Customer</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">Prepared By</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">Noted By</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">PO Date</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">Status</th>
                    <th class="text-left px-5 py-3 font-medium text-slate-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($allPOs)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-12 text-slate-400">No Purchase Orders yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allPOs as $i => $po): ?>
                        <?php
                        $isPending = empty($po['noted_by']);
                        $isReplacement = ($po['po_type'] ?? 'normal') === 'replacement';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $isReplacement ? 'bg-rose-50/40' : '' ?>">
                            <td class="px-5 py-3 text-slate-500"><?= $i + 1 ?></td>
                            <td class="px-5 py-3 font-medium text-slate-800">
                                <div class="flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($po['po_number']) ?></span>
                                    <?php if ($isReplacement): ?>
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700 border border-rose-200">
                                            ↺ Replacement
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-700">
                                <?= htmlspecialchars($po['nhccreference'] ?? '—') ?>
                            </td>
                            <td class="px-5 py-3 text-slate-700">
                                <?= htmlspecialchars($po['contact_name']) ?>
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                <?= htmlspecialchars($po['prepared_by']) ?>
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                <?= $isPending ? '<span class="text-slate-400 italic">—</span>' : htmlspecialchars($po['noted_by']) ?>
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs">
                                <?= date('M d, Y', strtotime($po['po_date'])) ?>
                            </td>
                            <td class="px-5 py-3">
                                <?php if ($isPending): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                        Pending
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        Noted
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 flex items-center gap-2">
                                <a href="<?= BASE_URL ?>/accounting-staffpoview?po_id=<?= $po['id'] ?>"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-600 hover:bg-slate-700 text-white text-xs font-medium rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View
                                </a>
                                <?php if ($isPending): ?>
                                    <button onclick="notePO(<?= $po['id'] ?>)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 <?= $isReplacement ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700' ?> text-white text-xs font-medium rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        Note PO
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function notePO(poId) {
    if (!confirm('Confirm noting this Purchase Order?')) return;

    fetch('<?= BASE_URL ?>/accounting-backendponote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ po_id: poId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('PO noted successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (res.message || 'Unknown error'));
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>