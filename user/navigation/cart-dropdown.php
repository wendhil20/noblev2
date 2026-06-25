<!-- Cart Dropdown Panel (desktop hover, kapareho ng style ng user dropdown) -->
<div id="cart-dropdown"
    class="hidden md:block absolute top-full right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">

    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <p class="text-sm font-semibold text-gray-800">My Cart</p>
        <span id="cart-dropdown-count" class="text-xs text-gray-400"></span>
    </div>

    <div id="cart-dropdown-body" class="max-h-80 overflow-y-auto">
        <div class="px-4 py-8 text-center text-xs text-gray-400">
            <i class="fa-solid fa-spinner fa-spin"></i>
        </div>
    </div>

    <div id="cart-dropdown-footer" class="border-t border-gray-100 px-4 py-3 hidden">
        <div id="cart-dropdown-warning"
            class="hidden flex items-start gap-2 text-[11px] text-red-600 bg-red-50 border border-red-100 rounded-lg px-2.5 py-2 mb-3">
            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
            <span>Some items exceed available stock. Please adjust before checkout.</span>
        </div>
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-600">Subtotal</span>
            <span id="cart-dropdown-subtotal" class="text-sm font-bold text-gray-900"></span>
        </div>
        <a href="<?= BASE_URL ?>/checkout" id="cart-dropdown-checkout"
            class="block w-full text-center bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold py-2.5 rounded-md transition-colors duration-150 mb-2">
            <i class="fa-solid fa-lock mr-1"></i> Proceed to Checkout
        </a>
        <a href="<?= BASE_URL ?>/cartview"
            class="block w-full text-center text-xs font-medium text-gray-500 hover:text-amber-600 py-1 transition-colors duration-150">
            View Full Cart
        </a>
    </div>
</div>

<script>
(function () {
    const wrapper = document.getElementById('cart-icon-wrapper');
    const dropdown = document.getElementById('cart-dropdown');
    const body = document.getElementById('cart-dropdown-body');
    const footer = document.getElementById('cart-dropdown-footer');
    const warningBanner = document.getElementById('cart-dropdown-warning');
    const countBadge = document.getElementById('cart-count');
    const dropdownCount = document.getElementById('cart-dropdown-count');
    const subtotalEl = document.getElementById('cart-dropdown-subtotal');
    const checkoutBtn = document.getElementById('cart-dropdown-checkout');
    const cartMiniUrl = '<?= BASE_URL ?>/cart-mini';
    const updateCartUrl = '<?= BASE_URL ?>/cartupdate';
    const removeCartUrl = '<?= BASE_URL ?>/cartremove';
    const uploadUrl = '<?= BASE_URL ?>/uploads/';

    let isUpdating = false; // simple lock para hindi mag-spam click habang nag-request

    function formatPrice(num) {
        return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    function renderItems(data) {
        if (!data.items || data.items.length === 0) {
            body.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Your cart is empty.</div>';
            footer.classList.add('hidden');
            return;
        }

        body.innerHTML = data.items.map(item => {
            const isOOS = item.stock <= 0;
            const exceeds = !isOOS && item.quantity > item.stock;
            return `
            <div class="cart-mini-row flex items-center gap-3 px-4 py-2.5 hover:bg-orange-50 transition-colors duration-150 border-b border-gray-50 last:border-0 ${isOOS ? 'opacity-50' : ''}"
                 data-cart-id="${item.cart_id}" data-stock="${item.stock}">

                <a href="<?= BASE_URL ?>/mainproductview?id=${item.product_id}"
                   class="w-12 h-12 rounded-lg bg-gray-50 overflow-hidden flex items-center justify-center border border-gray-100 shrink-0">
                    ${item.image
                        ? `<img src="${uploadUrl}${item.image}" class="w-full h-full object-contain p-1">`
                        : `<i class="fa-solid fa-image text-gray-300"></i>`}
                </a>

                <div class="min-w-0 flex-1">
                    <a href="<?= BASE_URL ?>/mainproductview?id=${item.product_id}"
                       class="text-xs text-gray-800 font-medium truncate block hover:text-amber-600">${item.name}</a>
                    <p class="text-[11px] text-gray-400 truncate">${item.variant ?? ''}</p>
                    <p class="text-[11px] font-semibold text-gray-700 mt-0.5">₱${formatPrice(item.price)}</p>
                    ${isOOS
                        ? '<p class="text-[10px] text-red-500 font-medium mt-0.5"><i class="fa-solid fa-circle-exclamation"></i> Out of stock</p>'
                        : exceeds
                            ? `<p class="text-[10px] text-red-500 font-medium mt-0.5"><i class="fa-solid fa-triangle-exclamation"></i> Only ${item.stock} available</p>`
                            : ''
                    }

                    <!-- Qty controls -->
                    <div class="flex items-center gap-1 mt-1.5 border border-gray-200 rounded-md w-fit overflow-hidden">
                        <button type="button" class="mini-qty-btn w-6 h-6 flex items-center justify-center text-gray-400 hover:bg-amber-50 hover:text-amber-600 text-[10px] disabled:opacity-30 disabled:cursor-not-allowed"
                            data-action="dec" data-cart-id="${item.cart_id}" ${isOOS ? 'disabled' : ''}>
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <span class="mini-qty-display w-6 text-center text-[11px] font-semibold text-gray-800">${item.quantity}</span>
                        <button type="button" class="mini-qty-btn w-6 h-6 flex items-center justify-center text-gray-400 hover:bg-amber-50 hover:text-amber-600 text-[10px] disabled:opacity-30 disabled:cursor-not-allowed"
                            data-action="inc" data-cart-id="${item.cart_id}" ${(isOOS || item.quantity >= item.stock) ? 'disabled' : ''}>
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>

                <button type="button" class="mini-remove-btn text-gray-300 hover:text-red-400 transition-colors duration-150 self-start mt-0.5"
                    data-cart-id="${item.cart_id}" title="Remove">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
        `;
        }).join('');

        footer.classList.remove('hidden');
        subtotalEl.textContent = '₱' + formatPrice(data.subtotal);

        const hasIssue = data.items.some(i => i.stock <= 0 || i.quantity > i.stock);
        warningBanner.classList.toggle('hidden', !hasIssue);
        if (checkoutBtn) {
            checkoutBtn.classList.toggle('opacity-50', hasIssue);
            checkoutBtn.classList.toggle('pointer-events-none', hasIssue);
        }
    }

    function updateBadge(count) {
        if (countBadge) {
            if (count > 0) {
                countBadge.textContent = count > 99 ? '99+' : count;
                countBadge.classList.remove('hidden');
            } else {
                countBadge.classList.add('hidden');
            }
        }
        if (dropdownCount) {
            dropdownCount.textContent = count > 0 ? count + ' item' + (count !== 1 ? 's' : '') : '';
        }
    }

    async function fetchMiniCart() {
        try {
            const res = await fetch(cartMiniUrl, { credentials: 'same-origin' });
            const data = await res.json();
            if (data.ok) {
                renderItems(data);
                updateBadge(data.count);
            }
        } catch (e) {
            body.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Unable to load cart.</div>';
        }
    }

    async function updateQty(cartId, newQty) {
        if (isUpdating) return;
        isUpdating = true;
        try {
            const fd = new FormData();
            fd.append('cart_id', cartId);
            fd.append('quantity', newQty);

            const res = await fetch(updateCartUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();

            // Tawag ulit ng buong mini cart para sigurado tama lahat ng laman
            // (subtotal, stock labels, atbp.) — simple lang at always in sync
            await fetchMiniCart();

            if (!data.ok) {
                showMiniError(data.msg || 'Update failed.');
            }
        } catch (e) {
            showMiniError('Something went wrong.');
        } finally {
            isUpdating = false;
        }
    }

    async function removeItem(cartId) {
        if (isUpdating) return;
        isUpdating = true;
        try {
            const fd = new FormData();
            fd.append('cart_id', cartId);

            const res = await fetch(removeCartUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();

            await fetchMiniCart();

            if (!data.ok) {
                showMiniError(data.msg || 'Failed to remove item.');
            }
        } catch (e) {
            showMiniError('Something went wrong.');
        } finally {
            isUpdating = false;
        }
    }

    function showMiniError(msg) {
        // Gamitin yung existing toast function kung available (galing cartview.php),
        // kung wala, simpleng inline message lang sa loob ng dropdown
        if (typeof showToast === 'function') {
            showToast('error', msg);
        } else {
            const note = document.createElement('div');
            note.className = 'px-4 py-2 text-[11px] text-red-500 bg-red-50 border-t border-red-100';
            note.textContent = msg;
            body.prepend(note);
            setTimeout(() => note.remove(), 2500);
        }
    }

    // Event delegation para sa qty buttons at remove buttons sa loob ng dropdown
    body.addEventListener('click', function (e) {
        const qtyBtn = e.target.closest('.mini-qty-btn');
        const removeBtn = e.target.closest('.mini-remove-btn');

        if (qtyBtn) {
            const cartId = qtyBtn.dataset.cartId;
            const row = qtyBtn.closest('.cart-mini-row'); // FIX: specific class, hindi generic [data-cart-id]
            const stock = parseInt(row?.dataset.stock || '0', 10);
            const display = row.querySelector('.mini-qty-display');
            let qty = parseInt(display.textContent, 10) || 1;

            if (qtyBtn.dataset.action === 'inc') {
                if (stock > 0 && qty >= stock) {
                    showMiniError(`Only ${stock} available for this item.`);
                    return;
                }
                qty += 1;
            } else {
                qty = Math.max(1, qty - 1);
            }

            display.textContent = qty; // optimistic update, sa-sync din after fetchMiniCart()
            updateQty(cartId, qty);
        }

        if (removeBtn) {
            const cartId = removeBtn.dataset.cartId;
            removeItem(cartId);
        }
    });

    // Load badge count agad pag-load ng page (hindi na maghintay ng hover)
    fetchMiniCart();

    // Pag hover, kuha ulit ng latest data — dito yung "realtime" feel
    wrapper.addEventListener('mouseenter', fetchMiniCart);

    // I-expose globally para magamit mo sa "Add to Cart" buttons mo (tex. cart-add.php success)
    window.refreshMiniCart = fetchMiniCart;
})();
</script>