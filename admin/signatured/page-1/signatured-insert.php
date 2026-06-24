<?php
// signature-insert.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE, ROLE_IT, ROLE_ACCOUNTING, ROLE_HR, ROLE_OPERATIONS, ROLE_SALES, ROLE_GRAPHIC, ROLE_DESIGNER, ROLE_CUTTING, ROLE_PRODUCTSPECIALIST, ROLE_LOGISTIC, ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$userId = $_SESSION['account_id'];
$successMsg = '';
$errorMsg = '';

// ── Handle form submissions ────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Set active signature
if (isset($_POST['action']) && $_POST['action'] === 'set_active') {
    $sigId = (int) $_POST['signature_id'];
    
    // 1. Clear all active for this user in noblesignature
    $stmt = $conn->prepare("UPDATE noblesignature SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 2. Set selected as active in noblesignature
    $stmt = $conn->prepare("UPDATE noblesignature SET is_active = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sigId, $userId);
    $stmt->execute();
    
    // 3. Update noblerole.active_signature_id  <-- DAGDAG
    $stmt = $conn->prepare("UPDATE noblerole SET active_signature_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $sigId, $userId);
    $stmt->execute();
    
    $successMsg = 'Active signature updated.';
}

    // Upload image signature
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === 0) {
            $allowed = ['image/png', 'image/jpeg', 'image/webp'];
            $mime = mime_content_type($_FILES['signature_image']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                $errorMsg = 'Only PNG, JPG, and WebP images are allowed.';
            } else {
                $uploadDir = ROOT_PATH . '/uploads/signatures/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                $ext = pathinfo($_FILES['signature_image']['name'], PATHINFO_EXTENSION);
                $filename = 'sig_' . $userId . '_' . time() . '.' . strtolower($ext);
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['signature_image']['tmp_name'], $destination)) {
                    $label = htmlspecialchars(trim($_POST['label_upload'] ?? 'Uploaded Signature'));
                    $imgPath = '/uploads/signatures/' . $filename;
                    $type = 'upload';
                    $stmt = $conn->prepare("INSERT INTO noblesignature (user_id, label, type, image_path, is_active, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                    $stmt->bind_param("isss", $userId, $label, $type, $imgPath);
                    $stmt->execute();
                    $successMsg = 'Signature uploaded successfully.';
                } else {
                    $errorMsg = 'Failed to save the uploaded file.';
                }
            }
        } else {
            $errorMsg = 'No file received or an upload error occurred.';
        }
    }

    // Save drawn signature
    if (isset($_POST['action']) && $_POST['action'] === 'draw') {
        $dataUrl = $_POST['signature_data'] ?? '';
        if (empty($dataUrl) || !str_starts_with($dataUrl, 'data:image/png;base64,')) {
            $errorMsg = 'Signature canvas is empty. Please draw your signature first.';
        } else {
            $uploadDir = ROOT_PATH . '/uploads/signatures/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
            $imageData = base64_decode($base64);
            $filename = 'sig_drawn_' . $userId . '_' . time() . '.png';
            $destination = $uploadDir . $filename;
            if (file_put_contents($destination, $imageData)) {
                $label = htmlspecialchars(trim($_POST['label_draw'] ?? 'Drawn Signature'));
                $imgPath = '/uploads/signatures/' . $filename;
                $type = 'drawn';
                $stmt = $conn->prepare("INSERT INTO noblesignature (user_id, label, type, image_path, is_active, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                $stmt->bind_param("isss", $userId, $label, $type, $imgPath);
                $stmt->execute();
                $successMsg = 'Drawn signature saved successfully.';
            } else {
                $errorMsg = 'Failed to save drawn signature.';
            }
        }
    }

    // Delete signature
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $sigId = (int) $_POST['signature_id'];
    $stmt = $conn->prepare("SELECT image_path, is_active FROM noblesignature WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sigId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $filePath = ROOT_PATH . $result['image_path'];
        if (file_exists($filePath)) @unlink($filePath);
        
        $stmt = $conn->prepare("DELETE FROM noblesignature WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $sigId, $userId);
        $stmt->execute();
        
        // If deleted was the active one, clear noblerole.active_signature_id  <-- DAGDAG
        if ($result['is_active']) {
            $stmt = $conn->prepare("UPDATE noblerole SET active_signature_id = NULL WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
        
        $successMsg = 'Signature deleted.';
    }
}
}

// ── Fetch user's signatures ────────────────────────────────────────────────────

$stmt = $conn->prepare("SELECT * FROM noblesignature WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$signatures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activeId = null;
foreach ($signatures as $sig) {
    if ($sig['is_active']) {
        $activeId = $sig['id'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Signatures</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
    <style>
        /* Canvas signature pad */
        #signature-canvas {
            border: 1.5px dashed #CBD5E1;
            border-radius: 10px;
            cursor: crosshair;
            background: #fff;
            touch-action: none;
            min-height: 220px;
        }

        #signature-canvas.has-drawing {
            border-color: #6366f1;
            border-style: solid;
        }

        .sig-card {
            transition: box-shadow 0.15s, border-color 0.15s;
        }

        .sig-card:hover {
            box-shadow: 0 4px 24px 0 rgba(99, 102, 241, 0.10);
        }

        .sig-card.active-sig {
            border-color: #6366f1 !important;
        }

        /* Tab toggle */
        .tab-btn.active {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }
    </style>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-slate-800">My Signatures</h1>
            <p class="text-slate-500 text-sm mt-1">Add a signature by uploading an image or drawing one. Set one as
                active to use on documents.</p>
        </div>

        <!-- Flash Messages -->
        <?php if ($successMsg): ?>
            <div
                class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <?= $successMsg ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div
                class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <?= $errorMsg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            <!-- ── LEFT: Add Signature Panel ── -->
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="text-base font-semibold text-slate-700 mb-4">Add New Signature</h2>

                <!-- Tab Toggle -->
                <div class="flex gap-2 mb-5">
                    <button type="button" onclick="switchTab('upload')" id="tab-upload"
                        class="tab-btn active text-sm px-4 py-2 rounded-lg border border-slate-300 font-medium transition">
                        Upload Image
                    </button>
                    <button type="button" onclick="switchTab('draw')" id="tab-draw"
                        class="tab-btn text-sm px-4 py-2 rounded-lg border border-slate-300 font-medium transition text-slate-600">
                        Draw Signature
                    </button>
                </div>

                <!-- UPLOAD TAB -->
                <div id="panel-upload">
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload">

                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Label</label>
                            <input type="text" name="label_upload" placeholder="e.g. Official Signature"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Signature Image</label>
                            <div id="upload-dropzone"
                                class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer hover:border-indigo-400 transition"
                                onclick="document.getElementById('sig-file').click()">
                                <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor"
                                    stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 16.5V9.75m0 0-3 3m3-3 3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.338-2.32 5.75 5.75 0 0 1 1.284 4.845A4.5 4.5 0 0 1 17.25 19.5H6.75Z" />
                                </svg>
                                <p class="text-sm text-slate-500" id="upload-label-text">Click to browse or drag & drop
                                </p>
                                <p class="text-xs text-slate-400 mt-1">PNG, JPG, WebP — max 5MB</p>
                            </div>
                            <input type="file" id="sig-file" name="signature_image"
                                accept="image/png,image/jpeg,image/webp" class="hidden">
                        </div>

                        <div id="upload-preview" class="hidden">
                            <p class="text-xs text-slate-400 mb-1">Preview</p>
                            <img id="preview-img" src="#" alt="preview"
                                class="max-h-28 border border-slate-200 rounded-lg p-2 bg-slate-50 object-contain">
                        </div>

                        <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                            Save Uploaded Signature
                        </button>
                    </form>
                </div>

                <!-- DRAW TAB -->
                <div id="panel-draw" class="hidden">
                    <form method="POST" id="draw-form" class="space-y-4">
                        <input type="hidden" name="action" value="draw">
                        <input type="hidden" name="signature_data" id="canvas-data">

                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Label</label>
                            <input type="text" name="label_draw" placeholder="e.g. My Handwritten Signature"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-sm font-medium text-slate-600">Draw your signature</label>
                                <button type="button" onclick="clearCanvas()"
                                    class="text-xs text-slate-400 hover:text-red-500 transition underline">Clear</button>
                            </div>
                            <canvas id="signature-canvas" width="500" height="220" class="w-full block"></canvas>
                            <p class="text-xs text-slate-400 mt-1">Use your mouse or finger to draw inside the box
                                above.</p>
                        </div>

                        <!-- Pen options -->
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-slate-500">Size</label>
                                <input type="range" id="pen-size" min="1" max="8" value="3" class="w-20">
                                <span id="pen-size-val" class="text-xs text-slate-500 w-4">3</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-slate-500">Color</label>
                                <input type="color" id="pen-color" value="#1e293b"
                                    class="w-8 h-6 rounded cursor-pointer border border-slate-200">
                            </div>
                        </div>

                        <button type="submit" onclick="return prepareCanvas()"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                            Save Drawn Signature
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── RIGHT: Signature List ── -->
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-slate-700">Your Signatures</h2>
                    <span class="text-xs text-slate-400"><?= count($signatures) ?> saved</span>
                </div>

                <?php if (empty($signatures)): ?>
                    <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <svg class="w-10 h-10 mb-3" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                        <p class="text-sm">No signatures yet. Add one on the left.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-[520px] overflow-y-auto pr-1">
                        <?php foreach ($signatures as $sig): ?>
                            <div
                                class="sig-card border rounded-xl p-4 flex items-center gap-4 <?= $sig['is_active'] ? 'active-sig border-indigo-400 bg-indigo-50/40' : 'border-slate-200' ?>">

                                <!-- Signature preview -->
                                <div
                                    class="flex-shrink-0 w-28 h-16 bg-white border border-slate-200 rounded-lg overflow-hidden flex items-center justify-center">
                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($sig['image_path']) ?>" alt="signature"
                                        class="max-w-full max-h-full object-contain p-1">
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-medium text-slate-700 truncate">
                                            <?= htmlspecialchars($sig['label']) ?>
                                        </p>
                                        <?php if ($sig['is_active']): ?>
                                            <span
                                                class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                                        <?php endif; ?>
                                        <span
                                            class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full capitalize"><?= htmlspecialchars($sig['type']) ?></span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">
                                        <?= date('M d, Y g:i A', strtotime($sig['created_at'])) ?>
                                    </p>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-col gap-2 flex-shrink-0">
                                    <?php if (!$sig['is_active']): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="set_active">
                                            <input type="hidden" name="signature_id" value="<?= $sig['id'] ?>">
                                            <button type="submit"
                                                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition whitespace-nowrap">
                                                Set Active
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Delete this signature?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="signature_id" value="<?= $sig['id'] ?>">
                                        <button type="submit"
                                            class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition w-full">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /grid -->
    </div><!-- /ml-60 -->

    <script>
        // ── Tab switching ──────────────────────────────────────────────────────────────
        function switchTab(tab) {
            const panels = { upload: document.getElementById('panel-upload'), draw: document.getElementById('panel-draw') };
            const btns = { upload: document.getElementById('tab-upload'), draw: document.getElementById('tab-draw') };
            Object.keys(panels).forEach(k => {
                panels[k].classList.toggle('hidden', k !== tab);
                btns[k].classList.toggle('active', k === tab);
                btns[k].classList.toggle('text-slate-600', k !== tab);
            });
        }

        // ── Upload preview ─────────────────────────────────────────────────────────────
        document.getElementById('sig-file').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            document.getElementById('upload-label-text').textContent = file.name;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('preview-img').src = e.target.result;
                document.getElementById('upload-preview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });

        // Drag-and-drop
        const dz = document.getElementById('upload-dropzone');
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-indigo-400', 'bg-indigo-50'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('border-indigo-400', 'bg-indigo-50'));
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.remove('border-indigo-400', 'bg-indigo-50');
            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                document.getElementById('sig-file').files = dt.files;
                document.getElementById('sig-file').dispatchEvent(new Event('change'));
            }
        });

        // ── Signature canvas (draw pad) ────────────────────────────────────────────────
        const canvas = document.getElementById('signature-canvas');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasDrawn = false;

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const src = e.touches ? e.touches[0] : e;
            return { x: (src.clientX - rect.left) * scaleX, y: (src.clientY - rect.top) * scaleY };
        }

        function startDraw(e) {
            e.preventDefault();
            drawing = true;
            const p = getPos(e);
            applyPen();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);

            // Draw a dot on single click/tap
            const size = document.getElementById('pen-size').value;
            ctx.beginPath();
            ctx.arc(p.x, p.y, size / 2, 0, Math.PI * 2);
            ctx.fillStyle = document.getElementById('pen-color').value;
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);

            hasDrawn = true;
            canvas.classList.add('has-drawing');
        }
        function draw(e) { if (!drawing) return; e.preventDefault(); const p = getPos(e); applyPen(); ctx.lineTo(p.x, p.y); ctx.stroke(); hasDrawn = true; canvas.classList.add('has-drawing'); }
        function stopDraw() { drawing = false; }
        function applyPen() { ctx.lineWidth = document.getElementById('pen-size').value; ctx.strokeStyle = document.getElementById('pen-color').value; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseleave', stopDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDraw);

        document.getElementById('pen-size').addEventListener('input', function () {
            document.getElementById('pen-size-val').textContent = this.value;
        });

        function clearCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            canvas.classList.remove('has-drawing');
        }

        function prepareCanvas() {
            if (!hasDrawn) {
                alert('Please draw your signature first.');
                return false;
            }

            // Create a white-background copy for saving
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;
            const tempCtx = tempCanvas.getContext('2d');

            // White background
            tempCtx.fillStyle = '#ffffff';
            tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);

            // Draw signature on top
            tempCtx.drawImage(canvas, 0, 0);

            document.getElementById('canvas-data').value = tempCanvas.toDataURL('image/png');
            return true;
        }
    </script>

</body>

</html>