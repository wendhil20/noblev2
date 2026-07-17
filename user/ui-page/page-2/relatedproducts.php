<?php
// relatedproducts.php

$relatedProducts = [];
$usedFallback = false; // true kapag wala talagang match sa category, random products na lang ang lumabas

if (!empty($product['category'])) {
    $relStmt = $conn->prepare("
        SELECT p.id, p.name, p.category, p.unit, p.imageproduct, p.description,
               MIN(v.pricesize) as min_price,
               MAX(v.pricesize) as max_price,
               AVG(r.rating) as avg_rating,
               COUNT(DISTINCT r.id) as review_count,
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
        WHERE p.category = ? AND p.id != ?
        GROUP BY p.id
        ORDER BY RAND()
        LIMIT 8
    ");
    $relStmt->bind_param("si", $product['category'], $productId);
    $relStmt->execute();
    $relResult = $relStmt->get_result();
    while ($rp = $relResult->fetch_assoc()) {
        $relatedProducts[] = $rp;
    }
    $relStmt->close();
}

// Fallback: kung wala talagang laman ang category o wala pang ibang product doon,
// kumuha na lang ng random products (para hindi laging blangko ang section)
if (empty($relatedProducts)) {
    $usedFallback = true;

    $fallbackStmt = $conn->prepare("
        SELECT p.id, p.name, p.category, p.unit, p.imageproduct, p.description,
               MIN(v.pricesize) as min_price,
               MAX(v.pricesize) as max_price,
               AVG(r.rating) as avg_rating,
               COUNT(DISTINCT r.id) as review_count,
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
        WHERE p.id != ?
        GROUP BY p.id
        ORDER BY RAND()
        LIMIT 8
    ");
    $fallbackStmt->bind_param("i", $productId);
    $fallbackStmt->execute();
    $fallbackResult = $fallbackStmt->get_result();
    while ($rp = $fallbackResult->fetch_assoc()) {
        $relatedProducts[] = $rp;
    }
    $fallbackStmt->close();
}


if (!function_exists('formatSoldCount')) {
    function formatSoldCount($n)
    {
        $n = intval($n);
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
        }
        return number_format($n);
    }
}

// Title: "Related Products" kapag totoong same-category match, "You may also like" kapag fallback/random na lang
$relatedSectionTitle = $usedFallback ? 'You may also like' : 'Related Products';
?>

<?php if (!empty($relatedProducts)): ?>
    <!-- ════════ Related Products ════════ -->
    <div class="mt-6 md:mt-8">
        <h2 class="text-sm md:text-lg font-bold text-gray-900 mb-3 md:mb-4">
            <i class="fa-solid fa-layer-group text-amber-400 mr-1.5"></i> <?= htmlspecialchars($relatedSectionTitle) ?>
        </h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 md:gap-4">
            <?php foreach ($relatedProducts as $rp): ?>
                <?php
                $rpMin = floatval($rp['min_price'] ?? 0);
                $rpMax = floatval($rp['max_price'] ?? 0);
                $rpAvgRating = round(floatval($rp['avg_rating'] ?? 0), 1);
                $rpReviewCount = intval($rp['review_count'] ?? 0);
                $rpSold = intval($rp['total_sold'] ?? 0);
                ?>
                <a href="<?= BASE_URL ?>/mainproductview?id=<?= $rp['id'] ?>"
                    class="group bg-white rounded-xl md:rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md hover:border-amber-200 transition">

                    <div class="bg-gray-50 flex items-center justify-center p-4 md:p-6 aspect-square">
                        <?php if (!empty($rp['imageproduct'])): ?>
                            <img src="<?= $uploadUrl . htmlspecialchars($rp['imageproduct']) ?>"
                                alt="<?= htmlspecialchars($rp['name']) ?>"
                                class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-200"
                                loading="lazy">
                        <?php else: ?>
                            <i class="fa-solid fa-image text-gray-300 text-2xl md:text-3xl"></i>
                        <?php endif; ?>
                    </div>

                    <div class="p-3 md:p-4">
                        <?php if (!empty($rp['category'])): ?>
                            <span class="text-[9px] md:text-[10px] font-semibold text-amber-500 uppercase tracking-widest">
                                <?= htmlspecialchars($rp['category']) ?>
                            </span>
                        <?php endif; ?>

                        <p class="text-xs md:text-sm font-semibold text-gray-800 mt-0.5 mb-1 line-clamp-2 group-hover:text-amber-600 transition">
                            <?= htmlspecialchars($rp['name']) ?>
                            <?php if (!empty($rp['unit'])): ?>
                                <span class="text-gray-400 font-normal">· <?= htmlspecialchars($rp['unit']) ?></span>
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($rp['description'])): ?>
                            <p class="text-[10px] md:text-xs text-gray-400 line-clamp-1 md:line-clamp-2 mb-1">
                                <?= htmlspecialchars($rp['description']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($rpMin > 0 || $rpMax > 0): ?>
                            <p class="text-xs md:text-sm font-bold text-gray-900 mb-1">
                                ₱<?= number_format($rpMin, 2) ?>
                                <?= $rpMin !== $rpMax ? ' – ₱' . number_format($rpMax, 2) : '' ?>
                            </p>
                        <?php else: ?>
                            <p class="text-[11px] md:text-xs text-gray-400 italic mb-1">Price not set</p>
                        <?php endif; ?>

                        <!-- Rating + Sold -->
                        <div class="flex items-center gap-1.5 text-[10px] md:text-xs text-gray-400">
                            <?php if ($rpReviewCount > 0): ?>
                                <span class="flex items-center gap-0.5 text-amber-500 font-medium">
                                    <i class="fa-solid fa-star text-[9px] md:text-[10px]"></i>
                                    <?= number_format($rpAvgRating, 1) ?>
                                </span>
                                <span class="text-gray-300">·</span>
                            <?php endif; ?>
                            <span><?= formatSoldCount($rpSold) ?> sold</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>