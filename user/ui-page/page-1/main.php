<?php
// page.php

include ROOT_PATH . '/network/connect.php';

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

<body class="">

    <div class="max-w-7xl mx-auto px-6 py-5">

        <!-- ===== PROMOTION BANNERS ===== -->
        <?php if (!empty($promotions)): ?>
            <div class="mb-6 md:mb-10">

                <div class="relative w-full rounded-lg overflow-hidden shadow-sm aspect-[16/5]"
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
                                bg-gradient-to-t from-black/30 via-black/20 to-transparent
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

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/most-popularproduct.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/promotion-website.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/discounted-products.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/new-arrivals.php'; ?>
        </section>

        <section>
            <div class="max-w-6xl mx-auto px-4 py-8 md:py-12">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-4 text-center">

                    <!-- Quality Products -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-12 h-12 md:w-14 md:h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-truck text-gray-700 text-lg md:text-xl"></i>
                        </div>
                        <h3 class="text-xs md:text-sm font-bold text-gray-900 mb-1">Quality products</h3>
                        <p class="text-[10px] md:text-xs text-gray-400 leading-relaxed">
                            All products are carefully inspected to ensure top-notch quality.
                        </p>
                    </div>

                    <!-- Support Services -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-12 h-12 md:w-14 md:h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-headset text-gray-700 text-lg md:text-xl"></i>
                        </div>
                        <h3 class="text-xs md:text-sm font-bold text-gray-900 mb-1">Support Services</h3>
                        <p class="text-[10px] md:text-xs text-gray-400 leading-relaxed">
                            Monday - Friday 8:00 AM - 5:00 PM · Saturday 8:00 AM - 12:00 PM
                        </p>
                    </div>

                    <!-- Secured Payment -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-12 h-12 md:w-14 md:h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-dollar-sign text-gray-700 text-lg md:text-xl"></i>
                        </div>
                        <h3 class="text-xs md:text-sm font-bold text-gray-900 mb-1">Secured Payment</h3>
                        <p class="text-[10px] md:text-xs text-gray-400 leading-relaxed">
                            Safe and encrypted payment options for your peace of mind.
                        </p>
                    </div>

                    <!-- Exclusive Deals -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-12 h-12 md:w-14 md:h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-tag text-gray-700 text-lg md:text-xl"></i>
                        </div>
                        <h3 class="text-xs md:text-sm font-bold text-gray-900 mb-1">Exclusive Deals & Discounts</h3>
                        <p class="text-[10px] md:text-xs text-gray-400 leading-relaxed">
                            Get special offers and discounts when you shop with us.
                        </p>
                    </div>

                </div>
            </div>
        </section>

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