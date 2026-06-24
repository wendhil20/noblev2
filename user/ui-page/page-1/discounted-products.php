<?php
// discounted-products.php
// Expects $conn from the including page (already connected via connect.php)

$discountedProducts = [];
$discResult = $conn->query("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.description,
        p.category,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price,
        MAX(v.discountvariant) AS max_discount,
        MIN(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS min_discounted_price,
        MAX(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS max_discounted_price
    FROM nobleproduct p
    INNER JOIN nobleproductcolor c ON c.product_id = p.id
    INNER JOIN nobleproductvariant v ON v.color_id = c.id
    WHERE v.discountvariant > 0
    GROUP BY p.id
    ORDER BY max_discount DESC
");
while ($row = $discResult->fetch_assoc())
    $discountedProducts[] = $row;
?>

<?php if (!empty($discountedProducts)): ?>
    <div class="mb-4 md:mb-8 mt-8">
        <h2 class="text-base md:text-2xl font-bold text-gray-900">
            <span class="text-red-500">DISCOUNTED</span> ITEMS
        </h2>
    </div>

    <div class="relative">

        <!-- Left arrow -->
        <button onclick="discountSlide(-1)" class="absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
               w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
               flex items-center justify-center text-gray-600
               hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Right arrow -->
        <button onclick="discountSlide(1)" class="absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
               w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
               flex items-center justify-center text-gray-600
               hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Track -->
        <div class="overflow-hidden px-1 p-2">
            <div class="flex gap-2 md:gap-4 transition-transform duration-500 ease-[cubic-bezier(.4,0,.2,1)]" id="discountTrack">
                <?php foreach ($discountedProducts as $p): ?>
                    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>" class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
                      block hover:shadow-lg transition-shadow duration-300 shrink-0 relative
                      w-[calc(50%-4px)] sm:w-[calc(33.333%-6px)] lg:w-[calc(25%-9px)]">

                        <!-- Discount badge -->
                        <span class="absolute top-2 left-2 z-10 bg-red-500 text-white text-[10px] md:text-xs font-bold px-1.5 py-0.5 rounded-md shadow">
                            -<?= rtrim(rtrim(number_format($p['max_discount'], 2), '0'), '.') ?>%
                        </span>

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
                            <h3 class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide leading-snug mb-0.5 md:mb-1 line-clamp-1">
                                <?= htmlspecialchars($p['name']) ?>
                            </h3>

                            <?php if (!empty($p['description'])): ?>
                                <p class="text-xs text-gray-400 line-clamp-1 md:line-clamp-2 mb-1 md:mb-2 hidden sm:block">
                                    <?= htmlspecialchars($p['description']) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Price: original (strikethrough) + discounted -->
                            <div class="mt-1 md:mt-2 flex items-baseline gap-1.5 flex-wrap">
                                <span class="text-[10px] md:text-sm font-semibold text-red-500">
                                    ₱<?= number_format($p['min_discounted_price'], 2) ?>
                                    <?= $p['min_discounted_price'] !== $p['max_discounted_price']
                                        ? ' – ₱' . number_format($p['max_discounted_price'], 2)
                                        : '' ?>
                                </span>
                                <span class="text-[9px] md:text-xs text-gray-400 line-through">
                                    ₱<?= number_format($p['min_price'], 2) ?>
                                    <?= $p['min_price'] !== $p['max_price'] ? ' – ₱' . number_format($p['max_price'], 2) : '' ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const track = document.getElementById('discountTrack');
            const cards = track.querySelectorAll('a');
            let current = 0;

            function getVisible() {
                const w = window.innerWidth;
                if (w >= 1024) return 4;
                if (w >= 640) return 3;
                return 2;
            }
            function getGap() {
                return window.innerWidth >= 768 ? 16 : 8;
            }
            function go(idx) {
                const visible = getVisible();
                const max = Math.max(0, cards.length - visible);
                current = Math.min(Math.max(idx, 0), max);
                const cardW = cards[0].offsetWidth;
                const gap = getGap();
                track.style.transform = `translateX(-${current * (cardW + gap)}px)`;
            }
            window.discountSlide = (dir) => go(current + dir);

            let startX = 0;
            track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
            track.addEventListener('touchend', e => {
                const diff = startX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 40) discountSlide(diff > 0 ? 1 : -1);
            });
            window.addEventListener('resize', () => go(current));
        })();
    </script>
<?php endif; ?>