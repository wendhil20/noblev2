<?php
//warehousestaff-pickupproof.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$poId = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if (!$poId) {
    header('Location: ' . BASE_URL . '/warehousestaff');
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        ppl.nhccreference,
        ppl.contact_name,
        ppl.delivery_method,
        ot.current_step,
        ot.pickup_location,
        ot.pickup_driver_name,
        ot.pickup_plate_number,
        ot.pickup_truck_details,
        npo.po_number
    FROM nobleordertracking ot
    JOIN noblepaidproductlist ppl ON ppl.id = ot.order_id
    JOIN noblepurchaseorder npo ON npo.id = ot.po_id
    WHERE ot.po_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $poId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: ' . BASE_URL . '/warehousestaff');
    exit;
}

// Dapat pickup method, at kasalukuyan ay step 2 (Item ready) — kung iba, hindi pa dapat dito
if ($order['delivery_method'] !== 'pickup' || (int) $order['current_step'] !== 2) {
    header('Location: ' . BASE_URL . '/warehousestaff-trackpo?po_id=' . $poId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proof of Pickup</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <div class="mb-6">
            <a href="<?= BASE_URL ?>/warehousestaff-trackpo?po_id=<?= $poId ?>"
                class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back
            </a>
            <h1 class="text-2xl font-semibold text-slate-800">Proof of Pickup</h1>
            <p class="text-sm text-slate-500 mt-1">
                <?= htmlspecialchars($order['nhccreference'] ?? '—') ?>
                — <?= htmlspecialchars($order['contact_name']) ?>
                <span class="ml-2 font-mono text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-600">
                    <?= htmlspecialchars($order['po_number']) ?>
                </span>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 max-w-lg">

            <!-- Summary ng pickup details -->
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-5">
                <p class="text-xs text-slate-500 mb-2">Pickup Details</p>
                <p class="text-sm text-slate-700">
                    Lokasyon: <span
                        class="font-medium"><?= $order['pickup_location'] === 'office' ? 'Office (Warehouse)' : 'Supplier Location' ?></span>
                </p>
                <p class="text-sm text-slate-700 mt-1">
                    Driver: <span class="font-medium"><?= htmlspecialchars($order['pickup_driver_name']) ?></span>
                    · Plate: <span class="font-medium"><?= htmlspecialchars($order['pickup_plate_number']) ?></span>
                </p>
                <?php if (!empty($order['pickup_truck_details'])): ?>
                    <p class="text-sm text-slate-700 mt-1"><?= htmlspecialchars($order['pickup_truck_details']) ?></p>
                <?php endif; ?>
            </div>

            <p class="text-xs text-slate-500 mb-4">
                Mag-upload ng litrato bilang patunay na nakuha na ng customer ang item (hal. larawan ng customer kasama
                ang item, o signed pickup form). JPG, PNG, or WEBP only.
            </p>

            <form id="pickupProofForm">
                <input type="hidden" name="po_id" value="<?= $poId ?>">

                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Photo <span
                            class="text-red-500">*</span></label>
                    <input type="file" id="proofFile" accept="image/jpeg,image/png,image/webp"
                        onchange="previewProofFile(this)"
                        class="w-full text-sm text-slate-600 border border-slate-200 rounded-lg px-3 py-2.5 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-emerald-50 file:text-emerald-700 file:text-xs file:font-medium">
                    <p id="proofFileError" class="hidden text-xs text-red-500 mt-1"></p>
                </div>

                <div id="proofPreviewWrap" class="hidden mb-4">
                    <img id="proofPreview" class="w-full h-44 object-cover rounded-lg border border-slate-200">
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes <span
                            class="text-slate-400 font-normal">(optional)</span></label>
                    <textarea name="notes" id="proofNotes" rows="3" placeholder="Add any notes about this pickup..."
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400 resize-none"></textarea>
                </div>

                <div class="flex gap-2">
                    <a href="<?= BASE_URL ?>/warehousestaff-trackpo?po_id=<?= $poId ?>"
                        class="flex-1 text-center px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                        Cancel
                    </a>
                    <button type="button" onclick="submitProof()" id="proofSubmitBtn"
                        class="flex-1 px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                        Confirm Picked Up
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewProofFile(input) {
            const err = document.getElementById('proofFileError');
            const wrap = document.getElementById('proofPreviewWrap');
            const img = document.getElementById('proofPreview');
            err.classList.add('hidden');

            const file = input.files[0];
            if (!file) { wrap.classList.add('hidden'); return; }

            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                err.textContent = 'Only JPG, PNG, or WEBP images are allowed.';
                err.classList.remove('hidden');
                input.value = '';
                wrap.classList.add('hidden');
                return;
            }
            if (file.size > 8 * 1024 * 1024) {
                err.textContent = 'Image is too large. Max size is 8MB.';
                err.classList.remove('hidden');
                input.value = '';
                wrap.classList.add('hidden');
                return;
            }

            const reader = new FileReader();
            reader.onload = e => { img.src = e.target.result; wrap.classList.remove('hidden'); };
            reader.readAsDataURL(file);
        }

        function submitProof() {
            const fileInput = document.getElementById('proofFile');
            const file = fileInput.files[0];
            const err = document.getElementById('proofFileError');

            if (!file) {
                err.textContent = 'Please attach a proof of pickup photo.';
                err.classList.remove('hidden');
                return;
            }
            if (!confirm('Confirm na nakuha na ng customer ang item?')) return;

            const btn = document.getElementById('proofSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Uploading…';

            const formData = new FormData();
            formData.append('po_id', document.querySelector('[name="po_id"]').value);
            formData.append('notes', document.getElementById('proofNotes').value);
            formData.append('proof_of_pickup', file);

            fetch('<?= BASE_URL ?>/warehouse-pickupcomplete', {
                method: 'POST',
                body: formData,
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '<?= BASE_URL ?>/warehousestaff';
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        btn.disabled = false;
                        btn.textContent = 'Confirm Picked Up';
                    }
                })
                .catch(() => {
                    alert('Network error.');
                    btn.disabled = false;
                    btn.textContent = 'Confirm Picked Up';
                });
        }
    </script>
</body>

</html>