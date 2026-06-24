<?php
// accountant-replacement.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all APPROVED replacement requests, joined sa order info
$sql = "SELECT 
            rr.id,
            rr.order_id,
            rr.user_id,
            rr.po_id,
            rr.booking_id,
            rr.reason,
            rr.photos,
            rr.status,
            rr.created_at,
            rr.updated_at,
            pl.nhccreference,
            pl.contact_name,
            pl.contact_email,
            pl.contact_phone,
            pl.grand_total,
            pl.payment_status,
            pl.order_status
        FROM noblereplacementrequest rr
        LEFT JOIN noblepaidproductlist pl ON rr.order_id = pl.id
        WHERE rr.status = 'approved'
        ORDER BY rr.created_at DESC";

$result = mysqli_query($conn, $sql);

$replacements = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $replacements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant - Replacement Order</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <h1 class="text-xl font-semibold text-slate-800 mb-4">Approved Replacement Requests</h1>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-slate-200 text-slate-700">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Order ID</th>
                        <th class="px-4 py-3">Booking ID</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Photos</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date Approved</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($replacements) > 0): ?>
                        <?php foreach ($replacements as $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3"><?= htmlspecialchars($r['id']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($r['nhccreference'] ?? 'N/A') ?></td>
                                <td class="px-4 py-3">
                                    <?= htmlspecialchars($r['contact_name'] ?? 'N/A') ?><br>
                                    <span class="text-xs text-slate-500"><?= htmlspecialchars($r['contact_phone'] ?? '') ?></span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($r['order_id']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($r['booking_id'] ?? 'N/A') ?></td>
                                <td class="px-4 py-3 max-w-xs"><?= htmlspecialchars($r['reason']) ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $photos = json_decode($r['photos'], true);
                                    if (is_array($photos)) {
                                        foreach ($photos as $photo) {
                                            echo '<a href="' . BASE_URL . '/' . htmlspecialchars($photo) . '" target="_blank">
                                                    <img src="' . BASE_URL . '/' . htmlspecialchars($photo) . '" class="w-12 h-12 object-cover rounded inline-block mr-1 border">
                                                  </a>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-3">₱<?= number_format($r['grand_total'] ?? 0, 2) ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700 font-medium">
                                        <?= htmlspecialchars($r['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($r['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="px-4 py-6 text-center text-slate-400">No approved replacement requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>