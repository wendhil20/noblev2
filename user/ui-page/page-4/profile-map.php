<?php
// profile-map.php
include ROOT_PATH . '/network/connect.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <!-- Mapbox GL JS -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
    <!-- Mapbox Search JS v1.5.1 -->
    <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.1/web.js"></script>

    <style>
        .step-panel {
            display: none;
            animation: fadeSlide 0.3s ease forwards;
        }

        .step-panel.active {
            display: block;
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #map {
            width: 100%;
            height: 420px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        mapbox-search-box {
            width: 100%;
            margin-bottom: 10px;
            display: block;
        }

        .confirm-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
        }

        .confirm-row:last-child {
            border-bottom: none;
        }

        .confirm-label {
            color: #6b7280;
        }

        .confirm-value {
            color: #111827;
            font-weight: 500;
            text-align: right;
        }
    </style>
</head>

<body class="bg-gray-50">

    <div class="max-w-2xl mx-auto px-6 py-8">
        <a href="<?= BASE_URL ?>/profile"
            class="inline-flex items-center gap-1.5 text-xs md:text-sm text-gray-400 hover:text-amber-500 transition mb-4 md:mb-6">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </a>

        <h2 class="text-2xl font-bold text-gray-800 mb-2">My Profile</h2>
        <p class="text-sm text-gray-500 mb-6">Set up your address and location information.</p>

        <!-- Step Indicator -->
        <div class="flex items-center gap-0 mb-7 px-1">
            <div class="flex flex-col items-center">
                <div id="dot-1"
                    class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 border-blue-600 text-blue-600 bg-blue-50 z-10 transition-all duration-300">
                    1</div>
                <span class="text-xs text-gray-400 mt-1 whitespace-nowrap">Location</span>
            </div>
            <div id="line-1" class="flex-1 h-0.5 bg-gray-200 transition-all duration-300 mb-4"></div>
            <div class="flex flex-col items-center">
                <div id="dot-2"
                    class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 border-gray-300 text-gray-400 bg-white z-10 transition-all duration-300">
                    2</div>
                <span class="text-xs text-gray-400 mt-1 whitespace-nowrap">Address</span>
            </div>
            <div id="line-2" class="flex-1 h-0.5 bg-gray-200 transition-all duration-300 mb-4"></div>
            <div class="flex flex-col items-center">
                <div id="dot-3"
                    class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 border-gray-300 text-gray-400 bg-white z-10 transition-all duration-300">
                    3</div>
                <span class="text-xs text-gray-400 mt-1 whitespace-nowrap">Review</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">

            <!-- STEP 1 — Map + Search -->
            <div class="step-panel active" id="step-1">
                <h3 class="text-base font-semibold text-gray-700 mb-1">Pin Your Location</h3>
                <p class="text-xs text-gray-400 mb-4">Search for your address or click anywhere on the map to drop a
                    pin.</p>

                <mapbox-search-box id="search-box"
                    access-token="pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ"
                    proximity="121.0,14.6" country="PH" language="en"
                    placeholder="Search address, landmark, barangay…"></mapbox-search-box>

                <div id="map"></div>
                <p class="text-xs text-gray-400 mt-2"> Drag the pin to fine-tune your exact location.</p>

                <div class="flex justify-end mt-5">
                    <button onclick="goToStep2()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                        Next: Address Details →
                    </button>
                </div>
            </div>

            <!-- STEP 2 — Address Form -->
            <div class="step-panel" id="step-2">
                <h3 class="text-base font-semibold text-gray-700 mb-1">Address Details</h3>
                <p class="text-xs text-gray-400 mb-4">Fill in the remaining details for your address.</p>

                <div class="space-y-4">

                    <!-- Contact Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g. 09171234567">
                    </div>

                    <!-- House No / Street -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">House No. / Street</label>
                        <input type="text" id="address" name="address"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g. Unit 2B, 123 Rizal Street">
                    </div>

                    <!-- Age -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Age</label>
                        <input type="number" id="age" name="age" min="1" max="120"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter your age">
                    </div>

                    <!-- Region -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Region</label>
                        <select id="region"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Select Region —</option>
                        </select>
                    </div>

                    <!-- Province -->
                    <div id="province-wrapper">
                        <label id="province-label" class="block text-sm font-medium text-gray-600 mb-1">Province</label>
                        <select id="province" disabled
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                            <option value="">— Select Province —</option>
                        </select>
                    </div>

                    <!-- City / Municipality -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">City / Municipality</label>
                        <select id="city" disabled
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                            <option value="">— Select City / Municipality —</option>
                        </select>
                    </div>

                    <!-- Barangay -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Barangay</label>
                        <select id="barangay" disabled
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100">
                            <option value="">— Select Barangay —</option>
                        </select>
                    </div>

                    <!-- Postal Code -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Postal Code</label>
                        <input type="text" id="postalcode" readonly
                            class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500"
                            placeholder="Auto-filled">
                    </div>

                </div>

                <div class="flex justify-between mt-6">
                    <button onclick="goToStep(1)"
                        class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2 rounded-lg border border-gray-200 hover:border-gray-300 transition">
                        ← Back
                    </button>
                    <button onclick="goToStep3()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                        Next: Review →
                    </button>
                </div>
            </div>

            <!-- STEP 3 — Review & Save -->
            <div class="step-panel" id="step-3">
                <h3 class="text-base font-semibold text-gray-700 mb-1">Review Your Information</h3>
                <p class="text-xs text-gray-400 mb-5">Please confirm your details before saving.</p>

                <div id="review-content" class="space-y-1 mb-6"></div>

                <div class="flex justify-between mt-4">
                    <button onclick="goToStep(2)"
                        class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2 rounded-lg border border-gray-200 hover:border-gray-300 transition">
                        ← Back
                    </button>
                    <button onclick="saveProfile()"
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                        ✓ Save Profile
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Toast -->
    <div id="toast"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-green-600 text-white text-sm font-medium px-6 py-3 rounded-xl shadow-lg transition-all duration-300 z-50 pointer-events-none opacity-0 translate-y-10 invisible">
        <i class="fa-solid fa-circle-check mr-2"></i> Profile saved successfully!
    </div>

    <!-- Hidden fields -->
    <input type="hidden" id="latitude">
    <input type="hidden" id="longitude">

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <script>
        const MAPBOX_TOKEN = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';
        const PSGC = 'https://psgc.cloud/api';
        const NCR_CODE = '1300000000';

        let currentStep = 1;

        // ── Step Navigation ──────────────────────────────────────
        function goToStep(n) {
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            currentStep = n;
            document.getElementById(`step-${currentStep}`).classList.add('active');
            updateDots();
            if (n === 1) setTimeout(() => map.resize(), 50);
        }

        function goToStep2() {
            if (!document.getElementById('latitude').value) {
                alert('Please pin your location on the map first.');
                return;
            }
            goToStep(2);
        }

        function goToStep3() {
            buildReview();
            goToStep(3);
        }

        function updateDots() {
            for (let i = 1; i <= 3; i++) {
                const dot = document.getElementById(`dot-${i}`);
                dot.className = 'flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 z-10 transition-all duration-300';
                if (i < currentStep) {
                    dot.classList.add('border-blue-600', 'bg-blue-600', 'text-white');
                } else if (i === currentStep) {
                    dot.classList.add('border-blue-600', 'text-blue-600', 'bg-blue-50');
                } else {
                    dot.classList.add('border-gray-300', 'text-gray-400', 'bg-white');
                }
            }
            for (let i = 1; i <= 2; i++) {
                const line = document.getElementById(`line-${i}`);
                if (i < currentStep) {
                    line.classList.remove('bg-gray-200');
                    line.classList.add('bg-blue-600');
                } else {
                    line.classList.remove('bg-blue-600');
                    line.classList.add('bg-gray-200');
                }
            }
        }

        function buildReview() {
            const getText = id => {
                const el = document.getElementById(id);
                if (!el) return '—';
                if (el.tagName === 'SELECT') {
                    const t = el.options[el.selectedIndex]?.text || '';
                    return t.startsWith('—') ? '—' : t;
                }
                return el.value || '—';
            };

            const rows = [
                ['Contact Number', getText('contact_number')],
                ['House No. / Street', getText('address')],
                ['Age', getText('age')],
                ['Region', getText('region')],
                ['Province', getText('province')],
                ['City / Municipality', getText('city')],
                ['Barangay', getText('barangay')],
                ['Postal Code', getText('postalcode')],
                ['Latitude', document.getElementById('latitude').value || '—'],
                ['Longitude', document.getElementById('longitude').value || '—'],
            ];

            document.getElementById('review-content').innerHTML = rows.map(([label, val]) => `
                <div class="confirm-row">
                    <span class="confirm-label">${label}</span>
                    <span class="confirm-value">${val}</span>
                </div>
            `).join('');
        }

        // ── Reset Form ───────────────────────────────────────────
        function resetForm() {
            // Text/number inputs
            ['contact_number', 'address', 'age', 'postalcode'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            // Hidden coords
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';

            // Selects
            document.getElementById('region').value = '';
            resetSelect(document.getElementById('province'), '— Select Province —');
            resetSelect(document.getElementById('city'), '— Select City / Municipality —');
            resetSelect(document.getElementById('barangay'), '— Select Barangay —');
            document.getElementById('province-wrapper').style.display = '';

            // Remove marker
            if (marker) {
                marker.remove();
                marker = null;
            }

            // Reset map view
            map.flyTo({ center: [121.0, 14.6], zoom: 11 });

            // Go back to step 1
            goToStep(1);
        }

        // ── PSGC ─────────────────────────────────────────────────
        async function fetchJSON(url) {
            const res = await fetch(url);
            if (!res.ok) throw new Error('API error ' + res.status);
            return res.json();
        }

        function populateSelect(selectEl, items, valueKey, labelKey, placeholder) {
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item[valueKey];
                opt.textContent = item[labelKey];
                if (item.zipCode) opt.dataset.zip = item.zipCode;
                selectEl.appendChild(opt);
            });
            selectEl.disabled = false;
        }

        function resetSelect(selectEl, placeholder) {
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            selectEl.disabled = true;
        }

        async function loadRegions() {
            try {
                const regions = await fetchJSON(`${PSGC}/regions`);
                regions.sort((a, b) => a.name.localeCompare(b.name));
                populateSelect(document.getElementById('region'), regions, 'code', 'name', '— Select Region —');
            } catch (e) { console.error('loadRegions:', e); }
        }

        document.getElementById('region').addEventListener('change', async function () {
            const code = this.value;
            const isNCR = (code === NCR_CODE);
            const wrapper = document.getElementById('province-wrapper');
            const provSel = document.getElementById('province');

            resetSelect(provSel, '— Select Province —');
            resetSelect(document.getElementById('city'), '— Select City / Municipality —');
            resetSelect(document.getElementById('barangay'), '— Select Barangay —');
            document.getElementById('postalcode').value = '';

            if (!code) { wrapper.style.display = ''; return; }

            if (isNCR) {
                wrapper.style.display = 'none';
                try {
                    const [cities, munis] = await Promise.all([
                        fetchJSON(`${PSGC}/regions/${code}/cities`).catch(() => []),
                        fetchJSON(`${PSGC}/regions/${code}/municipalities`).catch(() => [])
                    ]);
                    populateSelect(document.getElementById('city'),
                        [...cities, ...munis].sort((a, b) => a.name.localeCompare(b.name)),
                        'code', 'name', '— Select City / Municipality —');
                } catch (e) { console.error('NCR cities:', e); }
            } else {
                wrapper.style.display = '';
                document.getElementById('province-label').textContent = 'Province';
                try {
                    const provinces = await fetchJSON(`${PSGC}/regions/${code}/provinces`);
                    provinces.sort((a, b) => a.name.localeCompare(b.name));
                    populateSelect(provSel, provinces, 'code', 'name', '— Select Province —');
                } catch (e) { console.error('provinces:', e); }
            }
        });

        document.getElementById('province').addEventListener('change', async function () {
            const code = this.value;
            resetSelect(document.getElementById('city'), '— Select City / Municipality —');
            resetSelect(document.getElementById('barangay'), '— Select Barangay —');
            document.getElementById('postalcode').value = '';
            if (!code) return;
            try {
                const [cities, munis] = await Promise.all([
                    fetchJSON(`${PSGC}/provinces/${code}/cities`).catch(() => []),
                    fetchJSON(`${PSGC}/provinces/${code}/municipalities`).catch(() => [])
                ]);
                populateSelect(document.getElementById('city'),
                    [...cities, ...munis].sort((a, b) => a.name.localeCompare(b.name)),
                    'code', 'name', '— Select City / Municipality —');
            } catch (e) { console.error('cities:', e); }
        });

        document.getElementById('city').addEventListener('change', async function () {
            const code = this.value;
            resetSelect(document.getElementById('barangay'), '— Select Barangay —');
            const sel = this.options[this.selectedIndex];
            document.getElementById('postalcode').value = sel.dataset.zip || '';
            if (!code) return;
            try {
                const barangays = await fetchJSON(`${PSGC}/cities-municipalities/${code}/barangays`);
                barangays.sort((a, b) => a.name.localeCompare(b.name));
                populateSelect(document.getElementById('barangay'), barangays, 'code', 'name', '— Select Barangay —');
            } catch (e) { console.error('barangays:', e); }
        });

        // ── Mapbox Map ────────────────────────────────────────────
        mapboxgl.accessToken = MAPBOX_TOKEN;

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [121.0, 14.6],
            zoom: 11
        });

        map.addControl(new mapboxgl.NavigationControl(), 'top-right');

        const geolocate = new mapboxgl.GeolocateControl({
            positionOptions: { enableHighAccuracy: true },
            trackUserLocation: false,
            showUserHeading: false
        });
        map.addControl(geolocate, 'top-right');

        let marker = null;

        function placeMarker(lngLat) {
            if (marker) {
                marker.setLngLat(lngLat);
            } else {
                marker = new mapboxgl.Marker({ color: '#2563eb', draggable: true })
                    .setLngLat(lngLat)
                    .addTo(map);
                marker.on('dragend', () => updateCoords(marker.getLngLat()));
            }
            updateCoords(lngLat);
        }

        function updateCoords(lngLat) {
            const lat = lngLat.lat.toFixed(8);
            const lng = lngLat.lng.toFixed(8);
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('lat-preview').textContent = lat;
            document.getElementById('lng-preview').textContent = lng;
            document.getElementById('coords-preview').classList.remove('hidden');
        }

        map.on('click', e => placeMarker(e.lngLat));

        geolocate.on('geolocate', e => {
            const lngLat = { lng: e.coords.longitude, lat: e.coords.latitude };
            placeMarker(lngLat);
            map.flyTo({ center: lngLat, zoom: 16 });
        });

        // ── Search Box ────────────────────────────────────────────
        document.getElementById('search-js').addEventListener('load', () => {
            const searchBox = document.getElementById('search-box');
            searchBox.bindMap(map);

            searchBox.addEventListener('retrieve', (e) => {
                const feature = e.detail?.features?.[0];
                if (!feature) return;

                const coords = feature.geometry?.coordinates;
                const props = feature.properties || {};

                if (coords) {
                    const lngLat = { lng: coords[0], lat: coords[1] };
                    placeMarker(lngLat);
                    map.flyTo({ center: lngLat, zoom: 17 });
                }

                const postcode = props.context?.postcode?.name || props.postcode || '';
                if (postcode) document.getElementById('postalcode').value = postcode;

                const fullName = props.full_address || props.place_name || props.name || '';
                if (fullName) document.getElementById('address').value = fullName;
            });
        });

        // ── Save ──────────────────────────────────────────────────
        async function saveProfile() {
            const getText = id => {
                const el = document.getElementById(id);
                if (!el) return '';
                if (el.tagName === 'SELECT') {
                    const t = el.options[el.selectedIndex]?.text || '';
                    return t.startsWith('—') ? '' : t;
                }
                return el.value || '';
            };

            const data = {
                contact_number: document.getElementById('contact_number').value,
                age: document.getElementById('age').value,
                address: document.getElementById('address').value,
                city: getText('city'),
                barangay: getText('barangay'),
                postalcode: document.getElementById('postalcode').value,
                latitude: document.getElementById('latitude').value,
                longitude: document.getElementById('longitude').value,
            };

            try {
                const res = await fetch('<?= BASE_URL ?>/savemap', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    showToast();
                    resetForm();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (e) {
                alert('Failed to save. Please try again.');
            }
        }

        // ── Toast ────────────────────────────────────────────
        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('opacity-0', 'translate-y-10', 'invisible');
            toast.classList.add('opacity-100', 'translate-y-0', 'visible');
            setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0', 'visible');
                toast.classList.add('opacity-0', 'translate-y-10', 'invisible');
            }, 3000);
        }

        // ── Init ──────────────────────────────────────────────────
        loadRegions();
    </script>
</body>

</html>