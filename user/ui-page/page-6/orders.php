<?php
// user/ui-page/page-6/orders.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/page-6/orders-functions.php';


$userId = (int) $_SESSION['user_id'];

$orders = fetchUserOrders($conn, $userId);
$statusTabs = buildStatusTabs($orders);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <div class="max-w-7xl mx-auto px-4 py-3 pb-24 md:pb-5">

        <div class="flex items-center justify-between mb-4">
            <h1 class="text-lg font-semibold text-gray-900">My Orders</h1>
          
        </div>

        <!-- Search -->
        <div class="relative mb-3">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-4.35-4.35M16.5 10.5a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
            <input id="orderSearch" type="text" placeholder="Search by order number or product"
                class="w-full text-sm rounded-md border border-gray-200 bg-white pl-9 pr-3 py-2 text-gray-700 placeholder:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300">
        </div>

        <!-- Refreshed in place by orders-poll.php every few seconds -->
        <div id="ordersDynamic">
            <?php include ROOT_PATH . '/user/ui-page/page-6/orders-list-partial.php'; ?>
        </div>

    </div>

    <!-- ═══════════════ ORDER DETAILS MODAL (labas ng #ordersDynamic — hindi apektado ng polling) ═══════════════ -->
    <div id="orderModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4"
        onclick="closeOrderModalBackdrop(event)">
        <div class="bg-white rounded-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto relative" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex items-center justify-between z-10">
                <div class="min-w-0">
                    <p id="modalOrderRef" class="text-sm font-semibold text-gray-900"></p>
                    <p id="modalOrderMeta" class="text-xs text-gray-400"></p>
                </div>
                <button onclick="closeOrderModal()" class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 flex-shrink-0" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="modalOrderBody" class="p-4"></div>
        </div>
    </div>

    <script>
        window.ORDERS_POLL_URL = BASE_URL + "/orders-poll";

        // ── Order details modal ──────────────────────────────────────────────────────
        function openOrderModal(orderId) {
            const row = document.querySelector(`.order-row[data-order-id="${orderId}"]`);
            const template = document.getElementById(`order-template-${orderId}`);
            if (!row || !template) return;

            document.getElementById('modalOrderRef').textContent =
                row.querySelector('a').textContent.trim();
            document.getElementById('modalOrderMeta').textContent =
                row.querySelector('.text-gray-400').textContent.trim();

            const body = document.getElementById('modalOrderBody');
            body.innerHTML = '';
            body.appendChild(template.content.cloneNode(true));

            document.querySelectorAll('.order-row').forEach(r => r.classList.remove('bg-indigo-50/60'));
            row.classList.add('bg-indigo-50/60');

            const modal = document.getElementById('orderModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeOrderModal() {
            const modal = document.getElementById('orderModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');

            document.querySelectorAll('.order-row').forEach(r => r.classList.remove('bg-indigo-50/60'));
        }

        function closeOrderModalBackdrop(e) {
            if (e.target.id === 'orderModal') closeOrderModal();
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeOrderModal();
        });
    </script>

    <script>
        (function () {
            const POLL_INTERVAL_MS = 8000;
            const dynamicEl = document.getElementById('ordersDynamic');
            const searchInput = document.getElementById('orderSearch');
            const liveIndicator = document.getElementById('liveIndicator');
            let activeStatus = 'all';
            let pollTimer = null;
            let isPolling = false;
            let lastVersions = {};

            function getOpenOrderIds() {
                // Modal state na lang ang ginagamit natin ngayon (hindi na
                // <details open>), kaya wala nang open state na kailangang
                // i-preserve dito. Pinapanatili lang ang function shape
                // para hindi masira ang ibang tumatawag dito.
                return new Set();
            }

            function restoreOpenState(openIds) {
                // No-op na — modal-based na ang view ng order details.
            }

            function applyFilters() {
                const ordersList = document.getElementById('ordersList');
                const noResults = document.getElementById('noResults');
                if (!ordersList) return; // empty state, nothing to filter

                const q = searchInput.value.trim().toLowerCase();
                const rows = dynamicEl.querySelectorAll('.order-row');
                let visibleCount = 0;

                rows.forEach(function (row) {
                    const statusMatch = activeStatus === 'all' || row.dataset.status === activeStatus;
                    const searchMatch = !q || row.dataset.search.includes(q);
                    const show = statusMatch && searchMatch;
                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });

                if (noResults) noResults.classList.toggle('hidden', visibleCount > 0);
                ordersList.classList.toggle('hidden', visibleCount === 0);
            }

            function bindTabs() {
                const tabs = dynamicEl.querySelectorAll('.status-tab');

                // Kung naalis na yung tab na current active (e.g. wala na ulit
                // order sa status na 'yon), bumalik sa "All" para hindi mahang.
                const stillExists = Array.from(tabs).some(t => t.dataset.tab === activeStatus);
                if (!stillExists) activeStatus = 'all';

                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        tabs.forEach(function (t) {
                            t.classList.remove('border-indigo-500', 'text-indigo-600');
                            t.classList.add('border-transparent', 'text-gray-400');
                        });
                        tab.classList.remove('border-transparent', 'text-gray-400');
                        tab.classList.add('border-indigo-500', 'text-indigo-600');
                        activeStatus = tab.dataset.tab;
                        applyFilters();
                    });

                    if (tab.dataset.tab === activeStatus) {
                        tab.classList.remove('border-transparent', 'text-gray-400');
                        tab.classList.add('border-indigo-500', 'text-indigo-600');
                    } else {
                        tab.classList.remove('border-indigo-500', 'text-indigo-600');
                        tab.classList.add('border-transparent', 'text-gray-400');
                    }
                });
            }

            function flashUpdatedOrders(newVersions) {
                Object.keys(newVersions).forEach(function (id) {
                    if (lastVersions[id] && lastVersions[id] !== newVersions[id]) {
                        const row = dynamicEl.querySelector('.order-row[data-order-id="' + id + '"]');
                        if (row) {
                            row.classList.add('ring-2', 'ring-indigo-200');
                            setTimeout(function () {
                                row.classList.remove('ring-2', 'ring-indigo-200');
                            }, 2500);
                        }
                    }
                });
            }

            function setLiveState(ok) {
                if (!liveIndicator) return;
                liveIndicator.classList.toggle('text-gray-400', ok);
                liveIndicator.classList.toggle('text-rose-400', !ok);
                const dot = liveIndicator.querySelector('span');
                if (dot) {
                    dot.classList.toggle('bg-emerald-400', ok);
                    dot.classList.toggle('bg-rose-400', !ok);
                }
            }

            async function poll() {
                // Huwag mag-poll habang bukas ang modal — hindi natin gustong
                // magbago ang laman ng list habang tinitingnan ng user ang
                // detalye ng isang order.
                const modal = document.getElementById('orderModal');
                if (isPolling || document.hidden || (modal && !modal.classList.contains('hidden'))) return;

                isPolling = true;
                try {
                    const res = await fetch(window.ORDERS_POLL_URL, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store',
                        credentials: 'same-origin'
                    });
                    if (!res.ok) throw new Error('poll_failed_' + res.status);
                    const data = await res.json();

                    const openIds = getOpenOrderIds();
                    dynamicEl.innerHTML = data.html;
                    restoreOpenState(openIds);
                    bindTabs();
                    applyFilters();
                    flashUpdatedOrders(data.version || {});
                    lastVersions = data.version || {};
                    setLiveState(true);
                } catch (err) {
                    setLiveState(false);
                } finally {
                    isPolling = false;
                }
            }

            function schedulePoll() {
                pollTimer = setTimeout(function () {
                    poll().finally(schedulePoll);
                }, POLL_INTERVAL_MS);
            }

            // Pause polling habang hindi visible ang tab; agad mag-refresh
            // pagbalik ng focus.
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    clearTimeout(pollTimer);
                    poll().finally(schedulePoll);
                }
            });

            bindTabs();
            applyFilters();
            searchInput.addEventListener('input', applyFilters);
            schedulePoll();
        })();
    </script>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

</body>

</html>