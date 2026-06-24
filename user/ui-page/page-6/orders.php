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

    <div class="max-w-3xl mx-auto px-4 py-6 flex-1 w-full">

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

    <script>
        window.ORDERS_POLL_URL = BASE_URL + "/orders-poll";
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
                const open = new Set();
                dynamicEl.querySelectorAll('.order-row[open]').forEach(function (row) {
                    open.add(row.dataset.orderId);
                });
                return open;
            }

            function restoreOpenState(openIds) {
                dynamicEl.querySelectorAll('.order-row').forEach(function (row) {
                    if (openIds.has(row.dataset.orderId)) {
                        row.setAttribute('open', '');
                    }
                });
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
                if (isPolling || document.hidden) return;
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