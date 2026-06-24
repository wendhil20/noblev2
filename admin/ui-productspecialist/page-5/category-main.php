<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$result = $conn->query("
    SELECT c.*, COUNT(s.id) as sub_count
    FROM noblecategory c
    LEFT JOIN noblesubcategory s ON s.category_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Category Management</h1>
                <p class="text-sm text-slate-500 mt-1">Manage categories and their subcategories</p>
            </div>
            <button onclick="openAddCategoryModal()"
                class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition">
                <i class="fa-solid fa-plus"></i> Add Category
            </button>
        </div>

        <!-- Category List -->
        <div class="space-y-3">
            <?php foreach ($categories as $cat): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">

                    <!-- Category Row -->
                    <div class="flex items-center justify-between px-5 py-4">
                        <div class="flex items-center gap-3">

                            <?php if (!empty($cat['image'])): ?>
                                <img src="<?= htmlspecialchars($cat['image']) ?>"
                                    class="w-10 h-10 rounded-lg object-cover border border-slate-200 flex-shrink-0">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-layer-group text-amber-500 text-sm"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h2 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($cat['name']) ?></h2>
                                <p class="text-xs text-slate-400"><?= $cat['sub_count'] ?> subcategories</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick="openEditCatImageModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')"
                                class="text-xs bg-slate-50 hover:bg-slate-100 text-slate-500 font-semibold px-3 py-1.5 rounded-lg transition">
                                <i class="fa-solid fa-image"></i>
                            </button>
                            <button
                                onclick="openAddSubModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')"
                                class="text-xs bg-amber-50 hover:bg-amber-100 text-amber-600 font-semibold px-3 py-1.5 rounded-lg transition">
                                + Sub
                            </button>
                            <button onclick="deleteCategory(<?= $cat['id'] ?>)"
                                class="text-xs bg-red-50 hover:bg-red-100 text-red-500 font-semibold px-3 py-1.5 rounded-lg transition">
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Subcategory List -->
                    <?php
                    $catId = $cat['id'];
                    $stmt = $conn->prepare("SELECT * FROM noblesubcategory WHERE category_id = ? ORDER BY id DESC");
                    $stmt->bind_param("i", $catId);
                    $stmt->execute();
                    $subrows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>

                    <?php if (!empty($subrows)): ?>
                        <div class="border-t border-slate-100 divide-y divide-slate-50">
                            <?php foreach ($subrows as $sub): ?>
                                <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition">

                                    <?php if (!empty($sub['image'])): ?>
                                        <img src="<?= htmlspecialchars($sub['image']) ?>"
                                            class="w-8 h-8 rounded-lg object-cover border border-slate-200 flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-image text-slate-300 text-xs"></i>
                                        </div>
                                    <?php endif; ?>

                                    <span class="text-sm text-slate-700 flex-1"><?= htmlspecialchars($sub['name']) ?></span>

                                    <button
                                        onclick="openEditSubImageModal(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['name'], ENT_QUOTES) ?>')"
                                        class="text-slate-300 hover:text-slate-500 transition text-xs p-1">
                                        <i class="fa-solid fa-image"></i>
                                    </button>

                                    <button onclick="deleteSubcategory(<?= $sub['id'] ?>)"
                                        class="text-red-300 hover:text-red-500 transition text-xs p-1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- ===== ADD CATEGORY MODAL ===== -->
    <div id="addCategoryModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-4">Add Category</h2>
            <input id="categoryName" type="text" placeholder="Category name"
                class="w-full border border-slate-200 rounded-lg px-4 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-amber-400">
            <label class="block text-xs font-semibold text-slate-500 mb-1">Image (optional)</label>
            <input id="categoryImage" type="file" accept="image/*"
                class="w-full text-sm text-slate-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-600 file:font-semibold mb-4">
            <div class="flex justify-end gap-2">
                <button onclick="closeAddCategoryModal()"
                    class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="submitAddCategory()"
                    class="text-sm px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold transition">Add</button>
            </div>
        </div>
    </div>

    <!-- ===== EDIT CATEGORY IMAGE MODAL ===== -->
    <div id="editCatImageModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-1">Update Category Image</h2>
            <p class="text-sm text-slate-400 mb-4">Category: <span id="editCatName"
                    class="font-semibold text-amber-500"></span></p>
            <input id="editCatImage" type="file" accept="image/*"
                class="w-full text-sm text-slate-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-600 file:font-semibold mb-4">
            <input type="hidden" id="editCatId">
            <div class="flex justify-end gap-2">
                <button onclick="closeEditCatImageModal()"
                    class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="submitEditCatImage()"
                    class="text-sm px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold transition">Update</button>
            </div>
        </div>
    </div>

    <!-- ===== ADD SUBCATEGORY MODAL ===== -->
    <div id="addSubModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-1">Add Subcategory</h2>
            <p class="text-sm text-slate-400 mb-4">Under: <span id="subModalCatName"
                    class="font-semibold text-amber-500"></span></p>
            <input id="subCategoryName" type="text" placeholder="Subcategory name"
                class="w-full border border-slate-200 rounded-lg px-4 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-amber-400">
            <label class="block text-xs font-semibold text-slate-500 mb-1">Image (optional)</label>
            <input id="subCategoryImage" type="file" accept="image/*"
                class="w-full text-sm text-slate-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-600 file:font-semibold mb-4">
            <input type="hidden" id="subCategoryId">
            <div class="flex justify-end gap-2">
                <button onclick="closeAddSubModal()"
                    class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="submitAddSub()"
                    class="text-sm px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold transition">Add</button>
            </div>
        </div>
    </div>

    <!-- ===== EDIT SUBCATEGORY IMAGE MODAL ===== -->
    <div id="editSubImageModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-1">Update Subcategory Image</h2>
            <p class="text-sm text-slate-400 mb-4">Subcategory: <span id="editSubName"
                    class="font-semibold text-amber-500"></span></p>
            <input id="editSubImage" type="file" accept="image/*"
                class="w-full text-sm text-slate-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-600 file:font-semibold mb-4">
            <input type="hidden" id="editSubId">
            <div class="flex justify-end gap-2">
                <button onclick="closeEditSubImageModal()"
                    class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="submitEditSubImage()"
                    class="text-sm px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold transition">Update</button>
            </div>
        </div>
    </div>

    <script>
        // ===== Add Category Modal =====
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.remove('hidden');
        }
        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.add('hidden');
            document.getElementById('categoryName').value = '';
            document.getElementById('categoryImage').value = '';
        }
        function submitAddCategory() {
            const name = document.getElementById('categoryName').value.trim();
            const file = document.getElementById('categoryImage').files[0];
            if (!name) return alert('Please enter a category name.');

            const formData = new FormData();
            formData.append('name', name);
            if (file) formData.append('image', file);

            fetch('<?= BASE_URL ?>/ps-backendadd-category', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) location.reload();
                    else alert(d.message || 'Failed.');
                });
        }

        // ===== Edit Category Image Modal =====
        function openEditCatImageModal(catId, catName) {
            document.getElementById('editCatId').value = catId;
            document.getElementById('editCatName').textContent = catName;
            document.getElementById('editCatImageModal').classList.remove('hidden');
        }
        function closeEditCatImageModal() {
            document.getElementById('editCatImageModal').classList.add('hidden');
            document.getElementById('editCatImage').value = '';
        }
        function submitEditCatImage() {
            const id = document.getElementById('editCatId').value;
            const file = document.getElementById('editCatImage').files[0];
            if (!file) return alert('Please select an image.');

            const formData = new FormData();
            formData.append('id', id);
            formData.append('image', file);

            fetch('<?= BASE_URL ?>/ps-backendupdate-category-image', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) location.reload();
                    else alert(d.message || 'Failed.');
                });
        }

        // ===== Add Subcategory Modal =====
        function openAddSubModal(catId, catName) {
            document.getElementById('subCategoryId').value = catId;
            document.getElementById('subModalCatName').textContent = catName;
            document.getElementById('addSubModal').classList.remove('hidden');
        }
        function closeAddSubModal() {
            document.getElementById('addSubModal').classList.add('hidden');
            document.getElementById('subCategoryName').value = '';
            document.getElementById('subCategoryImage').value = '';
        }
        function submitAddSub() {
            const name = document.getElementById('subCategoryName').value.trim();
            const catId = document.getElementById('subCategoryId').value;
            const file = document.getElementById('subCategoryImage').files[0];
            if (!name) return alert('Please enter a subcategory name.');

            const formData = new FormData();
            formData.append('name', name);
            formData.append('category_id', catId);
            if (file) formData.append('image', file);

            fetch('<?= BASE_URL ?>/ps-backendadd-subcategory', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) location.reload();
                    else alert(d.message || 'Failed.');
                });
        }

        // ===== Edit Subcategory Image Modal =====
        function openEditSubImageModal(subId, subName) {
            document.getElementById('editSubId').value = subId;
            document.getElementById('editSubName').textContent = subName;
            document.getElementById('editSubImageModal').classList.remove('hidden');
        }
        function closeEditSubImageModal() {
            document.getElementById('editSubImageModal').classList.add('hidden');
            document.getElementById('editSubImage').value = '';
        }
        function submitEditSubImage() {
            const id = document.getElementById('editSubId').value;
            const file = document.getElementById('editSubImage').files[0];
            if (!file) return alert('Please select an image.');

            const formData = new FormData();
            formData.append('id', id);
            formData.append('image', file);

            fetch('<?= BASE_URL ?>/ps-backendupdate-subcategory-image', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) location.reload();
                    else alert(d.message || 'Failed.');
                });
        }

        // ===== Delete =====
        function deleteCategory(id) {
            if (!confirm('Delete this category and all its subcategories?')) return;
            fetch('<?= BASE_URL ?>/ps-backenddelete-category', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
                .then(r => r.json())
                .then(d => { if (d.success) location.reload(); });
        }

        function deleteSubcategory(id) {
            if (!confirm('Delete this subcategory?')) return;
            fetch('<?= BASE_URL ?>/ps-backenddelete-subcategory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
                .then(r => r.json())
                .then(d => { if (d.success) location.reload(); });
        }
    </script>

</body>

</html>