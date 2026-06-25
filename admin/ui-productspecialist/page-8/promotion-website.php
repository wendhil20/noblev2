<?php
// promotion-website.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// ===== HANDLE ACTIONS =====

// Add new
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $link = trim($_POST['website_link'] ?? '');
    $imageName = null;

    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $imageName = 'promosite_' . time() . '_' . uniqid() . '.' . $ext;
        $destPath = ROOT_PATH . '/uploads/promotionwebsite/' . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $destPath);
    }

    if ($name !== '' && $link !== '') {
        $stmt = $conn->prepare("INSERT INTO noblepromotionwebsite (name, website_link, image, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $name, $link, $imageName);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: " . BASE_URL . "/ps-promotionwebsite");
    exit;
}

// Edit existing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id   = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $link = trim($_POST['website_link'] ?? '');

    if ($id > 0 && $name !== '' && $link !== '') {
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $imageName = 'promosite_' . time() . '_' . uniqid() . '.' . $ext;
            $destPath = ROOT_PATH . '/uploads/promotionwebsite/' . $imageName;
            move_uploaded_file($_FILES['image']['tmp_name'], $destPath);

            $stmt = $conn->prepare("UPDATE noblepromotionwebsite SET name = ?, website_link = ?, image = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $link, $imageName, $id);
        } else {
            $stmt = $conn->prepare("UPDATE noblepromotionwebsite SET name = ?, website_link = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $link, $id);
        }
        $stmt->execute();
        $stmt->close();
    }
    header("Location: " . BASE_URL . "/ps-promotionwebsite");
    exit;
}

// Toggle active/off
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE noblepromotionwebsite SET is_active = 1 - is_active WHERE id = $id");
    header("Location: " . BASE_URL . "/ps-promotionwebsite");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM noblepromotionwebsite WHERE id = $id");
    header("Location: " . BASE_URL . "/ps-promotionwebsite");
    exit;
}

// ===== FETCH LIST =====
$sites = [];
$result = $conn->query("SELECT id, name, website_link, image, is_active, created_at FROM noblepromotionwebsite ORDER BY created_at DESC");
while ($row = $result->fetch_assoc())
    $sites[] = $row;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product promotion Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-800">Promotion Websites</h1>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                <i class="fa-solid fa-plus mr-1"></i> Add Website
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="text-left px-4 py-3">Image</th>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Link</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Created</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($sites)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-gray-400">No promotion websites yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sites as $s): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
    <?php if (!empty($s['image'])): ?>
        <img src="<?= BASE_URL ?>/uploads/promotionwebsite/<?= htmlspecialchars($s['image']) ?>"
            class="w-10 h-10 object-cover rounded-lg border border-gray-100">
    <?php else: ?>
        <div class="w-10 h-10 flex items-center justify-center bg-gray-100 rounded-lg text-gray-300">
            <i class="fa-solid fa-image"></i>
        </div>
    <?php endif; ?>
</td>
                                <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($s['name']) ?></td>
                                <td class="px-4 py-3 text-blue-600 truncate max-w-xs">
                                    <a href="<?= htmlspecialchars($s['website_link']) ?>" target="_blank" rel="noopener">
                                        <?= htmlspecialchars($s['website_link']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="?toggle=<?= $s['id'] ?>"
                                        class="px-2 py-1 rounded-full text-xs font-semibold
                                        <?= $s['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' ?>">
                                        <?= $s['is_active'] ? 'Active' : 'Off' ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-400"><?= htmlspecialchars($s['created_at']) ?></td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button
                                        onclick='openEdit(<?= json_encode($s) ?>)'
                                        class="text-gray-500 hover:text-amber-600">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Delete this entry?')"
                                        class="text-gray-500 hover:text-red-600">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- ===== ADD MODAL ===== -->
    <div id="addModal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Add Promotion Website</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <label class="text-xs font-semibold text-gray-500">Name</label>
                <input type="text" name="name" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 mb-3 mt-1 text-sm">

                <label class="text-xs font-semibold text-gray-500">Website Link</label>
                <input type="url" name="website_link" placeholder="https://example.com" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 mb-3 mt-1 text-sm">

                <label class="text-xs font-semibold text-gray-500">Image</label>
                <input type="file" name="image" accept="image/*" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 mb-4 mt-1 text-sm">

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm bg-amber-500 hover:bg-amber-600 text-white font-semibold">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== EDIT MODAL ===== -->
    <div id="editModal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Edit Promotion Website</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <label class="text-xs font-semibold text-gray-500">Name</label>
                <input type="text" name="name" id="edit_name" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 mb-3 mt-1 text-sm">

                <label class="text-xs font-semibold text-gray-500">Website Link</label>
                <input type="url" name="website_link" id="edit_link" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 mb-3 mt-1 text-sm">

                <label class="text-xs font-semibold text-gray-500">Image (leave blank to keep current)</label>
                <img id="edit_image_preview" src="" class="w-14 h-14 object-cover rounded-lg border border-gray-100 my-2 hidden">
                <input type="file" name="image" accept="image/*"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 mb-4 mt-1 text-sm">

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm bg-amber-500 hover:bg-amber-600 text-white font-semibold">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEdit(site) {
            document.getElementById('edit_id').value = site.id;
            document.getElementById('edit_name').value = site.name;
            document.getElementById('edit_link').value = site.website_link;

            const preview = document.getElementById('edit_image_preview');
            if (site.image) {
                preview.src = '<?= BASE_URL ?>/uploads/promotionwebsite/' + site.image;
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }
            document.getElementById('editModal').classList.remove('hidden');
        }
    </script>

</body>

</html>