<?php
// user/ui-page/page-6/order-tracking.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/page-6/order-tracking-functions.php';

$userId = (int) $_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if (!$orderId) {
    header('Location: ' . BASE_URL . '/orders');
    exit;
}

$data = fetchOrderTrackingData($conn, $userId, $orderId);

if ($data === null) {
    header('Location: ' . BASE_URL . '/orders');
    exit;
}

$order = $data['order'];
$isPickup = $data['isPickup'];
$bookings = $data['bookings'];
$pickupTrackings = $data['pickupTrackings'];
$replacementRequest = $data['replacementRequest'];
$officeBase = $data['officeBase'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <?php if ($isPickup): ?>
        <!-- Mapbox GL JS — static preview map ng pickup location (office o supplier) -->
        <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
        <style>
            .pickup-map {
                width: 100%;
                height: 220px;
                border-radius: 10px;
                border: 1px solid #e5e7eb;
                margin-top: 10px;
            }
        </style>
    <?php endif; ?>

    <style>
        .nhcc-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }

        .nhcc-modal-overlay.active {
            display: flex;
        }

        .nhcc-modal-box {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
        }

        .nhcc-file-label {
            border: 1.5px dashed #d1d5db;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            cursor: pointer;
            display: block;
        }

        .nhcc-file-label:hover {
            border-color: #9ca3af;
            background: #f9fafb;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <div class="max-w-3xl mx-auto px-4 py-8 flex-1 w-full">

        <a href="<?= BASE_URL ?>/orders"
            class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to My Orders
        </a>

        <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        Order #<?= htmlspecialchars($order['nhccreference'] ?: $order['id']) ?>
                    </h1>
                    <p class="text-sm text-gray-500">
                        <?= htmlspecialchars(date('M d, Y · h:i A', strtotime($order['created_at']))) ?>
                        &middot; <?= htmlspecialchars(ucfirst($order['delivery_method'])) ?>
                    </p>
                </div>
                <span class="font-semibold text-gray-900">₱<?= number_format($order['grand_total'], 2) ?></span>
            </div>
        </div>

        <!-- Refreshed in place by order-tracking-poll.php every few seconds -->
        <div id="trackingDynamic">
            <?php include ROOT_PATH . '/user/ui-page/page-6/order-tracking-partial.php'; ?>
        </div>

    </div>

    <!-- ════════════════ REQUEST REPLACEMENT MODAL ════════════════ -->
    <div id="replacementModalOverlay" class="nhcc-modal-overlay">
        <div class="nhcc-modal-box">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900">Request Replacement</h2>
                <button type="button" onclick="closeReplacementModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="replacementForm" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="rm_order_id">
                <input type="hidden" name="po_id" id="rm_po_id">
                <input type="hidden" name="booking_id" id="rm_booking_id">

                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Replacement</label>
                <textarea name="reason" id="rm_reason" rows="4" required
                    placeholder="Describe the issue (e.g. damaged item, wrong product, missing parts)..."
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>

                <label class="block text-sm font-medium text-gray-700 mb-1">Photo Proof (optional, up to 5
                    photos)</label>
                <label for="rm_photo" class="nhcc-file-label mb-1">
                    <i class="fa-solid fa-camera mb-1"></i>
                    <span id="rm_photo_filename">Click to add a photo (you can add more after)</span>
                </label>
                <input type="file" id="rm_photo" accept="image/*" class="hidden">

                <div id="rm_photo_preview" class="flex flex-wrap gap-2 mt-2"></div>

                <div id="rm_error" class="text-red-600 text-xs mt-3 hidden"></div>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" onclick="closeReplacementModal()"
                        class="px-4 py-2 text-sm rounded-md text-gray-600 hover:bg-gray-100">Cancel</button>
                    <button type="submit" id="rm_submit_btn"
                        class="px-4 py-2 text-sm rounded-md text-white bg-red-600 hover:bg-red-700 font-medium">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <?php if ($isPickup): ?>
        <script>
            mapboxgl.accessToken = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';

            const activePickupMaps = {};

            function initPickupMaps() {
                document.querySelectorAll('.pickup-map').forEach(el => {
                    const lat = parseFloat(el.dataset.lat);
                    const lng = parseFloat(el.dataset.lng);
                    if (isNaN(lat) || isNaN(lng)) return;

                    const clientLat = parseFloat(el.dataset.clientLat);
                    const clientLng = parseFloat(el.dataset.clientLng);
                    const hasClientPoint = !isNaN(clientLat) && !isNaN(clientLng);

                    const map = new mapboxgl.Map({
                        container: el,
                        style: 'mapbox://styles/mapbox/streets-v12',
                        center: [lng, lat],
                        zoom: 15,
                        interactive: true
                    });
                    map.addControl(new mapboxgl.NavigationControl(), 'top-right');

                    new mapboxgl.Marker({ color: '#4f46e5' })
                        .setLngLat([lng, lat])
                        .setPopup(new mapboxgl.Popup().setText(el.dataset.pickupAddress || 'Pickup location'))
                        .addTo(map);

                    if (hasClientPoint) {
                        new mapboxgl.Marker({ color: '#dc2626' })
                            .setLngLat([clientLng, clientLat])
                            .setPopup(new mapboxgl.Popup().setText(el.dataset.clientAddress || 'Your address'))
                            .addTo(map);

                        map.on('load', () => {
                            const bounds = new mapboxgl.LngLatBounds([lng, lat], [lng, lat]);
                            bounds.extend([clientLng, clientLat]);
                            map.fitBounds(bounds, { padding: 50, maxZoom: 15 });
                        });
                    }
                });
            }

            initPickupMaps();
        </script>
    <?php endif; ?>

    <script>
        const replacementOverlay = document.getElementById('replacementModalOverlay');
        const replacementForm = document.getElementById('replacementForm');
        const rmError = document.getElementById('rm_error');
        const rmSubmitBtn = document.getElementById('rm_submit_btn');
        const rmPhotoInput = document.getElementById('rm_photo');
        const rmPhotoFilename = document.getElementById('rm_photo_filename');
        const rmPhotoPreview = document.getElementById('rm_photo_preview');
        const MAX_PHOTOS = 5;

        let selectedFiles = []; // ─── Accumulator: dito mapupunta lahat ng napiling photos ───

        function openReplacementModal(orderId, poId, bookingId) {
            document.getElementById('rm_order_id').value = orderId ?? '';
            document.getElementById('rm_po_id').value = poId ?? '';
            document.getElementById('rm_booking_id').value = bookingId ?? '';
            document.getElementById('rm_reason').value = '';
            rmPhotoInput.value = '';
            selectedFiles = [];
            rmPhotoFilename.textContent = 'Click to add a photo (you can add more after)';
            rmPhotoPreview.innerHTML = '';
            rmError.classList.add('hidden');
            replacementOverlay.classList.add('active');
        }

        function closeReplacementModal() {
            replacementOverlay.classList.remove('active');
        }

        replacementOverlay.addEventListener('click', (e) => {
            if (e.target === replacementOverlay) closeReplacementModal();
        });

        // ─── Bawat pagkakapili (isa-isa), idadagdag sa array, di nare-replace ───
        rmPhotoInput.addEventListener('change', () => {
            if (!rmPhotoInput.files.length) return;

            const newFile = rmPhotoInput.files[0];

            if (selectedFiles.length >= MAX_PHOTOS) {
                rmError.textContent = `You can only upload up to ${MAX_PHOTOS} photos.`;
                rmError.classList.remove('hidden');
                rmPhotoInput.value = '';
                return;
            }

            rmError.classList.add('hidden');
            selectedFiles.push(newFile);
            renderPhotoPreviews();

            // ─── Reset input value para makapili ulit ng bago (kahit same filename) ───
            rmPhotoInput.value = '';
        });

        function renderPhotoPreviews() {
            rmPhotoPreview.innerHTML = '';

            rmPhotoFilename.textContent = selectedFiles.length
                ? `${selectedFiles.length} photo(s) added — click to add more`
                : 'Click to add a photo (you can add more after)';

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'relative w-16 h-16';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'w-16 h-16 object-cover rounded-md border border-gray-200';

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    removeBtn.className = 'absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs';
                    removeBtn.onclick = () => {
                        selectedFiles.splice(index, 1);
                        renderPhotoPreviews();
                    };

                    wrapper.appendChild(img);
                    wrapper.appendChild(removeBtn);
                    rmPhotoPreview.appendChild(wrapper);
                };
                reader.readAsDataURL(file);
            });
        }

        replacementForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            rmError.classList.add('hidden');

            const reason = document.getElementById('rm_reason').value.trim();
            if (!reason) {
                rmError.textContent = 'Please enter a reason for the replacement request.';
                rmError.classList.remove('hidden');
                return;
            }

            // ─── Manual na pag-build ng FormData (kasi galing sa JS array, hindi sa raw input) ───
            const formData = new FormData();
            formData.append('order_id', document.getElementById('rm_order_id').value);
            formData.append('po_id', document.getElementById('rm_po_id').value);
            formData.append('booking_id', document.getElementById('rm_booking_id').value);
            formData.append('reason', reason);

            selectedFiles.forEach(file => {
                formData.append('photos[]', file);
            });

            rmSubmitBtn.disabled = true;
            rmSubmitBtn.textContent = 'Submitting...';

            try {
                const res = await fetch('<?= BASE_URL ?>/request-replacement-submit', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    closeReplacementModal();
                    alert('Replacement request submitted! We will review it shortly.');
                    location.reload();
                } else {
                    rmError.textContent = data.message || 'Something went wrong. Please try again.';
                    rmError.classList.remove('hidden');
                }
            } catch (err) {
                rmError.textContent = 'Network error. Please try again.';
                rmError.classList.remove('hidden');
            } finally {
                rmSubmitBtn.disabled = false;
                rmSubmitBtn.textContent = 'Submit Request';
            }
        });
    </script>

    <!-- ════════════════ LIVE TRACKING POLL ════════════════ -->
    <script>
        (function () {
            const POLL_INTERVAL_MS = 8000;
            const ORDER_ID = <?= (int) $order['id'] ?>;
            const IS_PICKUP = <?= $isPickup ? 'true' : 'false' ?>;
            const dynamicEl = document.getElementById('trackingDynamic');
            const pollUrl = BASE_URL + '/order-tracking-poll?order_id=' + ORDER_ID;

            let pollTimer = null;
            let isPolling = false;
            let lastVersion = null;

            function flashIfChanged(newVersion) {
                if (lastVersion && lastVersion !== newVersion) {
                    dynamicEl.querySelectorAll(':scope > div').forEach(function (card) {
                        card.classList.add('ring-2', 'ring-indigo-200');
                        setTimeout(function () {
                            card.classList.remove('ring-2', 'ring-indigo-200');
                        }, 2500);
                    });
                }
                lastVersion = newVersion;
            }

            async function poll() {
                // Huwag mag-poll habang bukas ang replacement modal — para hindi
                // basta mawala ang form kung sakaling mag-refresh ang tracking card.
                if (isPolling || document.hidden || replacementOverlay.classList.contains('active')) {
                    return;
                }
                isPolling = true;
                try {
                    const res = await fetch(pollUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store',
                        credentials: 'same-origin'
                    });
                    if (!res.ok) throw new Error('poll_failed_' + res.status);
                    const data = await res.json();

                    dynamicEl.innerHTML = data.html;
                    flashIfChanged(data.version);

                    if (IS_PICKUP && typeof initPickupMaps === 'function') {
                        initPickupMaps();
                    }
                } catch (err) {
                    // Tahimik lang mag-fail; susubukan ulit sa next interval.
                } finally {
                    isPolling = false;
                }
            }

            function schedulePoll() {
                pollTimer = setTimeout(function () {
                    poll().finally(schedulePoll);
                }, POLL_INTERVAL_MS);
            }

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    clearTimeout(pollTimer);
                    poll().finally(schedulePoll);
                }
            });

            schedulePoll();
        })();
    </script>

</body>

</html>