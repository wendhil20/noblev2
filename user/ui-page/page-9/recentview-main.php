<?php
// recentview-main.php
include ROOT_PATH . '/network/connect.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$uploadUrl = BASE_URL . '/uploads/';
$recentProducts = [];

if ($isLoggedIn) {
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.name,
            p.imageproduct,
            p.category,
            rv.viewed_at,
            MIN(v.pricesize) AS min_price,
            MAX(v.pricesize) AS max_price
        FROM noblerecentview rv
        INNER JOIN nobleproduct p ON p.id = rv.product_id
        LEFT JOIN nobleproductcolor c ON c.product_id = p.id
        LEFT JOIN nobleproductvariant v ON v.color_id = c.id
        WHERE rv.user_id = ?
        GROUP BY p.id, rv.viewed_at
        ORDER BY rv.viewed_at DESC
        LIMIT 40
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentProducts[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Recent View — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <!-- Toast -->
    <div id="toast" class="fixed top-4 right-4 md:top-6 md:right-6 z-50 opacity-0 pointer-events-none translate-y-2
                flex items-center gap-2 md:gap-3 px-3 py-2 md:px-4 md:py-3 rounded-xl shadow-lg
                text-xs md:text-sm font-medium bg-white border border-gray-100 text-gray-800 min-w-40 md:min-w-56
                transition-opacity duration-300 transition-transform">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <!-- Confirm Modal (palit ng window.confirm) -->
    <div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-5 md:p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-trash-can text-red-500"></i>
                </div>
                <h3 class="text-sm md:text-base font-bold text-gray-900">Clear all recent views?</h3>
            </div>
            <p class="text-xs md:text-sm text-gray-500 mb-5">
                Tatanggalin ang lahat ng naka-record na recently viewed products mo. Hindi na ito mababawi.
            </p>
            <div class="flex items-center gap-2 justify-end">
                <button onclick="closeConfirmModal()"
                    class="px-4 py-2 rounded-xl text-xs md:text-sm font-semibold text-gray-500 hover:bg-gray-100 transition">
                    Cancel
                </button>
                <button id="confirm-modal-btn" onclick="confirmClearRecentViews()"
                    class="px-4 py-2 rounded-xl text-xs md:text-sm font-semibold text-white bg-red-500 hover:bg-red-600 transition">
                    <i class="fa-solid fa-trash-can mr-1.5"></i> Clear all
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8 flex-1 w-full">

        <a href="javascript:void(0)" onclick="goBackSafe()"
            class="inline-flex items-center gap-1.5 text-xs md:text-sm text-gray-400 hover:text-amber-500 transition mb-4 md:mb-6">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </a>

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-lg md:text-2xl font-bold text-gray-900">
                Recently <span class="text-amber-500">Viewed</span>
            </h1>
            <?php if (!empty($recentProducts)): ?>
                <button onclick="openConfirmModal()"
                    class="text-xs md:text-sm text-gray-400 hover:text-red-500 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-trash-can"></i> Clear all
                </button>
            <?php endif; ?>
        </div>

        <?php if (!$isLoggedIn): ?>
            <div class="text-center py-20 text-gray-400">
                <i class="fa-solid fa-clock-rotate-left text-5xl mb-4 block"></i>
                <p class="text-lg mb-3">Login to see your recently viewed products.</p>
                <a href="<?= BASE_URL ?>/google"
                    class="inline-block px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition">
                    Login
                </a>
            </div>
        <?php elseif (empty($recentProducts)): ?>
            <div class="text-center py-20 text-gray-400">
                <i class="fa-solid fa-clock-rotate-left text-5xl mb-4 block"></i>
                <p class="text-lg">No recently viewed products yet.</p>
            </div>
        <?php else: ?>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4" id="recent-grid">
                <?php foreach ($recentProducts as $p): ?>
                    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>"
                        class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
                               block hover:shadow-lg transition-shadow duration-300">

                        <div class="aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
                            <?php if (!empty($p['imageproduct'])): ?>
                                <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                    alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i class="fa-solid fa-image text-3xl md:text-5xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-2 md:p-3">
                            <h3 class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide leading-snug mb-1 line-clamp-1">
                                <?= htmlspecialchars($p['name']) ?>
                            </h3>

                            <?php
                            $min = floatval($p['min_price'] ?? 0);
                            $max = floatval($p['max_price'] ?? 0);
                            ?>
                            <?php if ($min > 0 || $max > 0): ?>
                                <span class="text-[10px] md:text-sm font-semibold text-gray-800">
                                    ₱<?= number_format($min, 2) ?>
                                    <?= $min !== $max ? ' – ₱' . number_format($max, 2) : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] md:text-xs text-gray-400 italic">Price not set</span>
                            <?php endif; ?>

                            <p class="text-[10px] text-gray-400 mt-1">
                                Viewed <?= htmlspecialchars(date('M d, Y', strtotime($p['viewed_at']))) ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

    <script>
        // ─── Toast ──────────────────────────────────────────────
        function showToast(type, msg) {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const text = document.getElementById('toast-msg');

            icon.innerHTML = type === 'success'
                ? '<i class="fa-solid fa-circle-check text-green-500"></i>'
                : type === 'warning'
                    ? '<i class="fa-solid fa-triangle-exclamation text-amber-500"></i>'
                    : '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
            text.textContent = msg;

            toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
            toast.classList.add('opacity-100', 'translate-y-0');

            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                toast.classList.remove('opacity-100', 'translate-y-0');
            }, 3000);
        }

        // ─── Confirm Modal (palit ng window.confirm) ─────────────
        function openConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // click sa labas ng card para isara
        document.getElementById('confirm-modal').addEventListener('click', (e) => {
            if (e.target.id === 'confirm-modal') closeConfirmModal();
        });

        async function confirmClearRecentViews() {
            const btn = document.getElementById('confirm-modal-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Clearing…';

            try {
                const res = await fetch(<?= json_encode(BASE_URL . '/clearrecentview') ?>, { method: 'POST' });
                const data = await res.json();

                closeConfirmModal();

                if (data.ok) {
                    showToast('success', 'Recent views cleared.');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast('error', data.msg || 'Failed to clear recent views.');
                }
            } catch (e) {
                closeConfirmModal();
                showToast('error', 'Something went wrong.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash-can mr-1.5"></i> Clear all';
        }

        // ─── Back button — history.back() lang, hindi nakaka-stuck sa recent view ───
        function goBackSafe() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = <?= json_encode(BASE_URL . '/') ?>;
            }
        }
    </script>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>

</html> 