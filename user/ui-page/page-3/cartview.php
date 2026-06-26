<?php
// cartview.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-2/productqtydiscount-helper.php';

$uploadUrl = BASE_URL . '/uploads/';

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/google');
    exit;
}

$userId = intval($_SESSION['user_id']);

// Load cart items with product, color, and variant details
$stmt = $conn->prepare("
    SELECT
        nc.id         AS cart_id,
        nc.quantity,
        nc.created_at,

        p.id          AS product_id,
        p.name        AS product_name,
        p.category    AS product_category,
        p.imageproduct,

        c.id          AS color_id,
        c.colorname,
        c.imagecolor,

        v.id          AS variant_id,
        v.sizename,
        v.pricesize,
        v.discountvariant,
        v.stock       AS variant_stock

    FROM noblecart nc
    JOIN nobleproduct       p ON p.id = nc.product_id
    JOIN nobleproductcolor  c ON c.id = nc.color_id
    JOIN nobleproductvariant v ON v.id = nc.variant_id

    WHERE nc.user_id = ?
    ORDER BY nc.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Group total qty per product (for tier discount resolution) ───────────────
$productQtyMap = []; // product_id => total qty in cart
foreach ($cartItems as $item) {
    $pid = $item['product_id'];
    $productQtyMap[$pid] = ($productQtyMap[$pid] ?? 0) + intval($item['quantity']);
}

// ─── Load tiers per unique product ───────────────────────────────────────────
$tiersPerProduct = []; // product_id => tiers array
foreach (array_unique(array_column($cartItems, 'product_id')) as $pid) {
    $tiersPerProduct[$pid] = getProductQtyTiers($conn, (int) $pid);
}

// ─── Compute subtotal with tier discounts applied ─────────────────────────────
$subtotal = 0;
foreach ($cartItems as $item) {
    $price      = floatval($item['pricesize']);
    $discount   = floatval($item['discountvariant']);
    $unitPrice  = $discount > 0 ? $price * (1 - $discount / 100) : $price;
    $qty        = intval($item['quantity']);
    $pid        = $item['product_id'];

    $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
    $tiers              = $tiersPerProduct[$pid] ?? [];
    $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);

    $lineSubtotal = $unitPrice * $qty;
    $subtotal    += $lineSubtotal * (1 - $tierDiscount / 100);
}

$LOW_STOCK_THRESHOLD = 5;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
    <style>
        #toast {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .qty-input {
            -moz-appearance: textfield;
        }

        .cart-row.out-of-stock {
            opacity: 0.6;
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Toast -->
    <div id="toast" class="fixed top-6 right-6 z-50 opacity-0 pointer-events-none translate-y-2
                flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium
                bg-white border border-gray-100 text-gray-800 min-w-56">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-10">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <a href="<?= BASE_URL ?>/"
                class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-amber-500 transition">
                <i class="fa-solid fa-arrow-left text-xs"></i> Back
            </a>
            <span class="text-gray-200">|</span>
            <h1 class="text-xl font-bold text-gray-900">My Cart</h1>
            <?php if (!empty($cartItems)): ?>
                <span class="ml-auto text-xs text-gray-400"><?= count($cartItems) ?>
                    item<?= count($cartItems) !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($cartItems)): ?>

            <!-- Empty State -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
                <i class="fa-solid fa-cart-shopping text-5xl text-gray-200 mb-4 block"></i>
                <p class="text-gray-400 text-sm mb-5">Your cart is empty.</p>
                <a href="<?= BASE_URL ?>/"
                    class="inline-block px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition">
                    Browse Products
                </a>
            </div>

        <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Cart Items -->
                <div class="lg:col-span-2 flex flex-col gap-4 max-h-[70vh] overflow-y-auto pr-1" id="cart-list">
                    <?php foreach ($cartItems as $item):
                        $price      = floatval($item['pricesize']);
                        $discount   = floatval($item['discountvariant']);
                        $unitPrice  = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                        $qty        = intval($item['quantity']);
                        $pid        = $item['product_id'];

                        $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
                        $tiers              = $tiersPerProduct[$pid] ?? [];
                        $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);
                        $discountedUnit     = $unitPrice * (1 - $tierDiscount / 100);

                        $image        = !empty($item['imagecolor']) ? $item['imagecolor'] : $item['imageproduct'];
                        $stock        = intval($item['variant_stock']);
                        $isOutOfStock = $stock <= 0;
                        $exceedsStock = !$isOutOfStock && $qty > $stock;
                        $maxQty       = max($stock, 1);
                    ?>
                        <div class="cart-row bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex gap-4 items-start <?= $isOutOfStock ? 'out-of-stock' : '' ?>"
                            id="cart-row-<?= $item['cart_id'] ?>"
                            data-stock="<?= $stock ?>"
                            data-product-id="<?= $pid ?>">

                            <!-- Product Image -->
                            <a href="<?= BASE_URL ?>/mainproductview?id=<?= $pid ?>"
                                class="flex-shrink-0 w-20 h-20 rounded-xl bg-gray-50 overflow-hidden flex items-center justify-center border border-gray-100">
                                <?php if ($image): ?>
                                    <img src="<?= $uploadUrl . htmlspecialchars($image) ?>"
                                        alt="<?= htmlspecialchars($item['product_name']) ?>"
                                        class="w-full h-full object-contain p-1">
                                <?php else: ?>
                                    <i class="fa-solid fa-image text-2xl text-gray-200"></i>
                                <?php endif; ?>
                            </a>

                            <!-- Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <?php if (!empty($item['product_category'])): ?>
                                            <span class="text-[10px] font-semibold text-amber-500 uppercase tracking-widest">
                                                <?= htmlspecialchars($item['product_category']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/mainproductview?id=<?= $pid ?>"
                                            class="block text-sm font-semibold text-gray-900 hover:text-amber-600 transition leading-tight mt-0.5 truncate max-w-xs">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </a>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            <?= htmlspecialchars($item['colorname']) ?>
                                            <?php if (!empty($item['sizename'])): ?>
                                                · <?= htmlspecialchars($item['sizename']) ?>
                                            <?php endif; ?>
                                        </p>

                                        <!-- Stock indicator -->
                                        <p class="text-[11px] mt-1" id="stock-label-<?= $item['cart_id'] ?>">
                                            <?php if ($isOutOfStock): ?>
                                                <span class="text-red-500 font-medium">
                                                    <i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock
                                                </span>
                                            <?php elseif ($exceedsStock): ?>
                                                <span class="text-red-500 font-medium">
                                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>Only <?= $stock ?> available — please adjust quantity
                                                </span>
                                            <?php elseif ($stock <= $LOW_STOCK_THRESHOLD): ?>
                                                <span class="text-amber-600 font-medium">
                                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>Only <?= $stock ?> left in stock
                                                </span>
                                            <?php else: ?>
                                                <span class="text-green-600">
                                                    <i class="fa-solid fa-circle-check mr-1"></i>In stock
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <!-- Remove -->
                                    <button type="button" onclick="removeItem(<?= $item['cart_id'] ?>)"
                                        class="text-gray-300 hover:text-red-400 transition flex-shrink-0 mt-0.5" title="Remove">
                                        <i class="fa-solid fa-xmark text-sm"></i>
                                    </button>
                                </div>

                                <!-- Price + Qty -->
                                <div class="flex items-center justify-between mt-3">
                                    <div class="flex flex-col">
                                        <!-- Unit price after variant discount + tier discount -->
                                        <span class="text-sm font-bold text-gray-900" id="item-price-<?= $item['cart_id'] ?>">
                                            ₱<?= number_format($discountedUnit, 2) ?>
                                        </span>
                                        <span class="text-[10px] text-gray-400" id="item-price-meta-<?= $item['cart_id'] ?>">
                                            <?php if ($discount > 0 || $tierDiscount > 0): ?>
                                                <span class="line-through mr-1">₱<?= number_format($price, 2) ?></span>
                                            <?php endif; ?>
                                            <?php if ($discount > 0): ?>
                                                <span class="text-red-400 font-semibold mr-1">-<?= $discount ?>%</span>
                                            <?php endif; ?>
                                            <?php if ($tierDiscount > 0): ?>
                                                <span class="text-green-600 font-semibold">-<?= $tierDiscount ?>% qty</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <!-- Quantity Controls -->
                                    <div class="flex items-center gap-1 border border-gray-200 rounded-lg overflow-hidden">
                                        <button type="button" onclick="changeQty(<?= $item['cart_id'] ?>, -1)"
                                            <?= $isOutOfStock ? 'disabled' : '' ?>
                                            class="w-7 h-7 flex items-center justify-center text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition text-xs disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                        <input type="number" id="qty-<?= $item['cart_id'] ?>"
                                            value="<?= $qty ?>" min="1" max="<?= $maxQty ?>"
                                            <?= $isOutOfStock ? 'disabled' : '' ?>
                                            onchange="updateQty(<?= $item['cart_id'] ?>, this.value)"
                                            class="qty-input w-8 h-7 text-center text-xs font-semibold text-gray-800 border-x border-gray-200 focus:outline-none focus:bg-amber-50 disabled:bg-gray-50 disabled:text-gray-400">
                                        <button type="button" onclick="changeQty(<?= $item['cart_id'] ?>, 1)"
                                            <?= ($isOutOfStock || $qty >= $stock) ? 'disabled' : '' ?>
                                            id="qty-plus-<?= $item['cart_id'] ?>"
                                            class="w-7 h-7 flex items-center justify-center text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition text-xs disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-6">
                        <h2 class="text-sm font-bold text-gray-900 mb-5">Order Summary</h2>

                        <?php
                        $hasStockIssue = false;
                        foreach ($cartItems as $item) {
                            $s = intval($item['variant_stock']);
                            if ($s <= 0 || intval($item['quantity']) > $s) {
                                $hasStockIssue = true;
                                break;
                            }
                        }
                        ?>
                        <div id="stock-warning-banner"
                            class="<?= $hasStockIssue ? '' : 'hidden' ?> flex items-start gap-2 text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2.5 mb-4">
                            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                            <span>Some items in your cart are out of stock or exceed available stock. Please adjust quantities before checkout.</span>
                        </div>

                        <div class="space-y-3 text-sm text-gray-600 mb-5" id="summary-lines">
                            <?php foreach ($cartItems as $item):
                                $price      = floatval($item['pricesize']);
                                $discount   = floatval($item['discountvariant']);
                                $unitPrice  = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                                $qty        = intval($item['quantity']);
                                $pid        = $item['product_id'];

                                $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
                                $tiers              = $tiersPerProduct[$pid] ?? [];
                                $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);
                                $discountedUnit     = $unitPrice * (1 - $tierDiscount / 100);
                                $lineTotal          = $discountedUnit * $qty;
                            ?>
                                <div class="flex justify-between items-start gap-2" id="summary-row-<?= $item['cart_id'] ?>">
                                    <span class="text-xs text-gray-500 leading-snug line-clamp-2">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                        <span class="text-gray-400" id="summary-qty-<?= $item['cart_id'] ?>">× <?= $qty ?></span>
                                        <span class="block text-[10px] text-gray-400 mt-0.5">
                                            <?= htmlspecialchars($item['colorname']) ?>
                                            <?php if (!empty($item['sizename'])): ?>
                                                · <?= htmlspecialchars($item['sizename']) ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($tierDiscount > 0): ?>
                                            <span class="block text-[10px] text-green-600 font-semibold mt-0.5">
                                                <i class="fa-solid fa-tag mr-0.5"></i>-<?= $tierDiscount ?>% qty discount applied
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-xs font-medium text-gray-700 whitespace-nowrap"
                                        id="summary-price-<?= $item['cart_id'] ?>">
                                        ₱<?= number_format($lineTotal, 2) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="border-t border-gray-100 pt-4 flex justify-between items-center mb-6">
                            <span class="text-sm font-semibold text-gray-700">Subtotal</span>
                            <span class="text-base font-bold text-gray-900" id="subtotal-display">
                                ₱<?= number_format($subtotal, 2) ?>
                            </span>
                        </div>

                        <a href="<?= BASE_URL ?>/checkout" id="checkout-btn"
                            class="block w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold text-center transition">
                            <i class="fa-solid fa-lock mr-2"></i> Proceed to Checkout
                        </a>

                        <a href="<?= BASE_URL ?>/shop"
                            class="block w-full py-2.5 mt-2 rounded-xl text-sm font-medium text-gray-500 hover:text-amber-600 text-center transition">
                            Continue Shopping
                        </a>
                    </div>
                </div>

            </div>

        <?php endif; ?>
    </div>
    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
    <script>
        const updateCartUrl = <?= json_encode(BASE_URL . '/cartupdate') ?>;
        const removeCartUrl = <?= json_encode(BASE_URL . '/cartremove') ?>;
        const LOW_STOCK_THRESHOLD = <?= (int) $LOW_STOCK_THRESHOLD ?>;

        // ─── Tier discount data from PHP ──────────────────────────────────────
        // tiersPerProduct: { product_id: [{min_qty, max_qty, discount_percent}, ...], ... }
        const tiersPerProduct = <?= json_encode($tiersPerProduct) ?>;
        // productQtyMap: mutable — total qty per product across all cart rows
        const productQtyMap = <?= json_encode($productQtyMap) ?>;

        /**
         * Resolve the tier discount percent for a given product + total qty.
         * Returns 0 if no tier matches.
         */
        function resolveTierDiscount(productId, totalQty) {
            const tiers = tiersPerProduct[productId] ?? [];
            for (const t of tiers) {
                if (totalQty >= parseInt(t.min_qty, 10) && totalQty <= parseInt(t.max_qty, 10)) {
                    return parseFloat(t.discount_percent) || 0;
                }
            }
            return 0;
        }

        /**
         * Recompute the total qty for a product across all visible cart rows,
         * and update productQtyMap accordingly.
         */
        function recomputeProductQty(productId) {
            let total = 0;
            document.querySelectorAll(`.cart-row[data-product-id="${productId}"]`).forEach(row => {
                const inp = row.querySelector('.qty-input');
                total += parseInt(inp?.value || '0', 10);
            });
            productQtyMap[productId] = total;
            return total;
        }

        /**
         * After any qty change, refresh all cart rows for the same product
         * because their tier discount may have changed.
         */
        function refreshProductRows(productId) {
            const totalQty     = productQtyMap[productId] ?? 0;
            const tierDiscount = resolveTierDiscount(productId, totalQty);

            document.querySelectorAll(`.cart-row[data-product-id="${productId}"]`).forEach(row => {
                const cartId    = row.id.replace('cart-row-', '');
                const inp       = document.getElementById('qty-' + cartId);
                const qty       = parseInt(inp?.value || '1', 10);
                const stock     = parseInt(row.dataset.stock, 10);

                // Read base unit price from data attributes stored on the price element
                const priceEl   = document.getElementById('item-price-' + cartId);
                const baseUnit  = parseFloat(priceEl?.dataset.baseUnit ?? '0');
                if (!baseUnit) return;

                const discountedUnit = baseUnit * (1 - tierDiscount / 100);
                const lineTotal      = discountedUnit * qty;

                // Update unit price display
                priceEl.textContent = '₱' + formatPrice(discountedUnit);

                // Update meta (strikethrough + discount badges)
                const metaEl = document.getElementById('item-price-meta-' + cartId);
                if (metaEl) {
                    const origPrice   = parseFloat(priceEl.dataset.origPrice ?? '0');
                    const variantDisc = parseFloat(priceEl.dataset.variantDisc ?? '0');
                    let metaHtml = '';
                    if (variantDisc > 0 || tierDiscount > 0) {
                        metaHtml += `<span class="line-through mr-1">₱${formatPrice(origPrice)}</span>`;
                    }
                    if (variantDisc > 0) {
                        metaHtml += `<span class="text-red-400 font-semibold mr-1">-${variantDisc}%</span>`;
                    }
                    if (tierDiscount > 0) {
                        metaHtml += `<span class="text-green-600 font-semibold">-${tierDiscount}% qty</span>`;
                    }
                    metaEl.innerHTML = metaHtml;
                }

                // Update summary price
                const summaryPrice = document.getElementById('summary-price-' + cartId);
                if (summaryPrice) summaryPrice.textContent = '₱' + formatPrice(lineTotal);

                // Update summary tier discount note
                const summaryRow = document.getElementById('summary-row-' + cartId);
                if (summaryRow) {
                    let tierNote = summaryRow.querySelector('.tier-discount-note');
                    if (tierDiscount > 0) {
                        if (!tierNote) {
                            tierNote = document.createElement('span');
                            tierNote.className = 'tier-discount-note block text-[10px] text-green-600 font-semibold mt-0.5';
                            summaryRow.querySelector('span')?.appendChild(tierNote);
                        }
                        tierNote.innerHTML = `<i class="fa-solid fa-tag mr-0.5"></i>-${tierDiscount}% qty discount applied`;
                    } else if (tierNote) {
                        tierNote.remove();
                    }
                }
            });

            // Recompute subtotal from all visible rows
            recomputeSubtotal();
        }

        /**
         * Recompute the full subtotal from all current cart row states.
         */
        function recomputeSubtotal() {
            let subtotal = 0;
            document.querySelectorAll('.cart-row').forEach(row => {
                const cartId    = row.id.replace('cart-row-', '');
                const inp       = document.getElementById('qty-' + cartId);
                const qty       = parseInt(inp?.value || '0', 10);
                const priceEl   = document.getElementById('item-price-' + cartId);
                const baseUnit  = parseFloat(priceEl?.dataset.baseUnit ?? '0');
                if (!baseUnit || !qty) return;

                const productId    = row.dataset.productId;
                const totalQty     = productQtyMap[productId] ?? qty;
                const tierDiscount = resolveTierDiscount(productId, totalQty);
                const discUnit     = baseUnit * (1 - tierDiscount / 100);
                subtotal          += discUnit * qty;
            });
            const el = document.getElementById('subtotal-display');
            if (el) el.textContent = '₱' + formatPrice(subtotal);
        }

        // ── Change qty via +/– buttons ────────────────────────────────────────
        function changeQty(cartId, delta) {
            const row   = document.getElementById('cart-row-' + cartId);
            const stock = parseInt(row?.dataset.stock ?? '0', 10);
            const input = document.getElementById('qty-' + cartId);

            let newVal = parseInt(input.value) + delta;
            newVal = Math.max(1, newVal);
            if (stock > 0) newVal = Math.min(stock, newVal);

            if (delta > 0 && stock > 0 && parseInt(input.value) >= stock) {
                showToast('error', `Only ${stock} available for this item.`);
                return;
            }

            input.value = newVal;
            updateQty(cartId, newVal);
        }

        // ── Update quantity via AJAX ──────────────────────────────────────────
        async function updateQty(cartId, qty) {
            const row       = document.getElementById('cart-row-' + cartId);
            const stock     = parseInt(row?.dataset.stock ?? '0', 10);
            const productId = row?.dataset.productId;

            qty = parseInt(qty) || 1;
            qty = Math.max(1, qty);
            if (stock > 0) qty = Math.min(stock, qty);

            document.getElementById('qty-' + cartId).value = qty;

            // Optimistically update productQtyMap for immediate tier feedback
            recomputeProductQty(productId);
            refreshProductRows(productId);

            try {
                const fd = new FormData();
                fd.append('cart_id', cartId);
                fd.append('quantity', qty);

                const res  = await fetch(updateCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    // Sync base unit price from server (variant-discount-only, no tier)
                    if (data.final_price !== undefined) {
                        const priceEl = document.getElementById('item-price-' + cartId);
                        if (priceEl) {
                            // Store base unit for client-side tier recomputation
                            priceEl.dataset.baseUnit = data.final_price;
                        }
                    }


                    // Sync summary qty label
                    const summaryQty = document.getElementById('summary-qty-' + cartId);
                    if (summaryQty) summaryQty.textContent = '× ' + qty;

                    // Sync stock from server
                    if (data.stock !== undefined) {
                        row.dataset.stock = data.stock;
                        recomputeProductQty(productId); // re-sync after stock update
                        refreshProductRows(productId);
                        updateStockLabel(cartId, data.stock, qty);
                    } else {
                        updateStockLabel(cartId, stock, qty);
                    }

                    refreshStockWarningBanner();
                } else {
                    showToast('error', data.msg || 'Update failed.');
                    if (data.max_quantity !== undefined) {
                        document.getElementById('qty-' + cartId).value = data.max_quantity;
                        recomputeProductQty(productId);
                        refreshProductRows(productId);
                    }
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
            }
        }

        function updateStockLabel(cartId, stock, qty) {
            const label   = document.getElementById('stock-label-' + cartId);
            const plusBtn = document.getElementById('qty-plus-' + cartId);
            if (!label) return;

            stock = parseInt(stock, 10) || 0;

            if (stock <= 0) {
                label.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
            } else if (qty > stock) {
                label.innerHTML = `<span class="text-red-500 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${stock} available — please adjust quantity</span>`;
            } else if (stock <= LOW_STOCK_THRESHOLD) {
                label.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${stock} left in stock</span>`;
            } else {
                label.innerHTML = '<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>In stock</span>';
            }

            if (plusBtn) plusBtn.disabled = stock <= 0 || qty >= stock;
        }

        function refreshStockWarningBanner() {
            const banner = document.getElementById('stock-warning-banner');
            if (!banner) return;
            let issue = false;
            document.querySelectorAll('.cart-row').forEach(row => {
                const stock    = parseInt(row.dataset.stock || '0', 10);
                const qtyInput = row.querySelector('.qty-input');
                const qty      = qtyInput ? parseInt(qtyInput.value || '0', 10) : 0;
                if (stock <= 0 || qty > stock) issue = true;
            });
            banner.classList.toggle('hidden', !issue);
        }

        // ── Remove item via AJAX ──────────────────────────────────────────────
        async function removeItem(cartId) {
            try {
                const fd = new FormData();
                fd.append('cart_id', cartId);

                const res  = await fetch(removeCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    const row        = document.getElementById('cart-row-' + cartId);
                    const productId  = row?.dataset.productId;
                    const summaryRow = document.getElementById('summary-row-' + cartId);

                    if (row) {
                        row.style.transition = 'opacity 0.25s, transform 0.25s';
                        row.style.opacity    = '0';
                        row.style.transform  = 'translateX(10px)';
                        setTimeout(() => {
                            row.remove();
                            // Re-sync qty map and refresh remaining rows of same product
                            if (productId) {
                                recomputeProductQty(productId);
                                refreshProductRows(productId);
                            }
                            refreshStockWarningBanner();
                        }, 260);
                    }
                    if (summaryRow) setTimeout(() => summaryRow.remove(), 260);

                  

                    showToast('success', 'Item removed from cart.');

                    if (data.cart_count === 0) {
                        setTimeout(() => location.reload(), 800);
                    }

                    const counter = document.getElementById('cart-count');
                    if (counter && data.cart_count !== undefined) {
                        if (data.cart_count > 0) {
                            counter.textContent = data.cart_count;
                        } else {
                            counter.classList.add('hidden');
                        }
                    }
                } else {
                    showToast('error', data.msg || 'Failed to remove item.');
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
            const icon  = document.getElementById('toast-icon');
            const text  = document.getElementById('toast-msg');

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

        // ── Init: stamp base unit prices on price elements for JS recomputation ──
        document.addEventListener('DOMContentLoaded', () => {
            <?php foreach ($cartItems as $item):
                $price      = floatval($item['pricesize']);
                $discount   = floatval($item['discountvariant']);
                $unitPrice  = $discount > 0 ? $price * (1 - $discount / 100) : $price;
            ?>
            (function () {
                const el = document.getElementById('item-price-<?= $item['cart_id'] ?>');
                if (el) {
                    el.dataset.baseUnit    = <?= json_encode($unitPrice) ?>;      // variant-discounted unit (no tier)
                    el.dataset.origPrice   = <?= json_encode($price) ?>;          // original price before any discount
                    el.dataset.variantDisc = <?= json_encode(floatval($item['discountvariant'])) ?>; // variant % off
                }
            })();
            <?php endforeach; ?>

                // 2. PAGKATAPOS lang mag-refresh ng rows at subtotal
    const seenProducts = new Set();
    document.querySelectorAll('.cart-row').forEach(row => {
        const pid = row.dataset.productId;
        if (pid && !seenProducts.has(pid)) {
            seenProducts.add(pid);
            refreshProductRows(pid);
        }
    });
        });
    </script>

</body>

</html>