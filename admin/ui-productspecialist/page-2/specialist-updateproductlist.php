<?php
// specialist-updateproductlist.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// ── Load all products with color + variant counts ──────────────────────────
$currentUserId = $_SESSION['account_id'] ?? 0;

$products = [];
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.category,
        p.description,
        creator.name AS created_by_name,
        COUNT(DISTINCT c.id)  AS color_count,
        COUNT(DISTINCT v.id)  AS variant_count
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor   c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id   = c.id
    LEFT JOIN noblerole creator      ON creator.id  = p.created_by
    WHERE p.created_by = ?
    GROUP BY p.id
    ORDER BY p.name ASC
");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc())
    $products[] = $row;
$stmt->close();

// ── Distinct categories for filter dropdown ────────────────────────────────
$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM nobleproduct WHERE category != '' ORDER BY category ASC");
while ($row = $catRes->fetch_assoc())
    $categories[] = $row['category'];

$uploadUrl = BASE_URL . '/uploads/'; // public URL prefix for product images
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-slate-100">
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Product Specialist</p>
                <h1 class="text-xl font-bold text-gray-800">Products</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?> total
                </p>
            </div>
            <a href="<?= BASE_URL ?>/ps-insertproduct"
                class="flex items-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition">
                <i class="fa-solid fa-plus text-xs"></i> Add Product
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-5">
            <div class="flex flex-wrap items-center gap-3">

                <!-- Search -->
                <div class="relative flex-1 min-w-56">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    <input type="text" id="search-input" placeholder="Search by name or description…"
                        class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                </div>

                <!-- Category filter -->
                <select id="category-filter"
                    class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 transition min-w-40">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Clear -->
                <button type="button" id="clear-filters"
                    class="px-3 py-2 text-xs text-gray-400 hover:text-gray-600 border border-gray-200 rounded-lg bg-gray-50 hover:bg-gray-100 transition hidden">
                    <i class="fa-solid fa-xmark mr-1"></i> Clear
                </button>

                <!-- Results count -->
                <span id="results-label" class="text-xs text-gray-400 ml-auto"></span>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm" id="products-table">
                <thead>
                    <tr
                        class="border-b border-gray-100 bg-gray-50 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">
                        <th class="text-left px-5 py-3 w-12">#</th>
                        <th class="text-left px-5 py-3">Product</th>
                        <th class="text-left px-5 py-3">Category</th>
                        <th class="text-center px-5 py-3">Colors</th>
                        <th class="text-center px-5 py-3">Variants</th>
                        <th class="text-left px-5 py-3">Added by</th>
                        <th class="text-right px-5 py-3">Action</th>
                    </tr>
                </thead>
                <tbody id="products-tbody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-400 text-sm">
                                <i class="fa-solid fa-box-open text-3xl mb-3 block text-gray-300"></i>
                                No products yet. <a href="<?= BASE_URL ?>/ps-insertproduct"
                                    class="text-amber-500 hover:underline">Add one</a>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $i => $p): ?>
                            <tr class="product-row border-b border-gray-50 hover:bg-amber-50/40 transition group"
                                data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                                data-desc="<?= htmlspecialchars(strtolower($p['description'] ?? '')) ?>"
                                data-category="<?= htmlspecialchars($p['category']) ?>">

                                <!-- Row number -->
                                <td class="px-5 py-3.5 text-gray-400 text-xs"><?= $i + 1 ?></td>

                                <!-- Name + image + description -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($p['imageproduct'])): ?>
                                            <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                                alt="<?= htmlspecialchars($p['name']) ?>"
                                                class="w-10 h-10 rounded-lg object-cover border border-gray-100 shrink-0">
                                        <?php else: ?>
                                            <div
                                                class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                                                <i class="fa-solid fa-image text-amber-400 text-sm"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-semibold text-gray-800 leading-snug">
                                                <?= htmlspecialchars($p['name']) ?></p>
                                            <?php if (!empty($p['description'])): ?>
                                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1 max-w-xs">
                                                    <?= htmlspecialchars($p['description']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td class="px-5 py-3.5">
                                    <?php if (!empty($p['category'])): ?>
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">
                                            <?= htmlspecialchars($p['category']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs italic">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Colors -->
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                         <?= $p['color_count'] > 0 ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-400' ?>">
                                        <?= $p['color_count'] ?>
                                    </span>
                                </td>

                                <!-- Variants -->
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                         <?= $p['variant_count'] > 0 ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400' ?>">
                                        <?= $p['variant_count'] ?>
                                    </span>
                                </td>

                                <!-- Added by -->
                                <td class="px-5 py-3.5">
                                    <?php if (!empty($p['created_by_name'])): ?>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($p['created_by_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs italic">Unknown</span>
                                    <?php endif; ?>
                                </td>


                                <!-- Action -->
                                <td class="px-5 py-3.5 text-right">
                                    <a href="<?= BASE_URL ?>/ps-updateproductlist?id=<?= $p['id'] ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold
                                      text-amber-600 bg-amber-50 hover:bg-amber-100 border border-amber-100
                                      rounded-lg transition group-hover:border-amber-300">
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Empty-filter state -->
            <div id="no-results" class="hidden px-5 py-12 text-center text-gray-400 text-sm">
                <i class="fa-solid fa-magnifying-glass text-3xl mb-3 block text-gray-300"></i>
                No products match your search.
            </div>
        </div>

    </div>

    <script>
        const searchInput = document.getElementById('search-input');
        const categoryFilter = document.getElementById('category-filter');
        const clearBtn = document.getElementById('clear-filters');
        const resultsLabel = document.getElementById('results-label');
        const noResults = document.getElementById('no-results');
        const rows = document.querySelectorAll('.product-row');

        function filterTable() {
            const q = searchInput.value.trim().toLowerCase();
            const cat = categoryFilter.value;
            let visible = 0;

            rows.forEach(row => {
                const nameMatch = row.dataset.name.includes(q) || row.dataset.desc.includes(q);
                const catMatch = !cat || row.dataset.category === cat;
                const show = nameMatch && catMatch;
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            noResults.classList.toggle('hidden', visible > 0);
            resultsLabel.textContent = q || cat
                ? `${visible} of ${rows.length} shown`
                : '';
            clearBtn.classList.toggle('hidden', !q && !cat);
        }

        searchInput.addEventListener('input', filterTable);
        categoryFilter.addEventListener('change', filterTable);
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            categoryFilter.value = '';
            filterTable();
        });
    </script>
</body>

</html>