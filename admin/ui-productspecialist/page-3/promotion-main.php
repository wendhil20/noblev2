<?php
// promotion-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// ---------- helpers ----------
$uploadDir = ROOT_PATH . '/uploads/promotions/';
if (!is_dir($uploadDir))
    mkdir($uploadDir, 0755, true);

$errors = [];
$success = '';
$editData = null;

// ---------- handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // validation
    if ($title === '')
        $errors[] = 'Title is required.';
    if ($start_date === '')
        $errors[] = 'Start date is required.';
    if ($end_date === '')
        $errors[] = 'End date is required.';
    if ($start_date && $end_date && $end_date < $start_date)
        $errors[] = 'End date must be after start date.';

    $imageName = null;

    // handle image upload
    if (!empty($_FILES['banner_image']['name'])) {
        $file = $_FILES['banner_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file['type'], $allowed))
            $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
        elseif ($file['size'] > $maxSize)
            $errors[] = 'Image must be 5 MB or smaller.';
        else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $imageName = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
                $errors[] = 'Failed to upload image.';
                $imageName = null;
            }
        }
    }

    if (empty($errors)) {
        if ($action === 'add') {
            // INSERT
            $stmt = $conn->prepare(
                "INSERT INTO noblepromotions (title, description, banner_image, start_date, end_date, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sssssi', $title, $description, $imageName, $start_date, $end_date, $is_active);
            $stmt->execute();
            $stmt->close();
            $success = 'Promotion added successfully!';

        } elseif ($action === 'update' && $id > 0) {
            // keep old image if no new upload
            if ($imageName === null) {
                $stmt = $conn->prepare(
                    "UPDATE noblepromotions SET title=?, description=?, start_date=?, end_date=?, is_active=? WHERE id=?"
                );
                $stmt->bind_param('ssssii', $title, $description, $start_date, $end_date, $is_active, $id);
                // fix spacing
                $stmt->bind_param('ssssii', $title, $description, $start_date, $end_date, $is_active, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                // delete old image file
                $old = $conn->prepare("SELECT banner_image FROM noblepromotions WHERE id=?");
                $old->bind_param('i', $id);
                $old->execute();
                $old->bind_result($oldImg);
                $old->fetch();
                $old->close();
                if ($oldImg && file_exists($uploadDir . $oldImg))
                    unlink($uploadDir . $oldImg);

                $stmt = $conn->prepare(
                    "UPDATE noblepromotions SET title=?, description=?, banner_image=?, start_date=?, end_date=?, is_active=? WHERE id=?"
                );
                $stmt->bind_param('sssssii', $title, $description, $imageName, $start_date, $end_date, $is_active, $id);
                $stmt->execute();
                $stmt->close();
            }
            $success = 'Promotion updated successfully!';
        }
    }
}

// fetch for edit
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM noblepromotions WHERE id=?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editData = $result->fetch_assoc();
    $stmt->close();
}

// fetch all promotions
$promotions = $conn->query("SELECT * FROM noblepromotions ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Page header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Promotion Banners</h1>
                <p class="text-sm text-slate-500 mt-1">Manage promotional banners shown to users.</p>
            </div>
            <button onclick="openModal('add')"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add Promotion
            </button>
        </div>

        <!-- Flash messages -->
        <?php if ($success): ?>
            <div
                class="mb-4 flex items-center gap-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
                <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm space-y-1">
                <?php foreach ($errors as $e): ?>
                    <p class="flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 text-red-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01" />
                        </svg>
                        <?= htmlspecialchars($e) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Promotions Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php while ($promo = $promotions->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                    <!-- Banner image -->
                    <div class="relative h-40 bg-slate-100">
                        <?php if ($promo['banner_image']): ?>
                            <img src="<?= BASE_URL ?>/uploads/promotions/<?= htmlspecialchars($promo['banner_image']) ?>"
                                alt="<?= htmlspecialchars($promo['title']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="flex h-full items-center justify-center text-slate-300">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <path d="M3 9l4-4 4 4 4-4 4 4" />
                                    <circle cx="8.5" cy="13.5" r="1.5" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        <!-- Status badge -->
                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-semibold
                    <?= $promo['is_active'] ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-500' ?>">
                            <?= $promo['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>

                    <!-- Card body -->
                    <div class="p-4 flex flex-col flex-1">
                        <h3 class="font-semibold text-slate-800 text-sm leading-snug mb-1">
                            <?= htmlspecialchars($promo['title']) ?>
                        </h3>
                        <?php if ($promo['description']): ?>
                            <p class="text-xs text-slate-500 line-clamp-2 mb-3">
                                <?= htmlspecialchars($promo['description']) ?>
                            </p>
                        <?php endif; ?>
                        <div class="mt-auto flex items-center justify-between text-xs text-slate-400">
                            <span><?= date('M d, Y', strtotime($promo['start_date'])) ?> –
                                <?= date('M d, Y', strtotime($promo['end_date'])) ?></span>
                        </div>
                    </div>

                    <!-- Card footer -->
                    <div class="border-t border-slate-100 px-4 py-3">
                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($promo)) ?>)"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.232 5.232l3.536 3.536M9 13l6.5-6.5a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.414V19h2.586a2 2 0 001.414-.586L20 12.414" />
                            </svg>
                            Edit Promotion
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>

            <?php if ($promotions->num_rows === 0): ?>
                <div class="col-span-full flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="w-14 h-14 mb-3" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01" />
                    </svg>
                    <p class="text-sm font-medium">No promotions yet.</p>
                    <p class="text-xs mt-1">Click <strong>Add Promotion</strong> to create your first banner.</p>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /ml-60 -->

    <!-- ============================================================
     MODAL — Add / Edit
     ============================================================ -->
    <div id="promoModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">

            <!-- Modal header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h2 id="modalTitle" class="text-base font-bold text-slate-800">Add Promotion</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal form -->
            <form id="promoForm" method="POST" enctype="multipart/form-data" class="px-6 py-5 space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="0">

                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Title <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="title" id="fieldTitle" required
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition"
                        placeholder="e.g. Summer Sale 2025">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                    <textarea name="description" id="fieldDescription" rows="3"
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition resize-none"
                        placeholder="Short description about the promotion..."></textarea>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Start Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="start_date" id="fieldStartDate" required
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">End Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="end_date" id="fieldEndDate" required
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                </div>

                <!-- Banner image -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                        Banner Image <span id="imageRequiredMark" class="text-red-500">*</span>
                        <span id="imageOptionalNote" class="hidden font-normal text-slate-400">(leave blank to keep
                            current)</span>
                    </label>
                    <!-- Preview -->
                    <div id="imagePreviewWrap" class="mb-2 hidden">
                        <img id="imagePreview" src="" alt="Current banner"
                            class="h-28 w-full object-cover rounded-lg border border-slate-200">
                    </div>
                    <label
                        class="flex flex-col items-center justify-center gap-2 cursor-pointer rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-5 hover:border-indigo-400 hover:bg-indigo-50 transition"
                        id="dropZone">
                        <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <span id="dropLabel" class="text-xs text-slate-500">Click to upload or drag & drop</span>
                        <span class="text-xs text-slate-400">JPG, PNG, WEBP, GIF · max 5 MB</span>
                        <input type="file" name="banner_image" id="fieldImage" accept="image/*" class="hidden"
                            onchange="previewImage(this)">
                    </label>
                </div>

                <!-- Active toggle -->
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_active" id="fieldIsActive" value="1"
                        class="w-4 h-4 accent-indigo-600 cursor-pointer">
                    <label for="fieldIsActive" class="text-sm text-slate-700 cursor-pointer">
                        Mark as Active
                    </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal()"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow">
                        Save Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('promoModal');

        function openModal(mode) {
            document.getElementById('modalTitle').textContent = 'Add Promotion';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formId').value = '0';
            document.getElementById('fieldTitle').value = '';
            document.getElementById('fieldDescription').value = '';
            document.getElementById('fieldStartDate').value = '';
            document.getElementById('fieldEndDate').value = '';
            document.getElementById('fieldIsActive').checked = false;
            document.getElementById('fieldImage').value = '';
            document.getElementById('imagePreviewWrap').classList.add('hidden');
            document.getElementById('imageRequiredMark').classList.remove('hidden');
            document.getElementById('imageOptionalNote').classList.add('hidden');
            document.getElementById('dropLabel').textContent = 'Click to upload or drag & drop';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openEditModal(promo) {
            document.getElementById('modalTitle').textContent = 'Edit Promotion';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = promo.id;
            document.getElementById('fieldTitle').value = promo.title || '';
            document.getElementById('fieldDescription').value = promo.description || '';
            document.getElementById('fieldStartDate').value = promo.start_date || '';
            document.getElementById('fieldEndDate').value = promo.end_date || '';
            document.getElementById('fieldIsActive').checked = promo.is_active == 1;
            document.getElementById('fieldImage').value = '';
            document.getElementById('dropLabel').textContent = 'Click to replace image';

            // image preview
            const wrap = document.getElementById('imagePreviewWrap');
            const img = document.getElementById('imagePreview');
            if (promo.banner_image) {
                img.src = '<?= BASE_URL ?>/uploads/promotions/' + promo.banner_image;
                wrap.classList.remove('hidden');
            } else {
                wrap.classList.add('hidden');
            }

            document.getElementById('imageRequiredMark').classList.add('hidden');
            document.getElementById('imageOptionalNote').classList.remove('hidden');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // close on backdrop click
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        function previewImage(input) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                const wrap = document.getElementById('imagePreviewWrap');
                const img = document.getElementById('imagePreview');
                img.src = e.target.result;
                wrap.classList.remove('hidden');
                document.getElementById('dropLabel').textContent = input.files[0].name;
            };
            reader.readAsDataURL(input.files[0]);
        }

        // drag & drop
        const dropZone = document.getElementById('dropZone');
        dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-indigo-400', 'bg-indigo-50'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-indigo-400', 'bg-indigo-50'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('border-indigo-400', 'bg-indigo-50');
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                const input = document.getElementById('fieldImage');
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                previewImage(input);
            }
        });

        // open modal if there were validation errors on add/update
        <?php if (!empty($errors)): ?>
            <?php if (!empty($_POST['action'])): ?>
                openModal('<?= $_POST['action'] === 'update' ? 'edit' : 'add' ?>');
                <?php if ($_POST['action'] === 'update'): ?>
                    document.getElementById('formId').value = '<?= (int) $_POST['id'] ?>';
                    document.getElementById('fieldTitle').value = <?= json_encode($_POST['title'] ?? '') ?>;
                    document.getElementById('fieldDescription').value = <?= json_encode($_POST['description'] ?? '') ?>;
                    document.getElementById('fieldStartDate').value = <?= json_encode($_POST['start_date'] ?? '') ?>;
                    document.getElementById('fieldEndDate').value = <?= json_encode($_POST['end_date'] ?? '') ?>;
                    document.getElementById('fieldIsActive').checked = <?= !empty($_POST['is_active']) ? 'true' : 'false' ?>;
                    document.getElementById('imageRequiredMark').classList.add('hidden');
                    document.getElementById('imageOptionalNote').classList.remove('hidden');
                    document.getElementById('modalTitle').textContent = 'Edit Promotion';
                    document.getElementById('formAction').value = 'update';
                <?php else: ?>
                    document.getElementById('fieldTitle').value = <?= json_encode($_POST['title'] ?? '') ?>;
                    document.getElementById('fieldDescription').value = <?= json_encode($_POST['description'] ?? '') ?>;
                    document.getElementById('fieldStartDate').value = <?= json_encode($_POST['start_date'] ?? '') ?>;
                    document.getElementById('fieldEndDate').value = <?= json_encode($_POST['end_date'] ?? '') ?>;
                    document.getElementById('fieldIsActive').checked = <?= !empty($_POST['is_active']) ? 'true' : 'false' ?>;
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </script>

</body>

</html>