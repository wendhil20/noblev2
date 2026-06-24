<?php
// tracklist-warehousebase.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all warehouse base locations
$stmt = $conn->prepare("SELECT id, placename, latitude, longitude FROM noblewarehousebase ORDER BY placename ASC");
$stmt->execute();
$warehouses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracklist Warehouse Base</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.17.0/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.17.0/mapbox-gl.js"></script>
    <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.1/web.js"></script>
    <style>
        #map { height: 480px; border-radius: 1rem; }
        .mapboxgl-popup-content {
            border-radius: 0.75rem;
            padding: 12px 16px;
            font-size: 13px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
    </style>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Warehouse Base Locations</h1>
                <p class="text-sm text-slate-400 mt-0.5">Overview of all registered warehouse bases</p>
            </div>
            <button onclick="openModal()"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition">
                <i class="fa-solid fa-plus text-xs"></i> Add Warehouse
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <!-- Map -->
            <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-slate-700">Map View</span>
                    <span class="text-xs text-slate-400" id="location-count">
                        <?= count($warehouses) ?> location<?= count($warehouses) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div id="map"></div>
            </div>

            <!-- Table -->
            <div class="xl:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 p-4 flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-slate-700">All Warehouses</span>
                </div>

                <!-- Search -->
                <div class="relative mb-3">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                    <input type="text" id="search-input" placeholder="Search warehouse..."
                        class="w-full pl-8 pr-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100 transition">
                </div>

                <?php if (empty($warehouses)): ?>
                    <div class="flex-1 flex flex-col items-center justify-center py-16 text-center">
                        <i class="fa-solid fa-warehouse text-4xl text-slate-200 mb-3"></i>
                        <p class="text-sm text-slate-400">No warehouse bases found.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-y-auto max-h-[420px] -mx-1 px-1">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                                <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                    <th class="text-left pb-2 font-semibold">Place</th>
                                    <th class="text-right pb-2 font-semibold">Lat</th>
                                    <th class="text-right pb-2 font-semibold">Lng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50" id="warehouse-tbody">
                                <?php foreach ($warehouses as $w): ?>
                                    <tr class="hover:bg-amber-50 cursor-pointer transition warehouse-row"
                                        data-id="<?= $w['id'] ?>"
                                        data-lat="<?= $w['latitude'] ?>"
                                        data-lng="<?= $w['longitude'] ?>"
                                        data-name="<?= htmlspecialchars(strtolower($w['placename'])) ?>">
                                        <td class="py-2.5 pr-2">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                                                <span class="font-medium text-slate-700 truncate max-w-[130px]">
                                                    <?= htmlspecialchars($w['placename']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 text-right text-xs text-slate-400 font-mono">
                                            <?= number_format(floatval($w['latitude']), 5) ?>
                                        </td>
                                        <td class="py-2.5 text-right text-xs text-slate-400 font-mono">
                                            <?= number_format(floatval($w['longitude']), 5) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-xs text-center text-slate-300 mt-3 hidden" id="no-results">No results found.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Add Warehouse Modal -->
    <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-bold text-slate-800">Add Warehouse Base</h2>
                <button onclick="closeModal()" class="text-slate-300 hover:text-slate-500 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Place Search -->
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Search Place</label>
                <div class="relative">
                    <input type="text" id="place-search" placeholder="e.g. Makati City, Manila..."
                        autocomplete="off"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-200 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100 transition pr-10">
                    <i class="fa-solid fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                </div>
                <!-- Suggestions Dropdown -->
                <div id="suggestions"
                    class="hidden mt-1 bg-white border border-slate-100 rounded-xl shadow-lg overflow-hidden z-50 max-h-48 overflow-y-auto">
                </div>
            </div>

            <!-- Place Name -->
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    Place Name <span class="text-red-400">*</span>
                </label>
                <input type="text" id="input-placename" placeholder="Warehouse place name"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-200 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100 transition">
            </div>

            <!-- Lat / Lng -->
            <div class="grid grid-cols-2 gap-3 mb-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Latitude <span class="text-red-400">*</span>
                    </label>
                    <input type="text" id="input-lat" placeholder="e.g. 14.5995"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-200 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100 transition font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Longitude <span class="text-red-400">*</span>
                    </label>
                    <input type="text" id="input-lng" placeholder="e.g. 120.9842"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-200 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-100 transition font-mono">
                </div>
            </div>

            <p id="modal-error" class="text-xs text-red-400 mb-3 hidden"></p>

            <div class="flex gap-2">
                <button onclick="closeModal()"
                    class="flex-1 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-500 hover:bg-slate-50 transition font-medium">
                    Cancel
                </button>
                <button onclick="saveWarehouse()"
                    class="flex-1 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition">
                    Save Warehouse
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="fixed top-6 right-6 z-50 opacity-0 pointer-events-none translate-y-2
        flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium
        bg-white border border-gray-100 text-gray-800 min-w-56 transition-all duration-300">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <script>
        const MAPBOX_TOKEN = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';
        const saveUrl     = <?= json_encode(BASE_URL . '/ps-backendwarehousebase-save') ?>;
        let warehouses    = <?= json_encode($warehouses) ?>;
        let sessionToken  = crypto.randomUUID();

        // ── MAP SETUP ─────────────────────────────────────────────────────────
        mapboxgl.accessToken = MAPBOX_TOKEN;

        let centerLng = 122.0, centerLat = 12.0, zoom = 5;
        if (warehouses.length > 0) {
            centerLng = parseFloat(warehouses[0].longitude);
            centerLat = parseFloat(warehouses[0].latitude);
            zoom = warehouses.length === 1 ? 12 : 6;
        }

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/light-v11',
            center: [centerLng, centerLat],
            zoom: zoom
        });

        map.addControl(new mapboxgl.NavigationControl(), 'top-right');

        const markers = {};

        function addMarker(w) {
            const el = document.createElement('div');
            el.style.cssText = `
                width:32px; height:32px; background:#f59e0b;
                border:3px solid white; border-radius:50%;
                box-shadow:0 2px 8px rgba(0,0,0,0.2); cursor:pointer;
                display:flex; align-items:center; justify-content:center;
            `;
            el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="14" height="14">
                <path d="M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1H4a1 1 0 01-1-1V9.75z"/>
            </svg>`;

            const popup = new mapboxgl.Popup({ offset: 20, closeButton: false })
                .setHTML(`<div>
                    <p style="font-weight:600;color:#1e293b;margin-bottom:4px">${w.placename}</p>
                    <p style="font-size:11px;color:#94a3b8;font-family:monospace">
                        ${parseFloat(w.latitude).toFixed(5)}, ${parseFloat(w.longitude).toFixed(5)}
                    </p>
                </div>`);

            markers[w.id] = new mapboxgl.Marker(el)
                .setLngLat([parseFloat(w.longitude), parseFloat(w.latitude)])
                .setPopup(popup)
                .addTo(map);
        }

        warehouses.forEach(addMarker);

        if (warehouses.length > 1) {
            const bounds = new mapboxgl.LngLatBounds();
            warehouses.forEach(w => bounds.extend([parseFloat(w.longitude), parseFloat(w.latitude)]));
            map.fitBounds(bounds, { padding: 60 });
        }

        // ── ROW CLICK ─────────────────────────────────────────────────────────
        document.getElementById('warehouse-tbody')?.addEventListener('click', e => {
            const row = e.target.closest('.warehouse-row');
            if (!row) return;
            const id  = row.dataset.id;
            const lat = parseFloat(row.dataset.lat);
            const lng = parseFloat(row.dataset.lng);

            map.flyTo({ center: [lng, lat], zoom: 14, speed: 1.4 });
            Object.values(markers).forEach(m => m.getPopup().remove());
            setTimeout(() => markers[id]?.togglePopup(), 600);

            document.querySelectorAll('.warehouse-row').forEach(r => r.classList.remove('bg-amber-100'));
            row.classList.add('bg-amber-100');
        });

        // ── TABLE SEARCH FILTER ───────────────────────────────────────────────
        document.getElementById('search-input').addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.warehouse-row');
            let visible = 0;
            rows.forEach(row => {
                const match = row.dataset.name.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('no-results').classList.toggle('hidden', visible > 0);
        });

        // ── MODAL ─────────────────────────────────────────────────────────────
        function openModal() {
            sessionToken = crypto.randomUUID(); // fresh session per modal open
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
            document.getElementById('modal').classList.remove('flex');
            document.getElementById('place-search').value    = '';
            document.getElementById('input-placename').value = '';
            document.getElementById('input-lat').value       = '';
            document.getElementById('input-lng').value       = '';
            document.getElementById('suggestions').classList.add('hidden');
            document.getElementById('modal-error').classList.add('hidden');
        }

        // ── SEARCH BOX API (v1.5.1) ───────────────────────────────────────────
        let searchTimeout;

        document.getElementById('place-search').addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const q = this.value.trim();
            if (q.length < 3) {
                document.getElementById('suggestions').classList.add('hidden');
                return;
            }
            searchTimeout = setTimeout(() => fetchSuggestions(q), 400);
        });

        async function fetchSuggestions(q) {
            const url = `https://api.mapbox.com/search/searchbox/v1/suggest`
                + `?q=${encodeURIComponent(q)}`
                + `&access_token=${MAPBOX_TOKEN}`
                + `&session_token=${sessionToken}`
                + `&country=PH`
                + `&limit=5`
                + `&language=en`;

            const res  = await fetch(url);
            const data = await res.json();
            const box  = document.getElementById('suggestions');

            if (!data.suggestions || data.suggestions.length === 0) {
                box.classList.add('hidden');
                return;
            }

            box.innerHTML = data.suggestions.map(f => `
                <div class="px-3 py-2.5 text-sm text-slate-700 hover:bg-amber-50 cursor-pointer border-b border-slate-50 last:border-0 suggestion-item"
                    data-mapbox-id="${f.mapbox_id}"
                    data-name="${f.name}${f.place_formatted ? ', ' + f.place_formatted : ''}">
                    <p class="font-medium truncate">${f.name}</p>
                    <p class="text-xs text-slate-400 truncate">${f.place_formatted ?? ''}</p>
                </div>
            `).join('');

            box.classList.remove('hidden');

            box.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', () => retrievePlace(item));
            });
        }

        async function retrievePlace(item) {
            const mapboxId = item.dataset.mapboxId;
            const url = `https://api.mapbox.com/search/searchbox/v1/retrieve/${mapboxId}`
                + `?access_token=${MAPBOX_TOKEN}`
                + `&session_token=${sessionToken}`;

            const res  = await fetch(url);
            const data = await res.json();
            const feature = data.features?.[0];

            if (!feature) return;

            const [lng, lat] = feature.geometry.coordinates;

            document.getElementById('input-placename').value = item.dataset.name;
            document.getElementById('input-lat').value       = parseFloat(lat).toFixed(7);
            document.getElementById('input-lng').value       = parseFloat(lng).toFixed(7);
            document.getElementById('place-search').value    = item.querySelector('p').textContent;
            document.getElementById('suggestions').classList.add('hidden');

            // Refresh session token after retrieve (Mapbox best practice)
            sessionToken = crypto.randomUUID();
        }

        // Close suggestions on outside click
        document.addEventListener('click', e => {
            if (!e.target.closest('#place-search') && !e.target.closest('#suggestions')) {
                document.getElementById('suggestions').classList.add('hidden');
            }
        });

        // ── SAVE WAREHOUSE ────────────────────────────────────────────────────
        async function saveWarehouse() {
            const placename = document.getElementById('input-placename').value.trim();
            const lat       = document.getElementById('input-lat').value.trim();
            const lng       = document.getElementById('input-lng').value.trim();
            const errEl     = document.getElementById('modal-error');

            if (!placename || !lat || !lng) {
                errEl.textContent = 'All fields are required.';
                errEl.classList.remove('hidden');
                return;
            }
            if (isNaN(lat) || isNaN(lng)) {
                errEl.textContent = 'Latitude and Longitude must be valid numbers.';
                errEl.classList.remove('hidden');
                return;
            }

            errEl.classList.add('hidden');

            const fd = new FormData();
            fd.append('placename', placename);
            fd.append('latitude',  lat);
            fd.append('longitude', lng);

            try {
                const res  = await fetch(saveUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    const newW = { id: String(data.id), placename, latitude: lat, longitude: lng };
                    warehouses.push(newW);

                    addMarker(newW);
                    map.flyTo({ center: [parseFloat(lng), parseFloat(lat)], zoom: 13 });

                    // Add row to table
                    const tbody = document.getElementById('warehouse-tbody');
                    if (tbody) {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-amber-50 cursor-pointer transition warehouse-row';
                        tr.dataset.id   = data.id;
                        tr.dataset.lat  = lat;
                        tr.dataset.lng  = lng;
                        tr.dataset.name = placename.toLowerCase();
                        tr.innerHTML = `
                            <td class="py-2.5 pr-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                                    <span class="font-medium text-slate-700 truncate max-w-[130px]">${placename}</span>
                                </div>
                            </td>
                            <td class="py-2.5 text-right text-xs text-slate-400 font-mono">${parseFloat(lat).toFixed(5)}</td>
                            <td class="py-2.5 text-right text-xs text-slate-400 font-mono">${parseFloat(lng).toFixed(5)}</td>
                        `;
                        tbody.prepend(tr);
                    }

                    // Update count
                    const c = warehouses.length;
                    document.getElementById('location-count').textContent = c + ' location' + (c !== 1 ? 's' : '');

                    closeModal();
                    showToast('success', 'Warehouse added successfully.');
                } else {
                    errEl.textContent = data.msg || 'Failed to save.';
                    errEl.classList.remove('hidden');
                }
            } catch (e) {
                errEl.textContent = 'Something went wrong.';
                errEl.classList.remove('hidden');
            }
        }

        // ── TOAST ─────────────────────────────────────────────────────────────
        function showToast(type, msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-icon').innerHTML = type === 'success'
                ? '<i class="fa-solid fa-circle-check text-green-500"></i>'
                : '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
            document.getElementById('toast-msg').textContent = msg;
            toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
            toast.classList.add('opacity-100', 'translate-y-0');
            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                toast.classList.remove('opacity-100', 'translate-y-0');
            }, 3000);
        }
    </script>

</body>
</html>