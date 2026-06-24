<?php
// mainproductview.php

include ROOT_PATH . '/network/connect.php';

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

$isLoggedIn = !empty($_SESSION['user_id']);
$min = floatval($priceRange['min_price'] ?? 0);
$max = floatval($priceRange['max_price'] ?? 0);
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

    <div class="max-w-5xl mx-auto px-4 md:px-6 py-6 md:py-10">

        <a href="javascript:void(0)" onclick="goBackSafe()"
    class="inline-flex items-center gap-1.5 text-xs md:text-sm text-gray-400 hover:text-amber-500 transition mb-4 md:mb-6">
    <i class="fa-solid fa-arrow-left text-xs"></i> Back
</a>

        <div class="bg-white overflow-hidden rounded-xl md:rounded-2xl border border-gray-100 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2">

                <!-- Image -->
                <div class="bg-gray-50 flex items-center justify-center p-6 md:p-10 min-h-56 md:min-h-80">
                    <?php if (!empty($product['imageproduct'])): ?>
                        <div id="img-zoom-container" class="relative overflow-hidden cursor-crosshair select-none"
                            style="width:100%; max-width:400px;">
                            <img id="main-image" src="<?= $uploadUrl . htmlspecialchars($product['imageproduct']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                                class="max-h-52 md:max-h-80 object-contain w-full transition-opacity duration-200"
                                draggable="false">
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
                    <?php if (!empty($product['category'])): ?>
                        <span
                            class="text-[10px] md:text-xs font-semibold text-amber-500 uppercase tracking-widest mb-1.5 md:mb-2">
                            <?= htmlspecialchars($product['category']) ?>
                        </span>
                    <?php endif; ?>

                    <h1 class="text-lg md:text-2xl font-bold text-gray-900 mb-2 md:mb-3">
                        <?= htmlspecialchars($product['name']) ?>
                        <?php if (!empty($product['unit'])): ?>
                            <span class="text-sm md:text-base font-normal text-gray-400 ml-1">·
                                <?= htmlspecialchars($product['unit']) ?></span>
                        <?php endif; ?>
                    </h1>

                    <div class="mb-1.5 md:mb-2" id="price-display">
                        <?php if ($min > 0 || $max > 0): ?>
                            <span class="text-base md:text-xl font-bold text-gray-900">
                                ₱<?= number_format($min, 2) ?>
                                <?= $min !== $max ? ' – ₱' . number_format($max, 2) : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs md:text-sm text-gray-400 italic">Price not set</span>
                        <?php endif; ?>
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
                        <div class="mb-4 md:mb-5" id="size-section" style="display:none;">
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
                        <div class="mb-4 md:mb-5 hidden" id="qty-section">
                            <p
                                class="text-[10px] md:text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 md:mb-2">
                                Quantity
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
                        </div>
                    <?php endif; ?>

                    <!-- Add to Cart -->
                    <?php if ($isLoggedIn): ?>
                        <button type="button" id="add-to-cart-btn" onclick="addToCart()" disabled
                            class="mt-auto w-full py-2.5 md:py-3 rounded-xl text-xs md:text-sm font-semibold transition bg-gray-100 text-gray-400 cursor-not-allowed"
                            data-product-id="<?= $productId ?>">
                            <i class="fa-solid fa-cart-plus mr-2"></i> Select color and size
                        </button>
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

        <?php
        $specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];
        $gallery = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
        ?>
        <?php if (!empty($specs) || !empty($gallery)): ?>
            <div class="bg-white rounded-xl md:rounded-2xl border border-gray-100 shadow-sm mt-4 md:mt-6 overflow-hidden">

                <!-- Tab Nav -->
                <div class="flex border-b border-gray-100">
                    <?php if (!empty($specs)): ?>
                        <button onclick="switchTab('specs')" id="tab-specs"
                            class="tab-btn px-5 md:px-8 py-3 md:py-4 text-xs md:text-sm font-semibold text-amber-500 border-b-2 border-amber-500 transition">
                            <i class="fa-solid fa-list-check mr-1.5"></i> Specifications
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($gallery)): ?>
                        <button onclick="switchTab('gallery')" id="tab-gallery"
                            class="tab-btn px-5 md:px-8 py-3 md:py-4 text-xs md:text-sm font-semibold text-gray-400 border-b-2 border-transparent hover:text-gray-600 transition">
                            <i class="fa-regular fa-images mr-1.5"></i> Collection
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Specs Panel -->
                <?php if (!empty($specs)): ?>
                    <div id="panel-specs" class="px-5 md:px-8 py-2 divide-y divide-gray-50">
                        <?php foreach ($specs as $key => $val): ?>
                            <div class="flex items-center gap-6 py-3">
                                <span class="text-xs md:text-sm text-gray-400 font-medium w-40 shrink-0">
                                    <?= htmlspecialchars($key) ?>
                                </span>
                                <span class="text-xs md:text-sm text-gray-800 font-medium">
                                    <?= htmlspecialchars($val) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Gallery Panel -->
                <?php if (!empty($gallery)): ?>
                    <div id="panel-gallery" class="p-5 md:p-8 hidden">
                        <div id="masonry-gallery" style="display: flex; gap: 10px;">
                            <div class="masonry-col" style="flex: 1; display: flex; flex-direction: column; gap: 10px;"></div>
                            <div class="masonry-col" style="flex: 1; display: flex; flex-direction: column; gap: 10px;"></div>
                            <div class="masonry-col" style="flex: 1; display: flex; flex-direction: column; gap: 10px;"></div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </div>

    <div id="lightbox" onclick="closeLightbox()"
        class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center hidden p-4 md:p-8">
        <button onclick="closeLightbox()"
            class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition text-lg z-10">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightbox-img" src="" alt="" class="max-w-full max-h-[90vh] object-contain rounded-xl shadow-2xl">
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
        });

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
        const defaultImg = <?= json_encode($product['imageproduct'] ?? '') ?>;
        const defaultPriceHtml = <?= json_encode(
            ($min > 0 || $max > 0)
            ? '<span class="text-base md:text-xl font-bold text-gray-900">₱' . ($min === $max
                ? number_format($min, 2)
                : number_format($min, 2) . ' – ₱' . number_format($max, 2)) . '</span>'
            : '<span class="text-xs md:text-sm text-gray-400 italic">Price not set</span>'
        ) ?>;

        let selectedColorIndex = null;
        let selectedColorId = null;
        let selectedSizeName = null;
        let selectedVariantId = null;
        let selectedVariantStock = 0;
        let selectedQty = 1;

        const LOW_STOCK_THRESHOLD = 5;

        const variantMap = {};
        colors.forEach((color, i) => {
            variantMap[i] = {};
            color.variants.forEach(v => { variantMap[i][v.sizename] = v; });
        });

        function updateFinalPrice(unitPrice) {
            const el = document.getElementById('final-price-display');
            if (!el) return;
            if (!unitPrice || selectedVariantStock <= 0) { el.classList.add('hidden'); return; }
            const total = unitPrice * selectedQty;
            el.classList.remove('hidden');
            el.innerHTML = `<span class="text-[10px] md:text-xs text-gray-400 uppercase tracking-widest font-semibold">Total</span>
        <span class="text-lg md:text-2xl font-bold text-black">₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
        }

        // ─── Quantity ──────────────────────────────────────────────────
        function showQtySection() {
            selectedQty = 1;
            const el = document.getElementById('qty-section');
            if (el) el.classList.remove('hidden');
            refreshQtyUI();
        }

        function hideQtySection() {
            selectedQty = 1;
            const el = document.getElementById('qty-section');
            if (el) el.classList.add('hidden');
            const disp = document.getElementById('qty-display');
            if (disp) disp.textContent = '1';
            const lbl = document.getElementById('qty-max-label');
            if (lbl) lbl.textContent = '';
            const fp = document.getElementById('final-price-display');
            if (fp) fp.classList.add('hidden');
        }

        function changeQty(delta) {
            const max = selectedVariantStock > 0 ? selectedVariantStock : 1;
            selectedQty = Math.max(1, Math.min(selectedQty + delta, max));
            refreshQtyUI();
            // i-recompute ang final price gamit ang current unit price
            const v = variantMap[selectedColorIndex]?.[selectedSizeName];
            if (v) {
                const orig = parseFloat(v.pricesize);
                const d = parseFloat(v.discountvariant) || 0;
                updateFinalPrice(d > 0 ? orig * (1 - d / 100) : orig);
            }

        }

        function refreshQtyUI() {
            const max = selectedVariantStock > 0 ? selectedVariantStock : 1;
            const disp = document.getElementById('qty-display');
            const minus = document.getElementById('qty-minus');
            const plus = document.getElementById('qty-plus');
            const lbl = document.getElementById('qty-max-label');

            if (disp) disp.textContent = selectedQty;
            if (minus) minus.disabled = selectedQty <= 1;
            if (plus) plus.disabled = selectedQty >= max;
            if (lbl) lbl.textContent = max > 1 ? `max ${max}` : '';
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
            const el = document.getElementById('stock-info');
            if (el) el.innerHTML = '';
        }

        function updateStockLabel() {
            const el = document.getElementById('stock-info');
            if (!el) return;

            if (!selectedVariantId) { el.innerHTML = ''; return; }

            if (selectedVariantStock <= 0) {
                el.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
            } else if (selectedVariantStock <= LOW_STOCK_THRESHOLD) {
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${selectedVariantStock} left in stock</span>`;
            } else {
                el.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>In stock — ${selectedVariantStock} available</span>`;
            }
        }

        function selectColor(index) {
            if (selectedColorIndex === index) {
                document.getElementById('color-btn-' + index).classList.remove('selected');
                selectedColorIndex = null;
                selectedColorId = null;
                selectedSizeName = null;
                selectedVariantId = null;
                clearStockInfo();
                hideQtySection();
                document.getElementById('selected-color-label').textContent = '';
                document.getElementById('selected-size-label').textContent = '';
                document.getElementById('size-section').style.display = 'none';
                resetImage();
                document.getElementById('price-display').innerHTML = defaultPriceHtml;
                updateCartBtn();
                return;
            }

            colors.forEach((_, i) => {
                document.getElementById('color-btn-' + i).classList.toggle('selected', i === index);
            });

            selectedColorIndex = index;
            selectedColorId = colors[index].id;
            selectedSizeName = null;
            selectedVariantId = null;
            clearStockInfo();
            hideQtySection();
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

            document.getElementById('size-section').style.display = '';

            const colorVariants = variantMap[index] || {};
            document.querySelectorAll('.size-btn').forEach(btn => {
                const sizeName = btn.dataset.size;
                const variant = colorVariants[sizeName];
                if (variant) {
                    btn.style.display = '';
                    btn.classList.remove('selected');
                    const outOfStock = parseInt(variant.stock, 10) <= 0;
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
                } else {
                    document.getElementById('price-display').innerHTML = defaultPriceHtml;
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
                selectedVariantStock = parseInt(variant.stock, 10) || 0;

                if (variant.pricesize > 0) {
                    const original = parseFloat(variant.pricesize);
                    const disc = parseFloat(variant.discountvariant) || 0;
                    const discounted = disc > 0 ? original * (1 - disc / 100) : original;

                    let html = `<span class="text-base md:text-xl font-bold text-gray-900">₱${discounted.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
                    if (disc > 0) {
                        html += ` <span class="text-xs md:text-sm text-gray-400 line-through ml-1">₱${original.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>`;
                        html += ` <span class="text-xs md:text-sm text-red-400 font-semibold ml-1">-${disc}%</span>`;
                    }
                    document.getElementById('price-display').innerHTML = html;

                    // ── Final price (qty × discounted price) ──
                    updateFinalPrice(discounted);
                }

                updateStockLabel();

                if (selectedVariantStock > 0) {
                    showQtySection();
                } else {
                    hideQtySection();
                }
            } else {
                selectedVariantId = null;
                clearStockInfo();
                hideQtySection();
            }
        }

        function updateCartBtn() {
            const btn = document.getElementById('add-to-cart-btn');
            if (!btn) return;

            const base = 'mt-auto w-full py-2.5 md:py-3 rounded-xl text-xs md:text-sm font-semibold transition';

            if (selectedColorId && selectedSizeName && selectedVariantId) {
                if (selectedVariantStock <= 0) {
                    btn.disabled = true;
                    btn.className = `${base} bg-red-50 text-red-400 cursor-not-allowed`;
                    btn.innerHTML = '<i class="fa-solid fa-ban mr-2"></i> Out of stock';
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
        }

        async function addToCart() {
            const btn = document.getElementById('add-to-cart-btn');

            if (selectedVariantStock <= 0) {
                showToast('error', 'This item is out of stock.');
                updateCartBtn();
                return;
            }

            const qty = selectedQty || 1;

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
                    showToast('success', data.msg || 'Added to cart!');
                    const counter = document.getElementById('cart-count');
                    if (counter && data.cart_count !== undefined) {
                        counter.textContent = data.cart_count;
                        counter.classList.remove('hidden');
                    }
                    if (data.remaining_stock !== undefined) {
                        selectedVariantStock = data.remaining_stock;
                        updateStockLabel();

                        const variant = variantMap[selectedColorIndex]?.[selectedSizeName];
                        if (variant) variant.stock = data.remaining_stock;

                        if (data.remaining_stock <= 0) {
                            const sizeBtn = document.getElementById('size-btn-' + selectedSizeName);
                            if (sizeBtn) { sizeBtn.classList.add('unavailable'); sizeBtn.disabled = true; }
                            hideQtySection();
                        } else {
                            // reset qty to 1 after successful add, refresh max
                            selectedQty = 1;
                            refreshQtyUI();
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
                    }
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
            }

            updateCartBtn();
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

        function switchTab(tab) {
            const panels = ['specs', 'gallery'];
            panels.forEach(p => {
                const panel = document.getElementById('panel-' + p);
                const btn = document.getElementById('tab-' + p);
                if (!panel || !btn) return;
                if (p === tab) {
                    panel.classList.remove('hidden');
                    btn.classList.add('text-amber-500', 'border-amber-500');
                    btn.classList.remove('text-gray-400', 'border-transparent');
                } else {
                    panel.classList.add('hidden');
                    btn.classList.remove('text-amber-500', 'border-amber-500');
                    btn.classList.add('text-gray-400', 'border-transparent');
                }
            });

            if (tab === 'gallery' && !window.masonryBuilt) {
                window.masonryBuilt = true;
                const galleryFiles = <?= json_encode(array_values($gallery)) ?>;
                const cols = document.querySelectorAll('.masonry-col');
                const colHeights = [0, 0, 0];
                galleryFiles.forEach(filename => {
                    const minIdx = colHeights.indexOf(Math.min(...colHeights));
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'border-radius:12px; overflow:hidden; cursor:pointer;';
                    wrapper.onclick = () => openLightbox(uploadUrl + filename);
                    const randomHeight = Math.floor(Math.random() * 120) + 120;
                    const img = document.createElement('img');
                    img.src = uploadUrl + filename;
                    img.style.cssText = `width:100%; height:${randomHeight}px; object-fit:cover; display:block;`;
                    wrapper.appendChild(img);
                    cols[minIdx].appendChild(wrapper);
                    colHeights[minIdx] += randomHeight + 10;
                });
            }
        }

        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.add('hidden');
            document.body.style.overflow = '';
        }

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