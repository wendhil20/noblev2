<?php
// user/ui-page/page-7/categoryfield.php
include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';

// ── 1. Get category ID from URL ─────────────────────────────────────────────
$categoryId  = intval($_GET['id'] ?? 0);
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

// ── 4. Filter & sort inputs ──────────────────────────────────────────────────
$sortOptions = [
    'newest'     => 'p.created_at DESC',
    'price_asc'  => 'min_price ASC',
    'price_desc' => 'max_price DESC',
    'name_asc'   => 'p.name ASC',
];
$sort = $_GET['sort'] ?? 'newest';
if (!array_key_exists($sort, $sortOptions)) $sort = 'newest';

$minPrice = (isset($_GET['minp']) && $_GET['minp'] !== '') ? floatval($_GET['minp']) : null;
$maxPrice = (isset($_GET['maxp']) && $_GET['maxp'] !== '') ? floatval($_GET['maxp']) : null;

$hasActiveFilters = $minPrice !== null || $maxPrice !== null || $sort !== 'newest';

// ── 5. Fetch products: filtered by subcategory, price range, and sorted ─────
$products = [];

$sql = "SELECT
            p.id, p.name, p.imageproduct, p.description, p.created_at,
            MIN(v.pricesize) AS min_price,
            MAX(v.pricesize) AS max_price
        FROM nobleproduct p
        INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id";

if (!$activeSubId) {
    $sql .= " INNER JOIN noblesubcategory s ON s.id = ps.subcategory_id";
}

$sql .= " LEFT JOIN nobleproductcolor c ON c.product_id = p.id
          LEFT JOIN nobleproductvariant v ON v.color_id = c.id
          WHERE " . ($activeSubId ? "ps.subcategory_id = ?" : "s.category_id = ?");

$types  = "i";
$params = [$activeSubId ?: $categoryId];

$sql .= " GROUP BY p.id";

$having = [];
if ($minPrice !== null) { $having[] = "MAX(v.pricesize) >= ?"; $types .= "d"; $params[] = $minPrice; }
if ($maxPrice !== null) { $having[] = "MIN(v.pricesize) <= ?"; $types .= "d"; $params[] = $maxPrice; }
if ($having) $sql .= " HAVING " . implode(" AND ", $having);

$sql .= " ORDER BY " . $sortOptions[$sort];

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$prodResult = $stmt->get_result();
while ($row = $prodResult->fetch_assoc())
    $products[] = $row;
$stmt->close();

// ── Helper: rebuild query string while overriding specific params ──────────
function buildUrl(array $overrides = []): string {
    $base = ['id' => $_GET['id'] ?? '', 'sub' => $_GET['sub'] ?? '', 'sort' => $_GET['sort'] ?? '', 'minp' => $_GET['minp'] ?? '', 'maxp' => $_GET['maxp'] ?? ''];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return BASE_URL . '/productcategory?' . http_build_query($merged);
}

$sortLabels = [
    'newest'     => 'Newest',
    'price_asc'  => 'Price: Low to High',
    'price_desc' => 'Price: High to Low',
    'name_asc'   => 'Name: A-Z',
];
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

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-5">
        <a href="<?= BASE_URL ?>" class="hover:text-amber-600 transition-colors">Home</a>
        <i class="fa-solid fa-chevron-right text-[9px]"></i>
        <span class="text-gray-600 font-medium"><?= htmlspecialchars($category['name']) ?></span>
        <?php if ($activeSubId): foreach ($subcategories as $sub): if ((int)$sub['id'] === $activeSubId): ?>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <span class="text-amber-600 font-medium"><?= htmlspecialchars($sub['name']) ?></span>
        <?php endif; endforeach; endif; ?>
    </nav>

    <!-- Category Header -->
    <div class="flex items-center gap-4 mb-7">
        <div class="w-14 h-14 md:w-16 md:h-16 rounded-full border-2 border-amber-400 flex items-center justify-center overflow-hidden bg-white p-2 shrink-0 shadow-sm">
            <?php if (!empty($category['image'])): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($category['image']) ?>"
                     alt="<?= htmlspecialchars($category['name']) ?>" class="w-full h-full object-contain" loading="lazy">
            <?php else: ?>
                <i class="fa-solid fa-layer-group text-xl text-gray-300"></i>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-[11px] text-amber-600 font-semibold uppercase tracking-widest">Category</p>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight"><?= htmlspecialchars($category['name']) ?></h1>
        </div>
    </div>

    <!-- Subcategory boxes with image -->
    <?php if (!empty($subcategories)): ?>
        <div class="flex flex-wrap gap-3 mb-8">

            <!-- "All" box -->
            <a href="<?= buildUrl(['sub' => '']) ?>"
               class="flex flex-col items-center gap-2 w-20 md:w-24 group">
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-xl border-2 flex items-center justify-center
                            overflow-hidden bg-white transition-all duration-200
                            <?= !$activeSubId
                                  ? 'border-amber-500 shadow-md ring-2 ring-amber-100'
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
                <a href="<?= buildUrl(['sub' => $sub['id']]) ?>"
                   class="flex flex-col items-center gap-2 w-20 md:w-24 group">
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-xl border-2 flex items-center justify-center
                                overflow-hidden bg-white p-2 transition-all duration-200
                                <?= $isActive
                                      ? 'border-amber-500 shadow-md ring-2 ring-amber-100'
                                      : 'border-gray-200 group-hover:border-amber-300' ?>">
                        <?php if (!empty($sub['image'])): ?>
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($sub['image']) ?>"
                                 alt="<?= htmlspecialchars($sub['name']) ?>"
                                 class="w-full h-full object-contain" loading="lazy">
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

    <!-- Filter / Sort Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pb-4 border-b border-gray-200">
        <p class="text-sm text-gray-500">
            <?= count($products) === 1 ? 'Product' : 'Products' ?>
            <?php if ($activeSubId): foreach ($subcategories as $sub): if ((int)$sub['id'] === $activeSubId): ?>
                in <span class="font-medium text-gray-700"> <?= htmlspecialchars($sub['name']) ?></span>
            <?php endif; endforeach; endif; ?>
        </p>

        <div class="flex items-center gap-2">
            <!-- Price filter -->
            <div class="relative">
                <button type="button" id="priceFilterBtn"
                        class="flex items-center gap-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:border-amber-400 transition-colors">
                    <i class="fa-solid fa-sliders text-xs text-gray-400"></i>
                    Price
                    <?php if ($minPrice !== null || $maxPrice !== null): ?>
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    <?php endif; ?>
                </button>

                <div id="priceFilterPanel"
                     class="hidden absolute right-0 mt-2 w-64 bg-white border border-gray-100 rounded-xl shadow-lg p-4 z-20">
                    <form method="GET" action="<?= BASE_URL ?>/productcategory" class="space-y-3">
                        <input type="hidden" name="id" value="<?= $categoryId ?>">
                        <?php if ($activeSubId): ?><input type="hidden" name="sub" value="<?= $activeSubId ?>"><?php endif; ?>
                        <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>

                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Price range</p>
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span>
                                <input type="number" name="minp" min="0" placeholder="Min"
                                       value="<?= $minPrice !== null ? htmlspecialchars($minPrice) : '' ?>"
                                       class="w-full pl-6 pr-2 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100">
                            </div>
                            <span class="text-gray-300 text-sm">–</span>
                            <div class="relative flex-1">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span>
                                <input type="number" name="maxp" min="0" placeholder="Max"
                                       value="<?= $maxPrice !== null ? htmlspecialchars($maxPrice) : '' ?>"
                                       class="w-full pl-6 pr-2 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100">
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <button type="submit"
                                    class="flex-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold py-2 rounded-md transition-colors">
                                Apply
                            </button>
                            <a href="<?= buildUrl(['minp' => '', 'maxp' => '']) ?>"
                               class="text-xs font-medium text-gray-400 hover:text-gray-600 px-2">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sort -->
            <form method="GET" action="<?= BASE_URL ?>/productcategory" class="relative">
                <input type="hidden" name="id" value="<?= $categoryId ?>">
                <?php if ($activeSubId): ?><input type="hidden" name="sub" value="<?= $activeSubId ?>"><?php endif; ?>
                <?php if ($minPrice !== null): ?><input type="hidden" name="minp" value="<?= htmlspecialchars($minPrice) ?>"><?php endif; ?>
                <?php if ($maxPrice !== null): ?><input type="hidden" name="maxp" value="<?= htmlspecialchars($maxPrice) ?>"><?php endif; ?>
                <select name="sort" onchange="this.form.submit()"
                        class="appearance-none text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg pl-3.5 pr-8 py-2 hover:border-amber-400 transition-colors cursor-pointer focus:outline-none focus:border-amber-400">
                    <?php foreach ($sortLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
            </form>
        </div>
    </div>

    <!-- Active filter chips -->
    <?php if ($hasActiveFilters): ?>
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <?php if ($minPrice !== null || $maxPrice !== null): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-full pl-3 pr-1.5 py-1">
                    ₱<?= number_format($minPrice ?? 0, 0) ?> – <?= $maxPrice !== null ? '₱' . number_format($maxPrice, 0) : 'and up' ?>
                    <a href="<?= buildUrl(['minp' => '', 'maxp' => '']) ?>" class="w-4 h-4 flex items-center justify-center rounded-full hover:bg-amber-200 transition-colors">
                        <i class="fa-solid fa-xmark text-[9px]"></i>
                    </a>
                </span>
            <?php endif; ?>
            <?php if ($sort !== 'newest'): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-full pl-3 pr-1.5 py-1">
                    <?= $sortLabels[$sort] ?>
                    <a href="<?= buildUrl(['sort' => '']) ?>" class="w-4 h-4 flex items-center justify-center rounded-full hover:bg-amber-200 transition-colors">
                        <i class="fa-solid fa-xmark text-[9px]"></i>
                    </a>
                </span>
            <?php endif; ?>
            <a href="<?= buildUrl(['sort' => '', 'minp' => '', 'maxp' => '']) ?>"
               class="text-xs font-medium text-gray-400 hover:text-gray-600 underline underline-offset-2">
                Clear all
            </a>
        </div>
    <?php endif; ?>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div class="text-center py-20 text-gray-400">
            <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
            <p class="text-lg text-gray-500 font-medium">No products found</p>
            <p class="text-sm mt-1">Try adjusting your filters or select "All".</p>
            <?php if ($hasActiveFilters || $activeSubId): ?>
                <a href="<?= buildUrl(['sub' => '', 'sort' => '', 'minp' => '', 'maxp' => '']) ?>"
                   class="inline-block mt-4 text-sm font-semibold text-amber-600 hover:text-amber-700">
                    Clear filters <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
            <?php foreach ($products as $p): ?>
                <?php $isNew = !empty($p['created_at']) && (strtotime($p['created_at']) >= strtotime('-14 days')); ?>
                <a href="<?= BASE_URL ?>/mainproductview?id=<?= $p['id'] ?>"
                   class="group bg-white rounded-xl md:rounded-2xl overflow-hidden border border-gray-100
                          block hover:shadow-lg hover:border-amber-200 hover:-translate-y-0.5 transition-all duration-300 relative">

                    <?php if ($isNew): ?>
                        <span class="absolute top-2 left-2 z-10 bg-amber-500 text-white text-[9px] md:text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full shadow-sm">
                            New
                        </span>
                    <?php endif; ?>

                    <div class="aspect-square overflow-hidden bg-gray-50 flex items-center justify-center p-2 md:p-4">
                        <?php if (!empty($p['imageproduct'])): ?>
                            <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <i class="fa-solid fa-image text-3xl md:text-5xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-2.5 md:p-3.5">
                        <h3 class="font-bold text-gray-900 text-xs md:text-sm uppercase tracking-wide leading-snug mb-0.5 md:mb-1 line-clamp-1">
                            <?= htmlspecialchars($p['name']) ?>
                        </h3>

                        <?php if (!empty($p['description'])): ?>
                            <p class="text-xs text-gray-400 line-clamp-1 md:line-clamp-2 mb-1 md:mb-2 hidden sm:block">
                                <?= htmlspecialchars($p['description']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="mt-1 md:mt-2 flex items-baseline gap-0.5">
                            <?php
                            $min = floatval($p['min_price'] ?? 0);
                            $max = floatval($p['max_price'] ?? 0);
                            ?>
                            <?php if ($min > 0 || $max > 0): ?>
                                <span class="text-[10px] md:text-sm font-semibold text-gray-900">
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

  <script>
    (function () {
        const btn   = document.getElementById('priceFilterBtn');
        const panel = document.getElementById('priceFilterPanel');
        if (!btn || !panel) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.classList.toggle('hidden');
        });

        document.addEventListener('click', function (e) {
            if (!panel.contains(e.target) && e.target !== btn) {
                panel.classList.add('hidden');
            }
        });
    })();
  </script>

</body>

</html>