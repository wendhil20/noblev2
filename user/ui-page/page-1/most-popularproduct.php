<?php
// /user/ui-page/page-1/most-popularproduct.php

$uploadUrl = BASE_URL . '/uploads/';

// Fetch products marked as "popular" by the product specialist
$products = [];
$result = $conn->query("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.description,
        p.category,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count,
        (
            SELECT COALESCE(SUM(v2.sold), 0)
            FROM nobleproductvariant v2
            JOIN nobleproductcolor c2 ON c2.id = v2.color_id
            WHERE c2.product_id = p.id
        ) AS total_sold
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    LEFT JOIN noblereview r ON r.product_id = p.id
    WHERE p.is_popular = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
while ($row = $result->fetch_assoc())
    $products[] = $row;

// Helper to format sold count (e.g. 1,200 -> "1.2K")
function formatSoldCount($n) {
    $n = intval($n);
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    }
    return number_format($n);
}
?>

<div class="mb-4 md:mb-8 mt-5">
    <div class="flex items-center gap-4">
        <h2 class="text-sm md:text-xl font-bold text-gray-900 whitespace-nowrap">
            MOST <span class="text-amber-500">POPULAR ITEM</span>
        </h2>
        <span class="h-px flex-1 max-w-[120px] md:max-w-[220px] bg-gradient-to-r from-amber-300 to-transparent"></span>
    </div>
</div>

<!-- Product Slider -->
<?php if (empty($products)): ?>
    <div class="text-center py-20 text-gray-400">
        <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
        <p class="text-lg">No popular products yet.</p>
    </div>
<?php else: ?>

    <div class="relative">

        <!-- Left arrow -->
        <button id="productPrev" onclick="productSlide(-1)" class="absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
               w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
               flex items-center justify-center text-gray-600
               hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Right arrow -->
        <button id="productNext" onclick="productSlide(1)" class="absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
               w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
               flex items-center justify-center text-gray-600
               hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Track -->
        <div class="overflow-hidden px-1 p-2">
            <div class="flex gap-2 md:gap-4 transition-transform duration-500 ease-[cubic-bezier(.4,0,.2,1)]"
                id="productTrack">
                <?php foreach ($products as $p): ?>
                    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>" class=" rounded-xl md:rounded-2xl overflow-hidden 
                      block hover:shadow-lg transition-shadow duration-300 shrink-0
                      w-[calc(50%-4px)] sm:w-[calc(33.333%-6px)] lg:w-[calc(25%-9px)]">

                        <!-- Image -->
                        <div class="aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
                            <?php if (!empty($p['imageproduct'])): ?>
                                <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                    alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i class="fa-solid fa-image text-3xl md:text-5xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
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

                            <!-- Rating + Sold count -->
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <?php if (!empty($p['review_count']) && $p['review_count'] > 0): ?>
                                    <div class="flex items-center gap-1">
                                        <i class="fa-solid fa-star text-amber-400 text-[10px] md:text-xs"></i>
                                        <span class="text-[10px] md:text-xs font-semibold text-gray-700">
                                            <?= number_format($p['avg_rating'], 1) ?>
                                        </span>
                                        <span class="text-[9px] md:text-xs text-gray-400">
                                            (<?= (int) $p['review_count'] ?>)
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($p['total_sold']) && $p['total_sold'] > 0): ?>
                                    <span class="text-[9px] md:text-xs text-gray-400">
                                        <?= formatSoldCount($p['total_sold']) ?> sold
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Price -->
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
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    (function () {
        const track = document.getElementById('productTrack');
        if (!track) return;
        const cards = track.querySelectorAll('a');
        const prevBtn = document.getElementById('productPrev');
        const nextBtn = document.getElementById('productNext');
        let current = 0;

        function getVisible() {
            const w = window.innerWidth;
            if (w >= 1024) return 4;
            if (w >= 640) return 3;
            return 2;
        }

        function getGap() {
            return window.innerWidth >= 768 ? 16 : 8; // gap-4 = 16px, gap-2 = 8px
        }

        function updateArrows(max) {
            // Itago kapag nasa unang slide na (wala nang dulo sa kaliwa)
            prevBtn.style.display = current <= 0 ? 'none' : 'flex';
            // Itago kapag nasa huling slide na (wala nang dulo sa kanan)
            nextBtn.style.display = current >= max ? 'none' : 'flex';
        }

        function go(idx) {
            const visible = getVisible();
            const max = Math.max(0, cards.length - visible);
            current = Math.min(Math.max(idx, 0), max);

            const cardW = cards[0].offsetWidth;
            const gap = getGap();
            track.style.transform = `translateX(-${current * (cardW + gap)}px)`;
            updateArrows(max);
        }

        window.productSlide = (dir) => go(current + dir);

        let startX = 0;
        track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
        track.addEventListener('touchend', e => {
            const diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) productSlide(diff > 0 ? 1 : -1);
        });

        window.addEventListener('resize', () => go(current));

        go(0); // initial check sa arrows pagka-load
    })();
</script>