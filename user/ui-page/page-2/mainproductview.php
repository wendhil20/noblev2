<?php
// mainproductview.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-2/productqtydiscount-helper.php'; // getProductQtyLimit(), getProductQtyMin(), getProductQtyTiers()

$uploadUrl = BASE_URL . '/uploads/';

$productId = intval($_GET['id'] ?? 0);
if (!$productId) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM nobleproduct WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Load colors
$colors = [];
$colorRes = $conn->prepare("SELECT * FROM nobleproductcolor WHERE product_id = ? ORDER BY id ASC");
$colorRes->bind_param("i", $productId);
$colorRes->execute();
$colorResult = $colorRes->get_result();
while ($c = $colorResult->fetch_assoc()) {
    $c['variants'] = [];
    $colors[$c['id']] = $c;
}
$colorRes->close();

// Load variants per color
if (!empty($colors)) {
    $colorIds = implode(',', array_keys($colors));
    $varRes = $conn->query("SELECT * FROM nobleproductvariant WHERE color_id IN ($colorIds) ORDER BY pricesize ASC");
    while ($v = $varRes->fetch_assoc()) {
        $colors[$v['color_id']]['variants'][] = $v;
    }
}
$colors = array_values($colors);

// Collect unique size names across all colors (for display)
$allSizes = [];
foreach ($colors as $color) {
    foreach ($color['variants'] as $v) {
        if (!isset($allSizes[$v['sizename']])) {
            $allSizes[$v['sizename']] = $v['sizename'];
        }
    }
}
$allSizes = array_values($allSizes);

// Price range
$priceStmt = $conn->prepare("
    SELECT MIN(v.pricesize) as min_price, MAX(v.pricesize) as max_price
    FROM nobleproductvariant v
    JOIN nobleproductcolor c ON c.id = v.color_id
    WHERE c.product_id = ?
");
$priceStmt->bind_param("i", $productId);
$priceStmt->execute();
$priceRange = $priceStmt->get_result()->fetch_assoc();
$priceStmt->close();

// ─── Total sold for this product ────────────────────────────────────────
$soldStmt = $conn->prepare("
    SELECT COALESCE(SUM(v.sold), 0) as total_sold
    FROM nobleproductvariant v
    JOIN nobleproductcolor c ON c.id = v.color_id
    WHERE c.product_id = ?
");
$soldStmt->bind_param("i", $productId);
$soldStmt->execute();
$totalSold = intval($soldStmt->get_result()->fetch_assoc()['total_sold'] ?? 0);
$soldStmt->close();

// ─── Active promos (date-based discount timer, per-color/size or general) ──
$productPromos = [];
$promoStmt = $conn->prepare("
    SELECT color_id, sizename, discount_percent, end_date
    FROM nobleproductpromo
    WHERE product_id = ? AND NOW() BETWEEN start_date AND end_date
");
$promoStmt->bind_param("i", $productId);
$promoStmt->execute();
$promoResult = $promoStmt->get_result();
while ($row = $promoResult->fetch_assoc()) {
    $productPromos[] = [
        'color_id' => $row['color_id'] !== null ? intval($row['color_id']) : null, // null = all colors
        'sizename' => $row['sizename'], // null = all sizes
        'discount_percent' => floatval($row['discount_percent']),
        'end_date' => $row['end_date'],
    ];
}
$promoStmt->close();

// ─── Fetch reviews for this product ────────────────────────────────────────
$reviews = [];
$avgRating = 0;
$totalReviews = 0;

$revStmt = $conn->prepare("
    SELECT r.rating, r.comment, r.created_at, u.name, u.avatar
    FROM noblereview r
    INNER JOIN nobleuseraccount u ON u.id = r.user_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
");
$revStmt->bind_param("i", $productId);
$revStmt->execute();
$revResult = $revStmt->get_result();
while ($r = $revResult->fetch_assoc()) {
    $reviews[] = $r;
}
$revStmt->close();

$totalReviews = count($reviews);
if ($totalReviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / $totalReviews, 1);
}

// Rating breakdown (5★ to 1★ counts) — para sa bar distribution
$ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $r) {
    $rt = (int) $r['rating'];
    if (isset($ratingBreakdown[$rt])) {
        $ratingBreakdown[$rt]++;
    }
}

$isLoggedIn = !empty($_SESSION['user_id']);

// ─── I-record ang recent view ng user para sa product na ito ──────────────
if ($isLoggedIn) {
    $rvStmt = $conn->prepare("
        INSERT INTO noblerecentview (user_id, product_id, viewed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $rvStmt->bind_param("ii", $_SESSION['user_id'], $productId);
    $rvStmt->execute();
    $rvStmt->close();
}

$min = floatval($priceRange['min_price'] ?? 0);
$max = floatval($priceRange['max_price'] ?? 0);

// ─── Qty limit + min + tiered discount (PER PRODUCT — same sa lahat ng color/size) ──
$productQtyLimit = getProductQtyLimit($conn, $productId);   // 0 = walang max limit
$productQtyMin = getProductQtyMin($conn, $productId);     // 1 = walang minimum (default)
$qtyTiers = getProductQtyTiers($conn, $productId);  // [{min_qty,max_qty,discount_percent}, ...]

// Ilan na ba ang nasa cart ng user para sa product na ito (across lahat ng color/size)?
// Ito ang gagamitin para malaman kung gaano pa puede idagdag bago maabot ang limit.
$currentProductCartQty = 0;
if ($isLoggedIn) {
    $cartQtyStmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM noblecart WHERE user_id = ? AND product_id = ?");
    $cartQtyStmt->bind_param("ii", $_SESSION['user_id'], $productId);
    $cartQtyStmt->execute();
    $currentProductCartQty = intval($cartQtyStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $cartQtyStmt->close();
}

// ─── Cart qty PER VARIANT (para malaman kung ilan na ang laman sa cart para sa
// bawat specific na color/size combination — dito kukunin ang TOTOONG available
// stock: raw stock minus quantity na nasa cart na ng user, hindi basta raw stock) ──
$variantCartQty = []; // variant_id => qty already in cart
if ($isLoggedIn) {
    $vcStmt = $conn->prepare("
        SELECT variant_id, SUM(quantity) as qty
        FROM noblecart
        WHERE user_id = ? AND product_id = ?
        GROUP BY variant_id
    ");
    $vcStmt->bind_param("ii", $_SESSION['user_id'], $productId);
    $vcStmt->execute();
    $vcRes = $vcStmt->get_result();
    while ($row = $vcRes->fetch_assoc()) {
        $variantCartQty[intval($row['variant_id'])] = intval($row['qty']);
    }
    $vcStmt->close();
}

if (!function_exists('formatSoldCount')) {
    function formatSoldCount($n)
    {
        $n = intval($n);
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
        }
        return number_format($n);
    }
}

$savedIds = [];
if ($isLoggedIn) {
    $savedResult = $conn->query("SELECT product_id FROM noblesavedproduct WHERE user_id = " . intval($_SESSION['user_id']));
    if ($savedResult) {
        while ($row = $savedResult->fetch_assoc()) {
            $savedIds[] = (int) $row['product_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
    <style>
        .color-btn.selected {
            border-color: #f59e0b;
            background-color: #fffbeb;
            color: #b45309;
        }

        .size-btn.selected {
            border-color: #f59e0b;
            background-color: #fffbeb;
            color: #b45309;
        }

        .size-btn.unavailable {
            opacity: 0.35;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        #toast {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .qty-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }
    </style>
</head>

<body class="bg-gray-50">

    <div id="toast" class="fixed top-4 right-4 md:top-6 md:right-6 z-50 opacity-0 pointer-events-none translate-y-2
                flex items-center gap-2 md:gap-3 px-3 py-2 md:px-4 md:py-3 rounded-xl shadow-lg
                text-xs md:text-sm font-medium bg-white border border-gray-100 text-gray-800 min-w-40 md:min-w-56">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-5 pb-24 md:pb-5">

        <a href="javascript:void(0)" onclick="goBackSafe()"
            class="inline-flex items-center gap-1.5 text-xs md:text-sm text-gray-400 hover:text-amber-500 transition mb-4 md:mb-6">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </a>

        <div class="bg-white overflow-hidden rounded-xl md:rounded-2xl border border-gray-100 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2">

                <!-- Image -->
                <div class="bg-white flex items-center justify-center p-6 md:p-10 min-h-56 md:min-h-80">
                    <?php if (!empty($product['imageproduct'])): ?>
                        <div id="img-zoom-container" class="relative overflow-hidden cursor-crosshair select-none"
                            style="width:100%; max-width:600px;">
                            <img id="main-image" src="<?= $uploadUrl . htmlspecialchars($product['imageproduct']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                                class="max-h-52 md:max-h-[650px] object-contain w-full transition-opacity duration-200"
                                draggable="false" loading="lazy">
                            <!-- Lens overlay -->
                            <div id="zoom-lens"
                                class="hidden absolute border-2 border-amber-400 bg-white/20 pointer-events-none"
                                style="width:90px; height:90px; border-radius:50%; box-shadow:0 0 0 9999px rgba(0,0,0,0.08);">
                            </div>
                        </div>
                        <!-- Zoom result box -->
                        <div id="zoom-result"
                            class="hidden absolute z-30 border border-gray-200 rounded-xl shadow-xl bg-white overflow-hidden pointer-events-none"
                            style="width:260px; height:260px; background-repeat:no-repeat;">
                        </div>
                    <?php else: ?>
                        <div class="text-gray-300 text-center">
                            <i class="fa-solid fa-image text-4xl md:text-6xl mb-2 block"></i>
                            <span class="text-xs md:text-sm">No image</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Details -->
                <div class="p-5 md:p-8 flex flex-col">
                    <div class="flex items-start justify-between mb-1.5 md:mb-2">
                        <?php if (!empty($product['category'])): ?>
                            <span class="text-[10px] md:text-xs font-semibold text-amber-500 uppercase tracking-widest">
                                <?= htmlspecialchars($product['category']) ?>
                            </span>
                        <?php endif; ?>
                        <div class="flex items-center gap-2">
                            <button type="button" id="save-btn" class="w-12 h-12 md:w-9 md:h-9 rounded-full bg-white
               flex items-center justify-center transition duration-200
               hover:bg-gray-50 active:scale-90" data-product-id="<?= $productId ?>" aria-label="Save to favorites">
                                <i
                                    class="<?= in_array($productId, $savedIds ?? []) ? 'fa-solid text-red-500' : 'fa-regular text-gray-500' ?> fa-bookmark text-lg md:text-base transition-transform"></i>
                            </button>

                            <button type="button" id="share-btn" class="w-12 h-12 md:w-9 md:h-9 rounded-full bg-white
               flex items-center justify-center transition duration-200
               hover:bg-gray-50 active:scale-90" data-product-id="<?= $productId ?>"
                                data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>"
                                aria-label="Share product">
                                <i class="fa-regular fa-share-from-square text-gray-500 text-lg md:text-base"></i>
                            </button>
                        </div>
                    </div>

                    <h1 class="text-lg md:text-2xl font-bold text-gray-900 mb-2 md:mb-3">
                        <?= htmlspecialchars($product['name']) ?>
                        <?php if (!empty($product['unit'])): ?>
                            <span class="text-sm md:text-base font-normal text-gray-400 ml-1">·
                                <?= htmlspecialchars($product['unit']) ?></span>
                        <?php endif; ?>
                    </h1>

                    <?php if ($totalReviews > 0): ?>
                        <button type="button"
                            onclick="document.getElementById('tab-reviews')?.click(); document.getElementById('panel-reviews')?.scrollIntoView({behavior:'smooth', block:'start'});"
                            class="flex items-center gap-1.5 mb-2 md:mb-3 w-fit">
                            <span class="flex items-center gap-0.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i
                                        class="fa-star text-xs <?= $i <= round($avgRating) ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </span>
                            <span class="text-xs text-gray-500 font-medium"><?= number_format($avgRating, 1) ?></span>
                            <span class="text-xs text-gray-400">(<?= $totalReviews ?>
                                review<?= $totalReviews !== 1 ? 's' : '' ?>)</span>
                            <?php if ($totalSold > 0): ?>
                                <span class="text-gray-300">·</span>
                                <span class="text-xs text-gray-400"><?= formatSoldCount($totalSold) ?> sold</span>
                            <?php endif; ?>
                        </button>
                    <?php else: ?>
                        <?php if ($totalSold > 0): ?>
                            <div class="flex items-center gap-1.5 mb-2 md:mb-3 w-fit">
                                <span class="text-xs text-gray-400"><?= formatSoldCount($totalSold) ?> sold</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center gap-2 mb-1.5 md:mb-2">
                        <div id="price-display">
                            <?php if ($min > 0 || $max > 0): ?>
                                <span class="text-base md:text-xl font-bold text-gray-900">
                                    ₱<?= number_format($min, 2) ?>
                                    <?= $min !== $max ? ' – ₱' . number_format($max, 2) : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs md:text-sm text-gray-400 italic">Price not set</span>
                            <?php endif; ?>
                        </div>
                        <span id="promo-timer" style="display:none;"
                            class="items-center gap-1 bg-red-50 text-red-500 text-[10px] md:text-xs font-semibold px-2 py-0.5 rounded-full border border-red-100">
                            <i class="fa-solid fa-clock"></i> <span id="promo-timer-text">--:--:--</span>
                        </span>
                    </div>

                    <p id="stock-info" class="text-xs md:text-sm mb-3 md:mb-4"></p>

                    <?php if (!empty($product['description'])): ?>
                        <p class="text-xs md:text-sm text-gray-500 leading-relaxed mb-4 md:mb-6">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($colors)): ?>
                        <!-- Colors -->
                        <div class="mb-4 md:mb-5">
                            <p
                                class="text-[10px] md:text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 md:mb-2">
                                Color <span id="selected-color-label" class="text-amber-600 normal-case font-normal"></span>
                            </p>
                            <div class="flex flex-wrap gap-1.5 md:gap-2">
                                <?php foreach ($colors as $i => $color): ?>
                                    <button type="button" onclick="selectColor(<?= $i ?>)" id="color-btn-<?= $i ?>"
                                        class="color-btn px-2.5 py-1 md:px-3 md:py-1.5 text-[11px] md:text-xs font-medium rounded-lg border transition border-gray-200 bg-white text-gray-600 hover:border-amber-300">
                                        <?= htmlspecialchars($color['colorname']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Sizes -->
                        <div class="mb-4 md:mb-5" id="size-section">
                            <p
                                class="text-[10px] md:text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 md:mb-2">
                                Size <span id="selected-size-label" class="text-amber-600 normal-case font-normal"></span>
                            </p>
                            <div class="flex flex-wrap gap-1.5 md:gap-2" id="size-buttons-wrapper">
                                <?php foreach ($allSizes as $sizeName): ?>
                                    <button type="button" onclick="selectSize('<?= htmlspecialchars($sizeName) ?>')"
                                        id="size-btn-<?= htmlspecialchars($sizeName) ?>"
                                        data-size="<?= htmlspecialchars($sizeName) ?>"
                                        class="size-btn px-2.5 py-1 md:px-3 md:py-1.5 text-[11px] md:text-xs border border-gray-200 rounded-lg text-gray-700 bg-gray-50 hover:border-amber-300 transition">
                                        <?= htmlspecialchars($sizeName) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div class="mb-4 md:mb-5" id="qty-section">
                            <p
                                class="text-[10px] md:text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 md:mb-2">
                                Quantity <span id="qty-limit-note" class="text-gray-400 normal-case font-normal"></span>
                            </p>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="changeQty(-1)" id="qty-minus"
                                    class="qty-btn w-8 h-8 md:w-9 md:h-9 rounded-lg border border-gray-200 bg-gray-50 hover:bg-amber-50 hover:border-amber-300 text-gray-600 flex items-center justify-center transition">
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <span id="qty-display"
                                    class="text-sm md:text-base font-semibold text-gray-900 min-w-[2rem] text-center select-none">1</span>
                                <button type="button" onclick="changeQty(1)" id="qty-plus"
                                    class="qty-btn w-8 h-8 md:w-9 md:h-9 rounded-lg border border-gray-200 bg-gray-50 hover:bg-amber-50 hover:border-amber-300 text-gray-600 flex items-center justify-center transition">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                                <span id="qty-max-label" class="text-[10px] md:text-xs text-gray-400 ml-1"></span>
                            </div>
                            <p id="qty-min-note" class="hidden text-[11px] md:text-xs text-amber-600 font-medium mt-1.5">
                                <i class="fa-solid fa-circle-info mr-1"></i>
                                <span id="qty-min-note-text"></span>
                            </p>
                        </div>

                        <!-- Quantity Discount Hint -->
                        <div id="qty-discount-hint"
                            class="hidden mb-4 md:mb-5 -mt-2 text-[11px] md:text-xs bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 text-amber-700">
                        </div>
                    <?php endif; ?>

                    <!-- Add to Cart + Checkout -->
                    <?php if ($isLoggedIn): ?>
                        <div class="mt-auto flex gap-2">
                            <button type="button" id="add-to-cart-btn" onclick="addToCart()" disabled
                                class="flex-1 py-2.5 md:py-3 rounded-xl text-xs md:text-sm font-semibold transition bg-gray-100 text-gray-400 cursor-not-allowed"
                                data-product-id="<?= $productId ?>">
                                <i class="fa-solid fa-cart-plus mr-2"></i> Select color and size
                            </button>
                            <button type="button" id="checkout-now-btn" onclick="buyNow()" disabled
                                style="background-color:#e5e7eb; border-color:#e5e7eb;"
                                class="flex-1 py-2.5 md:py-3 rounded-xl text-xs md:text-sm font-semibold transition text-gray-400 cursor-not-allowed border-2">
                                <i class="fa-solid fa-bag-shopping mr-2"></i> Checkout
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/google"
                            class="mt-auto w-full py-2.5 md:py-3 rounded-xl text-xs md:text-sm font-semibold text-center transition bg-amber-500 hover:bg-amber-600 text-white block">
                            <i class="fa-solid fa-right-to-bracket mr-2"></i> Login to Add to Cart
                        </a>
                    <?php endif; ?>

                    <div id="final-price-display"
                        class="hidden flex items-center justify-between rounded-xl px-4 py-3 mb-3 md:mb-4 ">
                    </div>
                </div>
            </div>
        </div>

        <?php include ROOT_PATH . '/user/ui-page/page-2/producttabs.php'; ?>
        <?php include ROOT_PATH . '/user/ui-page/page-2/relatedproducts.php'; ?>


    </div>

    <script>
        // ─── Image Zoom Lens ───────────────────────────────────────────
        function initZoom(imgEl) {
            const container = document.getElementById('img-zoom-container');
            const lens = document.getElementById('zoom-lens');
            const result = document.getElementById('zoom-result');
            if (!container || !lens || !result || !imgEl) return;

            const ZOOM = 2.8;

            function getPos(e) {
                const r = imgEl.getBoundingClientRect();
                let x = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
                let y = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
                x = Math.max(lens.offsetWidth / 2, Math.min(x, r.width - lens.offsetWidth / 2));
                y = Math.max(lens.offsetHeight / 2, Math.min(y, r.height - lens.offsetHeight / 2));
                return { x, y, r };
            }

            function move(e) {
                e.preventDefault();
                const { x, y, r } = getPos(e);
                lens.style.left = (x - lens.offsetWidth / 2) + 'px';
                lens.style.top = (y - lens.offsetHeight / 2) + 'px';
                const cRect = container.getBoundingClientRect();
                result.style.top = cRect.top + window.scrollY + 'px';
                result.style.left = (cRect.right + window.scrollX + 12) + 'px';
                const bx = -(x * ZOOM - result.offsetWidth / 2);
                const by = -(y * ZOOM - result.offsetHeight / 2);
                result.style.backgroundImage = `url(${imgEl.src})`;
                result.style.backgroundSize = `${r.width * ZOOM}px ${r.height * ZOOM}px`;
                result.style.backgroundPosition = `${bx}px ${by}px`;
            }

            function show() { lens.classList.remove('hidden'); result.classList.remove('hidden'); }
            function hide() { lens.classList.add('hidden'); result.classList.add('hidden'); }

            container.addEventListener('mouseenter', show);
            container.addEventListener('mouseleave', hide);
            container.addEventListener('mousemove', move);
            container.addEventListener('touchmove', move, { passive: false });
            container.addEventListener('touchend', hide);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const img = document.getElementById('main-image');
            if (img) {
                if (img.complete) initZoom(img);
                else img.addEventListener('load', () => initZoom(img));
            }

            showDefaultPreview(); // price/stock preview lang — walang selected na color/size
        });

        // ─── Display-only preview (WALANG select) ──────────────────────────────
        function showDefaultPreview() {
            if (!colors.length) return;

            let colorIndex = colors.findIndex(c => c.variants.some(v => getAvailableStock(v) > 0));
            if (colorIndex === -1) colorIndex = 0;

            const colorVariants = variantMap[colorIndex] || {};
            const sizeNames = Object.keys(colorVariants);
            if (!sizeNames.length) return;

            let sizeName = sizeNames.find(sn => getAvailableStock(colorVariants[sn]) > 0);
            if (!sizeName) sizeName = sizeNames[0];

            const variant = colorVariants[sizeName];
            if (!variant || !(variant.pricesize > 0)) return;

            const colorId = colors[colorIndex].id;
            const stock = getAvailableStock(variant);
            const originalPrice = parseFloat(variant.pricesize);
            const baseDiscount = getEffectiveDiscount(variant.discountvariant, colorId, sizeName);
            const tierDiscount = resolveTierDiscount(1);
            const effectiveDiscount = Math.max(baseDiscount, tierDiscount);
            const discounted = effectiveDiscount > 0 ? originalPrice * (1 - effectiveDiscount / 100) : originalPrice;

            const priceEl = document.getElementById('price-display');
            if (priceEl) {
                let html = `<span class="text-base md:text-xl font-bold text-gray-900">₱${discounted.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
                if (effectiveDiscount > 0) {
                    html += ` <span class="text-xs md:text-sm text-gray-400 line-through ml-1">₱${originalPrice.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
                    html += ` <span class="text-xs md:text-sm text-red-400 font-semibold ml-1">-${effectiveDiscount}%</span>`;
                }
                priceEl.innerHTML = html;
            }

            const stockEl = document.getElementById('stock-info');
            if (stockEl) {
                if (stock <= 0) {
                    stockEl.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
                } else if (stock <= LOW_STOCK_THRESHOLD) {
                    stockEl.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${stock} left in stock</span>`;
                } else {
                    stockEl.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>In stock — ${stock} available</span>`;
                }
            }

            // Tanggal na yung updatePromoTimer(colorId, sizeName) dito —
            // dapat wala pang timer na lalabas hangga't walang actual na
            // pinipiling color/size ang user.
        }

        function patchZoomOnImgChange() {
            const img = document.getElementById('main-image');
            if (!img) return;
            img.addEventListener('load', () => {
                const result = document.getElementById('zoom-result');
                if (result) result.style.backgroundImage = `url(${img.src})`;
            }, { once: true });
        }
        // ───────────────────────────────────────────────────────────────

        const colors = <?= json_encode(array_values($colors), JSON_HEX_TAG) ?>;
        const uploadUrl = <?= json_encode($uploadUrl) ?>;
        const addCartUrl = <?= json_encode(BASE_URL . '/cartadd') ?>;
        const cartVariantQtyUrl = <?= json_encode(BASE_URL . '/cart-variant-qty') ?>;
        const productId = <?= json_encode($productId) ?>;
        const defaultImg = <?= json_encode($product['imageproduct'] ?? '') ?>;
        const defaultPriceHtml = <?= json_encode(
            ($min > 0 || $max > 0)
            ? '<span class="text-base md:text-xl font-bold text-gray-900">₱' . ($min === $max
                ? number_format($min, 2)
                : number_format($min, 2) . ' – ₱' . number_format($max, 2)) . '</span>'
            : '<span class="text-xs md:text-sm text-gray-400 italic">Price not set</span>'
        ) ?>;

        // ─── Per-product qty limit + min + tiered discount data (from PHP) ───
        const productQtyLimit = <?= json_encode($productQtyLimit) ?>;   // 0 = no max limit
        const productQtyMin = <?= json_encode($productQtyMin) ?>;     // 1 = no minimum; must be ≥ this on EVERY add-to-cart
        const qtyTiers = <?= json_encode($qtyTiers) ?>;                  // [{min_qty,max_qty,discount_percent}, ...]
        let productCartQty = <?= json_encode($currentProductCartQty) ?>; // mutable — updated after successful add
        // ───────────────────────────────────────────────────────────────────


        const variantCartQty = <?= json_encode($variantCartQty) ?>; // variant_id => qty already in cart

        function getAvailableStock(variant) {
            if (!variant) return 0;
            const raw = parseInt(variant.stock, 10) || 0;
            const inCart = variantCartQty[variant.id] || 0;
            return Math.max(0, raw - inCart);
        }



        let isRefreshingCartQty = false;

        async function refreshVariantCartQty() {
            if (isRefreshingCartQty) return;
            isRefreshingCartQty = true;
            try {
                const res = await fetch(`${cartVariantQtyUrl}?product_id=${productId}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) return;

                // I-replace ang laman ng variantCartQty base sa pinaka-latest na estado
                Object.keys(variantCartQty).forEach(k => delete variantCartQty[k]);
                Object.entries(data.variant_qty || {}).forEach(([k, v]) => { variantCartQty[k] = v; });

                productCartQty = data.product_qty || 0;

                // I-render ulit ang currently selected variant (kung meron), o yung
                // default preview kung wala pang color/size na napili
                if (selectedColorIndex !== null && selectedSizeName !== null) {
                    resolveVariant();
                } else if (selectedColorIndex === null) {
                    showDefaultPreview();
                }
                updateCartBtn();
            } catch (e) {
                // Tahimik lang palyahin — hindi dapat makasira ng UX kung saglit
                // lang nawalan ng connection; susubukan ulit sa susunod na trigger
            } finally {
                isRefreshingCartQty = false;
            }
        }

        // Trigger 1: custom event na ide-dispatch ng cart dropdown / cartview
        // pagkatapos ng successful add/update/remove sa cart
        window.addEventListener('noblecart:changed', refreshVariantCartQty);

        // Trigger 2: pag bumalik ang focus sa tab/window (hal. galing ibang tab
        // na nag-edit ng cart, o galing ibang app)
        window.addEventListener('focus', refreshVariantCartQty);

        // Trigger 3: pag naging visible ulit ang tab (mas maaasahan sa mobile
        // kaysa sa 'focus' lang)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshVariantCartQty();
        });
        // ───────────────────────────────────────────────────────────────────

        let selectedColorIndex = null;
        let selectedColorId = null;
        let selectedSizeName = null;
        let selectedVariantId = null;
        let selectedVariantStock = 0;    // available = raw stock minus quantity already in user's cart
        let selectedVariantRawStock = 0; // totoong stock sa DB, hindi binabawasan ng laman ng cart
        let selectedQty = 1;

        const LOW_STOCK_THRESHOLD = 5;

        const variantMap = {};
        colors.forEach((color, i) => {
            variantMap[i] = {};
            color.variants.forEach(v => { variantMap[i][v.sizename] = v; });
        });

        // ─── Active promos (date-based discount, per-color/size or general) ──
        const productPromos = <?= json_encode($productPromos) ?>; // [{color_id, sizename, discount_percent, end_date}, ...]

        function findApplicablePromo(colorId, sizeName) {
            let best = null;
            productPromos.forEach(promo => {
                const colorMatches = promo.color_id === null || promo.color_id === colorId;
                const sizeMatches = promo.sizename === null || promo.sizename === sizeName;
                if (colorMatches && sizeMatches && (!best || promo.discount_percent > best.discount_percent)) {
                    best = promo;
                }
            });
            return best;
        }

        function getEffectiveDiscount(variantDiscount, colorId, sizeName) {
            const promo = findApplicablePromo(colorId, sizeName);
            const promoDiscount = promo ? parseFloat(promo.discount_percent) : 0;
            return Math.max(parseFloat(variantDiscount) || 0, promoDiscount);
        }


        // ─── Qty Limit / Min / Tier Discount helpers ──────────────────────────
        function isLimitReached() {
            return productQtyLimit > 0 && productCartQty >= productQtyLimit;
        }

        // Is there still enough stock/allowance left to meet the minimum quantity?
        function canMeetMinimum() {
            if (productQtyMin <= 1) return true;
            if (selectedVariantStock < productQtyMin) return false;
            if (productQtyLimit > 0) {
                const remainingByLimit = Math.max(0, productQtyLimit - productCartQty);
                if (remainingByLimit < productQtyMin) return false;
            }
            return true;
        }

        function getEffectiveMaxQty() {
            const stockMax = selectedVariantStock > 0 ? selectedVariantStock : 1;
            if (productQtyLimit <= 0) return stockMax;
            const remainingByLimit = Math.max(0, productQtyLimit - productCartQty);
            if (remainingByLimit <= 0) return 1; // nothing left that can actually be added; handled by updateCartBtn/resolveVariant
            return Math.min(stockMax, remainingByLimit);
        }

        function resolveTierDiscount(qty) {
            for (const t of qtyTiers) {
                if (qty >= parseInt(t.min_qty, 10) && qty <= parseInt(t.max_qty, 10)) {
                    return parseFloat(t.discount_percent) || 0;
                }
            }
            return 0;
        }

        function refreshDiscountHint() {
            const hint = document.getElementById('qty-discount-hint');
            if (!hint) return;
            hint.classList.add('hidden'); // always hide, don't show it anymore
        }
        // ──────────────────────────────────────────────────────────────────

        // ─── Single source of truth for the unit price of the current variant ──
        let currentVariantOriginalPrice = 0;
        let currentVariantBaseDiscountPercent = 0;

        function renderPriceDisplay() {
            const priceDisplay = document.getElementById('price-display');
            if (!priceDisplay) return;

            if (!currentVariantOriginalPrice) {
                priceDisplay.innerHTML = defaultPriceHtml;
                return;
            }

            const tierDiscountPercent = resolveTierDiscount(selectedQty);
            const effectiveDiscount = Math.max(currentVariantBaseDiscountPercent, tierDiscountPercent);
            const original = currentVariantOriginalPrice;
            const discounted = effectiveDiscount > 0 ? original * (1 - effectiveDiscount / 100) : original;

            let html = `<span class="text-base md:text-xl font-bold text-gray-900">₱${discounted.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
            if (effectiveDiscount > 0) {
                html += ` <span class="text-xs md:text-sm text-gray-400 line-through ml-1">₱${original.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
                html += ` <span class="text-xs md:text-sm text-red-400 font-semibold ml-1">-${effectiveDiscount}%</span>`;
            }
            priceDisplay.innerHTML = html;
        }

        function updateFinalPrice() {
            const el = document.getElementById('final-price-display');
            if (!el) return;
            if (!currentVariantOriginalPrice || selectedVariantStock <= 0) { el.classList.add('hidden'); return; }

            renderPriceDisplay();

            const subtotal = currentVariantOriginalPrice * selectedQty;
            const tierDiscountPercent = resolveTierDiscount(selectedQty);
            const effectiveDiscount = Math.max(currentVariantBaseDiscountPercent, tierDiscountPercent);
            const discountAmount = subtotal * (effectiveDiscount / 100);
            const total = subtotal - discountAmount;

            el.classList.remove('hidden');
            el.innerHTML = `<span class="text-[10px] md:text-xs text-gray-400 uppercase tracking-widest font-semibold">Total</span><span class="text-lg md:text-2xl font-bold text-black">₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
        }

        // ─── Quantity ──────────────────────────────────────────────────
        function showQtySection() {
            selectedQty = productQtyMin > 1 ? Math.min(productQtyMin, getEffectiveMaxQtyRaw()) : 1;
            refreshQtyUI();
            refreshDiscountHint();
        }

        function getEffectiveMaxQtyRaw() {
            const stockMax = selectedVariantStock > 0 ? selectedVariantStock : 1;
            if (productQtyLimit <= 0) return stockMax;
            const remainingByLimit = Math.max(0, productQtyLimit - productCartQty);
            return Math.min(stockMax, Math.max(remainingByLimit, 1));
        }

        function hideQtySection() {
            selectedQty = 1;
            const disp = document.getElementById('qty-display');
            if (disp) disp.textContent = '1';
            const lbl = document.getElementById('qty-max-label');
            if (lbl) lbl.textContent = '';
            const minus = document.getElementById('qty-minus');
            const plus = document.getElementById('qty-plus');
            if (minus) minus.disabled = true;
            if (plus) plus.disabled = true;
            const fp = document.getElementById('final-price-display');
            if (fp) fp.classList.add('hidden');
            const hint = document.getElementById('qty-discount-hint');
            if (hint) hint.classList.add('hidden');
            const minNote = document.getElementById('qty-min-note');
            if (minNote) minNote.classList.add('hidden');
        }

        function changeQty(delta) {
            const max = getEffectiveMaxQty();
            const floor = productQtyMin > 1 ? productQtyMin : 1;
            selectedQty = Math.max(floor, Math.min(selectedQty + delta, max));
            refreshQtyUI();
            refreshDiscountHint();
            updateFinalPrice();
        }

        function refreshQtyUI() {
            const max = getEffectiveMaxQty();
            const floor = productQtyMin > 1 ? productQtyMin : 1;
            const disp = document.getElementById('qty-display');
            const minus = document.getElementById('qty-minus');
            const plus = document.getElementById('qty-plus');
            const lbl = document.getElementById('qty-max-label');
            const limitNote = document.getElementById('qty-limit-note');
            const minNote = document.getElementById('qty-min-note');
            const minNoteText = document.getElementById('qty-min-note-text');

            if (disp) disp.textContent = selectedQty;
            if (minus) minus.disabled = selectedQty <= floor;
            if (plus) plus.disabled = selectedQty >= max;
            if (lbl) lbl.textContent = max > floor ? `max ${max}` : '';

            if (limitNote) {
                if (productQtyLimit > 0) {
                    limitNote.textContent = productCartQty > 0
                        ? `— max ${productQtyLimit} per order (${productCartQty} already in cart)`
                        : `— max ${productQtyLimit} per order`;
                } else {
                    limitNote.textContent = '';
                }
            }

            if (minNote && minNoteText) {
                if (productQtyMin > 1) {
                    minNoteText.textContent = `This product requires ${productQtyMin} pcs or more per add-to-cart.`;
                    minNote.classList.remove('hidden');
                } else {
                    minNote.classList.add('hidden');
                }
            }
        }
        // ──────────────────────────────────────────────────────────────

        function resetImage() {
            const mainImg = document.getElementById('main-image');
            if (mainImg && defaultImg) {
                mainImg.style.opacity = '0.6';
                mainImg.src = uploadUrl + defaultImg;
                mainImg.onload = () => mainImg.style.opacity = '1';
            }
        }

        function clearStockInfo() {
            selectedVariantStock = 0;
            selectedVariantRawStock = 0;
            const el = document.getElementById('stock-info');
            if (el) el.innerHTML = '';
        }

        function updateStockLabel() {
            const el = document.getElementById('stock-info');
            if (!el) return;

            if (!selectedVariantId) { el.innerHTML = ''; return; }

            const inCart = variantCartQty[selectedVariantId] || 0;

            if (selectedVariantRawStock <= 0) {
                // Totoong out of stock — walang natitira kahit sino pa mag-order
                el.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
            } else if (selectedVariantStock <= 0) {
                // May stock pa naman, pero nasa cart mo na lahat ng natitira
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-cart-shopping mr-1"></i>You already have ${inCart} pcs of this in your cart (max stock reached)</span>`;
            } else if (!canMeetMinimum()) {
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${selectedVariantStock} left — not enough for the min. order of ${productQtyMin} pcs</span>`;
            } else if (selectedVariantStock <= LOW_STOCK_THRESHOLD) {
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${selectedVariantStock} left in stock</span>` +
                    (inCart > 0 ? ` <span class="text-gray-400 font-normal">(${inCart} already in your cart)</span>` : '');
            } else {
                el.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>In stock — ${selectedVariantStock} available</span>` +
                    (inCart > 0 ? ` <span class="text-gray-400 font-normal">(${inCart} already in your cart)</span>` : '');
            }
        }

        function selectColor(index) {
            if (selectedColorIndex === index) {
                // ── UNSELECT COLOR ────────────────────────────────────────────
                document.getElementById('color-btn-' + index).classList.remove('selected');
                selectedColorIndex = null;
                selectedColorId = null;
                selectedSizeName = null;
                selectedVariantId = null;
                currentVariantOriginalPrice = 0;
                currentVariantBaseDiscountPercent = 0;
                clearStockInfo();
                hideQtySection();
                document.getElementById('selected-color-label').textContent = '';
                document.getElementById('selected-size-label').textContent = '';
                resetImage();

                document.querySelectorAll('.size-btn').forEach(btn => {
                    btn.style.display = '';
                    btn.classList.remove('selected', 'unavailable');
                    btn.disabled = false;
                });

                showDefaultPreview(); // ibalik yung generic price/stock preview
                updateCartBtn();
                return;
            }

            // ── SELECT COLOR ────────────────────────────────────────────────
            colors.forEach((_, i) => {
                document.getElementById('color-btn-' + i).classList.toggle('selected', i === index);
            });

            selectedColorIndex = index;
            selectedColorId = colors[index].id;
            selectedSizeName = null;
            selectedVariantId = null;
            currentVariantOriginalPrice = 0;           // ← DAGDAG: i-reset ang price state
            currentVariantBaseDiscountPercent = 0;      // ← DAGDAG
            clearStockInfo();
            hideQtySection();
            updatePromoTimer(null);                    // ← DAGDAG: itago ang promo timer, walang size pa
            document.getElementById('selected-color-label').textContent = '— ' + colors[index].colorname;
            document.getElementById('selected-size-label').textContent = '';

            const color = colors[index];
            if (color.imagecolor) {
                const mainImg = document.getElementById('main-image');
                if (mainImg) {
                    mainImg.style.opacity = '0.6';
                    mainImg.src = uploadUrl + color.imagecolor;
                    mainImg.onload = () => mainImg.style.opacity = '1';
                }
            }

            const colorVariants = variantMap[index] || {};
            document.querySelectorAll('.size-btn').forEach(btn => {
                const sizeName = btn.dataset.size;
                const variant = colorVariants[sizeName];
                if (variant) {
                    btn.style.display = '';
                    btn.classList.remove('selected');
                    const rawStock = parseInt(variant.stock, 10) || 0;
                    const outOfStock = rawStock <= 0;
                    btn.classList.toggle('unavailable', outOfStock);
                    btn.disabled = outOfStock;
                } else {
                    btn.style.display = 'none';
                    btn.classList.remove('selected', 'unavailable');
                    btn.disabled = false;
                }
            });
            patchZoomOnImgChange();
            updateCartBtn();
        }

        function selectSize(sizeName) {
            const sizeBtn = document.getElementById('size-btn-' + sizeName);
            if (sizeBtn && sizeBtn.classList.contains('unavailable')) return;

            if (selectedSizeName === sizeName) {
                document.getElementById('size-btn-' + sizeName).classList.remove('selected');
                selectedSizeName = null;
                selectedVariantId = null;
                currentVariantOriginalPrice = 0;
                currentVariantBaseDiscountPercent = 0;
                clearStockInfo();
                hideQtySection();
                document.getElementById('selected-size-label').textContent = '';

                if (selectedColorIndex !== null) {
                    const prices = Object.values(variantMap[selectedColorIndex] || {})
                        .map(v => parseFloat(v.pricesize)).filter(p => p > 0);
                    if (prices.length) {
                        const cMin = Math.min(...prices);
                        const cMax = Math.max(...prices);
                        document.getElementById('price-display').innerHTML = cMin === cMax
                            ? `<span class="text-base md:text-xl font-bold text-gray-900">₱${cMin.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`
                            : `<span class="text-base md:text-xl font-bold text-gray-900">₱${cMin.toLocaleString('en-PH', { minimumFractionDigits: 2 })} – ₱${cMax.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
                    }
                    updatePromoTimer(null); // ← FIX: clear/hide the leftover promo countdown, no size selected anymore
                } else {
                    showDefaultPreview();
                }
                patchZoomOnImgChange();
                updateCartBtn();
                return;
            }

            document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
            document.getElementById('size-btn-' + sizeName).classList.add('selected');

            selectedSizeName = sizeName;
            document.getElementById('selected-size-label').textContent = '— ' + sizeName;

            if (selectedColorIndex !== null) resolveVariant();

            updateCartBtn();
        }

        function resolveVariant() {
            const variant = variantMap[selectedColorIndex]?.[selectedSizeName] ?? null;
            if (variant) {
                selectedVariantId = variant.id;
                selectedVariantRawStock = parseInt(variant.stock, 10) || 0;
                selectedVariantStock = getAvailableStock(variant);

                if (variant.pricesize > 0) {
                    currentVariantOriginalPrice = parseFloat(variant.pricesize);
                    currentVariantBaseDiscountPercent = getEffectiveDiscount(variant.discountvariant, selectedColorId, selectedSizeName);

                    renderPriceDisplay();
                    updateFinalPrice();
                    updatePromoTimer(selectedColorId, selectedSizeName); // ← nandito lang dapat tumatawag
                }

                updateStockLabel();
                if (selectedVariantStock > 0 && !isLimitReached() && canMeetMinimum()) {
                    showQtySection();
                } else {
                    hideQtySection();
                }
            } else {
                selectedVariantId = null;
                currentVariantOriginalPrice = 0;
                currentVariantBaseDiscountPercent = 0;
                clearStockInfo();
                hideQtySection();
                updatePromoTimer(null);
            }
        }

        function updateCartBtn() {
            const btn = document.getElementById('add-to-cart-btn');
            const checkoutBtn = document.getElementById('checkout-now-btn');
            if (!btn) return;

            const base = 'flex-1 py-2.5 md:py-3 rounded-xl text-xs md:text-sm font-semibold transition';

            if (selectedColorId && selectedSizeName && selectedVariantId) {
                if (selectedVariantStock <= 0) {
                    btn.disabled = true;
                    if (selectedVariantRawStock <= 0) {
                        btn.className = `${base} bg-red-50 text-red-400 cursor-not-allowed`;
                        btn.innerHTML = '<i class="fa-solid fa-ban mr-2"></i> Out of stock';
                    } else {
                        const inCart = variantCartQty[selectedVariantId] || 0;
                        btn.className = `${base} bg-amber-50 text-amber-600 cursor-not-allowed`;
                        btn.innerHTML = `<i class="fa-solid fa-cart-shopping mr-2"></i> Already in your cart (${inCart} pcs — max stock)`;
                    }
                } else if (isLimitReached()) {
                    btn.disabled = true;
                    btn.className = `${base} bg-amber-50 text-amber-600 cursor-not-allowed`;
                    btn.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-2"></i> Max ${productQtyLimit} per order reached`;
                } else if (!canMeetMinimum()) {
                    btn.disabled = true;
                    btn.className = `${base} bg-amber-50 text-amber-600 cursor-not-allowed`;
                    btn.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-2"></i> Min. order is ${productQtyMin} pcs`;
                } else {
                    btn.disabled = false;
                    btn.className = `${base} bg-amber-500 hover:bg-amber-600 text-white cursor-pointer`;
                    btn.innerHTML = '<i class="fa-solid fa-cart-plus mr-2"></i> Add to Cart';
                }
            } else if (selectedColorId && selectedSizeName && !selectedVariantId) {
                btn.disabled = true;
                btn.className = `${base} bg-red-50 text-red-400 cursor-not-allowed`;
                btn.innerHTML = '<i class="fa-solid fa-circle-xmark mr-2"></i> Combination not available';
            } else if (!selectedColorId) {
                btn.disabled = true;
                btn.className = `${base} bg-gray-100 text-gray-400 cursor-not-allowed`;
                btn.innerHTML = '<i class="fa-solid fa-cart-plus mr-2"></i> Select color and size';
            } else {
                btn.disabled = true;
                btn.className = `${base} bg-gray-100 text-gray-400 cursor-not-allowed`;
                btn.innerHTML = '<i class="fa-solid fa-cart-plus mr-2"></i> Select a size';
            }

            if (checkoutBtn) {
                if (!btn.disabled) {
                    checkoutBtn.disabled = false;
                    checkoutBtn.className = `${base} text-white cursor-pointer shadow-sm`;
                    checkoutBtn.style.backgroundColor = '#111827'; // dark navy/black
                    checkoutBtn.onmouseenter = () => checkoutBtn.style.backgroundColor = '#000000';
                    checkoutBtn.onmouseleave = () => checkoutBtn.style.backgroundColor = '#111827';
                    checkoutBtn.innerHTML = '<i class="fa-solid fa-bag-shopping mr-2"></i> Checkout';
                } else {
                    checkoutBtn.disabled = true;
                    checkoutBtn.className = `${base} text-gray-400 cursor-not-allowed border-2`;
                    checkoutBtn.style.backgroundColor = '#e5e7eb'; // light gray
                    checkoutBtn.style.borderColor = '#e5e7eb';
                    checkoutBtn.onmouseenter = null;
                    checkoutBtn.onmouseleave = null;
                    checkoutBtn.innerHTML = '<i class="fa-solid fa-bag-shopping mr-2"></i> Checkout';
                }
            }
        }

        async function addToCart() {
            const btn = document.getElementById('add-to-cart-btn');

            if (selectedVariantStock <= 0) {
                if (selectedVariantRawStock <= 0) {
                    showToast('error', 'This item is out of stock.');
                } else {
                    showToast('warning', `You already have all available stock (${variantCartQty[selectedVariantId] || 0} pcs) of this in your cart.`);
                }
                updateCartBtn();
                return;
            }

            if (isLimitReached()) {
                showToast('warning', `Only ${productQtyLimit} pcs can be purchased per order for this product.`);
                updateCartBtn();
                return;
            }

            const qty = selectedQty || 1;

            if (productQtyMin > 1 && qty < productQtyMin) {
                showToast('warning', `You need ${productQtyMin} pcs or more per add-to-cart for this product.`);
                updateCartBtn();
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Adding…';

            try {
                const fd = new FormData();
                fd.append('product_id', btn.dataset.productId);
                fd.append('color_id', selectedColorId);
                fd.append('variant_id', selectedVariantId);
                fd.append('qty', qty);

                const res = await fetch(addCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    showToast(data.limit_reached ? 'warning' : 'success', data.msg || 'Added to cart!');
                    window.dispatchEvent(new CustomEvent('noblecart:changed'));

                    if (data.product_qty_in_cart !== undefined) {
                        productCartQty = data.product_qty_in_cart;
                    }

                    if (data.remaining_stock !== undefined) {

                        const variant = variantMap[selectedColorIndex]?.[selectedSizeName];
                        if (variant) {
                            const rawStock = parseInt(variant.stock, 10) || 0;
                            variantCartQty[selectedVariantId] = Math.max(0, rawStock - data.remaining_stock);
                        }

                        selectedVariantStock = data.remaining_stock;
                        updateStockLabel();

                        if (data.remaining_stock <= 0) {

                            hideQtySection();
                        } else if (isLimitReached() || !canMeetMinimum()) {
                            hideQtySection();
                        } else {
                            selectedQty = productQtyMin > 1 ? Math.min(productQtyMin, getEffectiveMaxQtyRaw()) : 1;
                            refreshQtyUI();
                            refreshDiscountHint();
                        }
                    }
                } else {
                    showToast('error', data.msg || 'Failed to add to cart.');
                    if (data.out_of_stock) {
                        selectedVariantStock = 0;
                        updateStockLabel();
                        hideQtySection();
                        const sizeBtn = document.getElementById('size-btn-' + selectedSizeName);
                        if (sizeBtn) { sizeBtn.classList.add('unavailable'); sizeBtn.disabled = true; }
                    } else if (data.limit_reached) {
                        if (data.product_qty_in_cart !== undefined) productCartQty = data.product_qty_in_cart;
                        hideQtySection();
                    }
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
            }

            updateCartBtn();
        }

        async function buyNow() {
            const btn = document.getElementById('checkout-now-btn');
            if (!btn || btn.disabled) return;

            if (selectedVariantStock <= 0) {
                showToast(selectedVariantRawStock <= 0 ? 'error' : 'warning',
                    selectedVariantRawStock <= 0 ? 'This item is out of stock.' : 'You already have all available stock of this in your cart.');
                return;
            }
            if (isLimitReached()) {
                showToast('warning', `Only ${productQtyLimit} pcs can be purchased per order for this product.`);
                return;
            }

            const qty = selectedQty || 1;
            if (productQtyMin > 1 && qty < productQtyMin) {
                showToast('warning', `You need ${productQtyMin} pcs or more per add-to-cart for this product.`);
                return;
            }

            const addBtn = document.getElementById('add-to-cart-btn');
            btn.disabled = true;
            addBtn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing…';

            try {
                const fd = new FormData();
                fd.append('product_id', productId);
                fd.append('color_id', selectedColorId);
                fd.append('variant_id', selectedVariantId);
                fd.append('qty', qty);

                const res = await fetch(addCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    window.dispatchEvent(new CustomEvent('noblecart:changed'));
                    window.location.href = <?= json_encode(BASE_URL . '/checkout') ?>;
                } else {
                    showToast('error', data.msg || 'Failed to proceed to checkout.');
                    updateCartBtn();
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
                updateCartBtn();
            }
        }

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

        function goBackSafe() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = <?= json_encode(BASE_URL . '/') ?>;
            }
        }

        document.getElementById('save-btn')?.addEventListener('click', function () {
            const icon = this.querySelector('i');
            fetch('<?= BASE_URL ?>/savedproduct', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId)
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) { showToast('error', data.message || 'Something went wrong.'); return; }
                    if (data.saved) {
                        icon.classList.remove('fa-regular', 'text-gray-500');
                        icon.classList.add('fa-solid', 'text-red-500');
                        showToast('success', 'Saved to favorites!');
                    } else {
                        icon.classList.remove('fa-solid', 'text-red-500');
                        icon.classList.add('fa-regular', 'text-gray-500');
                        showToast('success', 'Removed from favorites.');
                    }
                })
                .catch(() => showToast('error', 'Something went wrong.'));
        });

        document.getElementById('share-btn')?.addEventListener('click', function () {
            const productName = this.dataset.productName;
            const shareUrl = window.location.href;

            if (navigator.share) {
                navigator.share({ title: productName, url: shareUrl }).catch(() => { });
            } else {
                navigator.clipboard.writeText(shareUrl)
                    .then(() => showToast('success', 'Link copied to clipboard!'))
                    .catch(() => showToast('error', 'Could not copy link.'));
            }
        });

        let promoTimerInterval = null;

        function updatePromoTimer(colorId, sizeName) {
            const el = document.getElementById('promo-timer');
            const textEl = document.getElementById('promo-timer-text');
            if (!el || !textEl) return;

            if (promoTimerInterval) { clearInterval(promoTimerInterval); promoTimerInterval = null; }

            const promo = (colorId && sizeName) ? findApplicablePromo(colorId, sizeName) : null;
            if (!promo) {
                el.style.display = 'none';
                return;
            }

            const end = new Date(promo.end_date.replace(' ', 'T')).getTime();
            el.style.display = 'inline-flex';
            el.classList.remove('opacity-50');

            function tick() {
                const diff = end - Date.now();
                if (diff <= 0) {
                    el.classList.add('opacity-50');
                    textEl.textContent = 'Promo ended';
                    clearInterval(promoTimerInterval);
                    return;
                }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                textEl.textContent = d > 0
                    ? `${d}d ${h}h left`
                    : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }
            tick();
            promoTimerInterval = setInterval(tick, 1000);
        }
    </script>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

</body>

</html>