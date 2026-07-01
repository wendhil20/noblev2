<?php
// user/ui-page/page-8/shop-products.php

header('Content-Type: application/json');

include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';

// ── Read filters ─────────────────────────────────────────────────────────────
$searchQuery       = trim($_GET['search']    ?? '');
$saleOnly          = isset($_GET['sale_only']) && $_GET['sale_only'] === '1';
$selectedCategories = array_filter(array_map('trim', (array)($_GET['categories'] ?? [])));
$selectedColors     = array_filter(array_map('trim', (array)($_GET['colors']     ?? [])));
$selectedSizes      = array_filter(array_map('trim', (array)($_GET['sizes']      ?? [])));
$minPriceFilter    = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$maxPriceFilter    = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;

$selectedCategoriesLower = array_map('mb_strtolower', $selectedCategories);
$selectedColorsLower     = array_map('mb_strtolower', $selectedColors);
$selectedSizesLower      = array_map('mb_strtolower', $selectedSizes);

$perPage = 8;
$page    = max(1, intval($_GET['page'] ?? 1));

function formatSoldCount($n) {
    $n = intval($n);
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    }
    return number_format($n);
}

// ── Build WHERE clause ───────────────────────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

if (!empty($selectedCategories)) {
    $ph = implode(',', array_fill(0, count($selectedCategories), '?'));
    $where[] = "LOWER(p.category) IN ($ph)";
    foreach ($selectedCategoriesLower as $v) { $params[] = $v; $types .= 's'; }
}
if (!empty($selectedColors)) {
    $ph = implode(',', array_fill(0, count($selectedColors), '?'));
    $where[] = "LOWER(c.colorname) IN ($ph)";
    foreach ($selectedColorsLower as $v) { $params[] = $v; $types .= 's'; }
}
if (!empty($selectedSizes)) {
    $ph = implode(',', array_fill(0, count($selectedSizes), '?'));
    $where[] = "LOWER(v.sizename) IN ($ph)";
    foreach ($selectedSizesLower as $v) { $params[] = $v; $types .= 's'; }
}
if ($minPriceFilter !== null) { $where[] = "v.pricesize >= ?"; $params[] = $minPriceFilter; $types .= 'd'; }
if ($maxPriceFilter !== null) { $where[] = "v.pricesize <= ?"; $params[] = $maxPriceFilter; $types .= 'd'; }
if ($searchQuery !== '') {
    $where[] = "(p.name LIKE ? OR p.category LIKE ? OR p.description LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($saleOnly) {
    $where[] = "p.id IN (
        SELECT c2.product_id FROM nobleproductcolor c2
        INNER JOIN nobleproductvariant v2 ON v2.color_id = c2.id
        WHERE v2.discountvariant > 0
    )";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ────────────────────────────────────────────────────────────────────
$countSql = "
    SELECT COUNT(*) AS total FROM (
        SELECT p.id FROM nobleproduct p
        INNER JOIN nobleproductcolor c ON c.product_id = p.id
        INNER JOIN nobleproductvariant v ON v.color_id = c.id
        $whereSql GROUP BY p.id
    ) AS t";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalProducts = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// ── Fetch products ───────────────────────────────────────────────────────────
$sql = "
    SELECT p.id, p.name, p.imageproduct, p.description,
           MIN(v.pricesize) AS min_price, MAX(v.pricesize) AS max_price,
           AVG(r.rating) AS avg_rating,
           COUNT(DISTINCT r.id) AS review_count,
           (
               SELECT COALESCE(SUM(v2.sold), 0)
               FROM nobleproductvariant v2
               JOIN nobleproductcolor c2 ON c2.id = v2.color_id
               WHERE c2.product_id = p.id
           ) AS total_sold
    FROM nobleproduct p
    INNER JOIN nobleproductcolor c ON c.product_id = p.id
    INNER JOIN nobleproductvariant v ON v.color_id = c.id
    LEFT JOIN noblereview r ON r.product_id = p.id
    $whereSql
    GROUP BY p.id ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

$pagedParams = array_merge($params, [$perPage, $offset]);
$pagedTypes  = $types . 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($pagedTypes, ...$pagedParams);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Color + variant data for shown products ──────────────────────────────────
$productColorData = [];
$productIds = array_column($products, 'id');

if (!empty($productIds)) {
    $idPh    = implode(',', array_fill(0, count($productIds), '?'));
    $idTypes = str_repeat('i', count($productIds));

    $detailStmt = $conn->prepare("
        SELECT c.id AS color_id, c.product_id, c.colorname, c.imagecolor,
               v.id AS variant_id, v.sizename, v.pricesize, v.discountvariant, v.stock
        FROM nobleproductcolor c
        INNER JOIN nobleproductvariant v ON v.color_id = c.id
        WHERE c.product_id IN ($idPh)
        ORDER BY c.colorname ASC, v.sizename ASC");
    $detailStmt->bind_param($idTypes, ...$productIds);
    $detailStmt->execute();

    foreach ($detailStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $pid = $row['product_id'];
        $cid = $row['color_id'];
        if (!isset($productColorData[$pid][$cid])) {
            $productColorData[$pid][$cid] = [
                'id'        => $cid,
                'colorname' => $row['colorname'],
                'imagecolor'=> $row['imagecolor'],
                'variants'  => [],
            ];
        }
        $productColorData[$pid][$cid]['variants'][] = [
            'id'       => $row['variant_id'],
            'sizename' => $row['sizename'],
            'price'    => floatval($row['pricesize']),
            'discount' => floatval($row['discountvariant']),
            'stock'    => intval($row['stock']),
        ];
    }
    $detailStmt->close();
    foreach ($productColorData as $pid => $cols) {
        $productColorData[$pid] = array_values($cols);
    }
}

// ── JS product data blob ─────────────────────────────────────────────────────
$productDataForJs = [];
$MAX_SWATCHES = 4;

foreach ($products as $p) {
    $pid    = $p['id'];
    $colors = $productColorData[$pid] ?? [];
    $stock  = 0;
    foreach ($colors as $col) foreach ($col['variants'] as $v) $stock += $v['stock'];
    $productDataForJs[$pid] = [
        'id'          => $pid,
        'name'        => $p['name'],
        'image'       => !empty($p['imageproduct']) ? $uploadUrl . $p['imageproduct'] : null,
        'colors'      => $colors,
        'total_stock' => $stock,
    ];
}

// ── Helper: build URL preserving current filters ─────────────────────────────
function buildPageUrl(array $base, int $pageNum): string {
    $base['page'] = $pageNum;
    return BASE_URL . '/shop?' . http_build_query($base);
}

// ── Render HTML ──────────────────────────────────────────────────────────────
ob_start();
?>
<?php if (empty($products)): ?>
    <div class="col-span-full flex flex-col items-center justify-center py-20 text-gray-400">
        <i class="fa-solid fa-box-open text-5xl mb-4"></i>
        <p class="text-lg">No products match your filters.</p>
    </div>
<?php else: ?>
    <?php foreach ($products as $p):
        $cardColors     = $productColorData[$p['id']] ?? [];
        $visibleColors  = array_slice($cardColors, 0, $MAX_SWATCHES);
        $extraColorCount= max(0, count($cardColors) - $MAX_SWATCHES);
        $cardTotalStock = 0;
        $hasSale        = false;
        foreach ($cardColors as $col) foreach ($col['variants'] as $v) {
            $cardTotalStock += $v['stock'];
            if ($v['discount'] > 0) $hasSale = true;
        }
    ?>
    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>"
       class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
              block hover:shadow-lg transition-shadow duration-300">

        <div class="aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4 relative">
            <?php if ($hasSale): ?>
                <span class="absolute top-1.5 left-1.5 md:top-2 md:left-2 z-10 bg-red-500 text-white
                             text-[8px] md:text-[10px] font-bold px-1.5 md:px-2 py-0.5 rounded-full shadow">
                    SALE
                </span>
            <?php endif; ?>
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
            <h3 class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide
                       leading-snug mb-0.5 md:mb-1 line-clamp-1">
                <?= htmlspecialchars($p['name']) ?>
            </h3>

            <?php if (!empty($p['description'])): ?>
                <p class="text-xs text-gray-400 line-clamp-1 md:line-clamp-2 mb-1 md:mb-2 hidden sm:block">
                    <?= htmlspecialchars($p['description']) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($cardColors)): ?>
                <div class="flex items-center gap-1 mb-1.5 md:mb-2 flex-wrap">
                    <?php foreach ($visibleColors as $col): ?>
                        <span class="text-[8px] md:text-[9px] px-1.5 py-0.5 rounded-full
                                     bg-gray-100 text-gray-500 border border-gray-200 whitespace-nowrap">
                            <?= htmlspecialchars($col['colorname']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if ($extraColorCount > 0): ?>
                        <span class="text-[8px] md:text-[9px] px-1.5 py-0.5 rounded-full
                                     bg-amber-50 text-amber-600 font-semibold border border-amber-100">
                            +<?= $extraColorCount ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

                <div class="flex items-center gap-2 mb-1 flex-wrap">
        <?php if (!empty($p['review_count']) && $p['review_count'] > 0): ?>
            <div class="flex items-center gap-1">
                <i class="fa-solid fa-star text-amber-400 text-[9px] md:text-[10px]"></i>
                <span class="text-[9px] md:text-[10px] font-semibold text-gray-700">
                    <?= number_format($p['avg_rating'], 1) ?>
                </span>
                <span class="text-[8px] md:text-[9px] text-gray-400">
                    (<?= (int) $p['review_count'] ?>)
                </span>
            </div>
        <?php endif; ?>
        <?php if (!empty($p['total_sold']) && $p['total_sold'] > 0): ?>
            <span class="text-[8px] md:text-[9px] text-gray-400">
                <?= formatSoldCount($p['total_sold']) ?> sold
            </span>
        <?php endif; ?>
    </div>


            <div class="mt-1 md:mt-2">
                <?php $min = floatval($p['min_price'] ?? 0); $max = floatval($p['max_price'] ?? 0); ?>
                <?php if ($min > 0 || $max > 0): ?>
                    <span class="text-[10px] md:text-sm font-semibold text-gray-800">
                        ₱<?= number_format($min, 2) ?>
                        <?= $min !== $max ? ' – ₱' . number_format($max, 2) : '' ?>
                    </span>
                <?php else: ?>
                    <span class="text-[10px] md:text-xs text-gray-400 italic">Price not set</span>
                <?php endif; ?>
            </div>

            <p class="text-[10px] md:text-xs mt-1 mb-1.5 md:mb-2">
                <?php if ($cardTotalStock <= 0): ?>
                    <span class="text-red-500 font-medium">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock
                    </span>
                <?php elseif ($cardTotalStock <= 5): ?>
                    <span class="text-amber-600 font-medium">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= $cardTotalStock ?> left
                    </span>
                <?php else: ?>
                    <span class="text-green-600">
                        <i class="fa-solid fa-circle-check mr-1"></i><?= $cardTotalStock ?> in stock
                    </span>
                <?php endif; ?>
            </p>

            <button type="button"
                onclick="event.preventDefault(); event.stopPropagation(); openAddToCart(<?= $p['id'] ?>)"
                class="mt-1.5 md:mt-2 w-full py-1.5 text-[10px] md:text-xs font-semibold rounded-lg
                       bg-amber-500 hover:bg-amber-600 text-white transition">
                <i class="fa-solid fa-cart-plus mr-1"></i> Add to Cart
            </button>
        </div>
    </a>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$html = ob_get_clean();

// ── Render pagination separately ─────────────────────────────────────────────
ob_start();
if ($totalPages > 1): ?>
    <div class="flex items-center justify-center gap-1.5 mt-8">
        <button type="button" data-page="<?= $page - 1 ?>"
            class="page-btn w-8 h-8 flex items-center justify-center rounded-lg border text-xs transition
                   <?= $page > 1
                       ? 'border-gray-200 text-gray-500 hover:bg-amber-50 hover:border-amber-300'
                       : 'border-gray-100 text-gray-300 cursor-not-allowed pointer-events-none' ?>"
            <?= $page <= 1 ? 'disabled' : '' ?>>
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <button type="button" data-page="<?= $i ?>"
                class="page-btn w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold transition
                       <?= $i === $page
                           ? 'bg-amber-500 text-white'
                           : 'border border-gray-200 text-gray-500 hover:bg-amber-50 hover:border-amber-300' ?>">
                <?= $i ?>
            </button>
        <?php endfor; ?>

        <button type="button" data-page="<?= $page + 1 ?>"
            class="page-btn w-8 h-8 flex items-center justify-center rounded-lg border text-xs transition
                   <?= $page < $totalPages
                       ? 'border-gray-200 text-gray-500 hover:bg-amber-50 hover:border-amber-300'
                       : 'border-gray-100 text-gray-300 cursor-not-allowed pointer-events-none' ?>"
            <?= $page >= $totalPages ? 'disabled' : '' ?>>
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>
<?php endif;
$paginationHtml = ob_get_clean();

echo json_encode([
    'html'         => $html,
    'pagination'   => $paginationHtml,
    'total'        => $totalProducts,
    'product_data' => $productDataForJs,
]);