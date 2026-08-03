<?php
// shop-by-department.php
// Expects $conn from the including page (page.php)

$departments = [];
$deptResult = $conn->query("
    SELECT id, name, image
    FROM noblecategory
    ORDER BY name ASC
");
while ($row = $deptResult->fetch_assoc())
    $departments[] = $row;
?>

<?php if (!empty($departments)): ?>
    <div class="py-2 md:py-3">

        <!-- Heading with decorative lines -->
        <div class="flex items-center justify-center gap-4 mb-6 md:mb-10">
            <span class="h-px flex-1 max-w-[120px] md:max-w-[220px] bg-gradient-to-r from-transparent to-amber-300"></span>
            <h2 class="text-lg md:text-2xl font-bold text-gray-900 whitespace-nowrap">
                Shop by Department
            </h2>
            <span class="h-px flex-1 max-w-[120px] md:max-w-[220px] bg-gradient-to-l from-transparent to-amber-300"></span>
        </div>

        <!-- Department slider -->
        <div class="relative">

            <!-- Left arrow (hidden on touch/mobile, native scroll handles it there) -->
            <button id="deptPrev" onclick="deptSlide(-1)" class="hidden md:flex absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
                   w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
                   items-center justify-center text-gray-600
                   hover:bg-gray-50 transition-colors duration-200">
                <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Right arrow (hidden on touch/mobile) -->
            <button id="deptNext" onclick="deptSlide(1)" class="hidden md:flex absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
                   w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
                   items-center justify-center text-gray-600
                   hover:bg-gray-50 transition-colors duration-200">
                <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <!-- Track: native horizontal scroll + snap. Fast/native feel on touch, JS-assisted arrows on desktop -->
            <div class="overflow-hidden px-1 p-2">
                <div id="deptTrack" class="flex gap-4 md:gap-8 overflow-x-auto scroll-smooth snap-x snap-mandatory
                            [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <?php foreach ($departments as $dept): ?>
                        <a href="<?= BASE_URL ?>/productcategory?id=<?= $dept['id'] ?>" class="flex flex-col items-center gap-2 shrink-0 snap-start
                                  w-[calc(25%-12px)] sm:w-[calc(16.666%-14px)] lg:w-28 group">

                            <div
                                class="w-16 h-16 md:w-24 md:h-24 rounded-full
                                        flex items-center justify-center overflow-hidden bg-white
                                        group-hover:border-amber-500 group-hover:shadow-md transition-all duration-200 p-2 md:p-3 shrink-0">
                                <?php if (!empty($dept['image'])): ?>
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($dept['image']) ?>"
                                        alt="<?= htmlspecialchars($dept['name']) ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <i class="fa-solid fa-layer-group text-xl md:text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <span class="w-full block px-0.5 text-[10px] md:text-xs font-semibold text-gray-800
                                         text-center uppercase tracking-wide leading-tight
                                         break-words line-clamp-2 min-h-[2em] md:min-h-[2.2em]"
                                title="<?= htmlspecialchars($dept['name']) ?>">
                                <?= htmlspecialchars($dept['name']) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
<?php endif; ?>

<script>
    (function () {
        const track = document.getElementById('deptTrack');
        if (!track) return;
        const cards = track.querySelectorAll('a');
        const prevBtn = document.getElementById('deptPrev');
        const nextBtn = document.getElementById('deptNext');

        function getGap() {
            return window.innerWidth >= 768 ? 32 : 16; // gap-8 = 32px, gap-4 = 16px
        }

        // Desktop arrow click: scroll by roughly one "page" of visible cards, native smooth scroll
        function deptSlide(dir) {
            if (!cards.length) return;
            const cardW = cards[0].offsetWidth;
            const gap = getGap();
            const containerWidth = track.offsetWidth;
            const visible = Math.max(1, Math.floor((containerWidth + gap) / (cardW + gap)));
            track.scrollBy({ left: dir * visible * (cardW + gap), behavior: 'smooth' });
        }
        window.deptSlide = deptSlide;

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
</script>