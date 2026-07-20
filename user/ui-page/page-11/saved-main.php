<?php
// saved-main.php

include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';
$products = [];

if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $result = $conn->query("
        SELECT
            p.id, p.name, p.imageproduct, p.description,
            MIN(v.pricesize) AS min_price,
            MAX(v.pricesize) AS max_price
        FROM noblesavedproduct s
        JOIN nobleproduct p ON p.id = s.product_id
        LEFT JOIN nobleproductcolor c ON c.product_id = p.id
        LEFT JOIN nobleproductvariant v ON v.color_id = c.id
        WHERE s.user_id = $uid
        GROUP BY p.id
        ORDER BY s.created_at DESC
    ");
    while ($row = $result->fetch_assoc())
        $products[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8 flex-1 w-full">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Saved Items</h2>

        <?php if (empty($products)): ?>
            <div class="text-center py-20 text-gray-400">
                <i class="fa-regular fa-heart text-5xl mb-4 block"></i>
                <p class="text-lg">Wala ka pang naka-save na item.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 md:gap-4">
                <?php foreach ($products as $p): ?>
                    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>"
                        class="group relative rounded-xl md:rounded-2xl overflow-hidden block hover:shadow-lg transition-shadow duration-300">
                        <div
                            class="relative aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
                            <?php if (!empty($p['imageproduct'])): ?>
                                <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                    alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i class="fa-solid fa-image text-3xl md:text-5xl"></i>
                                </div>
                            <?php endif; ?>

                            <button type="button"
                                class="save-btn absolute top-1.5 right-1.5 md:top-2 md:right-2 z-10 w-7 h-7 md:w-8 md:h-8 rounded-full bg-white/90 shadow flex items-center justify-center opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-200"
                                data-product-id="<?= $p['id'] ?>" aria-label="Remove from favorites">
                                <i class="fa-solid fa-bookmark text-red-500 text-xs md:text-sm"></i>
                            </button>
                        </div>
                        <div class="p-2 md:p-3">
                            <h3
                                class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide leading-snug mb-0.5 md:mb-1 line-clamp-1">
                                <?= htmlspecialchars($p['name']) ?></h3>
                            <?php
                            $min = floatval($p['min_price'] ?? 0);
                            $max = floatval($p['max_price'] ?? 0);
                            ?>
                            <?php if ($min > 0 || $max > 0): ?>
                                <span
                                    class="text-[10px] md:text-sm font-semibold text-gray-800">₱<?= number_format($min, 2) ?><?= $min !== $max ? ' – ₱' . number_format($max, 2) : '' ?></span>
                            <?php else: ?>
                                <span class="text-[10px] md:text-xs text-gray-400 italic">Price not set</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // same toggle logic — pag na-unsave dito, tanggalin sa list
        document.querySelectorAll('.save-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const productId = this.dataset.productId;
                fetch('<?= BASE_URL ?>/savedproduct', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + encodeURIComponent(productId)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && !data.saved) {
                            this.closest('a').remove(); // alisin sa view kapag na-unsave
                        }
                    });
            });
        });
    </script>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>

</html>