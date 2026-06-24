<?php
//warehousestaff-trackpo.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_WAREHOUSE];
$allowedPositions = [POSITION_WAREHOUSESTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$poId = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if (!$poId) {
    header('Location: ' . BASE_URL . '/warehouse-staffmain');
    exit;
}

// Kunin muna ang PO info
$stmtPO = $conn->prepare("
    SELECT npo.id, npo.order_id, npo.supplier_id, npo.po_number,
           ppl.nhccreference, ppl.contact_name, ppl.delivery_method
    FROM noblepurchaseorder npo
    JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
    WHERE npo.id = ?
    LIMIT 1
");
$stmtPO->bind_param("i", $poId);
$stmtPO->execute();
$poData = $stmtPO->get_result()->fetch_assoc();
$stmtPO->close();

if (!$poData) {
    header('Location: ' . BASE_URL . '/warehouse-staffmain');
    exit;
}

// Check kung may tracking na
$stmtCheck = $conn->prepare("SELECT * FROM nobleordertracking WHERE po_id = ? LIMIT 1");
$stmtCheck->bind_param("i", $poId);
$stmtCheck->execute();
$existingTrack = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

// Kung wala, gumawa ng bagong tracking record
if (!$existingTrack) {
    $stmtInsert = $conn->prepare("
        INSERT INTO nobleordertracking (order_id, po_id, delivery_method, current_step)
        VALUES (?, ?, ?, 0)
    ");
    $stmtInsert->bind_param("iis", $poData['order_id'], $poId, $poData['delivery_method']);
    $stmtInsert->execute();
    $stmtInsert->close();
}

// Ngayon kunin ang tracking
$stmt = $conn->prepare("
    SELECT 
        ppl.nhccreference,
        ppl.contact_name,
        ppl.delivery_method,
        ot.po_id,
        ot.order_id,
        ot.current_step,
        ot.expected_delivery_from,
        ot.expected_delivery_to,
        ot.pickup_location,
        ot.pickup_driver_name,
        ot.pickup_plate_number,
        ot.pickup_truck_details,
        ot.proof_of_pickup_path,
        ot.supplier_address,
        ot.supplier_latitude,
        ot.supplier_longitude,
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
    header('Location: ' . BASE_URL . '/warehouse-staffmain');
    exit;
}

// Office pickup base location — para hindi na hardcoded, kunin mula sa DB
$stmtOffice = $conn->prepare("SELECT placename, latitude, longitude FROM noblewarehousebase LIMIT 1");
$stmtOffice->execute();
$officeBase = $stmtOffice->get_result()->fetch_assoc();
$stmtOffice->close();

$isDelivery = $order['delivery_method'] === 'delivery';
$currentStep = (int) ($order['current_step'] ?? 0);

$deliverySteps = [
    0 => 'Passed to supplier',
    1 => 'Processing',
    2 => 'Expected delivery',
    3 => 'Out for delivery',
    4 => 'Assign receiving',
    5 => 'Completed',
];
$pickupSteps = [
    0 => 'Passed to supplier',
    1 => 'Processing',
    2 => 'Item ready',
    3 => 'Picked up',
];

$steps = $isDelivery ? $deliverySteps : $pickupSteps;
$maxStep = count($steps) - 1;
$nextStep = min($currentStep + 1, $maxStep);

// Kailan ipapakita ang pickup location + driver/plate fields: kapag pickup method, at next step ay "Item ready" (step 2)
$showPickupLocationField = (!$isDelivery && $nextStep === 2);

// Kailan dapat i-redirect sa proof of pickup page imbes na generic form: kapag pickup method,
// kasalukuyan ay "Item ready" (step 2) na, at ang susunod na step ay ang final step ("Picked up")
$isPickupAwaitingProof = (!$isDelivery && $currentStep === 2 && $nextStep === $maxStep);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Tracking</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <?php if ($showPickupLocationField): ?>
        <!-- Mapbox GL JS — only needed when staff may need to pin a supplier location -->
        <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
        <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.1/web.js"></script>
        <style>
            #supplierMap {
                width: 100%;
                height: 280px;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
            }

            mapbox-search-box {
                width: 100%;
                margin-bottom: 8px;
                display: block;
            }
        </style>
    <?php endif; ?>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <div class="mb-6">
            <a href="<?= BASE_URL ?>/warehousestaff"
                class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Orders
            </a>
            <h1 class="text-2xl font-semibold text-slate-800">Update Tracking</h1>
            <p class="text-sm text-slate-500 mt-1">
                <?= htmlspecialchars($order['nhccreference'] ?? '—') ?>
                — <?= htmlspecialchars($order['contact_name']) ?>
                <span class="ml-2 font-mono text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-600">
                    <?= htmlspecialchars($order['po_number']) ?>
                </span>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 max-w-lg">

            <!-- Current step display -->
            <div class="mb-6">
                <p class="text-xs text-slate-500 mb-3 font-medium uppercase tracking-wide">Current Progress</p>
                <div class="flex items-center mb-3">
                    <?php foreach ($steps as $si => $label):
                        $done = $si < $currentStep;
                        $active = $si === $currentStep;
                        ?>
                        <?php if ($si > 0): ?>
                            <div class="h-0.5 flex-1 <?= $done ? 'bg-emerald-400' : 'bg-slate-200' ?>"></div>
                        <?php endif; ?>
                        <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-medium
                        <?php
                        if ($done)
                            echo 'bg-emerald-500 text-white';
                        elseif ($active)
                            echo 'bg-indigo-500 text-white';
                        else
                            echo 'bg-slate-200 text-slate-400';
                        ?>">
                            <?php if ($done): ?>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            <?php else: ?>
                                <?= $si + 1 ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-sm font-medium text-indigo-600">
                    Step <?= $currentStep + 1 ?>: <?= htmlspecialchars($steps[$currentStep]) ?>
                    <?php if (!$isDelivery && $currentStep === 2 && !empty($order['pickup_location'])): ?>
                        <span
                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            <?= $order['pickup_location'] === 'office' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' ?>">
                            <?= $order['pickup_location'] === 'office' ? 'Pickup sa Office' : 'Pickup sa Supplier' ?>
                        </span>
                    <?php endif; ?>
                </p>

                <?php if (!$isDelivery && $currentStep === 2 && !empty($order['pickup_driver_name'])): ?>
                    <div class="mt-2 text-xs text-slate-500">
                        Kumuha mula sa supplier: <span
                            class="font-medium text-slate-700"><?= htmlspecialchars($order['pickup_driver_name']) ?></span>
                        · Plate: <span
                            class="font-medium text-slate-700"><?= htmlspecialchars($order['pickup_plate_number']) ?></span>
                        <?php if (!empty($order['pickup_truck_details'])): ?>
                            · <?= htmlspecialchars($order['pickup_truck_details']) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$isDelivery && $currentStep === 2 && !empty($order['supplier_address'])): ?>
                    <div class="mt-2 text-xs text-slate-500">
                        <?= $order['pickup_location'] === 'office' ? 'Office address' : 'Supplier address' ?>: <span
                            class="font-medium text-slate-700"><?= htmlspecialchars($order['supplier_address']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($currentStep >= $maxStep): ?>
                <div
                    class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-sm text-emerald-700 font-medium text-center">
                    ✓ This PO tracking is already completed.
                </div>
                <?php if (!$isDelivery && !empty($order['proof_of_pickup_path'])): ?>
                    <div class="mt-4">
                        <p class="text-xs text-slate-500 mb-2">Proof of Pickup:</p>
                        <a href="<?= BASE_URL ?>/<?= htmlspecialchars($order['proof_of_pickup_path']) ?>" target="_blank">
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($order['proof_of_pickup_path']) ?>"
                                class="w-full rounded-lg border border-slate-200 max-h-64 object-cover">
                        </a>
                    </div>
                <?php endif; ?>

            <?php elseif ($isPickupAwaitingProof): ?>
                <!-- Item ready na, may driver/location info na — kailangan na lang ng proof of pickup -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-amber-700 font-medium">Item ready na para sa pickup.</p>
                    <p class="text-xs text-amber-600 mt-1">Mag-upload ng proof of pickup kapag nakuha na ng customer ang
                        item.</p>
                </div>
                <a href="<?= BASE_URL ?>/warehousestaff-pickupproof?po_id=<?= $poId ?>"
                    class="block text-center w-full px-4 py-2.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                    Upload Proof of Pickup
                </a>

            <?php else: ?>
                <form id="updateForm">
                    <input type="hidden" name="po_id" value="<?= $poId ?>">
                    <input type="hidden" name="step" value="<?= $nextStep ?>">

                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4">
                        <p class="text-xs text-slate-500 mb-1">Next step will be:</p>
                        <p class="text-sm font-semibold text-slate-800">
                            Step <?= $nextStep + 1 ?>: <?= htmlspecialchars($steps[$nextStep]) ?>
                        </p>
                    </div>

                    <?php if ($isDelivery && $nextStep === 2): ?>
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Expected Delivery Date Range</label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="text-xs text-slate-400 mb-0.5 block">From</label>
                                    <input type="date" name="expected_from" id="expectedFrom" required
                                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                                </div>
                                <div class="flex-1">
                                    <label class="text-xs text-slate-400 mb-0.5 block">To</label>
                                    <input type="date" name="expected_to" id="expectedTo" required
                                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($showPickupLocationField): ?>
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-slate-600 mb-2">Saan kukunin ng customer ang
                                item?</label>
                            <div class="space-y-2">
                                <label
                                    class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 cursor-pointer transition-colors">
                                    <input type="radio" name="pickup_location" value="office" id="locOffice"
                                        class="text-indigo-600" required>
                                    <div>
                                        <div class="text-sm font-medium text-slate-800">Office (Warehouse)</div>
                                        <div class="text-xs text-slate-500">Naidala na sa office niyo, customer kukuha dito.
                                        </div>
                                    </div>
                                </label>
                                <label
                                    class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 cursor-pointer transition-colors">
                                    <input type="radio" name="pickup_location" value="supplier" id="locSupplier"
                                        class="text-indigo-600" required>
                                    <div>
                                        <div class="text-sm font-medium text-slate-800">Supplier Location</div>
                                        <div class="text-xs text-slate-500">Naiwan sa supplier, customer diretso doon kukuha.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Office address — lalabas lang kapag pinili ang "Office" -->
                        <div id="officeLocationSection" class="hidden mb-4 border border-blue-200 rounded-lg p-4 bg-blue-50">
                            <p class="text-xs font-medium text-blue-700 mb-1">📍 Office Pickup Address</p>
                            <p class="text-sm text-blue-800 font-medium">
                                <?= htmlspecialchars($officeBase['placename'] ?? 'Office address not yet configured') ?>
                            </p>
                            <input type="hidden" id="officeAddressText" value="<?= htmlspecialchars($officeBase['placename'] ?? '') ?>">
                            <input type="hidden" id="officeLatitude" value="<?= htmlspecialchars($officeBase['latitude'] ?? '') ?>">
                            <input type="hidden" id="officeLongitude" value="<?= htmlspecialchars($officeBase['longitude'] ?? '') ?>">
                        </div>

                        <!-- Supplier address + map — lalabas lang kapag pinili ang "Supplier Location" -->
                        <div id="supplierMapSection" class="hidden mb-4 border border-slate-200 rounded-lg p-4 bg-slate-50">
                            <p class="text-xs font-medium text-slate-600 mb-2">Saan eksaktong location ng supplier?</p>

                            <mapbox-search-box id="supplier-search-box"
                                access-token="pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ"
                                proximity="121.0,14.6" country="PH" language="en"
                                placeholder="Search supplier address, landmark…"></mapbox-search-box>

                            <div id="supplierMap"></div>
                            <p class="text-xs text-slate-400 mt-2">📍 I-drag ang pin para sa eksaktong lokasyon, o i-type ang
                                address sa baba.</p>

                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Paste Address Here <span class="text-slate-400 font-normal">(kopya mula sa search mo, ito ang isesave)</span></label>
                                <input type="text" id="supplierAddressPaste"
                                    placeholder="I-paste dito ang address na nahanap/kinopya mo…"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                            </div>

                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Supplier Address</label>
                                <input type="text" id="supplierAddressText"
                                    placeholder="Auto-filled mula sa search, pwede i-edit"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                            </div>

                            <div id="supplierCoordsPreview" class="hidden mt-2 text-xs text-slate-500">
                                Lat: <span id="supplierLatPreview" class="font-mono">—</span>
                                &nbsp;&nbsp;Lng: <span id="supplierLngPreview" class="font-mono">—</span>
                            </div>

                            <input type="hidden" id="supplierLatitude">
                            <input type="hidden" id="supplierLongitude">
                        </div>

                        <!-- Driver/Truck fields — palagi lumalabas, kahit saan napupunta ang item -->
                        <div class="mb-4 border border-slate-200 rounded-lg p-4 bg-slate-50">
                            <p class="text-xs font-medium text-slate-600 mb-3">Detalye ng kumuha mula sa supplier</p>

                            <div class="mb-3">
                                <label class="block text-xs text-slate-500 mb-1">Driver Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" id="pickupDriverName" placeholder="Hal. Juan Dela Cruz"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                            </div>

                            <div class="mb-3">
                                <label class="block text-xs text-slate-500 mb-1">Plate Number <span
                                        class="text-red-500">*</span></label>
                                <input type="text" id="pickupPlateNumber" placeholder="Hal. ABC-1234"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                            </div>

                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Truck/Vehicle Details <span
                                        class="text-slate-400 font-normal">(optional)</span></label>
                                <input type="text" id="pickupTruckDetails" placeholder="Hal. Multicab, Toyota Hi-Ace"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-5">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Notes <span
                                class="text-slate-400 font-normal">(optional)</span></label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this update..."
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-400 resize-none"></textarea>
                    </div>

                    <div class="flex gap-2">
                        <a href="<?= BASE_URL ?>/warehousestaff"
                            class="flex-1 text-center px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                            Cancel
                        </a>
                        <button type="button" onclick="submitUpdate()"
                            class="flex-1 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                            Advance to Next Step
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showPickupLocationField): ?>
        <script>
            const MAPBOX_TOKEN = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';

            const locOffice = document.getElementById('locOffice');
            const locSupplier = document.getElementById('locSupplier');
            const supplierMapSection = document.getElementById('supplierMapSection');
            const officeLocationSection = document.getElementById('officeLocationSection');
            const supplierAddressPaste = document.getElementById('supplierAddressPaste');
            const supplierAddressText = document.getElementById('supplierAddressText');

            // Kapag may i-type/i-paste sa "Paste Address Here", automatic mapupunta sa
            // Supplier Address field — ito na ang isesave sa database.
            supplierAddressPaste?.addEventListener('input', () => {
                supplierAddressText.value = supplierAddressPaste.value;
            });

            let supplierMap = null;
            let supplierMarker = null;

            // Lazy-init: only build the Mapbox map once the staff actually picks "Supplier Location",
            // since initializing a map inside a hidden/zero-height container renders it broken.
            function initSupplierMap() {
                if (supplierMap) return;

                mapboxgl.accessToken = MAPBOX_TOKEN;
                supplierMap = new mapboxgl.Map({
                    container: 'supplierMap',
                    style: 'mapbox://styles/mapbox/streets-v12',
                    center: [121.0, 14.6],
                    zoom: 11
                });
                supplierMap.addControl(new mapboxgl.NavigationControl(), 'top-right');
                supplierMap.on('click', e => placeSupplierMarker(e.lngLat));

                document.getElementById('search-js')?.addEventListener('load', bindSupplierSearch);
                // In case the script already finished loading before this ran
                if (window.customElements?.get('mapbox-search-box')) {
                    bindSupplierSearch();
                }
            }

            function bindSupplierSearch() {
                const searchBox = document.getElementById('supplier-search-box');
                if (!searchBox || searchBox.dataset.bound) return;
                searchBox.dataset.bound = '1';
                searchBox.bindMap(supplierMap);

                searchBox.addEventListener('retrieve', (e) => {
                    const feature = e.detail?.features?.[0];
                    if (!feature) return;

                    const coords = feature.geometry?.coordinates;
                    const props = feature.properties || {};

                    if (coords) {
                        const lngLat = { lng: coords[0], lat: coords[1] };
                        placeSupplierMarker(lngLat);
                        supplierMap.flyTo({ center: lngLat, zoom: 17 });
                    }

                    const fullName = props.full_address || props.place_name || props.name || '';
                    if (fullName) document.getElementById('supplierAddressText').value = fullName;
                });
            }

            function placeSupplierMarker(lngLat) {
                if (supplierMarker) {
                    supplierMarker.setLngLat(lngLat);
                } else {
                    supplierMarker = new mapboxgl.Marker({ color: '#d97706', draggable: true })
                        .setLngLat(lngLat)
                        .addTo(supplierMap);
                    supplierMarker.on('dragend', () => updateSupplierCoords(supplierMarker.getLngLat()));
                }
                updateSupplierCoords(lngLat);
            }

            function updateSupplierCoords(lngLat) {
                const lat = lngLat.lat.toFixed(8);
                const lng = lngLat.lng.toFixed(8);
                document.getElementById('supplierLatitude').value = lat;
                document.getElementById('supplierLongitude').value = lng;
                document.getElementById('supplierLatPreview').textContent = lat;
                document.getElementById('supplierLngPreview').textContent = lng;
                document.getElementById('supplierCoordsPreview').classList.remove('hidden');
            }

            [locOffice, locSupplier].forEach(radio => {
                radio?.addEventListener('change', () => {
                    if (locSupplier.checked) {
                        supplierMapSection.classList.remove('hidden');
                        officeLocationSection.classList.add('hidden');
                        initSupplierMap();
                        setTimeout(() => supplierMap?.resize(), 50);
                    } else {
                        supplierMapSection.classList.add('hidden');
                        officeLocationSection.classList.remove('hidden');
                    }
                });
            });
        </script>
    <?php endif; ?>

    <script>
        function submitUpdate() {
            const form = document.getElementById('updateForm');
            const poId = form.querySelector('[name="po_id"]').value;
            const step = form.querySelector('[name="step"]').value;
            const notes = form.querySelector('[name="notes"]').value;

            <?php if ($isDelivery && $nextStep === 2): ?>
                const expectedFrom = document.getElementById('expectedFrom').value;
                const expectedTo = document.getElementById('expectedTo').value;
                if (!expectedFrom || !expectedTo) {
                    alert('Please fill in the expected delivery date range.');
                    return;
                }
            <?php endif; ?>

            let pickupLocation = null;
            let pickupDriverName = null;
            let pickupPlateNumber = null;
            let pickupTruckDetails = null;
            let supplierAddress = null;
            let supplierLatitude = null;
            let supplierLongitude = null;

            <?php if ($showPickupLocationField): ?>
                pickupLocation = document.querySelector('input[name="pickup_location"]:checked')?.value;
                if (!pickupLocation) {
                    alert('Please select kung saan kukunin ang item (Office o Supplier).');
                    return;
                }

                pickupDriverName = document.getElementById('pickupDriverName').value.trim();
                pickupPlateNumber = document.getElementById('pickupPlateNumber').value.trim();
                pickupTruckDetails = document.getElementById('pickupTruckDetails').value.trim();

                if (!pickupDriverName || !pickupPlateNumber) {
                    alert('Please fill in ang Driver Name at Plate Number.');
                    return;
                }

                if (pickupLocation === 'supplier') {
                    supplierAddress = document.getElementById('supplierAddressText').value.trim();
                    supplierLatitude = document.getElementById('supplierLatitude').value;
                    supplierLongitude = document.getElementById('supplierLongitude').value;

                    if (!supplierAddress || !supplierLatitude || !supplierLongitude) {
                        alert('Please i-pin o i-search ang exact location ng supplier sa map.');
                        return;
                    }
                } else if (pickupLocation === 'office') {
                    supplierAddress = document.getElementById('officeAddressText').value;
                    supplierLatitude = document.getElementById('officeLatitude').value;
                    supplierLongitude = document.getElementById('officeLongitude').value;
                }
            <?php endif; ?>

            const payload = {
                po_id: parseInt(poId),
                step: parseInt(step),
                notes: notes,
                <?php if ($isDelivery && $nextStep === 2): ?>
                        expected_from: document.getElementById('expectedFrom').value,
                    expected_to: document.getElementById('expectedTo').value,
                <?php endif; ?>
                <?php if ($showPickupLocationField): ?>
                        pickup_location: pickupLocation,
                    pickup_driver_name: pickupDriverName,
                    pickup_plate_number: pickupPlateNumber,
                    pickup_truck_details: pickupTruckDetails,
                    supplier_address: supplierAddress,
                    supplier_latitude: supplierLatitude,
                    supplier_longitude: supplierLongitude,
                <?php endif; ?>
            };

            fetch('<?= BASE_URL ?>/warehouse-assignupdate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '<?= BASE_URL ?>/warehousestaff';
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
        }
    </script>
</body>

</html>