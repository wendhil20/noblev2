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

            <!-- Left arrow -->
            <button id="deptPrev" onclick="deptSlide(-1)" class="absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 z-10
                   w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
                   flex items-center justify-center text-gray-600
                   hover:bg-gray-50 transition-colors duration-200">
                <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Right arrow -->
            <button id="deptNext" onclick="deptSlide(1)" class="absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 z-10
                   w-7 h-7 md:w-9 md:h-9 rounded-full bg-white border border-gray-200 shadow
                   flex items-center justify-center text-gray-600
                   hover:bg-gray-50 transition-colors duration-200">
                <svg class="w-3 h-3 md:w-4 md:h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <!-- Track -->
            <div class="overflow-hidden px-1 p-2">
                <div class="flex gap-4 md:gap-8 transition-transform duration-500 ease-[cubic-bezier(.4,0,.2,1)]"
                    id="deptTrack">
                    <?php foreach ($departments as $dept): ?>
                        <a href="<?= BASE_URL ?>/productcategory?id=<?= $dept['id'] ?>"
                           class="flex flex-col items-center gap-2 shrink-0
                                  w-[calc(25%-12px)] sm:w-[calc(16.666%-14px)] lg:w-28 group">

                            <div class="w-16 h-16 md:w-24 md:h-24 rounded-full
                                        flex items-center justify-center overflow-hidden bg-white
                                        group-hover:border-amber-500 group-hover:shadow-md transition-all duration-200 p-2 md:p-3">
                                <?php if (!empty($dept['image'])): ?>
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($dept['image']) ?>"
                                         alt="<?= htmlspecialchars($dept['name']) ?>"
                                         class="w-full h-full object-contain" >
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <i class="fa-solid fa-layer-group text-xl md:text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <span class="text-[10px] md:text-xs font-semibold text-gray-800 text-center uppercase tracking-wide leading-tight">
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
        let current = 0;

        function getVisible() {
            if (!cards.length) return 1;
            const containerWidth = track.parentElement.offsetWidth;
            const cardW = cards[0].offsetWidth;
            const gap = getGap();
            if (!containerWidth || !cardW) return 1;
            const visible = Math.floor((containerWidth + gap) / (cardW + gap));
            return Math.max(1, Math.min(visible, cards.length));
        }

        function getGap() {
            return window.innerWidth >= 768 ? 32 : 16; // gap-8 = 32px, gap-4 = 16px
        }

        function updateArrows(max) {
            if (!prevBtn || !nextBtn) return;
            // Itago ang mga arrow kapag hindi na kailangan i-slide (fit na lahat sa isang view)
            if (max <= 0) {
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
                return;
            }
            // Itago kapag nasa unang slide na (wala nang dulo sa kaliwa)
            prevBtn.style.display = current <= 0 ? 'none' : 'flex';
            // Itago kapag nasa huling slide na (wala nang dulo sa kanan)
            nextBtn.style.display = current >= max ? 'none' : 'flex';
        }

        function go(idx) {
            if (!cards.length) return;
            const cardWCheck = cards[0].offsetWidth;
            if (!cardWCheck) return; // layout not ready yet, skip silently

            const visible = getVisible();
            const max = Math.max(0, cards.length - visible);
            current = Math.min(Math.max(idx, 0), max);

            const cardW = cards[0].offsetWidth;
            const gap = getGap();
            track.style.transform = `translateX(-${current * (cardW + gap)}px)`;
            updateArrows(max);
        }

        window.deptSlide = (dir) => go(current + dir);

        let startX = 0;
        track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
        track.addEventListener('touchend', e => {
            const diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) deptSlide(diff > 0 ? 1 : -1);
        });

        window.addEventListener('resize', () => go(current));

        go(0); // initial check sa arrows pagka-load

        // Re-check pagkatapos ganap na ma-load ang page (kasama images),
        // kasi pwedeng magbago ang laki ng cards pagkatapos mag-load ng images
        window.addEventListener('load', () => go(current));
    })();
</script>