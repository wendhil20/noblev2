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

        <!-- Department circles -->
        <div class="flex flex-wrap justify-center gap-x-4 gap-y-6 md:gap-x-8">
            <?php foreach ($departments as $dept): ?>
                <a href="<?= BASE_URL ?>/productcategory?id=<?= $dept['id'] ?>"
                   class="flex flex-col items-center gap-2 w-20 md:w-28 group">

                    <div class="w-16 h-16 md:w-24 md:h-24 rounded-full
                                flex items-center justify-center overflow-hidden bg-white
                                group-hover:border-amber-500 group-hover:shadow-md transition-all duration-200 p-2 md:p-3">
                        <?php if (!empty($dept['image'])): ?>
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($dept['image']) ?>"
                                 alt="<?= htmlspecialchars($dept['name']) ?>"
                                 class="w-full h-full object-contain">
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
<?php endif; ?>