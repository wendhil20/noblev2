<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all products
$products = $conn->query("SELECT id, name, imageproduct FROM nobleproduct ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch all categories with their subcategories
$catResult = $conn->query("
    SELECT c.id as cat_id, c.name as cat_name,
           s.id as sub_id, s.name as sub_name, s.image as sub_image
    FROM noblecategory c
    LEFT JOIN noblesubcategory s ON s.category_id = c.id
    ORDER BY c.name, s.name
");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $cid = $row['cat_id'];
    if (!isset($categories[$cid])) {
        $categories[$cid] = ['name' => $row['cat_name'], 'subs' => []];
    }
    if ($row['sub_id']) {
        $categories[$cid]['subs'][] = ['id' => $row['sub_id'], 'name' => $row['sub_name'], 'image' => $row['sub_image']];
    }
}

// Fetch all existing links, and build lookups
$linkResult = $conn->query("SELECT product_id, subcategory_id FROM nobleproduct_subcategory");
$productSubIds = []; // product_id => [subcategory_id, ...]
foreach ($products as $p) {
    $productSubIds[$p['id']] = [];
}
$subNameLookup = [];
foreach ($categories as $cat) {
    foreach ($cat['subs'] as $sub) {
        $subNameLookup[$sub['id']] = $sub['name'];
    }
}
while ($row = $linkResult->fetch_assoc()) {
    if (isset($productSubIds[$row['product_id']])) {
        $productSubIds[$row['product_id']][] = (int) $row['subcategory_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Linking Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 flex">

        <!-- Sidebar filters -->
        <aside class="w-64 flex-shrink-0 bg-white border-r border-slate-200 p-5 min-h-screen">
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-bold text-slate-800 tracking-widest">TARGET SUBCATEGORIES</p>
                <button type="button" onclick="clearSubSelection()"
                    class="text-xs font-semibold text-amber-600 hover:text-amber-700">Clear</button>
            </div>

            <label class="flex items-center justify-between mb-4 cursor-pointer">
                <span class="text-xs font-semibold text-red-500">UNLINKED PRODUCTS ONLY</span>
                <input type="checkbox" id="unlinkedOnly" onchange="renderProducts()"
                    class="w-4 h-4 accent-red-500">
            </label>

            <div class="border-t border-slate-100 pt-4">
                <?php foreach ($categories as $catId => $cat): ?>
                    <?php if (empty($cat['subs']))
                        continue; ?>
                    <div class="mb-3">
                        <button type="button" onclick="toggleCatFilter(<?= $catId ?>)"
                            class="w-full flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">
                            <?= htmlspecialchars($cat['name']) ?>
                            <i class="fa-solid fa-chevron-down text-[10px]" id="filter-chevron-<?= $catId ?>"></i>
                        </button>
                        <div id="filter-body-<?= $catId ?>" class="space-y-1.5 pl-1">
                            <?php foreach ($cat['subs'] as $sub): ?>
                                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                                    <input type="checkbox" class="sub-filter w-4 h-4 accent-amber-500"
                                        value="<?= $sub['id'] ?>" onchange="onSubFilterChange()">
                                    <?= htmlspecialchars($sub['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 p-6 pb-28">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
                <h1 class="text-2xl font-bold text-slate-800">Link Products</h1>
                <div class="relative w-full sm:w-80">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                    <input type="text" id="productSearch" placeholder="Search products..."
                        class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400"
                        oninput="renderProducts()">
                </div>
            </div>

            <p id="pickSubHint" class="text-sm text-slate-400 italic mb-4">
                Tick one or more subcategories on the left, then check the products you want to link to them.
            </p>

            <!-- Product grid -->
            <div id="productGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
            <p id="noProductsMsg" class="hidden text-sm text-slate-400 italic mt-4">No products match your search/filter.</p>
        </div>

        <!-- Sticky apply bar -->
        <div id="applyBar"
            class="hidden fixed bottom-0 left-60 right-0 bg-white border-t border-slate-200 px-6 py-3 flex items-center justify-between shadow-[0_-2px_10px_rgba(0,0,0,0.05)] z-40">
            <p class="text-sm text-slate-600">
                <span id="selectedCount" class="font-bold text-slate-800">0</span> product(s) selected →
                <span id="selectedSubCount" class="font-bold text-amber-600">0</span> subcategor(y/ies) checked
            </p>
            <button type="button" onclick="applyLinks()"
                class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold px-5 py-2 rounded-lg flex items-center gap-2">
                <i class="fa-solid fa-check"></i> Apply
            </button>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="hidden fixed bottom-24 right-6 bg-slate-800 text-white text-sm px-4 py-2 rounded-lg shadow-lg z-50"></div>

    <!-- Linked subcategories modal -->
    <div id="tagsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onclick="if(event.target===this) closeTagsModal()">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <p class="text-xs font-bold text-slate-400 tracking-widest">LINKED SUBCATEGORIES</p>
                    <p id="tagsModalProductName" class="text-base font-bold text-slate-800"></p>
                </div>
                <button type="button" onclick="closeTagsModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div id="tagsModalBody" class="p-5 overflow-y-auto flex flex-wrap content-start gap-2"></div>
        </div>
    </div>

    <script>
        // ---- Data from PHP ----
        const products = <?= json_encode($products) ?>;                    // [{id, name, imageproduct}]
        const productSubIds = <?= json_encode($productSubIds) ?>;           // {product_id: [sub_id,...]}
        const subNames = <?= json_encode($subNameLookup) ?>;                // {sub_id: name}
        const baseUrl = '<?= rtrim(BASE_URL, '/') ?>';
        const selectedProducts = new Set();

        function resolveImageSrc(path) {
            if (!path) return null;
            if (/^https?:\/\//i.test(path)) return path; // already a full URL
            const clean = path.replace(/^\/+/, '');
            // filenames in the DB have no folder prefix -- actual files live in /uploads/
            const withFolder = clean.startsWith('uploads/') ? clean : 'uploads/' + clean;
            return baseUrl + '/' + withFolder;
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.remove('hidden');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => toast.classList.add('hidden'), 2500);
        }

        function toggleCatFilter(catId) {
            document.getElementById('filter-body-' + catId).classList.toggle('hidden');
            document.getElementById('filter-chevron-' + catId).classList.toggle('rotate-180');
        }

        function getCheckedSubIds() {
            return Array.from(document.querySelectorAll('.sub-filter:checked')).map(cb => cb.value);
        }

        function clearSubSelection() {
            document.querySelectorAll('.sub-filter:checked').forEach(cb => cb.checked = false);
            onSubFilterChange();
        }

        function onSubFilterChange() {
            renderProducts();
            updateApplyBar();
        }

        // ---- Validation helpers ----
        // Returns which of the currently-checked subcategories a product is ALREADY linked to
        function alreadyLinkedChecked(productId) {
            const checkedSubs = getCheckedSubIds().map(Number);
            const linked = productSubIds[productId] || [];
            return checkedSubs.filter(sid => linked.includes(sid));
        }

        function isFullyLinkedToChecked(productId) {
            const checkedSubs = getCheckedSubIds().map(Number);
            if (checkedSubs.length === 0) return false;
            return alreadyLinkedChecked(productId).length === checkedSubs.length;
        }

        // Compact summary shown on the card itself (no more crowded pill lists)
        function tagSummaryFor(productId) {
            const subs = productSubIds[productId] || [];
            if (subs.length === 0) {
                return '<span class="text-[11px] text-red-500 font-semibold">No category yet</span>';
            }
            const firstName = subNames[subs[0]] || subs[0];
            const label = subs.length === 1
                ? firstName
                : `${firstName} <span class="text-slate-400 font-normal">+${subs.length - 1} more</span>`;
            return `
                <button type="button" onclick="event.stopPropagation(); openTagsModal(${productId})"
                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded-md">
                    <i class="fa-solid fa-tags text-slate-400"></i>
                    ${label}
                </button>`;
        }

        // ---- Modal: full list of a product's linked subcategories ----
        let activeModalProductId = null;

        function renderTagsModalBody(productId) {
            const subs = productSubIds[productId] || [];
            const body = document.getElementById('tagsModalBody');
            if (subs.length === 0) {
                body.innerHTML = '<p class="text-sm text-slate-400 italic">No subcategories linked yet.</p>';
                return;
            }
            body.innerHTML = subs.map(sid => `
                <span class="inline-flex items-center gap-1.5 text-xs bg-slate-100 text-slate-600 px-2.5 py-1.5 rounded-lg">
                    ${subNames[sid] || sid}
                    <i class="fa-solid fa-xmark cursor-pointer hover:text-red-500" onclick="unlinkOne(${productId}, ${sid})"></i>
                </span>`
            ).join('');
        }

        function openTagsModal(productId) {
            activeModalProductId = productId;
            const product = products.find(p => String(p.id) === String(productId));
            document.getElementById('tagsModalProductName').textContent = product ? product.name : '';
            renderTagsModalBody(productId);
            document.getElementById('tagsModal').classList.remove('hidden');
        }

        function closeTagsModal() {
            activeModalProductId = null;
            document.getElementById('tagsModal').classList.add('hidden');
        }

        function renderProducts() {
            const query = document.getElementById('productSearch').value.trim().toLowerCase();
            const unlinkedOnly = document.getElementById('unlinkedOnly').checked;
            const grid = document.getElementById('productGrid');
            grid.innerHTML = '';

            const checkedSubs = getCheckedSubIds().map(Number);

            let visibleCount = 0;
            products.forEach(p => {
                const nameMatch = p.name.toLowerCase().includes(query);
                const linkMatch = !unlinkedOnly || (productSubIds[p.id] || []).length === 0;
                if (!nameMatch || !linkMatch) return;
                visibleCount++;

                const checked = selectedProducts.has(String(p.id));
                const matchedLinked = alreadyLinkedChecked(p.id);
                const fullyLinked = checkedSubs.length > 0 && matchedLinked.length === checkedSubs.length;
                const partiallyLinked = checkedSubs.length > 0 && matchedLinked.length > 0 && !fullyLinked;

                const card = document.createElement('div');
                let cardClasses = 'bg-white rounded-lg border p-3 transition ';
                if (fullyLinked) {
                    cardClasses += 'border-emerald-200 bg-emerald-50/40 opacity-70 cursor-not-allowed';
                } else {
                    cardClasses += 'cursor-pointer ' + (checked ? 'border-amber-500 ring-2 ring-amber-200' : 'border-slate-200 hover:border-amber-300');
                }
                card.className = cardClasses;
                card.onclick = () => toggleProductSelect(p.id, card);

                const resolvedImg = resolveImageSrc(p.imageproduct);
                const imageBlock = resolvedImg
                    ? `<img src="${resolvedImg}" class="w-full h-28 object-contain rounded-lg mb-2 bg-slate-100"
                           onerror="this.outerHTML='<div class=&quot;w-full h-28 rounded-lg mb-2 bg-slate-100 flex items-center justify-center&quot;><i class=&quot;fa-solid fa-box text-slate-300 text-2xl&quot;></i></div>'">`
                    : `<div class="w-full h-28 rounded-lg mb-2 bg-slate-100 flex items-center justify-center">
                           <i class="fa-solid fa-box text-slate-300 text-2xl"></i>
                       </div>`;

                let statusBadge = '';
                if (fullyLinked) {
                    statusBadge = '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 mb-1"><i class="fa-solid fa-circle-check"></i> Already linked</span>';
                } else if (partiallyLinked) {
                    statusBadge = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-500 mb-1"><i class="fa-solid fa-triangle-exclamation"></i> ${matchedLinked.length}/${checkedSubs.length} already linked</span>`;
                }

                card.innerHTML = `
                    ${imageBlock}
                    ${statusBadge}
                    <div class="flex items-start justify-between mb-2">
                        <p class="text-sm font-bold text-slate-800 leading-snug">${p.name}</p>
                        <input type="checkbox" class="w-4 h-4 accent-amber-500 mt-0.5 pointer-events-none flex-shrink-0 ml-2" ${checked ? 'checked' : ''} ${fullyLinked ? 'disabled' : ''}>
                    </div>
                    <div class="flex flex-wrap">${tagSummaryFor(p.id)}</div>
                `;
                grid.appendChild(card);
            });

            document.getElementById('noProductsMsg').classList.toggle('hidden', visibleCount !== 0);
        }

        function toggleProductSelect(productId, card) {
            const key = String(productId);

            // Block selecting a product that's already linked to ALL currently checked subcategories
            if (!selectedProducts.has(key) && isFullyLinkedToChecked(productId)) {
                showToast('This product is already linked to the selected subcategory/ies.');
                return;
            }

            if (selectedProducts.has(key)) {
                selectedProducts.delete(key);
            } else {
                selectedProducts.add(key);
            }
            renderProducts();
            updateApplyBar();
        }

        function updateApplyBar() {
            const subCount = getCheckedSubIds().length;
            const prodCount = selectedProducts.size;
            document.getElementById('selectedCount').textContent = prodCount;
            document.getElementById('selectedSubCount').textContent = subCount;
            document.getElementById('applyBar').classList.toggle('hidden', !(subCount > 0 && prodCount > 0));
            document.getElementById('pickSubHint').classList.toggle('hidden', subCount > 0 || prodCount > 0);
        }

        function saveLink(productId, subcategoryId, linked) {
            return fetch('<?= BASE_URL ?>/ps-backendtoggle-subcategory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, subcategory_id: subcategoryId, linked: linked })
            })
                .then(r => r.json())
                .catch(() => ({ success: false }));
        }

        function unlinkOne(productId, subId) {
            saveLink(productId, subId, false).then(d => {
                if (d.success) {
                    productSubIds[productId] = (productSubIds[productId] || []).filter(id => id !== subId);
                    renderProducts();
                    if (activeModalProductId !== null && String(activeModalProductId) === String(productId)) {
                        renderTagsModalBody(productId);
                    }
                } else {
                    showToast('Could not remove that link. Please try again.');
                }
            });
        }

        function applyLinks() {
            const subIds = getCheckedSubIds();
            const prodIds = Array.from(selectedProducts);
            if (subIds.length === 0 || prodIds.length === 0) return;

            const applyBtn = document.querySelector('#applyBar button');
            applyBtn.disabled = true;
            applyBtn.classList.add('opacity-60');
            applyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Applying...';

            const jobs = [];
            let skippedCount = 0;
            prodIds.forEach(pid => {
                subIds.forEach(sid => {
                    // skip if already linked
                    if (!(productSubIds[pid] || []).includes(Number(sid))) {
                        jobs.push({ pid, sid });
                    } else {
                        skippedCount++;
                    }
                });
            });

            if (jobs.length === 0) {
                applyBtn.disabled = false;
                applyBtn.classList.remove('opacity-60');
                applyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply';
                showToast('Nothing to apply — all selected products are already linked to those subcategories.');
                return;
            }

            Promise.all(jobs.map(j => saveLink(j.pid, j.sid, true).then(d => ({ ...j, success: d.success }))))
                .then(results => {
                    let failCount = 0;
                    results.forEach(r => {
                        if (r.success) {
                            if (!productSubIds[r.pid]) productSubIds[r.pid] = [];
                            productSubIds[r.pid].push(Number(r.sid));
                        } else {
                            failCount++;
                        }
                    });

                    applyBtn.disabled = false;
                    applyBtn.classList.remove('opacity-60');
                    applyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply';

                    let msg = '';
                    if (failCount > 0) {
                        msg = failCount + ' link(s) failed to save. Please retry.';
                    } else {
                        msg = 'Linked successfully!';
                    }
                    if (skippedCount > 0) {
                        msg += ' (' + skippedCount + ' pair(s) skipped — already linked)';
                    }
                    showToast(msg);

                    selectedProducts.clear();
                    document.querySelectorAll('.sub-filter:checked').forEach(cb => cb.checked = false);
                    renderProducts();
                    updateApplyBar();
                });
        }

        // initial render
        renderProducts();
        updateApplyBar();
    </script>

</body>

</html>