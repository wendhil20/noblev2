<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$supplierId = intval($_GET['supplier_id'] ?? 0);
if (!$supplierId) {
    header('Location: ' . BASE_URL . '/ps-posupplier');
    exit;
}

// Fetch supplier info
$stmtSup = $conn->prepare("SELECT * FROM noblecompanysupplier WHERE id = ? LIMIT 1");
$stmtSup->bind_param("i", $supplierId);
$stmtSup->execute();
$supplier = $stmtSup->get_result()->fetch_assoc();
$stmtSup->close();

if (!$supplier) {
    header('Location: ' . BASE_URL . '/ps-posupplier');
    exit;
}

// Fetch all products with their color variants
$products = [];
$resP = $conn->query("
    SELECT p.id, p.name, p.imageproduct, p.category,
           COUNT(pc.id) AS color_count
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor pc ON pc.product_id = p.id
    GROUP BY p.id
    ORDER BY p.name ASC
");
while ($row = $resP->fetch_assoc()) {
    $products[] = $row;
}

// Fetch existing links for this supplier
$linkedMap = [];
$stmtL = $conn->prepare("SELECT product_id, link_type FROM nobleproductsupplierlink WHERE supplier_id = ?");
$stmtL->bind_param("i", $supplierId);
$stmtL->execute();
$resL = $stmtL->get_result();
while ($row = $resL->fetch_assoc()) {
    $linkedMap[$row['product_id']] = $row['link_type'];
}
$stmtL->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Products</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen p-6">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-1">
            <a href="<?= BASE_URL ?>/ps-posupplier" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="flex items-center gap-3">
                <?php if (!empty($supplier['suplogoimagecompany'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($supplier['suplogoimagecompany']) ?>"
                        class="w-8 h-8 rounded-lg object-contain border border-gray-100 bg-white" alt="logo">
                <?php endif; ?>
                <div>
                    <h1 class="text-lg font-bold text-gray-800 leading-tight">
                        <?= htmlspecialchars($supplier['supname']) ?>
                    </h1>
                    <p class="text-xs text-gray-400">Link products to this supplier</p>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 mb-5 mt-4">
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>
                <span class="text-[10px] text-gray-500 font-medium">Primary Supplier</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span>
                <span class="text-[10px] text-gray-500 font-medium">Secondary Supplier</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-gray-200 inline-block"></span>
                <span class="text-[10px] text-gray-500 font-medium">Not linked</span>
            </div>
        </div>

        <!-- Search -->
        <div class="mb-4">
            <input type="text" id="search-input" placeholder="Search products…" oninput="filterProducts()"
                class="w-full max-w-sm border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-amber-400 bg-white">
        </div>

        <!-- Product List -->
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-xl border border-gray-100 px-6 py-12 text-center">
                <i class="fa-solid fa-box-open text-gray-200 text-4xl mb-3"></i>
                <p class="text-xs text-gray-400">No products found.</p>
            </div>
        <?php else: ?>
            <div class="space-y-2" id="product-grid">
                <?php foreach ($products as $p): ?>
                    <?php
                    $linked = $linkedMap[$p['id']] ?? null;
                    $isPrimary = $linked === 'primary';
                    $isSecond = $linked === 'secondary';
                    ?>
                    <div class="product-card bg-white rounded-xl border px-4 py-3 flex items-center gap-4 transition-all
            <?= $isPrimary ? 'border-amber-400 ring-2 ring-amber-200' : ($isSecond ? 'border-blue-400 ring-2 ring-blue-200' : 'border-gray-100') ?>"
                        id="card-<?= $p['id'] ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                        data-category="<?= strtolower(htmlspecialchars($p['category'] ?? '')) ?>">

                        <!-- Image -->
                        <div
                            class="w-12 h-12 bg-gray-50 rounded-lg overflow-hidden flex items-center justify-center shrink-0 border border-gray-100">
                            <?php if (!empty($p['imageproduct'])): ?>
                                <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($p['imageproduct']) ?>"
                                    class="w-full h-full object-contain" alt="">
                            <?php else: ?>
                                <i class="fa-solid fa-image text-gray-200 text-lg"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    <?= htmlspecialchars($p['name']) ?>
                                </p>
                                <span id="badge-<?= $p['id'] ?>">
                                    <?php if ($isPrimary): ?>
                                        <span
                                            class="text-[9px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">Primary</span>
                                    <?php elseif ($isSecond): ?>
                                        <span
                                            class="text-[9px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-semibold">Secondary</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p class="text-[10px] text-gray-400">
                                <?= htmlspecialchars($p['category'] ?? '—') ?>
                                · <?= $p['color_count'] ?> color<?= $p['color_count'] != 1 ? 's' : '' ?>
                            </p>
                        </div>

                        <!-- Link Buttons -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button onclick="setLink(<?= $p['id'] ?>, 'primary')" id="btn-primary-<?= $p['id'] ?>"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $isPrimary ? 'bg-amber-500 text-white' : 'border border-gray-200 text-gray-500 hover:border-amber-400 hover:text-amber-500' ?>">
                                Primary
                            </button>
                            <button onclick="setLink(<?= $p['id'] ?>, 'secondary')" id="btn-secondary-<?= $p['id'] ?>"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $isSecond ? 'bg-blue-500 text-white' : 'border border-gray-200 text-gray-500 hover:border-blue-400 hover:text-blue-500' ?>">
                                Secondary
                            </button>
                            <button onclick="removeLink(<?= $p['id'] ?>)" id="btn-remove-<?= $p['id'] ?>"
                                class="px-2 py-1.5 rounded-lg text-xs text-red-400 border border-gray-200 hover:bg-red-50 hover:border-red-300 transition"
                                style="display: <?= $linked ? 'inline-flex' : 'none' ?>;">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
        const supplierId = <?= $supplierId ?>;
        const baseUrl = '<?= BASE_URL ?>';

        // HANAPIN AT PALITAN ANG BUONG setLink FUNCTION:
        function setLink(productId, type) {
            const btnPrimary = document.getElementById('btn-primary-' + productId);
            const btnSecondary = document.getElementById('btn-secondary-' + productId);
            const btnRemove = document.getElementById('btn-remove-' + productId);
            const card = document.getElementById('card-' + productId);
            const badge = document.getElementById('badge-' + productId);

            fetch(baseUrl + '/ps-backendsupplierlink-save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&supplier_id=${supplierId}&link_type=${type}`
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) { alert(res.error ?? 'Error'); return; }

                    if (type === 'primary') {
                        card.className = 'product-card bg-white rounded-xl border border-amber-400 ring-2 ring-amber-200 px-4 py-3 flex items-center gap-4 transition-all';
                        badge.innerHTML = '<span class="text-[9px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">Primary</span>';
                        btnPrimary.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition bg-amber-500 text-white';
                        btnSecondary.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-gray-200 text-gray-500 hover:border-blue-400 hover:text-blue-500';
                    } else {
                        card.className = 'product-card bg-white rounded-xl border border-blue-400 ring-2 ring-blue-200 px-4 py-3 flex items-center gap-4 transition-all';
                        badge.innerHTML = '<span class="text-[9px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-semibold">Secondary</span>';
                        btnSecondary.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition bg-blue-500 text-white';
                        btnPrimary.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-gray-200 text-gray-500 hover:border-amber-400 hover:text-amber-500';
                    }

                    // Show remove button properly
                    btnRemove.className = 'px-2 py-1.5 rounded-lg text-xs text-red-400 border border-gray-200 hover:bg-red-50 hover:border-red-300 transition';
                    btnRemove.style.display = '';
                })
                .catch(() => alert('Something went wrong.'));
        }
        function removeLink(productId) {
            const btnPrimary = document.getElementById('btn-primary-' + productId);
            const btnSecondary = document.getElementById('btn-secondary-' + productId);
            const btnRemove = document.getElementById('btn-remove-' + productId);
            const card = document.getElementById('card-' + productId);
            const badge = document.getElementById('badge-' + productId);

            fetch(baseUrl + '/ps-backendsupplierlink-save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&supplier_id=${supplierId}&link_type=remove`
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) { alert(res.error ?? 'Error'); return; }

                    // Reset card border
                    card.className = 'product-card bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center gap-4 transition-all';

                    badge.innerHTML = '';

                    // Reset primary button
                    btnPrimary.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-gray-200 text-gray-500 hover:border-amber-400 hover:text-amber-500';

                    // Reset secondary button
                    btnSecondary.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-gray-200 text-gray-500 hover:border-blue-400 hover:text-blue-500';

                    // Fully hide remove button — no leftover classes
                    btnRemove.style.display = 'none';
                })
                .catch(() => alert('Something went wrong.'));
        }

        function filterProducts() {
            const q = document.getElementById('search-input').value.toLowerCase().trim();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.name ?? '';
                const category = card.dataset.category ?? '';
                card.style.display = (name.includes(q) || category.includes(q)) ? '' : 'none';
            });
        }
    </script>

</body>

</html>