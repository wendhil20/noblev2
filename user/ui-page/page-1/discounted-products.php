<?php
// discounted-products.php
// Expects $conn from the including page (already connected via connect.php)

// Avoid redeclare error if this helper is already defined by another included file
if (!function_exists('formatSoldCount')) {
    function formatSoldCount($n) {
        $n = intval($n);
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
        }
        return number_format($n);
    }
}

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
        MAX(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS max_discounted_price,
        rv.avg_rating,
        rv.review_count,
        (
            SELECT COALESCE(SUM(v2.sold), 0)
            FROM nobleproductvariant v2
            JOIN nobleproductcolor c2 ON c2.id = v2.color_id
            WHERE c2.product_id = p.id
        ) AS total_sold
    FROM nobleproduct p
    INNER JOIN nobleproductcolor c ON c.product_id = p.id
    INNER JOIN nobleproductvariant v ON v.color_id = c.id
    LEFT JOIN (
        SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM noblereview
        GROUP BY product_id
    ) rv ON rv.product_id = p.id
    WHERE v.discountvariant > 0
    GROUP BY p.id
    ORDER BY max_discount DESC
");
while ($row = $discResult->fetch_assoc())
    $discountedProducts[] = $row;

// Kunin yung mga naka-save na product ng current user (para alam kung alin bookmark ang naka-red)
$discSavedIds = [];
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $discSavedResult = $conn->query("SELECT product_id FROM noblesavedproduct WHERE user_id = $uid");
    if ($discSavedResult) {
        while ($row = $discSavedResult->fetch_assoc()) {
            $discSavedIds[] = (int) $row['product_id'];
        }
    }
}
?>

<?php if (!empty($discountedProducts)): ?>
    <div class="mb-4 md:mb-8 mt-8">
        <div class="flex items-center gap-4">
            <h2 class="text-xs md:text-lg font-bold text-gray-900 whitespace-nowrap">
                DISCOUNTED<span class="text-amber-500"> ITEMS</span>
            </h2>
           <span class="h-px w-16 md:w-32 bg-gradient-to-r from-amber-300 to-transparent"></span>
        </div>
    </div>

    <div class="relative">

        <!-- Left arrow (desktop only; mobile uses native touch scroll) -->
        <button id="discountPrev" onclick="discountSlide(-1)" class="hidden md:flex absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
               w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
               items-center justify-center text-gray-600
               hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Right arrow (desktop only) -->
        <button id="discountNext" onclick="discountSlide(1)" class="hidden md:flex absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
               w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
               items-center justify-center text-gray-600
               hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Track: native horizontal scroll + snap. Fast/native feel on touch, JS-assisted arrows on desktop -->
        <div class="overflow-hidden px-1 p-2">
            <div id="discountTrack"
                class="flex gap-2 md:gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory
                       [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <?php foreach ($discountedProducts as $p): ?>
                    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>" class="group rounded-xl md:rounded-2xl overflow-hidden 
                      block hover:shadow-lg transition-shadow duration-300 shrink-0 relative snap-start
                      w-[calc(50%-4px)] sm:w-[calc(33.333%-6px)] lg:w-[calc(25%-9px)]">

                        <!-- Discount badge -->
                        <span
                            class="absolute top-2 left-2 z-10 bg-red-500 text-white text-[10px] md:text-xs font-bold px-1.5 py-0.5 rounded-md shadow">
                            -<?= rtrim(rtrim(number_format($p['max_discount'], 2), '0'), '.') ?>%
                        </span>

                        <!-- Image -->
                        <div class="relative aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
                            <?php if (!empty($p['imageproduct'])): ?>
                                <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                    alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain" loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i class="fa-solid fa-image text-3xl md:text-5xl"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Save / Bookmark button -->
                            <button type="button"
                                class="save-btn absolute top-1.5 right-1.5 md:top-2 md:right-2 z-10
                                       w-7 h-7 md:w-8 md:h-8 rounded-full bg-white/90 shadow
                                       flex items-center justify-center"
                                data-product-id="<?= $p['id'] ?>"
                                aria-label="Save to favorites">
                                <i class="<?= in_array($p['id'], $discSavedIds) ? 'fa-solid text-red-500' : 'fa-regular text-gray-500' ?> fa-bookmark text-xs md:text-sm"></i>
                            </button>
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
            const prevBtn = document.getElementById('discountPrev');
            const nextBtn = document.getElementById('discountNext');

            function getGap() {
                return window.innerWidth >= 768 ? 16 : 8;
            }

            // Desktop arrow click: scroll by roughly one "page" of visible cards, native smooth scroll
            function discountSlide(dir) {
                if (!cards.length) return;
                const cardW = cards[0].offsetWidth;
                const gap = getGap();
                const containerWidth = track.offsetWidth;
                const visible = Math.max(1, Math.floor((containerWidth + gap) / (cardW + gap)));
                track.scrollBy({ left: dir * visible * (cardW + gap), behavior: 'smooth' });
            }
            window.discountSlide = discountSlide;

            function updateArrows() {
                if (!prevBtn || !nextBtn) return;
                const maxScroll = track.scrollWidth - track.clientWidth;

                if (maxScroll <= 4) {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                    return;
                }
                prevBtn.style.display = track.scrollLeft <= 4 ? 'none' : 'flex';
                nextBtn.style.display = track.scrollLeft >= maxScroll - 4 ? 'none' : 'flex';
            }

            // Native scroll fires this on both touch swipe and arrow-triggered scroll
            track.addEventListener('scroll', updateArrows, { passive: true });
            window.addEventListener('resize', updateArrows);
            window.addEventListener('load', updateArrows);

            updateArrows();
        })();

        // Save / Unsave (bookmark) functionality — scoped lang sa #discountTrack
        document.querySelectorAll('#discountTrack .save-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const icon = this.querySelector('i');
                const productId = this.dataset.productId;

                fetch('<?= BASE_URL ?>/savedproduct', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + encodeURIComponent(productId)
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'May error, subukan ulit.');
                        return;
                    }
                    if (data.saved) {
                        icon.classList.remove('fa-regular', 'text-gray-500');
                        icon.classList.add('fa-solid', 'text-red-500');
                    } else {
                        icon.classList.remove('fa-solid', 'text-red-500');
                        icon.classList.add('fa-regular', 'text-gray-500');
                    }
                })
                .catch(() => alert('May error, subukan ulit.'));
            });
        });
    </script>
<?php endif; ?>