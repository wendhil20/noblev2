<?php
// producttabs.php


$specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];
$gallery = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
?>

<!-- ════════ Tabs Card: Specs / Gallery / Reviews ════════ -->
<div class="bg-white rounded-xl md:rounded-2xl border border-gray-100 shadow-sm mt-4 md:mt-6 overflow-hidden">

    <!-- Tab Nav -->
    <div class="flex border-b border-gray-100 overflow-x-auto">
        <?php if (!empty($specs)): ?>
            <button onclick="switchTab('specs')" id="tab-specs"
                class="tab-btn px-5 md:px-8 py-3 md:py-4 text-xs md:text-sm font-semibold text-amber-500 border-b-2 border-amber-500 transition whitespace-nowrap">
                <i class="fa-solid fa-list-check mr-1.5"></i> Specifications
            </button>
        <?php endif; ?>
        <?php if (!empty($gallery)): ?>
            <button onclick="switchTab('gallery')" id="tab-gallery"
                class="tab-btn px-5 md:px-8 py-3 md:py-4 text-xs md:text-sm font-semibold text-gray-400 border-b-2 border-transparent hover:text-gray-600 transition whitespace-nowrap">
                <i class="fa-regular fa-images mr-1.5"></i> Collection
            </button>
        <?php endif; ?>
        <button onclick="switchTab('reviews')" id="tab-reviews"
            class="tab-btn px-5 md:px-8 py-3 md:py-4 text-xs md:text-sm font-semibold <?= (empty($specs)) ? 'text-amber-500 border-b-2 border-amber-500' : 'text-gray-400 border-b-2 border-transparent hover:text-gray-600' ?> transition whitespace-nowrap">
            <i class="fa-solid fa-star mr-1.5"></i> Reviews
            <?php if ($totalReviews > 0): ?>
                <span class="ml-1 text-[10px] text-gray-400">(<?= $totalReviews ?>)</span>
            <?php endif; ?>
        </button>
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

    <!-- Reviews Panel -->
    <div id="panel-reviews" class="p-5 md:p-8 <?= (empty($specs)) ? '' : 'hidden' ?>">

        <?php if ($totalReviews === 0): ?>
            <div class="text-center py-10 text-gray-400 text-sm">
                <i class="fa-regular fa-comment-dots text-3xl mb-2 block"></i>
                No reviews yet for this product.
            </div>
        <?php else: ?>

            <!-- Summary -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 pb-6 border-b border-gray-100 mb-6">
                <div class="text-center shrink-0">
                    <p class="text-3xl md:text-4xl font-bold text-gray-900"><?= number_format($avgRating, 1) ?></p>
                    <div class="flex items-center justify-center gap-0.5 mt-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i
                                class="fa-star text-sm <?= $i <= round($avgRating) ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?= $totalReviews ?>
                        review<?= $totalReviews !== 1 ? 's' : '' ?></p>
                </div>

                <div class="flex-1 w-full space-y-1.5">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <?php $pct = $totalReviews > 0 ? round(($ratingBreakdown[$star] / $totalReviews) * 100) : 0; ?>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="text-gray-500 w-8 shrink-0"><?= $star ?>★</span>
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: <?= $pct ?>%"></div>
                            </div>
                            <span class="text-gray-400 w-8 text-right shrink-0"><?= $ratingBreakdown[$star] ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Individual reviews -->
            <div class="space-y-5">
                <?php foreach ($reviews as $r): ?>
                    <div class="flex gap-3">
                        <div class="w-9 h-9 rounded-full bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                            <?php if (!empty($r['avatar'])): ?>
                                <img src="<?= htmlspecialchars($r['avatar']) ?>" class="w-full h-full object-cover" loading="lazy">
                            <?php else: ?>
                                <i class="fa-solid fa-user text-gray-300 text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($r['name']) ?></p>
                                <span class="text-[11px] text-gray-400">
                                    <?= htmlspecialchars(date('M d, Y', strtotime($r['created_at']))) ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-0.5 mt-0.5 mb-1.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i
                                        class="fa-star text-xs <?= $i <= (int) $r['rating'] ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if (!empty($r['comment'])): ?>
                                <p class="text-xs md:text-sm text-gray-600 leading-relaxed">
                                    <?= nl2br(htmlspecialchars($r['comment'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

</div>

<!-- Lightbox (ginagamit ng gallery panel sa itaas) -->
<div id="lightbox" onclick="closeLightbox()"
    class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center hidden p-4 md:p-8">
    <button onclick="closeLightbox()"
        class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition text-lg z-10">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img id="lightbox-img" src="" alt="" class="max-w-full max-h-[90vh] object-contain rounded-xl shadow-2xl">
</div>

<script>
    // ─── Tabs (Specs / Gallery / Reviews) + Lightbox ───────────────────
    // Ginagamit: uploadUrl (galing sa parent script), $gallery (mula sa PHP sa itaas)

    function switchTab(tab) {
        const panels = ['specs', 'gallery', 'reviews'];
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
</script>