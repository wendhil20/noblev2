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

$prod_result = $conn->query("
    SELECT p.id, p.name, p.imageproduct, ps.subcategory_id
    FROM nobleproduct p
    INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id
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

    <a href="<?= BASE_URL ?>/shop" class="flex items-center gap-1 text-sm font-medium text-gray-700 hover:text-orange-500 transition-colors duration-150 focus:outline-none">
        Products
        <svg class="w-4 h-4 mt-0.5 transition-transform duration-200 group-hover:rotate-180"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </a>

    <!-- Full-width panel pinned below navbar -->
    <div class="fixed left-0 right-0 top-16 bg-white border-t border-gray-200 shadow-xl
                opacity-0 invisible group-hover:opacity-100 group-hover:visible
                transition-all duration-200 z-50">
        <div class="max-w-screen-xl mx-auto px-6 py-6">

            <div class="grid gap-8"
                 style="grid-template-columns: repeat(<?= min(count($categories), 5) ?>, minmax(140px, 1fr));">

                <?php foreach ($categories as $cid => $cat): ?>
                    <?php if (empty($cat['subcategories'])) continue; ?>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 mb-3 pb-2 border-b border-gray-100">
                            <?= htmlspecialchars($cat['name']) ?>
                        </p>
                        <ul class="space-y-1">
                            <?php foreach ($cat['subcategories'] as $sid => $sub): ?>
                                <li>
                                    <button class="subcategory-btn w-full text-left flex items-center justify-between gap-2
                                                   text-sm text-gray-700 hover:text-orange-500 py-1.5 px-2 rounded-md
                                                   hover:bg-orange-50 transition-colors duration-150"
                                            data-target="sub-products-<?= $sid ?>">
                                        <span class="font-medium"><?= htmlspecialchars($sub['name']) ?></span>
                                        <?php if (!empty($sub['products'])): ?>
                                            <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 chevron-icon transition-transform duration-200"
                                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        <?php endif; ?>
                                    </button>

                                    <?php if (!empty($sub['products'])): ?>
                                        <div id="sub-products-<?= $sid ?>"
                                             class="sub-products hidden mt-1 ml-2 pl-2 border-l-2 border-orange-100 space-y-1 pb-1">
                                            <?php foreach ($sub['products'] as $prod): ?>
                                                <a href="<?= BASE_URL ?>/mainproductview/<?= $prod['id'] ?>"
                                                   class="flex items-center gap-2 py-1.5 px-1 rounded-md text-xs text-gray-600
                                                          hover:text-orange-500 hover:bg-orange-50 transition-colors duration-150 group/prod">
                                                    <div class="w-9 h-9 rounded-md bg-gray-100 shrink-0 overflow-hidden border border-gray-200">
                                                        <?php if (!empty($prod['imageproduct'])): ?>
                                                            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($prod['imageproduct']) ?>"
                                                                 alt="<?= htmlspecialchars($prod['name']) ?>"
                                                                 class="w-full h-full object-contain"
                                                                 onerror="this.style.display='none'">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                </svg>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="leading-tight line-clamp-2 group-hover/prod:underline">
                                                        <?= htmlspecialchars($prod['name']) ?>
                                                    </span>
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
document.querySelectorAll('.subcategory-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const target = document.getElementById(this.dataset.target);
        const chevron = this.querySelector('.chevron-icon');
        if (!target) return;
        const isOpen = !target.classList.contains('hidden');
        this.closest('ul')?.querySelectorAll('.sub-products').forEach(el => el.classList.add('hidden'));
        this.closest('ul')?.querySelectorAll('.chevron-icon').forEach(el => el.classList.remove('rotate-180'));
        if (!isOpen) {
            target.classList.remove('hidden');
            chevron?.classList.add('rotate-180');
        }
    });
});

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