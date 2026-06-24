<?php
// user/ui-page/page-8/shop.php
include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';
$searchQuery = trim($_GET['search'] ?? '');
$saleOnly = isset($_GET['sale_only']) && $_GET['sale_only'] === '1';

// ── 1. Read filters from URL ────────────────────────────────────────────────
$selectedCategories = $_GET['categories'] ?? []; // array of category name strings
$selectedColors = $_GET['colors'] ?? [];   // array of colorname strings
$selectedSizes = $_GET['sizes'] ?? [];    // array of sizename strings
$minPriceFilter = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$maxPriceFilter = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;

// sanitize arrays
$selectedCategories = array_filter(array_map('trim', (array) $selectedCategories));
$selectedColors = array_filter(array_map('trim', (array) $selectedColors));
$selectedSizes = array_filter(array_map('trim', (array) $selectedSizes));

// lowercase versions used for case-insensitive comparisons (checked state, matching)
$selectedCategoriesLower = array_map('mb_strtolower', $selectedCategories);
$selectedColorsLower = array_map('mb_strtolower', $selectedColors);
$selectedSizesLower = array_map('mb_strtolower', $selectedSizes);

// ── 2. Fetch available filter options ───────────────────────────────────────

// Categories (case-insensitive dedupe, pulled directly from nobleproduct.category)
$availableCategories = [];
$seenCategories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM nobleproduct WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
while ($row = $catRes->fetch_assoc()) {
    $name = trim($row['category']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenCategories[$key]))
        continue;
    $seenCategories[$key] = true;
    $availableCategories[] = $name; // keeps first-seen casing for display
}

// Colors (case-insensitive dedupe)
$availableColors = [];
$seenColors = [];
$colorRes = $conn->query("SELECT DISTINCT colorname FROM nobleproductcolor WHERE colorname != '' ORDER BY colorname ASC");
while ($row = $colorRes->fetch_assoc()) {
    $name = trim($row['colorname']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenColors[$key]))
        continue;
    $seenColors[$key] = true;
    $availableColors[] = $name; // keeps first-seen casing for display
}

// Sizes (case-insensitive dedupe)
$availableSizes = [];
$seenSizes = [];
$sizeRes = $conn->query("SELECT DISTINCT sizename FROM nobleproductvariant WHERE sizename != '' ORDER BY sizename ASC");
while ($row = $sizeRes->fetch_assoc()) {
    $name = trim($row['sizename']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenSizes[$key]))
        continue;
    $seenSizes[$key] = true;
    $availableSizes[] = $name;
}

$priceBounds = $conn->query("SELECT MIN(pricesize) AS lo, MAX(pricesize) AS hi FROM nobleproductvariant")->fetch_assoc();
$priceLo = floatval($priceBounds['lo'] ?? 0);
$priceHi = floatval($priceBounds['hi'] ?? 0);

// ── 3. Build dynamic product query ──────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if (!empty($selectedCategories)) {
    $placeholders = implode(',', array_fill(0, count($selectedCategories), '?'));
    $where[] = "LOWER(p.category) IN ($placeholders)";
    foreach ($selectedCategoriesLower as $catName) {
        $params[] = $catName;
        $types .= 's';
    }
}

if (!empty($selectedColors)) {
    $placeholders = implode(',', array_fill(0, count($selectedColors), '?'));
    $where[] = "LOWER(c.colorname) IN ($placeholders)";
    foreach ($selectedColorsLower as $cName) {
        $params[] = $cName;
        $types .= 's';
    }
}

if (!empty($selectedSizes)) {
    $placeholders = implode(',', array_fill(0, count($selectedSizes), '?'));
    $where[] = "LOWER(v.sizename) IN ($placeholders)";
    foreach ($selectedSizesLower as $sName) {
        $params[] = $sName;
        $types .= 's';
    }
}

if ($minPriceFilter !== null) {
    $where[] = "v.pricesize >= ?";
    $params[] = $minPriceFilter;
    $types .= 'd';
}

if ($maxPriceFilter !== null) {
    $where[] = "v.pricesize <= ?";
    $params[] = $maxPriceFilter;
    $types .= 'd';
}

if ($searchQuery !== '') {
    $where[] = "(p.name LIKE ? OR p.category LIKE ? OR p.description LIKE ?)";
    $likeTerm = '%' . $searchQuery . '%';
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $types .= 'sss';
}

if ($saleOnly) {
    $where[] = "p.id IN (
        SELECT c2.product_id
        FROM nobleproductcolor c2
        INNER JOIN nobleproductvariant v2 ON v2.color_id = c2.id
        WHERE v2.discountvariant > 0
    )";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        p.id, p.name, p.imageproduct, p.description,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price
    FROM nobleproduct p
    INNER JOIN nobleproductcolor c ON c.product_id = p.id
    INNER JOIN nobleproductvariant v ON v.color_id = c.id
    $whereSql
    GROUP BY p.id
    ORDER BY p.created_at DESC
";

$products = [];
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc())
    $products[] = $row;
$stmt->close();

// ── 4. Fetch colors + variants for the products being shown ────────────────
// Used for: card color swatches (with +N overflow) and the Add to Cart modal
// (color -> size -> price/stock), all without an extra AJAX call per product.
$productColorData = [];
$productIds = array_column($products, 'id');

if (!empty($productIds)) {
    $idPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
    $idTypes = str_repeat('i', count($productIds));

    $detailSql = "
        SELECT
            c.id AS color_id, c.product_id, c.colorname, c.imagecolor,
            v.id AS variant_id, v.sizename, v.pricesize, v.discountvariant, v.stock
        FROM nobleproductcolor c
        INNER JOIN nobleproductvariant v ON v.color_id = c.id
        WHERE c.product_id IN ($idPlaceholders)
        ORDER BY c.colorname ASC, v.sizename ASC
    ";
    $detailStmt = $conn->prepare($detailSql);
    $detailStmt->bind_param($idTypes, ...$productIds);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    while ($row = $detailResult->fetch_assoc()) {
        $pid = $row['product_id'];
        $cid = $row['color_id'];

        if (!isset($productColorData[$pid])) {
            $productColorData[$pid] = [];
        }
        if (!isset($productColorData[$pid][$cid])) {
            $productColorData[$pid][$cid] = [
                'id' => $cid,
                'colorname' => $row['colorname'],
                'imagecolor' => $row['imagecolor'],
                'variants' => [],
            ];
        }
        $productColorData[$pid][$cid]['variants'][] = [
            'id' => $row['variant_id'],
            'sizename' => $row['sizename'],
            'price' => floatval($row['pricesize']),
            'discount' => floatval($row['discountvariant']),
            'stock' => intval($row['stock']),
        ];
    }
    $detailStmt->close();

    // reindex colors as plain arrays for cleaner JSON output
    foreach ($productColorData as $pid => $colors) {
        $productColorData[$pid] = array_values($colors);
    }
}

// Build the JSON blob consumed by the Add to Cart modal JS
$productDataForJs = [];
foreach ($products as $p) {
    $pid = $p['id'];
    $colors = $productColorData[$pid] ?? [];

    $totalStock = 0;
    foreach ($colors as $col) {
        foreach ($col['variants'] as $v) {
            $totalStock += $v['stock'];
        }
    }

    $productDataForJs[$pid] = [
        'id' => $pid,
        'name' => $p['name'],
        'image' => !empty($p['imageproduct']) ? $uploadUrl . $p['imageproduct'] : null,
        'colors' => $colors,
        'total_stock' => $totalStock,
    ];
}

$MAX_SWATCHES = 4; // max color chips shown directly on the card before "+N"
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <!-- Toast -->
    <div id="toast" class="fixed top-6 right-6 z-50 opacity-0 pointer-events-none translate-y-2
                flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium
                bg-white border border-gray-100 text-gray-800 min-w-56">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8 flex-1 w-full">

        <div class="flex flex-col md:flex-row gap-6">

            <!-- ═══════════════ SIDEBAR FILTERS ═══════════════ -->
            <aside class="w-full md:w-64 shrink-0">
                <form method="GET" action="<?= BASE_URL ?>/shop" id="filterForm" class="rounded-xl p-5 sticky top-4">

                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Filters</h2>
                        <a href="<?= BASE_URL ?>/shop"
                            class="text-xs text-amber-500 hover:text-amber-600 font-medium">Clear</a>
                    </div>

                    <!-- Sale Only toggle -->
                    <div class="mb-5 pb-5 border-b border-gray-100">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sale Items Only</span>
                            <div class="relative">
                                <input type="checkbox" name="sale_only" value="1"
                                    <?= $saleOnly ? 'checked' : '' ?>
                                    class="sr-only peer"
                                    onchange="document.getElementById('filterForm').submit()">
                                <div class="w-10 h-5 bg-gray-200 rounded-full peer-checked:bg-amber-500 transition-colors duration-200"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5"></div>
                            </div>
                        </label>
                    </div>

                    <!-- Category filter -->
                    <?php if (!empty($availableCategories)): ?>
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Category</p>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                <?php foreach ($availableCategories as $catName): ?>
                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-amber-600">
                                        <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($catName) ?>"
                                            <?= in_array(mb_strtolower($catName), $selectedCategoriesLower) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                                        <?= htmlspecialchars($catName) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Color filter -->
                    <?php if (!empty($availableColors)): ?>
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Color</p>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                <?php foreach ($availableColors as $cName): ?>
                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-amber-600">
                                        <input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cName) ?>"
                                            <?= in_array(mb_strtolower($cName), $selectedColorsLower) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                                        <?= htmlspecialchars($cName) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Size filter -->
                    <?php if (!empty($availableSizes)): ?>
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Size</p>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                <?php foreach ($availableSizes as $sName): ?>
                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-amber-600">
                                        <input type="checkbox" name="sizes[]" value="<?= htmlspecialchars($sName) ?>"
                                            <?= in_array(mb_strtolower($sName), $selectedSizesLower) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                                        <?= htmlspecialchars($sName) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Price range filter -->
                    <div class="mb-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Price Range</p>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" placeholder="₱<?= number_format($priceLo, 0) ?>"
                                value="<?= $minPriceFilter !== null ? htmlspecialchars((string) $minPriceFilter) : '' ?>"
                                min="0" step="0.01"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
                            <span class="text-gray-300 text-xs">–</span>
                            <input type="number" name="max_price" placeholder="₱<?= number_format($priceHi, 0) ?>"
                                value="<?= $maxPriceFilter !== null ? htmlspecialchars((string) $maxPriceFilter) : '' ?>"
                                min="0" step="0.01"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400">
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1.5">
                            Available range: ₱<?= number_format($priceLo, 2) ?> – ₱<?= number_format($priceHi, 2) ?>
                        </p>
                    </div>

                    <button type="submit"
                        class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition">
                        Apply Filters
                    </button>
                </form>
            </aside>

            <!-- ═══════════════ PRODUCT GRID ═══════════════ -->
            <div class="flex-1">

                <div class="flex items-center justify-between mb-5">
                    <h1 class="text-lg md:text-xl font-bold text-gray-900">Shop</h1>
                    <?php if ($searchQuery !== ''): ?>
                        <p class="text-xs text-gray-400 mb-3">
                            Showing results for "<span
                                class="font-semibold text-gray-600"><?= htmlspecialchars($searchQuery) ?></span>"
                        </p>
                    <?php endif; ?>
                    <span class="text-xs text-gray-400"><?= count($products) ?>
                        product<?= count($products) !== 1 ? 's' : '' ?> found</span>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-20 text-gray-400">
                        <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
                        <p class="text-lg">No products match your filters.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
                        <?php foreach ($products as $p):
                            $cardColors = $productColorData[$p['id']] ?? [];
                            $visibleColors = array_slice($cardColors, 0, $MAX_SWATCHES);
                            $extraColorCount = max(0, count($cardColors) - $MAX_SWATCHES);

                            // total stock across all colors/sizes for this product
                            // and whether any variant currently has a discount
                            $cardTotalStock = 0;
                            $hasSale = false;
                            foreach ($cardColors as $col) {
                                foreach ($col['variants'] as $v) {
                                    $cardTotalStock += $v['stock'];
                                    if ($v['discount'] > 0) {
                                        $hasSale = true;
                                    }
                                }
                            }
                            ?>
                            <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>" class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
                                  block hover:shadow-lg transition-shadow duration-300">

                                <div
                                    class="aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4 relative">
                                    <?php if ($hasSale): ?>
                                        <span
                                            class="absolute top-1.5 left-1.5 md:top-2 md:left-2 z-10 bg-red-500 text-white text-[8px] md:text-[10px] font-bold px-1.5 md:px-2 py-0.5 rounded-full shadow">
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
                                    <h3
                                        class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide leading-snug mb-0.5 md:mb-1 line-clamp-1">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </h3>

                                    <?php if (!empty($p['description'])): ?>
                                        <p class="text-xs text-gray-400 line-clamp-1 md:line-clamp-2 mb-1 md:mb-2 hidden sm:block">
                                            <?= htmlspecialchars($p['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Color swatches with +N overflow -->
                                    <?php if (!empty($cardColors)): ?>
                                        <div class="flex items-center gap-1 mb-1.5 md:mb-2 flex-wrap">
                                            <?php foreach ($visibleColors as $col): ?>
                                                <span
                                                    class="text-[8px] md:text-[9px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200 whitespace-nowrap">
                                                    <?= htmlspecialchars($col['colorname']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if ($extraColorCount > 0): ?>
                                                <span
                                                    class="text-[8px] md:text-[9px] px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-600 font-semibold border border-amber-100">
                                                    +<?= $extraColorCount ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-1 md:mt-2">
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
                                    </div>

                                    <!-- Stock indicator -->
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

                                    <!-- Add to Cart -->
                                    <button type="button"
                                        onclick="event.preventDefault(); event.stopPropagation(); openAddToCart(<?= $p['id'] ?>)"
                                        class="mt-1.5 md:mt-2 w-full py-1.5 text-[10px] md:text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                                        <i class="fa-solid fa-cart-plus mr-1"></i> Add to Cart
                                    </button>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- ═══════════════ ADD TO CART MODAL ═══════════════ -->
    <div id="addToCartModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4"
        onclick="closeModalBackdrop(event)">
        <div class="bg-white rounded-2xl w-full max-w-sm p-5 relative" onclick="event.stopPropagation()">
            <button onclick="closeAddToCartModal()" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500"
                title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="flex gap-3 mb-4">
                <div id="modalProductImage"
                    class="w-16 h-16 rounded-xl bg-gray-50 overflow-hidden flex items-center justify-center border border-gray-100 shrink-0">
                </div>
                <div>
                    <h3 id="modalProductName" class="text-sm font-bold text-gray-900"></h3>
                    <p id="modalProductPrice" class="text-sm font-semibold text-amber-600 mt-1"></p>
                </div>
            </div>

            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Color</p>
                <div id="modalColorOptions" class="flex flex-wrap gap-2"></div>
            </div>

            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Size</p>
                <div id="modalSizeOptions" class="flex flex-wrap gap-2">
                    <span class="text-xs text-gray-400">Select a color first</span>
                </div>
            </div>

            <p id="modalStockLabel" class="text-xs mb-3"></p>

            <div class="flex items-center gap-3 mb-4">
                <span class="text-xs text-gray-500">Quantity</span>
                <div class="flex items-center gap-1 border border-gray-200 rounded-lg overflow-hidden">
                    <button type="button" onclick="modalChangeQty(-1)"
                        class="w-7 h-7 flex items-center justify-center text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition text-xs">
                        <i class="fa-solid fa-minus"></i>
                    </button>
                    <input type="number" id="modalQtyInput" value="1" min="1"
                        class="qty-input w-10 h-7 text-center text-xs font-semibold text-gray-800 border-x border-gray-200 focus:outline-none focus:bg-amber-50">
                    <button type="button" onclick="modalChangeQty(1)"
                        class="w-7 h-7 flex items-center justify-center text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition text-xs">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>

            <button id="modalAddBtn" onclick="confirmAddToCart()" disabled
                class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition">
                Add to Cart
            </button>
        </div>
    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <style>
        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .qty-input {
            -moz-appearance: textfield;
        }

        #toast {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
    </style>

    <script>
        // NOTE: adjust this URL if your route to cart-add.php is named differently.
        const addToCartUrl = <?= json_encode(BASE_URL . '/cartadd') ?>;
        const PRODUCT_DATA = <?= json_encode($productDataForJs, JSON_HEX_TAG) ?>;
        const LOW_STOCK_THRESHOLD = 5;

        let currentModalState = { productId: null, colorId: null, variantId: null };

        // ── Open / close modal ──────────────────────────────────────────────
        function openAddToCart(productId) {
            const product = PRODUCT_DATA[productId];
            if (!product) return;

            currentModalState = { productId, colorId: null, variantId: null };

            document.getElementById('modalProductName').textContent = product.name;

            const imgWrap = document.getElementById('modalProductImage');
            imgWrap.innerHTML = product.image
                ? `<img src="${product.image}" class="w-full h-full object-contain p-1">`
                : `<i class="fa-solid fa-image text-2xl text-gray-200"></i>`;

            document.getElementById('modalProductPrice').textContent = '';
            document.getElementById('modalStockLabel').textContent = '';
            document.getElementById('modalSizeOptions').innerHTML =
                '<span class="text-xs text-gray-400">Select a color first</span>';
            document.getElementById('modalQtyInput').value = 1;
            document.getElementById('modalAddBtn').disabled = true;

            renderColorOptions(product.colors);

            const modal = document.getElementById('addToCartModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeAddToCartModal() {
            const modal = document.getElementById('addToCartModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function closeModalBackdrop(e) {
            if (e.target.id === 'addToCartModal') closeAddToCartModal();
        }

        // ── Color step ───────────────────────────────────────────────────────
        function renderColorOptions(colors) {
            const wrap = document.getElementById('modalColorOptions');
            wrap.innerHTML = '';

            if (!colors || colors.length === 0) {
                wrap.innerHTML = '<span class="text-xs text-gray-400">No colors available</span>';
                return;
            }

            colors.forEach(color => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = color.colorname;
                btn.className = 'color-option px-3 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600 hover:border-amber-400 transition';
                btn.dataset.colorId = color.id;
                btn.onclick = () => selectColor(color.id);
                wrap.appendChild(btn);
            });
        }

        function selectColor(colorId) {
            currentModalState.colorId = colorId;
            currentModalState.variantId = null;

            document.querySelectorAll('#modalColorOptions .color-option').forEach(btn => {
                const isSelected = parseInt(btn.dataset.colorId) === colorId;
                btn.classList.toggle('border-amber-500', isSelected);
                btn.classList.toggle('bg-amber-50', isSelected);
                btn.classList.toggle('text-amber-600', isSelected);
            });

            const product = PRODUCT_DATA[currentModalState.productId];
            const color = product.colors.find(c => c.id === colorId);

            const sizeWrap = document.getElementById('modalSizeOptions');
            sizeWrap.innerHTML = '';

            document.getElementById('modalProductPrice').textContent = '';
            document.getElementById('modalStockLabel').textContent = '';
            document.getElementById('modalAddBtn').disabled = true;

            if (!color || color.variants.length === 0) {
                sizeWrap.innerHTML = '<span class="text-xs text-gray-400">No sizes available</span>';
                return;
            }

            color.variants.forEach(v => {
                const outOfStock = v.stock <= 0;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = v.sizename + (outOfStock ? ' (Out of stock)' : '');
                btn.disabled = outOfStock;
                btn.className = 'size-option px-3 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600 hover:border-amber-400 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:border-gray-200';
                btn.dataset.variantId = v.id;
                btn.onclick = () => selectSize(v.id);
                sizeWrap.appendChild(btn);
            });
        }

        // ── Size step (price + stock update here) ───────────────────────────
        function selectSize(variantId) {
            currentModalState.variantId = variantId;

            document.querySelectorAll('#modalSizeOptions .size-option').forEach(btn => {
                const isSelected = parseInt(btn.dataset.variantId) === variantId;
                btn.classList.toggle('border-amber-500', isSelected);
                btn.classList.toggle('bg-amber-50', isSelected);
                btn.classList.toggle('text-amber-600', isSelected);
            });

            const product = PRODUCT_DATA[currentModalState.productId];
            const color = product.colors.find(c => c.id === currentModalState.colorId);
            const variant = color.variants.find(v => v.id === variantId);
            if (!variant) return;

            const finalPrice = variant.discount > 0
                ? variant.price * (1 - variant.discount / 100)
                : variant.price;

            const priceLabel = document.getElementById('modalProductPrice');
            priceLabel.innerHTML = variant.discount > 0
                ? `₱${formatPrice(finalPrice)} <span class="text-xs text-gray-400 line-through ml-1">₱${formatPrice(variant.price)}</span> <span class="text-xs text-red-400 font-semibold ml-1">-${variant.discount}%</span>`
                : `₱${formatPrice(finalPrice)}`;

            const qtyInput = document.getElementById('modalQtyInput');
            qtyInput.max = variant.stock > 0 ? variant.stock : 1;
            qtyInput.value = 1;

            const stockLabel = document.getElementById('modalStockLabel');
            if (variant.stock <= 0) {
                stockLabel.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
            } else if (variant.stock <= LOW_STOCK_THRESHOLD) {
                stockLabel.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${variant.stock} left in stock</span>`;
            } else {
                stockLabel.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>${variant.stock} in stock</span>`;
            }
            document.getElementById('modalAddBtn').disabled = variant.stock <= 0;
        }

        // ── Quantity ─────────────────────────────────────────────────────────
        function modalChangeQty(delta) {
            const input = document.getElementById('modalQtyInput');
            const max = parseInt(input.max) || 99;
            let val = parseInt(input.value || '1') + delta;
            val = Math.max(1, Math.min(val, max));
            input.value = val;
        }

        // ── Submit to cart-add.php ───────────────────────────────────────────
        async function confirmAddToCart() {
            const { productId, colorId, variantId } = currentModalState;
            if (!productId || !colorId || !variantId) {
                showToast('error', 'Please select a color and size.');
                return;
            }

            const qty = parseInt(document.getElementById('modalQtyInput').value) || 1;

            try {
                const fd = new FormData();
                fd.append('product_id', productId);
                fd.append('color_id', colorId);
                fd.append('variant_id', variantId);
                fd.append('qty', qty);

                const res = await fetch(addToCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    showToast('success', data.msg || 'Added to cart!');

                    const counter = document.getElementById('cart-count');
                    if (counter && data.cart_count !== undefined) {
                        counter.textContent = data.cart_count;
                        counter.classList.remove('hidden');
                    }

                    closeAddToCartModal();
                } else {
                    showToast('error', data.msg || 'Failed to add to cart.');
                    // refresh the size's stock display if server says it's out/over
                    if (data.out_of_stock !== undefined) {
                        selectSize(variantId);
                    }
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
            }
        }

        // ── Helpers ───────────────────────────────────────────────────────────
        function formatPrice(num) {
            return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }

        function showToast(type, msg) {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const text = document.getElementById('toast-msg');

            icon.innerHTML = type === 'success'
                ? '<i class="fa-solid fa-circle-check text-green-500"></i>'
                : '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
            text.textContent = msg;

            toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
            toast.classList.add('opacity-100', 'translate-y-0');

            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                toast.classList.remove('opacity-100', 'translate-y-0');
            }, 3000);
        }
    </script>

</body>

</html>