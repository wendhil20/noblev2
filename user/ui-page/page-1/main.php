<?php
// page.php

include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';

// Fetch active promotions (within date range)
$promotions = [];
$promoResult = $conn->query("
    SELECT id, title, description, banner_image
    FROM noblepromotions
    WHERE is_active = 1
      AND start_date <= CURDATE()
      AND end_date   >= CURDATE()
    ORDER BY created_at DESC
");
while ($row = $promoResult->fetch_assoc())
    $promotions[] = $row;

// Fetch products with price range from variants
$products = [];
$result = $conn->query("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.description,
        p.category,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
while ($row = $result->fetch_assoc())
    $products[] = $row;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="bg-gray-50">

    <div class="max-w-7xl mx-auto px-6 py-5">

        <!-- ===== PROMOTION BANNERS ===== -->
        <?php if (!empty($promotions)): ?>
            <div class="mb-6 md:mb-10">

                <div class="relative w-full rounded-lg overflow-hidden shadow-sm bg-slate-800 aspect-[16/5]"
                    id="promoSlider">

                    <!-- Slides container -->
                    <div class="flex h-full w-full" id="promoTrack">
                        <?php foreach ($promotions as $promo): ?>
                            <div class="relative shrink-0 w-full h-full">
                                <?php if ($promo['banner_image']): ?>
                                    <img src="<?= BASE_URL ?>/uploads/promotions/<?= htmlspecialchars($promo['banner_image']) ?>"
                                        alt="<?= htmlspecialchars($promo['title']) ?>" class="w-full h-full object-contain">
                                <?php endif; ?>
                                <!-- Gradient overlay + text -->
                                <div class="absolute inset-0 flex flex-col justify-end
                                bg-gradient-to-t from-black/70 via-black/20 to-transparent
                                px-4 md:px-14 pb-3 md:pb-8">
                                    <p
                                        class="text-white font-bold text-[11px] md:text-3xl leading-snug drop-shadow-lg line-clamp-1">
                                        <?= htmlspecialchars($promo['title']) ?>
                                    </p>
                                    <?php if ($promo['description']): ?>
                                        <p
                                            class="text-white/75 text-[9px] md:text-base mt-0.5 md:mt-1 leading-relaxed drop-shadow line-clamp-1 md:line-clamp-2">
                                            <?= htmlspecialchars($promo['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($promotions) > 1): ?>
                        <!-- Prev -->
                        <button onclick="sliderMove(-1)" class="absolute left-1.5 md:left-3 top-1/2 -translate-y-1/2 z-10
                           w-5 h-5 md:w-9 md:h-9 rounded-full bg-black/30 hover:bg-black/60
                           flex items-center justify-center text-white
                           backdrop-blur-sm transition-colors duration-200">
                            <svg class="w-2.5 h-2.5 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Next -->
                        <button onclick="sliderMove(1)" class="absolute right-1.5 md:right-3 top-1/2 -translate-y-1/2 z-10
                           w-5 h-5 md:w-9 md:h-9 rounded-full bg-black/30 hover:bg-black/60
                           flex items-center justify-center text-white
                           backdrop-blur-sm transition-colors duration-200">
                            <svg class="w-2.5 h-2.5 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Dots -->
                        <div class="absolute bottom-1.5 md:bottom-3 left-1/2 -translate-x-1/2 flex gap-1 md:gap-2 z-10">
                            <?php foreach ($promotions as $i => $_): ?>
                                <button onclick="sliderGo(<?= $i ?>)" data-dot="<?= $i ?>" class="w-1 h-1 md:w-2 md:h-2 rounded-full transition-all duration-300
                                   <?= $i === 0 ? 'bg-white scale-125' : 'bg-white/50' ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>

            </div>
        <?php endif; ?>
        <!-- ===== END PROMOTION BANNERS ===== -->

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/shop-by-department.php'; ?>
        </section>

        <div class="mb-4 md:mb-8">
            <h2 class="text-base md:text-2xl font-bold text-gray-900">
              MOST POPULAR <span class="text-amber-500">ITEM</span>
            </h2>
        </div>

        <!-- Product Slider -->
        <?php if (empty($products)): ?>
            <div class="text-center py-20 text-gray-400">
                <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
                <p class="text-lg">No products available yet.</p>
            </div>
        <?php else: ?>

            <div class="relative">

                <!-- Left arrow -->
                <button onclick="productSlide(-1)" class="absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
                       w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
                       flex items-center justify-center text-gray-600
                       hover:bg-gray-50 transition-colors duration-200">
                    <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Right arrow -->
                <button onclick="productSlide(1)" class="absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
                       w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
                       flex items-center justify-center text-gray-600
                       hover:bg-gray-50 transition-colors duration-200">
                    <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Track -->
                <div class="overflow-hidden px-1 p-2">
                    <div class="flex gap-2 md:gap-4 transition-transform duration-500 ease-[cubic-bezier(.4,0,.2,1)]"
                        id="productTrack">
                        <?php foreach ($products as $p): ?>
                            <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>" class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
                              block hover:shadow-lg transition-shadow duration-300 shrink-0
                              w-[calc(50%-4px)] sm:w-[calc(33.333%-6px)] lg:w-[calc(25%-9px)]">

                                <!-- Image -->
                                <div
                                    class="aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
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


        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/discounted-products.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/new-arrivals.php'; ?>
        </section>

        <script>
            (function () {
                const track = document.getElementById('productTrack');
                const cards = track.querySelectorAll('a');
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

                function go(idx) {
                    const visible = getVisible();
                    const max = Math.max(0, cards.length - visible);
                    current = Math.min(Math.max(idx, 0), max);

                    const cardW = cards[0].offsetWidth;
                    const gap = getGap();
                    track.style.transform = `translateX(-${current * (cardW + gap)}px)`;
                }

                window.productSlide = (dir) => go(current + dir);

                let startX = 0;
                track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
                track.addEventListener('touchend', e => {
                    const diff = startX - e.changedTouches[0].clientX;
                    if (Math.abs(diff) > 40) productSlide(diff > 0 ? 1 : -1);
                });

                window.addEventListener('resize', () => go(current));
            })();
        </script>

    </div>

    <?php if (!empty($promotions) && count($promotions) > 1): ?>
        <script>
            (function () {
                let current = 0;
                const total = <?= count($promotions) ?>;
                const track = document.getElementById('promoTrack');
                const dots = document.querySelectorAll('[data-dot]');
                const desc = document.getElementById('promoDesc');
                const descriptions = <?= json_encode(array_column($promotions, 'description')) ?>;
                let timer = null;

                function go(idx) {
                    current = (idx + total) % total;
                    track.style.transform = `translateX(-${current * 100}%)`;
                    track.style.transition = 'transform 500ms cubic-bezier(.4,0,.2,1)';

                    dots.forEach((d, i) => {
                        d.classList.toggle('bg-white', i === current);
                        d.classList.toggle('scale-125', i === current);
                        d.classList.toggle('bg-white/50', i !== current);
                    });

                    // update description
                    if (desc) {
                        desc.style.opacity = '0';
                        setTimeout(() => {
                            desc.textContent = descriptions[current] || '';
                            desc.style.opacity = '1';
                        }, 200);
                    }
                }

                function startAuto() { timer = setInterval(() => go(current + 1), 4500); }
                function resetAuto() { clearInterval(timer); startAuto(); }

                window.sliderMove = (dir) => { go(current + dir); resetAuto(); };
                window.sliderGo = (idx) => { go(idx); resetAuto(); };

                startAuto();

                const slider = document.getElementById('promoSlider');
                let startX = 0;
                slider.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
                slider.addEventListener('touchend', e => {
                    const diff = startX - e.changedTouches[0].clientX;
                    if (Math.abs(diff) > 40) { go(current + (diff > 0 ? 1 : -1)); resetAuto(); }
                });
            })();
        </script>
    <?php endif; ?>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

</body>

</html>