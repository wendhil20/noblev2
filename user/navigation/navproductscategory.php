<?php
// navproductscategory.php

$categories = [];
$cat_result = $conn->query("SELECT id, name FROM noblecategory ORDER BY name ASC");
while ($cat = $cat_result->fetch_assoc()) {
    $categories[$cat['id']] = ['name' => $cat['name'], 'subcategories' => []];
}

$sub_result = $conn->query("SELECT id, category_id, name FROM noblesubcategory ORDER BY name ASC");
while ($sub = $sub_result->fetch_assoc()) {
    $cid = $sub['category_id'];
    if (isset($categories[$cid])) {
        $categories[$cid]['subcategories'][$sub['id']] = ['name' => $sub['name'], 'products' => []];
    }
}

// Pull products WITH price + a color/tag so the preview cards can match the shop grid look
$prod_result = $conn->query("
    SELECT
        p.id, p.name, p.imageproduct, p.description, ps.subcategory_id,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price
    FROM nobleproduct p
    INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    GROUP BY p.id, ps.subcategory_id
    ORDER BY p.name ASC
");
while ($prod = $prod_result->fetch_assoc()) {
    $sid = $prod['subcategory_id'];
    foreach ($categories as $cid => &$cat) {
        if (isset($cat['subcategories'][$sid])) {
            $cat['subcategories'][$sid]['products'][] = $prod;
            break;
        }
    }
    unset($cat);
}


?>

<!-- DESKTOP MEGA DROPDOWN -->
<div class="relative group" id="desktop-products-dropdown">

    <!-- Page-level dim: sits BEHIND the white dropdown panel, dims the rest of the site -->
    <div id="page-dim-backdrop" class="hidden fixed inset-0 top-16 bg-gray-900/40 z-40"></div>

    <a href="<?= BASE_URL ?>/shop" class="flex items-center gap-1 text-sm font-medium text-gray-700 hover:text-orange-500 transition-colors duration-150 focus:outline-none">
        Products
        <i class="fa-solid fa-caret-down mt-0.5 transition-transform duration-200 group-hover:rotate-180"></i>
    </a>

    <!-- Full-width panel pinned below navbar -->
    <div class="fixed left-0 right-0 top-16 bg-white border-t border-gray-200 shadow-xl
                opacity-0 invisible group-hover:opacity-100 group-hover:visible
                transition-all duration-200 z-50">
        <div class="max-w-screen-xl mx-auto px-6 py-6 relative" id="dropdown-inner">

            <div class="grid gap-8 relative z-40" id="categories-grid"
                 style="grid-template-columns: repeat(<?= min(count($categories), 5) ?>, minmax(140px, 1fr));">

                <?php foreach ($categories as $cid => $cat): ?>
                    <?php if (empty($cat['subcategories'])) continue; ?>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-gray-600 mb-3 pb-2 border-b border-gray-100">
                            <?= htmlspecialchars($cat['name']) ?>
                        </p>
                        <ul class="space-y-1">
                            <?php foreach ($cat['subcategories'] as $sid => $sub): ?>
                                <li>
                                    <div class="subcategory-row w-full flex items-center justify-between gap-1 rounded-md
                                                hover:bg-orange-50 transition-colors duration-150">
                                        <a href="<?= BASE_URL ?>/productcategory?id=<?= $cid ?>&sub=<?= $sid ?>"
                                           class="subcategory-link flex-1 text-sm font-medium text-gray-700
                                                  hover:text-orange-500 py-1.5 px-2 transition-colors duration-150">
                                            <?= htmlspecialchars($sub['name']) ?>
                                        </a>
                                        <?php if (!empty($sub['products'])): ?>
                                            <button type="button"
                                                    class="subcategory-btn shrink-0 p-1.5 mr-1 rounded-md text-gray-400
                                                           hover:text-orange-500 transition-colors duration-150"
                                                    data-target="sub-products-<?= $sid ?>"
                                                    data-subname="<?= htmlspecialchars($sub['name']) ?>"
                                                    data-catid="<?= $cid ?>"
                                                    data-subid="<?= $sid ?>"
                                                    aria-label="Preview <?= htmlspecialchars($sub['name']) ?> products">
                                               <i class="fa-solid fa-caret-right"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Hidden data source only — cloned into the preview panel below -->
                                    <?php if (!empty($sub['products'])): ?>
                                        <div id="sub-products-<?= $sid ?>" class="hidden">
                                            <?php foreach ($sub['products'] as $prod):
                                                $min = floatval($prod['min_price'] ?? 0);
                                                $max = floatval($prod['max_price'] ?? 0);
                                                $priceLabel = ($min > 0 || $max > 0)
                                                    ? '₱' . number_format($min, 2) . ($min !== $max ? ' – ₱' . number_format($max, 2) : '')
                                                    : '';
                                            ?>
                                                <a href="<?= BASE_URL ?>/mainproductview/<?= $prod['id'] ?>"
                                                   class="preview-product-item"
                                                   data-name="<?= htmlspecialchars($prod['name']) ?>"
                                                   data-desc="<?= htmlspecialchars($prod['description'] ?? '') ?>"
                                                   data-price="<?= htmlspecialchars($priceLabel) ?>"
                                                   data-img="<?= !empty($prod['imageproduct']) ? BASE_URL . '/uploads/' . htmlspecialchars($prod['imageproduct']) : '' ?>">
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>

            </div>

            <!-- SPEECH-BUBBLE PREVIEW: floats above the panel, doesn't push layout down -->
            <div id="sub-preview-wrap" class="hidden absolute left-0 right-0 z-40">
                <div id="sub-preview-arrow"
                     class="absolute -top-2 w-4 h-4 bg-orange-500 border-l border-t border-gray-100 rotate-45 transition-all duration-150"></div>
                <div id="sub-preview-panel"
                     class="relative bg-white border border-gray-100 rounded-xl shadow-2xl p-4 flex flex-col">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                        <p class="text-[11px] font-bold uppercase tracking-widest text-gray-500">Recommended</p>
                        <a id="sub-preview-viewall" href="#"
                           class="text-[11px] font-semibold text-orange-500 hover:text-orange-600 transition-colors duration-150">
                            View all →
                        </a>
                    </div>
                    <div id="sub-preview-grid"
                         class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 max-h-72 overflow-y-auto">
                        <!-- product cards injected here -->
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-400">Browse our full catalog</span>
                <a href="<?= BASE_URL ?>/shop"
                   class="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors duration-150">
                    View All Products →
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const previewWrap    = document.getElementById('sub-preview-wrap');
const previewArrow   = document.getElementById('sub-preview-arrow');
const previewPanel   = document.getElementById('sub-preview-grid');
const previewBackdrop = document.getElementById('page-dim-backdrop');
const dropdownInner  = document.getElementById('dropdown-inner');

let activeBtn = null;

function renderProductCard(item) {
    const name  = item.dataset.name;
    const desc  = item.dataset.desc;
    const price = item.dataset.price;
    const img   = item.dataset.img;
    const href  = item.getAttribute('href');

    const card = document.createElement('a');
    card.href = href;
    card.className = 'bg-white rounded-xl overflow-hidden border border-gray-100 block hover:shadow-md transition-shadow duration-200';

    card.innerHTML = `
        <div class="h-32 overflow-hidden bg-gray-50 flex items-center justify-center p-2.5">
            ${img
                ? `<img src="${img}" alt="${name}" class="max-h-full max-w-full object-contain" onerror="this.style.display='none'">`
                : `<svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`
            }
        </div>
        <div class="p-2.5">
            <h4 class="font-bold text-gray-900 text-xs uppercase tracking-wide leading-snug line-clamp-1">${name}</h4>
            ${desc ? `<p class="text-[11px] text-gray-400 line-clamp-1 mt-0.5">${desc}</p>` : ''}
            <div class="mt-1.5">
                ${price
                    ? `<span class="text-xs font-semibold text-gray-800">${price}</span>`
                    : `<span class="text-[11px] text-gray-400 italic">Price not set</span>`
                }
            </div>
        </div>
    `;
    return card;
}

function positionPreview(btn) {
    const btnRect  = btn.getBoundingClientRect();
    const wrapRect = dropdownInner.getBoundingClientRect();
    const arrowSize = 16; // matches w-4/h-4
    const gap = 10; // distance between button row and the tip of the arrow

    // vertical: sit just under the row this button lives in
    const top = (btnRect.bottom - wrapRect.top) + gap;
    previewWrap.style.top = top + 'px';

    // horizontal: center the arrow on the button
    let arrowLeft = (btnRect.left - wrapRect.left) + (btnRect.width / 2) - (arrowSize / 2);
    arrowLeft = Math.max(8, Math.min(arrowLeft, wrapRect.width - arrowSize - 8));
    previewArrow.style.left = arrowLeft + 'px';
}

function openPreview(btn) {
    const target = document.getElementById(btn.dataset.target);
    if (!target) return;

    // reset previous active state
    document.querySelectorAll('.subcategory-row').forEach(r => {
        r.classList.remove('bg-orange-50');
        r.querySelector('.subcategory-link')?.classList.remove('text-orange-500');
    });
    const row = btn.closest('.subcategory-row');
    row?.classList.add('bg-orange-50');
    row?.querySelector('.subcategory-link')?.classList.add('text-orange-500');

    previewPanel.innerHTML = '';
    target.querySelectorAll('.preview-product-item').forEach(item => {
        previewPanel.appendChild(renderProductCard(item));
    });

    const viewAllLink = document.getElementById('sub-preview-viewall');
    if (viewAllLink && btn.dataset.catid && btn.dataset.subid) {
        viewAllLink.href = '<?= BASE_URL ?>/productcategory?id=' + btn.dataset.catid + '&sub=' + btn.dataset.subid;
    }

    previewWrap.classList.remove('hidden');
    previewBackdrop.classList.remove('hidden');
    positionPreview(btn);
    activeBtn = btn;
}

function closePreview() {
    previewWrap.classList.add('hidden');
    previewBackdrop.classList.add('hidden');
    document.querySelectorAll('.subcategory-row').forEach(r => {
        r.classList.remove('bg-orange-50');
        r.querySelector('.subcategory-link')?.classList.remove('text-orange-500');
    });
    activeBtn = null;
}

document.querySelectorAll('.subcategory-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (!this.dataset.target || !document.getElementById(this.dataset.target)) return;
        if (activeBtn === this) {
            closePreview();
        } else {
            openPreview(this);
        }
    });
});

// keep panel aligned if window resizes while open
window.addEventListener('resize', () => {
    if (activeBtn) positionPreview(activeBtn);
});

// close preview whenever the whole mega menu closes (mouse leaves)
document.getElementById('desktop-products-dropdown')?.addEventListener('mouseleave', closePreview);

// close preview when clicking the dim backdrop
previewBackdrop?.addEventListener('click', closePreview);

document.querySelectorAll('.mobile-sub-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const target = document.getElementById(this.dataset.target);
        const chevron = this.querySelector('.mobile-chevron');
        if (!target) return;
        target.classList.toggle('hidden');
        chevron?.classList.toggle('rotate-180');
    });
});

const mobileProductsToggle  = document.getElementById('mobile-products-toggle');
const mobileProductsMenu    = document.getElementById('mobile-products-menu');
const mobileProductsChevron = document.getElementById('products-chevron');
if (mobileProductsToggle) {
    mobileProductsToggle.addEventListener('click', () => {
        mobileProductsMenu?.classList.toggle('hidden');
        mobileProductsChevron?.classList.toggle('rotate-180');
    });
}
</script>