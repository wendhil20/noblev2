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

// Kunin yung mga naka-save na product ng current user (para alam kung alin bookmark ang naka-red)
$savedIds = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $savedResult = $conn->query("SELECT product_id FROM noblesavedproduct WHERE user_id = $uid");
    if ($savedResult) {
        while ($row = $savedResult->fetch_assoc()) {
            $savedIds[] = (int) $row['product_id'];
        }
    }
}

// Helper to format sold count (e.g. 1,200 -> "1.2K")
function formatSoldCount($n)
{
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
        <span class="h-px flex-1 max-w-30 md:max-w-55 bg-linear-to-r from-amber-300 to-transparent"></span>
    </div>
</div>

<!-- Product Slider -->
<?php if (empty($products)): ?>
    <div class="text-center py-20 text-gray-400">
        <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
        <p class="text-lg">No popular products yet.</p>
    </div>
<?php else: ?>

    <div class="relative  ">

        <!-- Left arrow (desktop only; mobile uses native touch scroll) -->
        <button id="productPrev" onclick="productSlide(-1)" aria-label="Previous product" class="hidden md:flex absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
       w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
       items-center justify-center text-gray-600
       hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Right arrow (desktop only) -->
        <button id="productNext" onclick="productSlide(1)" aria-label="Next product" class="hidden md:flex absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
       w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
       items-center justify-center text-gray-600
       hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Track: native horizontal scroll + snap. Fast/native feel on touch, JS-assisted arrows on desktop -->
        <div class="overflow-hidden px-1 p-2">
            <div id="productTrack" class="flex gap-2 md:gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory
                       [-ms-overflow-style:none] scrollbar-none [&::-webkit-scrollbar]:hidden p-1 ">
                <?php foreach ($products as $p): ?>
                    <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>"
                        aria-label="View details for <?= htmlspecialchars($p['name']) ?>" class="group relative rounded-xl md:rounded-2xl overflow-hidden 
      block hover:shadow-lg transition-shadow duration-300 shrink-0 snap-start
      w-[calc(50%-4px)] sm:w-[calc(33.333%-6px)] lg:w-[calc(25%-9px)] hover:text-amber-500 ">

                        <!-- Image -->
                        <div
                            class="relative aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
                            <?php if (!empty($p['imageproduct'])): ?>
                                <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                    alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i class="fa-solid fa-image text-3xl md:text-5xl"></i>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="save-btn absolute top-2 right-2 z-20
                                   hidden md:flex md:w-10 md:h-10 rounded-full bg-white/90
                                   items-center justify-center
                                   md:opacity-0 md:group-hover:opacity-100
                                   transition-opacity duration-200" data-product-id="<?= $p['id'] ?>"
                                aria-label="Save to favorites">
                                <i
                                    class="<?= in_array($p['id'], $savedIds) ? 'fa-solid text-red-500' : 'fa-regular text-orange-400' ?> fa-heart text-sm md:text-lg"></i>
                            </button>

                            <button type="button" class="share-btn absolute top-14 right-2 z-20
                                   hidden md:flex md:w-10 md:h-10 rounded-full bg-white/90
                                   items-center justify-center
                                   md:opacity-0 md:group-hover:opacity-100
                                   transition-opacity duration-200" data-product-id="<?= $p['id'] ?>"
                                data-product-name="<?= htmlspecialchars($p['name']) ?>" aria-label="Share product">
                                <i class="fa-solid fa-share-nodes text-orange-400 text-sm md:text-lg"></i>
                            </button>
                        </div>

                        <!-- Info -->
                        <div class="p-2 md:p-3 ">
                            <h3
                                class="font-bold text-xs md:text-sm uppercase tracking-wide leading-snug mb-0.5 md:mb-1 line-clamp-1">
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

        function getGap() {
            return window.innerWidth >= 768 ? 16 : 8; // gap-4 = 16px, gap-2 = 8px
        }

        // Desktop arrow click: scroll by roughly one "page" of visible cards, native smooth scroll
        function productSlide(dir) {
            if (!cards.length) return;
            const cardW = cards[0].offsetWidth;
            const gap = getGap();
            const containerWidth = track.offsetWidth;
            const visible = Math.max(1, Math.floor((containerWidth + gap) / (cardW + gap)));
            track.scrollBy({ left: dir * visible * (cardW + gap), behavior: 'smooth' });
        }
        window.productSlide = productSlide;

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

    // Save / Unsave (bookmark) functionality
    document.querySelectorAll('.save-btn').forEach(btn => {
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

    // Share functionality
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const shareUrl = '<?= BASE_URL ?>/mainproductview?id=' + productId;

            if (navigator.share) {
                navigator.share({
                    title: productName,
                    text: 'Check out ' + productName,
                    url: shareUrl
                }).catch(() => { }); // user cancelled, ignore
            } else {
                navigator.clipboard.writeText(shareUrl)
                    .then(() => {
                        // simple feedback
                        const icon = this.querySelector('i');
                        icon.classList.remove('fa-share-nodes');
                        icon.classList.add('fa-check');
                        setTimeout(() => {
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-share-nodes');
                        }, 1500);
                    })
                    .catch(() => alert('Link: ' + shareUrl));
            }
        });
    });
</script>