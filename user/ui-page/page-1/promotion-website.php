<?php
// /user/ui-page/page-1/promotion-website.php

$promoSites = [];
$psResult = $conn->query("
    SELECT id, name, website_link, image
    FROM noblepromotionwebsite
    WHERE is_active = 1
    ORDER BY created_at DESC
");
while ($row = $psResult->fetch_assoc())
    $promoSites[] = $row;
?>

<?php if (!empty($promoSites)): ?>
    <div class="mb-6 space-y-3 md:space-y-4 py-5">
        <?php foreach ($promoSites as $site): ?>
            <a href="<?= htmlspecialchars($site['website_link']) ?>" target="_blank" rel="noopener"
                class="relative w-full rounded-lg overflow-hidden shadow-sm bg-slate-800
                   aspect-[16/5] block group">

                <?php if (!empty($site['image'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/promotionwebsite/<?= htmlspecialchars($site['image']) ?>"
                        alt="<?= htmlspecialchars($site['name']) ?>"
                        class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500">
                <?php endif; ?>

                <!-- Gradient overlay + text -->
                <div class="absolute inset-0 flex flex-col justify-end
                        bg-gradient-to-t from-black/70 via-black/20 to-transparent
                        px-4 md:px-14 pb-3 md:pb-8">
                    <p class="text-white font-bold text-[11px] md:text-3xl leading-snug drop-shadow-lg line-clamp-1">
                        <?= htmlspecialchars($site['name']) ?>
                    </p>
                </div>

            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>