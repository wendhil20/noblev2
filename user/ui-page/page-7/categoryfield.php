<?php
// user/ui-page/page-7/categoryfield.php
include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';

// ── 1. Get category ID from URL ─────────────────────────────────────────────
$categoryId = intval($_GET['id'] ?? 0);
$activeSubId = intval($_GET['sub'] ?? 0); // optional subcategory filter

if (!$categoryId) {
    header('Location: ' . BASE_URL);
    exit;
}

// ── 2. Fetch the category itself ────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, name, image FROM noblecategory WHERE id = ?");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    header('Location: ' . BASE_URL);
    exit;
}

// ── 3. Fetch subcategories under this category ──────────────────────────────
$subcategories = [];
$stmt = $conn->prepare("SELECT id, name, image FROM noblesubcategory WHERE category_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$subResult = $stmt->get_result();
while ($row = $subResult->fetch_assoc())
    $subcategories[] = $row;
$stmt->close();

// ── 4. Fetch products: filtered by subcategory if selected, else whole category ─
$products = [];

if ($activeSubId) {
    $stmt = $conn->prepare("
        SELECT
            p.id, p.name, p.imageproduct, p.description,
            MIN(v.pricesize) AS min_price,
            MAX(v.pricesize) AS max_price
        FROM nobleproduct p
        INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id
        LEFT JOIN nobleproductcolor c ON c.product_id = p.id
        LEFT JOIN nobleproductvariant v ON v.color_id = c.id
        WHERE ps.subcategory_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param("i", $activeSubId);
} else {
    $stmt = $conn->prepare("
        SELECT
            p.id, p.name, p.imageproduct, p.description,
            MIN(v.pricesize) AS min_price,
            MAX(v.pricesize) AS max_price
        FROM nobleproduct p
        INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id
        INNER JOIN noblesubcategory s ON s.id = ps.subcategory_id
        LEFT JOIN nobleproductcolor c ON c.product_id = p.id
        LEFT JOIN nobleproductvariant v ON v.color_id = c.id
        WHERE s.category_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param("i", $categoryId);
}
$stmt->execute();
$prodResult = $stmt->get_result();
while ($row = $prodResult->fetch_assoc())
    $products[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($category['name']) ?> - NobleHome</title>
  <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

<?php include ROOT_PATH . '/user/navigation/top.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8 flex-1 w-full">

    <!-- Category Header -->
    <div class="flex items-center gap-4 mb-6">
        <div class="w-14 h-14 md:w-16 md:h-16 rounded-full border-2 border-amber-400 flex items-center justify-center overflow-hidden bg-white p-2 shrink-0">
            <?php if (!empty($category['image'])): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($category['image']) ?>"
                     alt="<?= htmlspecialchars($category['name']) ?>" class="w-full h-full object-contain">
            <?php else: ?>
                <i class="fa-solid fa-layer-group text-xl text-gray-300"></i>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">Category</p>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900"><?= htmlspecialchars($category['name']) ?></h1>
        </div>
    </div>

    <!-- Subcategory boxes with image -->
    <?php if (!empty($subcategories)): ?>
        <div class="flex flex-wrap gap-3 mb-8">

            <!-- "All" box -->
            <a href="<?= BASE_URL ?>/productcategory?id=<?= $categoryId ?>"
               class="flex flex-col items-center gap-2 w-20 md:w-24 group">
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-xl border-2 flex items-center justify-center
                            overflow-hidden bg-white transition-all duration-200
                            <?= !$activeSubId
                                  ? 'border-amber-500 shadow-md'
                                  : 'border-gray-200 group-hover:border-amber-300' ?>">
                    <i class="fa-solid fa-grip text-xl md:text-2xl <?= !$activeSubId ? 'text-amber-500' : 'text-gray-300' ?>"></i>
                </div>
                <span class="text-[11px] md:text-xs font-semibold text-center uppercase tracking-wide leading-tight
                             <?= !$activeSubId ? 'text-amber-600' : 'text-gray-600' ?>">
                    All
                </span>
            </a>

            <!-- Per subcategory box -->
            <?php foreach ($subcategories as $sub): ?>
                <?php $isActive = $activeSubId === (int)$sub['id']; ?>
                <a href="<?= BASE_URL ?>/productcategory?id=<?= $categoryId ?>&sub=<?= $sub['id'] ?>"
                   class="flex flex-col items-center gap-2 w-20 md:w-24 group">
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-xl border-2 flex items-center justify-center
                                overflow-hidden bg-white p-2 transition-all duration-200
                                <?= $isActive
                                      ? 'border-amber-500 shadow-md'
                                      : 'border-gray-200 group-hover:border-amber-300' ?>">
                        <?php if (!empty($sub['image'])): ?>
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($sub['image']) ?>"
                                 alt="<?= htmlspecialchars($sub['name']) ?>"
                                 class="w-full h-full object-contain">
                        <?php else: ?>
                            <i class="fa-solid fa-image text-xl md:text-2xl <?= $isActive ? 'text-amber-500' : 'text-gray-300' ?>"></i>
                        <?php endif; ?>
                    </div>
                    <span class="text-[11px] md:text-xs font-semibold text-center uppercase tracking-wide leading-tight
                                 <?= $isActive ? 'text-amber-600' : 'text-gray-600' ?>">
                        <?= htmlspecialchars($sub['name']) ?>
                    </span>
                </a>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div class="text-center py-20 text-gray-400">
            <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
            <p class="text-lg">No products found in this <?= $activeSubId ? 'subcategory' : 'category' ?> yet.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
            <?php foreach ($products as $p): ?>
                <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>"
                   class="bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
                          block hover:shadow-lg transition-shadow duration-300">

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

                    <div class="p-2 md:p-3">
                        <h3 class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide leading-snug mb-0.5 md:mb-1 line-clamp-1">
                            <?= htmlspecialchars($p['name']) ?>
                        </h3>

                        <?php if (!empty($p['description'])): ?>
                            <p class="text-xs text-gray-400 line-clamp-1 md:line-clamp-2 mb-1 md:mb-2 hidden sm:block">
                                <?= htmlspecialchars($p['description']) ?>
                            </p>
                        <?php endif; ?>

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
    <?php endif; ?>

  </div>

  <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>

</html>