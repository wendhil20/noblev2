<?php
// user/ui-page/page-8/shop.php
include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';
$searchQuery = trim($_GET['search'] ?? '');
$saleOnly = isset($_GET['sale_only']) && $_GET['sale_only'] === '1';

// Read current filter values (for pre-checking UI only — products are fetched via AJAX)
$selectedCategories = array_filter(array_map('trim', (array) ($_GET['categories'] ?? [])));
$selectedColors = array_filter(array_map('trim', (array) ($_GET['colors'] ?? [])));
$selectedSizes = array_filter(array_map('trim', (array) ($_GET['sizes'] ?? [])));
$minPriceFilter = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$maxPriceFilter = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$selectedCategoriesLower = array_map('mb_strtolower', $selectedCategories);
$selectedColorsLower = array_map('mb_strtolower', $selectedColors);
$selectedSizesLower = array_map('mb_strtolower', $selectedSizes);

// ── Filter options (sidebar only — lightweight queries) ──────────────────────
$availableCategories = [];
$seenCategories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM nobleproduct WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
while ($row = $catRes->fetch_assoc()) {
    $name = trim($row['category']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenCategories[$key]))
        continue;
    $seenCategories[$key] = true;
    $availableCategories[] = $name;
}

$availableColors = [];
$seenColors = [];
$colorRes = $conn->query("SELECT DISTINCT colorname FROM nobleproductcolor WHERE colorname != '' ORDER BY colorname ASC");
while ($row = $colorRes->fetch_assoc()) {
    $name = trim($row['colorname']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenColors[$key]))
        continue;
    $seenColors[$key] = true;
    $availableColors[] = $name;
}

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
                <div class="rounded-xl p-5 sticky top-4">

                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Filters</h2>
                        <button type="button" id="clearFiltersBtn"
                            class="text-xs text-amber-500 hover:text-amber-600 font-medium">Clear</button>
                    </div>

                    <!-- Sale Only -->
                    <div class="mb-5 pb-5 border-b border-gray-100">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-xs font-semibold text-red-500 uppercase tracking-wider">Sale Items
                                Only</span>
                            <div class="relative">
                                <input type="checkbox" id="filter_sale_only" <?= $saleOnly ? 'checked' : '' ?>
                                    class="sr-only peer filter-input">
                                <div
                                    class="w-10 h-5 bg-gray-200 rounded-full peer-checked:bg-amber-500 transition-colors duration-200">
                                </div>
                                <div
                                    class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Category -->
                    <?php if (!empty($availableCategories)): ?>
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Category</p>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                <?php foreach ($availableCategories as $catName): ?>
                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-amber-600">
                                        <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($catName) ?>"
                                            <?= in_array(mb_strtolower($catName), $selectedCategoriesLower) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-amber-500 focus:ring-amber-400 filter-input">
                                        <?= htmlspecialchars($catName) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Color -->
                    <?php if (!empty($availableColors)): ?>
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Color</p>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                <?php foreach ($availableColors as $cName): ?>
                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-amber-600">
                                        <input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cName) ?>"
                                            <?= in_array(mb_strtolower($cName), $selectedColorsLower) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-amber-500 focus:ring-amber-400 filter-input">
                                        <?= htmlspecialchars($cName) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Size -->
                    <?php if (!empty($availableSizes)): ?>
                        <div class="mb-5 pb-5 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Size</p>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                <?php foreach ($availableSizes as $sName): ?>
                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-amber-600">
                                        <input type="checkbox" name="sizes[]" value="<?= htmlspecialchars($sName) ?>"
                                            <?= in_array(mb_strtolower($sName), $selectedSizesLower) ? 'checked' : '' ?>
                                            class="rounded border-gray-300 text-amber-500 focus:ring-amber-400 filter-input">
                                        <?= htmlspecialchars($sName) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Price Range -->
                    <div class="mb-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5">Price Range</p>
                        <div class="flex items-center gap-2">
                            <input type="number" id="filter_min_price" placeholder="₱<?= number_format($priceLo, 0) ?>"
                                value="<?= $minPriceFilter !== null ? htmlspecialchars((string) $minPriceFilter) : '' ?>"
                                min="0" step="0.01"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400 filter-input">
                            <span class="text-gray-300 text-xs">–</span>
                            <input type="number" id="filter_max_price" placeholder="₱<?= number_format($priceHi, 0) ?>"
                                value="<?= $maxPriceFilter !== null ? htmlspecialchars((string) $maxPriceFilter) : '' ?>"
                                min="0" step="0.01"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-amber-400 filter-input">
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1.5">
                            Available range: ₱<?= number_format($priceLo, 2) ?> – ₱<?= number_format($priceHi, 2) ?>
                        </p>
                    </div>

                </div>
            </aside>

            <!-- ═══════════════ PRODUCT AREA ═══════════════ -->
            <div class="flex-1 flex flex-col">

                <!-- Header row -->
                <div class="flex items-center gap-3 mb-5">
                    <h1 class="text-lg md:text-xl font-bold text-gray-900 shrink-0">Shop</h1>

                    <!-- Realtime search input -->
                    <div class="relative w-48 md:w-64">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-0 top-1/2 -translate-y-1/2 text-gray-300 text-xs pointer-events-none"></i>
                        <input type="text" id="searchInput" value="<?= htmlspecialchars($searchQuery) ?>"
                            placeholder="Search products…" class="w-full pl-5 pr-6 py-1.5 text-xs border-0 border-b border-gray-200
                               focus:outline-none focus:border-amber-400 bg-transparent">
                        <span id="searchSpinner" class="hidden absolute right-0 top-1/2 -translate-y-1/2">
                            <i class="fa-solid fa-circle-notch fa-spin text-amber-400 text-xs"></i>
                        </span>
                    </div>


                </div>

                <!-- Product grid (populated by AJAX) -->
                <div id="products-area"
                    class="flex-1 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5 content-start">
                    <!-- skeleton shown on first load -->
                </div>

                <!-- Pagination (injected separately so it sits outside the grid) -->
                <div id="pagination-area"></div>

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
            <button id="modalAddBtn" onclick="confirmAddToCart()" disabled class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300
                   disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition">
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
            transition: opacity .3s ease, transform .3s ease;
        }
    </style>

    <script>
        // ── Config ────────────────────────────────────────────────────────────────────
        const ADD_TO_CART_URL = <?= json_encode(BASE_URL . '/cartadd') ?>;
        const PRODUCTS_URL = <?= json_encode(BASE_URL . '/shop-products') ?>;   // AJAX endpoint
        const LOW_STOCK_THRESHOLD = 5;
        let promoInterval = null;

        // Merged from every AJAX response — persists across page changes
        let PRODUCT_DATA = {};

        // ── State ─────────────────────────────────────────────────────────────────────
        let currentPage = <?= max(1, intval($_GET['page'] ?? 1)) ?>;
        let fetchController = null;   // AbortController for in-flight requests

         function startPromoTimers() {
            if (promoInterval) clearInterval(promoInterval);

            function tick() {
                const now = Date.now();
                document.querySelectorAll('.promo-timer').forEach(el => {
                    const end = new Date(el.dataset.end).getTime();
                    const diff = end - now;

                    if (diff <= 0) {
                        el.textContent = 'Ended';
                        el.classList.add('opacity-50');
                        return;
                    }

                    const d = Math.floor(diff / 86400000);
                    const h = Math.floor((diff % 86400000) / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);

                    el.textContent = d > 0
                        ? `${d}d ${h}h left`
                        : `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
                });
            }
            tick();
            promoInterval = setInterval(tick, 1000);
        }

        // ── Collect filters into a URLSearchParams ────────────────────────────────────
        function collectFilters(page = 1) {
            const params = new URLSearchParams();

            const search = document.getElementById('searchInput').value.trim();
            if (search) params.set('search', search);

            if (document.getElementById('filter_sale_only').checked) params.set('sale_only', '1');

            document.querySelectorAll('input[name="categories[]"]:checked')
                .forEach(el => params.append('categories[]', el.value));
            document.querySelectorAll('input[name="colors[]"]:checked')
                .forEach(el => params.append('colors[]', el.value));
            document.querySelectorAll('input[name="sizes[]"]:checked')
                .forEach(el => params.append('sizes[]', el.value));

            const minP = document.getElementById('filter_min_price').value.trim();
            const maxP = document.getElementById('filter_max_price').value.trim();
            if (minP) params.set('min_price', minP);
            if (maxP) params.set('max_price', maxP);

            params.set('page', page);
            return params;
        }

        // ── Skeleton cards ────────────────────────────────────────────────────────────
        function renderSkeletons(n = 8) {
            let html = '';
            for (let i = 0; i < n; i++) {
                html += `
        <div class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100 animate-pulse">
            <div class="aspect-square bg-gray-200"></div>
            <div class="p-2 md:p-3 space-y-2">
                <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                <div class="h-2 bg-gray-200 rounded w-1/2"></div>
                <div class="h-4 bg-gray-200 rounded w-1/3 mt-2"></div>
                <div class="h-7 bg-gray-200 rounded mt-2"></div>
            </div>
        </div>`;
            }
            return html;
        }

        // ── Main fetch function ───────────────────────────────────────────────────────
        async function fetchProducts(page = 1, showSkeleton = true) {
            // Abort any in-flight request
            if (fetchController) fetchController.abort();
            fetchController = new AbortController();

            const params = collectFilters(page);
            const area = document.getElementById('products-area');
            const spinner = document.getElementById('searchSpinner');

            if (showSkeleton) {
                area.innerHTML = renderSkeletons(8);
                document.getElementById('pagination-area').innerHTML = '';
            }
            spinner.classList.remove('hidden');

            // Keep URL in sync (browser history) without reload
            const newUrl = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', newUrl);

            try {
                const res = await fetch(PRODUCTS_URL + '?' + params.toString(), {
                    signal: fetchController.signal
                });
                const data = await res.json();

                // Merge new product data into global store
                Object.assign(PRODUCT_DATA, data.product_data ?? {});

                area.innerHTML = data.html;
                startPromoTimers(); 

                const paginationArea = document.getElementById('pagination-area');
                paginationArea.innerHTML = data.pagination ?? '';

                // Bind pagination buttons
                paginationArea.querySelectorAll('.page-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const pg = parseInt(btn.dataset.page);
                        if (!isNaN(pg)) {
                            currentPage = pg;
                            fetchProducts(pg, true);
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    });
                });
            } catch (err) {
                if (err.name === 'AbortError') return;   // superseded request — ignore
                area.innerHTML = `<div class="col-span-full text-center py-20 text-gray-400">
            <i class="fa-solid fa-triangle-exclamation text-4xl mb-3 block"></i>
            <p>Failed to load products. Please refresh.</p></div>`;
            } finally {
                spinner.classList.add('hidden');
            }
        }

        // ── Debounce helper ───────────────────────────────────────────────────────────
        function debounce(fn, delay) {
            let timer;
            return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
        }

        // ── Wire up filter listeners ──────────────────────────────────────────────────
        const debouncedFetch = debounce(() => { currentPage = 1; fetchProducts(1); }, 350);

        // Checkboxes + sale toggle: instant (but still debounced to batch rapid clicks)
        document.querySelectorAll('.filter-input').forEach(el => {
            el.addEventListener('change', debouncedFetch);
        });

        // Search: 350ms debounce while typing
        document.getElementById('searchInput').addEventListener('input', debouncedFetch);

        // Price fields: 600ms debounce (user finishes typing the number)
        const debouncedPriceFetch = debounce(() => { currentPage = 1; fetchProducts(1); }, 600);
        document.getElementById('filter_min_price').addEventListener('input', debouncedPriceFetch);
        document.getElementById('filter_max_price').addEventListener('input', debouncedPriceFetch);

        // Clear all filters
        document.getElementById('clearFiltersBtn').addEventListener('click', () => {
            document.querySelectorAll('.filter-input').forEach(el => {
                if (el.type === 'checkbox') el.checked = false;
                else el.value = '';
            });
            document.getElementById('searchInput').value = '';
            currentPage = 1;
            fetchProducts(1);
        });

        // ── Initial load ──────────────────────────────────────────────────────────────
        fetchProducts(currentPage, true);


        // ═════════════════════════════════════════════════════════════════════════════
        // ADD TO CART MODAL (unchanged logic, same as before)
        // ═════════════════════════════════════════════════════════════════════════════
        let currentModalState = { productId: null, colorId: null, variantId: null };

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
                const sel = parseInt(btn.dataset.colorId) === colorId;
                btn.classList.toggle('border-amber-500', sel);
                btn.classList.toggle('bg-amber-50', sel);
                btn.classList.toggle('text-amber-600', sel);
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
                const oos = v.stock <= 0;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = v.sizename + (oos ? ' (Out of stock)' : '');
                btn.disabled = oos;
                btn.className = 'size-option px-3 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600 hover:border-amber-400 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:border-gray-200';
                btn.dataset.variantId = v.id;
                btn.onclick = () => selectSize(v.id);
                sizeWrap.appendChild(btn);
            });
        }

        function selectSize(variantId) {
            currentModalState.variantId = variantId;

            document.querySelectorAll('#modalSizeOptions .size-option').forEach(btn => {
                const sel = parseInt(btn.dataset.variantId) === variantId;
                btn.classList.toggle('border-amber-500', sel);
                btn.classList.toggle('bg-amber-50', sel);
                btn.classList.toggle('text-amber-600', sel);
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

        function modalChangeQty(delta) {
            const input = document.getElementById('modalQtyInput');
            const max = parseInt(input.max) || 99;
            input.value = Math.max(1, Math.min(parseInt(input.value || '1') + delta, max));
        }

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

                const res = await fetch(ADD_TO_CART_URL, { method: 'POST', body: fd });
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
                    if (data.out_of_stock !== undefined) selectSize(variantId);
                }
            } catch {
                showToast('error', 'Something went wrong.');
            }
        }

        function formatPrice(num) {
            return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }

        function showToast(type, msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-icon').innerHTML = type === 'success'
                ? '<i class="fa-solid fa-circle-check text-green-500"></i>'
                : '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
            document.getElementById('toast-msg').textContent = msg;

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