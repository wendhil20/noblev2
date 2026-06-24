<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all products
$products = $conn->query("SELECT * FROM nobleproduct ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

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
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Product Linking</h1>
            <p class="text-sm text-slate-500 mt-1">Link subcategories to each product</p>
        </div>

        <!-- Product List -->
        <div class="space-y-3">
            <?php foreach ($products as $product):
                // Get already linked subcategory IDs for this product
                $pid = $product['id'];
                $linkedStmt = $conn->prepare("SELECT subcategory_id FROM nobleproduct_subcategory WHERE product_id = ?");
                $linkedStmt->bind_param("i", $pid);
                $linkedStmt->execute();
                $linkedRows = $linkedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $linkedIds = array_column($linkedRows, 'subcategory_id');
                ?>
                <div class="bg-white rounded-lg border border-slate-200 shadow-sm">

                    <!-- Product Row -->
                    <div class="flex items-center justify-between px-4 py-3 cursor-pointer"
                        onclick="toggleProduct(<?= $pid ?>)">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-box text-slate-400 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($product['name']) ?></p>
                                <p class="text-xs text-slate-400">
                                    <?= count($linkedIds) ?> subcategor<?= count($linkedIds) == 1 ? 'y' : 'ies' ?> linked
                                </p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform"
                            id="chevron-<?= $pid ?>"></i>
                    </div>

                    <!-- Subcategory Picker (hidden by default) -->
                    <div id="picker-<?= $pid ?>" class="hidden border-t border-slate-100 px-5 py-4">

                        <?php foreach ($categories as $catId => $cat): ?>
                            <?php if (empty($cat['subs']))
                                continue; ?>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 mt-3 first:mt-0">
                                <?= htmlspecialchars($cat['name']) ?>
                            </p>
                            <div class="flex flex-wrap gap-2 mb-1">
                                <?php foreach ($cat['subs'] as $sub):
                                    $isLinked = in_array($sub['id'], $linkedIds);
                                    ?>
                                    <button id="sub-btn-<?= $pid ?>-<?= $sub['id'] ?>"
                                        onclick="toggleLink(<?= $pid ?>, <?= $sub['id'] ?>, this)" class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition
                <?= $isLinked
                    ? 'bg-amber-500 border-amber-500 text-white'
                    : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-amber-300 hover:bg-amber-50' ?>">
                                        <?php if (!empty($sub['image'])): ?>
                                            <img src="<?= htmlspecialchars($sub['image']) ?>" class="w-5 h-5 rounded object-cover">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
        function toggleProduct(pid) {
            const picker = document.getElementById('picker-' + pid);
            const chevron = document.getElementById('chevron-' + pid);
            picker.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function toggleLink(productId, subcategoryId, btn) {
            const isLinked = btn.classList.contains('bg-amber-500');

            fetch('<?= BASE_URL ?>/ps-backendtoggle-subcategory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, subcategory_id: subcategoryId, linked: !isLinked })
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        if (!isLinked) {
                            btn.classList.remove('bg-slate-50', 'border-slate-200', 'text-slate-600', 'hover:border-amber-300', 'hover:bg-amber-50');
                            btn.classList.add('bg-amber-500', 'border-amber-500', 'text-white');
                        } else {
                            btn.classList.remove('bg-amber-500', 'border-amber-500', 'text-white');
                            btn.classList.add('bg-slate-50', 'border-slate-200', 'text-slate-600', 'hover:border-amber-300', 'hover:bg-amber-50');
                        }
                    }
                });
        }
    </script>

</body>

</html>