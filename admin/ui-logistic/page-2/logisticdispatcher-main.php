<?php
// logisticdispatcher-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICDISPATCHER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dispatcher</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-slate-100">

    <div class="ml-60 min-h-screen p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800">Delivery Dispatcher</h1>
                <p class="text-sm text-slate-500 mt-0.5">Track loading, transit, and delivery for scheduled orders</p>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-slate-400">
                <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block" id="realtime-dot"></span>
                <span id="realtime-label">Live</span>
            </div>
        </div>

        <div id="dispatcher-list" class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center">
                <p class="text-slate-400 text-sm">Loading…</p>
            </div>
        </div>

    </div>

    <!-- ===== PROOF OF DELIVERY MODAL ===== -->
    <div id="podModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-[420px] max-h-[90vh] overflow-y-auto relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Proof of Delivery</h3>
                    <p id="pod-ref" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closePodModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <p class="text-xs text-slate-500">Upload a photo of the signed delivery receipt or proof that the order
                    reached the customer. JPG, PNG, or WEBP only.</p>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Photo <span
                            class="text-red-500">*</span></label>
                    <input type="file" id="pod-file" accept="image/jpeg,image/png,image/webp"
                        onchange="previewPodFile(this)"
                        class="w-full text-sm text-slate-600 border border-slate-200 rounded-lg px-3 py-2.5 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-emerald-50 file:text-emerald-700 file:text-xs file:font-medium">
                    <p id="pod-file-error" class="hidden text-xs text-red-500 mt-1"></p>
                </div>
                <div id="pod-preview-wrap" class="hidden">
                    <img id="pod-preview" class="w-full h-44 object-cover rounded-lg border border-slate-200">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                <button onclick="closePodModal()"
                    class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">Cancel</button>
                <button onclick="submitPod()" id="pod-submit"
                    class="px-5 py-2.5 text-sm font-medium bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Confirm Delivered
                </button>
            </div>
        </div>
    </div>

    <script>
        const POLL_INTERVAL_MS = 7000;
        let pollTimer = null;
        let podBookingId = 0;
        let lastBookingsJson = null; // skip re-render if nothing changed, avoids flicker

        // ── Helpers (mirrors the PHP helpers from before, now in JS) ───────
        function getTodayStr() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        function statusBadgeClasses(status) {
            switch (status) {
                case 'scheduled':
                case 'rescheduled': return 'bg-slate-100 text-slate-600 border-slate-200';
                case 'loading': return 'bg-orange-50 text-orange-700 border-orange-200';
                case 'in_transit': return 'bg-blue-50 text-blue-700 border-blue-200';
                case 'delivered': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                default: return 'bg-slate-100 text-slate-500 border-slate-200';
            }
        }

        function statusLabel(status) {
            return status === 'in_transit' ? 'In Transit' : (status.charAt(0).toUpperCase() + status.slice(1));
        }

        function formatDateHeader(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }

        function formatDeliveredAt(dt) {
            if (!dt) return '';
            const d = new Date(dt.replace(' ', 'T'));
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) + ', ' +
                d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }).toLowerCase();
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // ── Fetch + render loop ──────────────────────────────────────────
        function fetchBookings() {
            document.getElementById('realtime-dot').className = 'w-2 h-2 rounded-full bg-amber-400 inline-block';
            document.getElementById('realtime-label').textContent = 'Syncing…';

            fetch('<?= BASE_URL ?>/logisticdispatcher-getbookings')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('realtime-dot').className = 'w-2 h-2 rounded-full bg-emerald-400 inline-block';
                    document.getElementById('realtime-label').textContent = 'Live';
                    if (!data.success) return;

                    const json = JSON.stringify(data.bookings);
                    if (json === lastBookingsJson) return; // nothing changed, skip render
                    lastBookingsJson = json;
                    renderDispatcherList(data.bookings);
                })
                .catch(() => {
                    document.getElementById('realtime-dot').className = 'w-2 h-2 rounded-full bg-red-400 inline-block';
                    document.getElementById('realtime-label').textContent = 'Offline';
                });
        }

        function startPolling() {
            stopPolling();
            pollTimer = setInterval(fetchBookings, POLL_INTERVAL_MS);
        }

        function stopPolling() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        function renderDispatcherList(bookings) {
            const container = document.getElementById('dispatcher-list');
            const todayStr = getTodayStr();

            if (!bookings.length) {
                container.innerHTML = `
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center">
                        <p class="text-slate-400 text-sm">No active deliveries scheduled.</p>
                    </div>`;
                return;
            }

            // Group by delivery_date
            const grouped = {};
            bookings.forEach(b => {
                const key = b.delivery_date || 'unscheduled';
                if (!grouped[key]) grouped[key] = [];
                grouped[key].push(b);
            });
            const sortedKeys = Object.keys(grouped).sort();

            container.innerHTML = sortedKeys.map(dateKey => {
                const dayBookings = grouped[dateKey];
                const isUnscheduled = dateKey === 'unscheduled';
                const isToday = !isUnscheduled && dateKey === todayStr;
                const isPast = !isUnscheduled && dateKey < todayStr;

                const headerBadge = isToday
                    ? `<span class="px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-semibold uppercase tracking-wide">Today</span>`
                    : (isPast ? `<span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-semibold uppercase tracking-wide">Past</span>` : '');

                const rows = dayBookings.map(b => {
                    const needsDetails = !b.driver_name || !b.truck_details || !b.plate_number || !b.delivery_address;
                    const isPending = b.status === 'scheduled' || b.status === 'rescheduled';
                    const canStartLoading = isPending && !needsDetails && !isUnscheduled && dateKey <= todayStr;
                    const awaitingDeliveryDate = isPending && !needsDetails && !isUnscheduled && dateKey > todayStr;

                    const needsDetailsBadge = (needsDetails && isPending)
                        ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-orange-50 text-orange-700 border-orange-200">Needs Details</span>`
                        : '';

                    // Replacement PO indicator — mirrors the badge used on the
                    // Logistic Staff booking screen so dispatchers can also
                    // tell at a glance that this delivery belongs to a
                    // replacement order.
                    const replacementBadge = (b.po_type === 'replacement')
                        ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>`
                        : '';

                    const detailsLine = [
                        b.driver_name ? escapeHtml(b.driver_name) : '—',
                        b.truck_details ? escapeHtml(b.truck_details) : '',
                        b.plate_number ? escapeHtml(b.plate_number) : '',
                    ].filter(Boolean).join(' · ');

                    const addressLine = b.delivery_address
                        ? `<p class="text-xs text-slate-400 mt-1">${escapeHtml(b.delivery_address)}</p>`
                        : '';

                    let actionHtml;
                    if (needsDetails && isPending) {
                        actionHtml = `<span class="text-xs text-orange-600 font-medium">Awaiting delivery details</span>`;
                    } else if (awaitingDeliveryDate) {
                        actionHtml = `<span class="text-xs text-slate-400">Not yet due</span>`;
                    } else if (canStartLoading) {
                        actionHtml = `
                            <button onclick="startLoading(${b.id}, this)"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-orange-500 hover:bg-orange-600 text-white transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                Start Loading
                            </button>`;
                    } else if (b.status === 'loading') {
                        actionHtml = `
                            <button onclick="markInTransit(${b.id}, this)"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h7v-3.5a1.5 1.5 0 00-.44-1.06l-3-3A1.5 1.5 0 0015.5 8H13m0 8V8m0 8H3m0 0V6a1 1 0 011-1h8a1 1 0 011 1v10" />
                                </svg>
                                In Transit
                            </button>`;
                    } else if (b.status === 'in_transit') {
                        actionHtml = `
                            <button onclick="openPodModal(${b.id}, '${escapeHtml(b.nhccreference)}')"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Delivered
                            </button>`;
                    } else if (b.status === 'delivered') {
                        const thumb = b.proof_of_delivery_path
                            ? `<a href="<?= BASE_URL ?>/${b.proof_of_delivery_path}" target="_blank">
                                   <img src="<?= BASE_URL ?>/${b.proof_of_delivery_path}" alt="Proof of delivery" class="w-10 h-10 rounded-lg object-cover border border-slate-200">
                               </a>`
                            : '';
                        actionHtml = `
                            <div class="flex items-center gap-2">
                                ${thumb}
                                <span class="text-xs text-emerald-600 font-medium">Delivered ${formatDeliveredAt(b.delivered_at)}</span>
                            </div>`;
                    } else {
                        actionHtml = '';
                    }

                    return `
                        <div class="flex items-center gap-4 px-5 py-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-800">${escapeHtml(b.nhccreference)}</p>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${statusBadgeClasses(b.status)} capitalize">${statusLabel(b.status)}</span>
                                    ${needsDetailsBadge}
                                    ${replacementBadge}
                                </div>
                                <p class="text-xs text-slate-500 mt-1">${escapeHtml(b.contact_name)} · <span class="capitalize">${escapeHtml(b.delivery_method)}</span></p>
                                <p class="text-xs text-slate-400 mt-1">${detailsLine}</p>
                                ${addressLine}
                            </div>
                            <div class="flex-shrink-0">${actionHtml}</div>
                        </div>`;
                }).join('');

                return `
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 ${isToday ? 'bg-indigo-50/40' : ''}">
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-sm font-semibold text-slate-800">${isUnscheduled ? 'No delivery date set' : formatDateHeader(dateKey)}</h2>
                                ${headerBadge}
                            </div>
                            <p class="text-xs text-slate-400">${dayBookings.length} delivery${dayBookings.length !== 1 ? 'ies' : ''}</p>
                        </div>
                        <div class="divide-y divide-slate-100">${rows}</div>
                    </div>`;
            }).join('');
        }

        // ── Actions ──────────────────────────────────────────────────────
        function setBtnLoading(btn) {
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = 'Saving…';
        }

        function restoreBtn(btn) {
            btn.disabled = false;
            if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
        }

        function postAction(url, bookingId, btn) {
            setBtnLoading(btn);
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        lastBookingsJson = null; // force re-render on next fetch
                        fetchBookings();
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        restoreBtn(btn);
                    }
                })
                .catch(() => {
                    alert('Network error.');
                    restoreBtn(btn);
                });
        }

        function startLoading(bookingId, btn) {
            postAction('<?= BASE_URL ?>/logisticdispatcher-startloading', bookingId, btn);
        }

        function markInTransit(bookingId, btn) {
            postAction('<?= BASE_URL ?>/logisticdispatcher-intransit', bookingId, btn);
        }

        // ── Proof of Delivery modal ────────────────────────────────────────
        function openPodModal(bookingId, ref) {
            podBookingId = bookingId;
            document.getElementById('pod-ref').textContent = ref;
            document.getElementById('pod-file').value = '';
            document.getElementById('pod-file-error').classList.add('hidden');
            document.getElementById('pod-preview-wrap').classList.add('hidden');
            document.getElementById('pod-preview').src = '';
            document.getElementById('podModal').classList.remove('hidden');
            document.getElementById('podModal').classList.add('flex');
            stopPolling(); // pause polling while a modal/form is open to avoid clobbering input
        }

        function closePodModal() {
            document.getElementById('podModal').classList.add('hidden');
            document.getElementById('podModal').classList.remove('flex');
            startPolling();
        }

        function previewPodFile(input) {
            const err = document.getElementById('pod-file-error');
            const wrap = document.getElementById('pod-preview-wrap');
            const img = document.getElementById('pod-preview');
            err.classList.add('hidden');

            const file = input.files[0];
            if (!file) { wrap.classList.add('hidden'); return; }

            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                err.textContent = 'Only JPG, PNG, or WEBP images are allowed (no GIFs).';
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

        function submitPod() {
            const fileInput = document.getElementById('pod-file');
            const file = fileInput.files[0];
            const err = document.getElementById('pod-file-error');

            if (!file) {
                err.textContent = 'Please attach a proof of delivery photo.';
                err.classList.remove('hidden');
                return;
            }
            if (!confirm('Confirm that the driver reported this order as delivered?')) return;

            const btn = document.getElementById('pod-submit');
            btn.disabled = true;
            btn.textContent = 'Uploading…';

            const formData = new FormData();
            formData.append('booking_id', podBookingId);
            formData.append('proof_of_delivery', file);

            fetch('<?= BASE_URL ?>/logisticdispatcher-delivered', {
                method: 'POST',
                body: formData,
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closePodModal();
                        lastBookingsJson = null;
                        fetchBookings();
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        btn.disabled = false;
                        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirm Delivered`;
                    }
                })
                .catch(() => {
                    alert('Network error.');
                    btn.disabled = false;
                    btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirm Delivered`;
                });
        }

        document.getElementById('podModal').addEventListener('click', function (e) { if (e.target === this) closePodModal(); });

        // ── Init ─────────────────────────────────────────────────────────
        fetchBookings();
        startPolling();
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) { stopPolling(); } else { fetchBookings(); startPolling(); }
        });
    </script>

</body>

</html>