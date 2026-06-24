<?php
// logisticstaff-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_LOGISTIC];
$allowedPositions = [POSITION_LOGISTICSTAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$staffId = $_SESSION['account_id'] ?? 0;

$monthOffset = isset($_GET['month']) ? (int) $_GET['month'] : 0;
$baseDate = new DateTime('first day of this month');
$baseDate->setTime(0, 0, 0);
if ($monthOffset !== 0) {
    $baseDate->modify("{$monthOffset} months");
}
$year = (int) $baseDate->format('Y');
$month = (int) $baseDate->format('n');

$monthStart = new DateTime("{$year}-{$month}-01");
$monthEnd = new DateTime($monthStart->format('Y-m-t'));

$today = new DateTime();
$today->setTime(0, 0, 0);
$todayStr = $today->format('Y-m-d');

// can pre-fill the Truck/Vehicle + Delivery Address fields and show capacity as a reference.
$bStmt = $conn->prepare("
    SELECT db.*, ppl.nhccreference, ppl.contact_name, ppl.delivery_method,
           ppl.truck_name AS order_truck_name,
           ppl.truck_max_cubic_meter AS order_truck_max_cubic_meter,
           ppl.truck_max_weight_capacity AS order_truck_max_weight_capacity,
           ppl.address_full AS order_address_full,
           ppl.address_barangay AS order_address_barangay,
           ppl.address_city AS order_address_city,
           ppl.address_postalcode AS order_address_postalcode
    FROM nobledeliverybooking db
    JOIN noblepaidproductlist ppl ON ppl.id = db.order_id
    WHERE db.scheduled_date BETWEEN ? AND ?
      AND db.status NOT IN ('cancelled')
    ORDER BY db.scheduled_date ASC, db.scheduled_time_from ASC
");
$ms = $monthStart->format('Y-m-d');
$me = $monthEnd->format('Y-m-d');
$bStmt->bind_param("ss", $ms, $me);
$bStmt->execute();
$bookingsRaw = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bStmt->close();

$bookingsByDate = [];
foreach ($bookingsRaw as $b) {
    // A booking is "incomplete" (Step 1 only) when driver/truck/address haven't been filled in yet
    $b['needs_details'] = empty($b['driver_name']) || empty($b['truck_details']) || empty($b['delivery_address']);
    $bookingsByDate[$b['scheduled_date']][] = $b;
}

// Fetch ready-for-booking orders
$roStmt = $conn->prepare("
    SELECT DISTINCT
        ppl.id AS order_id, ppl.nhccreference, ppl.contact_name, ppl.delivery_method,
        npo.id AS po_id, npo.po_number, npo.po_type, rr.location, rr.ready_for_booking_at,
        rr.suggested_date_from, rr.suggested_date_to
    FROM noblereceivingreceiver rr
    JOIN noblepurchaseorder npo ON npo.id = rr.po_id
    JOIN noblepaidproductlist ppl ON ppl.id = npo.order_id
    LEFT JOIN nobledeliverybooking db ON db.order_id = ppl.id AND db.status NOT IN ('cancelled')
    WHERE rr.ready_for_booking = 1 AND db.id IS NULL
    ORDER BY rr.ready_for_booking_at ASC
");
$roStmt->execute();
$readyOrders = $roStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$roStmt->close();

$groupedReady = [];
foreach ($readyOrders as $o) {
    $groupedReady[$o['nhccreference']][] = $o;
}

$monthLabel = $baseDate->format('F Y');
$bookingsJson = json_encode($bookingsByDate);

$suggestedRanges = [];
foreach ($groupedReady as $ref => $orders) {
    $first = $orders[0];
    if (!empty($first['suggested_date_from']) && !empty($first['suggested_date_to'])) {
        $suggestedRanges[] = [
            'from' => $first['suggested_date_from'],
            'to' => $first['suggested_date_to'],
            'ref' => $ref,
        ];
    }
}
$suggestedRangesJson = json_encode($suggestedRanges);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Bookings — Logistic Staff</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
    <style>
        @keyframes pulseRedWarning {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
            50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        }
        .today-due-warning {
            border: 2px solid #ef4444 !important;
            animation: pulseRedWarning 1.6s ease-in-out infinite;
        }
        .today-due-badge {
            animation: pulseRedWarning 1.6s ease-in-out infinite;
        }
    </style>
</head>

<body class="bg-slate-100">
    <div class="ml-60 min-h-screen p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800">Delivery Bookings</h1>
                <p class="text-sm text-slate-500 mt-0.5">Schedule and manage delivery dispatches</p>
            </div>
            <!-- Month nav (only visible on calendar tab) -->
            <div id="month-nav" class="flex items-center gap-2">
                <a href="?month=<?= $monthOffset - 1 ?>"
                    class="p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <span
                    class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 min-w-36 text-center">
                    <?= $monthLabel ?>
                </span>
                <a href="?month=<?= $monthOffset + 1 ?>"
                    class="p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <?php if ($monthOffset !== 0): ?>
                    <a href="?"
                        class="ml-1 px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors">
                        Today
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-5 bg-white border border-slate-200 rounded-xl p-1 w-fit shadow-sm">
            <button onclick="switchTab('calendar')" id="tab-calendar"
                class="tab-btn px-5 py-2 text-sm font-medium rounded-lg transition-colors bg-indigo-600 text-white">
                <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Calendar
            </button>
            <button onclick="switchTab('list')" id="tab-list"
                class="tab-btn px-5 py-2 text-sm font-medium rounded-lg transition-colors text-slate-500 hover:text-slate-700">
                <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                List
            </button>
        </div>

        <!-- ══ CALENDAR TAB ══════════════════════════════════════════════ -->
        <div id="pane-calendar">
            <div class="flex gap-5">
                <!-- Calendar -->
                <div class="flex-1 min-w-0">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-7 border-b border-slate-200">
                            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow): ?>
                                <div
                                    class="px-3 py-2.5 text-center text-xs font-medium uppercase tracking-wide <?= $dow === 'Sun' ? 'text-red-400' : 'text-slate-400' ?>">
                                    <?= $dow ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php
                        $firstDow = (int) $monthStart->format('w');
                        $daysInMonth = (int) $monthEnd->format('j');
                        $totalCells = ceil(($firstDow + $daysInMonth) / 7) * 7;
                        $prevMonthEnd = (clone $monthStart)->modify('-1 day');
                        $prevDaysInMonth = (int) $prevMonthEnd->format('j');
                        ?>
                        <div class="grid grid-cols-7">
                            <?php for ($i = 0; $i < $totalCells; $i++):
                                if ($i < $firstDow) {
                                    $cellDay = $prevDaysInMonth - $firstDow + $i + 1;
                                    $cellMonth = $month - 1;
                                    $cellYear = $year;
                                    if ($cellMonth < 1) {
                                        $cellMonth = 12;
                                        $cellYear--;
                                    }
                                    $otherMonth = true;
                                } elseif ($i >= $firstDow + $daysInMonth) {
                                    $cellDay = $i - $firstDow - $daysInMonth + 1;
                                    $cellMonth = $month + 1;
                                    $cellYear = $year;
                                    if ($cellMonth > 12) {
                                        $cellMonth = 1;
                                        $cellYear++;
                                    }
                                    $otherMonth = true;
                                } else {
                                    $cellDay = $i - $firstDow + 1;
                                    $cellMonth = $month;
                                    $cellYear = $year;
                                    $otherMonth = false;
                                }
                                $dateStr = sprintf('%04d-%02d-%02d', $cellYear, $cellMonth, $cellDay);
                                $isToday = $dateStr === $todayStr;
                                $isSunday = $i % 7 === 0;
                                $isLastRow = $i >= $totalCells - 7;
                                $dayBooks = !$otherMonth ? ($bookingsByDate[$dateStr] ?? []) : [];
                                $hasBooks = count($dayBooks) > 0;
                                ?>
                                <div class="relative min-h-[88px] p-2 border-r border-b border-slate-100
                                <?= $isLastRow ? 'border-b-0' : '' ?>
                                <?= $otherMonth ? 'bg-slate-50/70 opacity-50' : '' ?>
                                <?= !$otherMonth && $hasBooks ? 'cursor-pointer hover:bg-indigo-50/40 transition-colors' : '' ?>
                                <?= $isToday ? 'bg-indigo-50/30' : '' ?>" <?= !$otherMonth ? "id=\"cell-{$dateStr}\"" : '' ?>
                                    <?= $isToday ? 'data-today-cell="1"' : '' ?>
                                    <?= (!$otherMonth && $hasBooks) ? "onclick=\"selectDay('{$dateStr}')\"" : '' ?>>
                                    <div class="flex justify-end">
                                        <?php if ($isToday): ?>
                                            <span id="today-cell-marker"
                                                class="w-7 h-7 flex items-center justify-center rounded-full bg-indigo-600 text-white text-xs font-semibold"><?= $cellDay ?></span>
                                        <?php else: ?>
                                            <span
                                                class="text-sm font-medium <?= $isSunday && !$otherMonth ? 'text-red-400' : 'text-slate-700' ?>"><?= $cellDay ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$otherMonth && $hasBooks): ?>
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            <?php $shown = 0;
                                            foreach ($dayBooks as $b):
                                                if ($shown >= 4)
                                                    break;
                                                // Dot color: in_transit = sky, needs_details = orange, rescheduled = amber, default = indigo
                                                if ($b['status'] === 'in_transit') {
                                                    $dotColor = 'bg-sky-400';
                                                } elseif ($b['needs_details']) {
                                                    $dotColor = 'bg-orange-400';
                                                } elseif ($b['status'] === 'rescheduled') {
                                                    $dotColor = 'bg-amber-400';
                                                } else {
                                                    $dotColor = 'bg-indigo-500';
                                                }
                                                ?>
                                                <span class="w-2 h-2 rounded-full <?= $dotColor ?>"></span>
                                                <?php $shown++; endforeach; ?>
                                            <?php if (count($dayBooks) > 4): ?>
                                                <span
                                                    class="text-[10px] text-slate-400 leading-none self-center">+<?= count($dayBooks) - 4 ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-1"><?= count($dayBooks) ?>
                                            booking<?= count($dayBooks) !== 1 ? 's' : '' ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="flex items-center gap-4 px-4 py-2.5 border-t border-slate-100 flex-wrap">
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 inline-block"></span> Scheduled
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="w-2.5 h-2.5 rounded-full bg-orange-400 inline-block"></span> Needs details
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span> Rescheduled
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="w-2.5 h-2.5 rounded-full bg-sky-400 inline-block"></span> In Transit
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span
                                    class="w-2.5 h-2.5 rounded-sm bg-indigo-50 ring-1 ring-indigo-300 inline-block"></span>
                                Suggested delivery window
                            </div>
                        </div>
                    </div>

                    <!-- Day detail panel -->
                    <div id="detail-panel"
                        class="hidden mt-4 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-800" id="panel-date-label"></h2>
                                <p class="text-xs text-slate-400 mt-0.5" id="panel-count-label"></p>
                            </div>
                            <button onclick="closePanel()" class="text-slate-400 hover:text-slate-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div id="panel-list" class="divide-y divide-slate-100"></div>
                    </div>
                </div>

                <!-- Ready for Booking sidebar -->
                <div class="w-72 flex-shrink-0">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden sticky top-6">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <h2 class="text-sm font-semibold text-slate-800">Ready for Booking</h2>
                            <p class="text-xs text-slate-400 mt-0.5"><?= count($groupedReady) ?>
                                order<?= count($groupedReady) !== 1 ? 's' : '' ?> pending</p>
                        </div>
                        <?php if (empty($groupedReady)): ?>
                            <div class="p-6 text-center">
                                <div
                                    class="w-10 h-10 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <p class="text-xs text-slate-400">All orders scheduled</p>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-slate-100 max-h-[70vh] overflow-y-auto">
                                <?php foreach ($groupedReady as $ref => $orders):
                                    $first = $orders[0]; ?>
                                    <div class="px-4 py-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-slate-800 truncate">
                                                    <?= htmlspecialchars($ref) ?></p>
                                                <p class="text-xs font-semibold text-slate-800 truncate">
    <?= htmlspecialchars($ref) ?>
    <?php if (($first['po_type'] ?? 'normal') === 'replacement'): ?>
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>
    <?php endif; ?>
</p>
                                                <p class="text-xs text-slate-400 mt-0.5 truncate">
                                                    <?= htmlspecialchars($first['contact_name']) ?></p>
                                                <p class="text-xs text-slate-400 capitalize">
                                                    <?= htmlspecialchars($first['delivery_method']) ?></p>
                                                <?php if (!empty($first['suggested_date_from']) && !empty($first['suggested_date_to'])): ?>
                                                    <p class="text-xs text-indigo-600 font-medium mt-1">
                                                        <svg class="w-3 h-3 inline-block mr-0.5 -mt-0.5" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <?= date('M j', strtotime($first['suggested_date_from'])) ?> –
                                                        <?= date('M j', strtotime($first['suggested_date_to'])) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <button
                                                onclick="openBookingModal(<?= $first['order_id'] ?>, <?= $first['po_id'] ?>, '<?= htmlspecialchars($ref, ENT_QUOTES) ?>', '<?= htmlspecialchars($first['contact_name'], ENT_QUOTES) ?>')"
                                                class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 4v16m8-8H4" />
                                                </svg>
                                                Book
                                            </button>
                                        </div>
                                        <?php if (count($orders) > 1): ?>
                                            <p class="text-[10px] text-slate-400 mt-1.5"><?= count($orders) ?> POs</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ LIST TAB ═════════════════════════════════════════════════ -->
        <div id="pane-list" class="hidden">
            <!-- Search + filter bar -->
            <div class="flex items-center gap-3 mb-4">
                <div class="relative flex-1 max-w-sm">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" id="list-search" placeholder="Search reference, customer, driver…"
                        oninput="fetchBookings()"
                        class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <select id="list-status" onchange="fetchBookings()"
                    class="px-3 py-2.5 rounded-lg border border-slate-200 bg-white text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">All statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="rescheduled">Rescheduled</option>
                    <option value="in_transit">In Transit</option>
                </select>
                <div class="flex items-center gap-1.5 text-xs text-slate-400 ml-auto">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block" id="realtime-dot"></span>
                    <span id="realtime-label">Live</span>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Reference</th>
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Customer</th>
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Date Scheduled</th>
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Delivery Date</th>
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Time</th>
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Driver</th>
                            <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide px-5 py-3">
                                Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="list-tbody" class="divide-y divide-slate-100">
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-slate-400 text-sm">Loading…</td>
                        </tr>
                    </tbody>
                </table>
                <div id="list-empty" class="hidden p-10 text-center">
                    <p class="text-slate-400 text-sm">No bookings found.</p>
                </div>
            </div>
        </div>

    </div>

    <!-- ===== STEP 1: SCHEDULE MODAL ===== -->
    <div id="bookingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-[460px] max-h-[90vh] overflow-y-auto relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Schedule Delivery</h3>
                    <p id="bm-ref" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closeBookingModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">Schedule</p>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Date Scheduled <span
                            class="text-red-500">*</span></label>
                    <input type="date" id="bm-date" onchange="validateBookingDate(this)"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <p id="bm-date-error" class="hidden text-xs text-red-500 mt-1"></p>
                    <p class="text-xs text-slate-400 mt-1">Mon–Fri · Sat 8am–12pm · Closed Sundays</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Delivery Date <span
                            class="text-red-500">*</span></label>
                    <input type="date" id="bm-delivery-date" onchange="validateBookingDeliveryDate(this)"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <p id="bm-delivery-date-error" class="hidden text-xs text-red-500 mt-1"></p>
                    <p class="text-xs text-slate-400 mt-1">The date the order is expected to reach the customer. Closed Sundays.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Time From <span
                            class="text-red-500">*</span></label>
                    <input type="time" id="bm-time-from"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <p id="bm-time-error" class="hidden text-xs text-red-500 -mt-2"></p>
                <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Customer</p>
                    <p id="bm-customer" class="text-sm font-medium text-slate-800"></p>
                </div>
                <div id="bm-suggested-banner" class="hidden rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3">
                    <p class="text-xs font-medium text-indigo-700 mb-0.5">Suggested delivery window</p>
                    <p id="bm-suggested-text" class="text-xs text-indigo-600"></p>
                </div>
                <p class="text-xs text-slate-400">
                    Delivery details (truck, driver, address) will be added in the next step once the schedule is
                    confirmed.
                </p>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                <button onclick="closeBookingModal()"
                    class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">Cancel</button>
                <button onclick="submitBooking()" id="bm-submit"
                    class="px-5 py-2.5 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Confirm Booking
                </button>
            </div>
        </div>
    </div>

    <!-- ===== STEP 2: DELIVERY DETAILS MODAL ===== -->
    <div id="detailsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-[520px] max-h-[90vh] overflow-y-auto relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Delivery Details</h3>
                    <p id="dm-ref" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closeDetailsModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mx-6 mt-5 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-xs text-indigo-700">
                <p class="font-medium mb-0.5">Scheduled</p>
                <p id="dm-schedule-text"></p>
            </div>
            <!-- Reference info pulled from noblepaidproductlist: truck name + max capacity -->
            <div id="dm-truck-ref-banner" class="hidden mx-6 mt-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-600">
                <p class="font-medium text-slate-500 uppercase tracking-wide mb-1">Order Truck Reference</p>
                <p id="dm-truck-ref-text"></p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">Delivery Details</p>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Truck / Vehicle <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="dm-truck" placeholder="e.g. ABC 1234 — Isuzu Elf"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Plate Number <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="dm-plate" placeholder="e.g. ABC 1234"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Driver Name <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="dm-driver" placeholder="Full name"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Delivery Address <span
                            class="text-red-500">*</span></label>
                    <textarea id="dm-address" rows="3" placeholder="Full delivery address"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Notes</label>
                    <textarea id="dm-notes" rows="3" placeholder="Special instructions, fragile items, etc."
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                <button onclick="closeDetailsModal()"
                    class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">Cancel</button>
                <button onclick="submitDetails()" id="dm-submit"
                    class="px-5 py-2.5 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Confirm Details
                </button>
            </div>
        </div>
    </div>

    <!-- ===== RESCHEDULE MODAL ===== -->
    <div id="rescheduleModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-[860px] max-h-[90vh] overflow-y-auto relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Reschedule Delivery</h3>
                    <p id="rm-ref" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closeRescheduleModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mx-6 mt-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                <p class="font-medium mb-0.5">Current Schedule</p>
                <p id="rm-original-text"></p>
            </div>
            <div class="grid grid-cols-2 gap-0 divide-x divide-slate-100 mt-1">
                <div class="px-6 py-5 space-y-4">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">New Schedule</p>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">New Date Scheduled <span
                                class="text-red-500">*</span></label>
                        <input type="date" id="rm-date" onchange="validateRescheduleDate(this)"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        <p id="rm-date-error" class="hidden text-xs text-red-500 mt-1"></p>
                        <p class="text-xs text-slate-400 mt-1">Mon–Fri · Sat 8am–12pm · Closed Sundays</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">New Delivery Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" id="rm-delivery-date" onchange="validateRescheduleDeliveryDate(this)"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        <p id="rm-delivery-date-error" class="hidden text-xs text-red-500 mt-1"></p>
                        <p class="text-xs text-slate-400 mt-1">Closed Sundays.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Time From <span
                                class="text-red-500">*</span></label>
                        <input type="time" id="rm-time-from"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                    </div>
                    <p id="rm-time-error" class="hidden text-xs text-red-500 -mt-2"></p>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Truck / Vehicle <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="rm-truck"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Plate Number <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="rm-plate"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Driver Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="rm-driver"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                    </div>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">Additional Info</p>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Delivery Address <span
                                class="text-red-500">*</span></label>
                        <textarea id="rm-address" rows="3"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Reason for Rescheduling <span
                                class="text-red-500">*</span></label>
                        <textarea id="rm-reason" rows="3" placeholder="Why is the delivery being rescheduled?"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Additional Notes</label>
                        <textarea id="rm-notes" rows="3"
                            class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"></textarea>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-between items-center">
                <button onclick="cancelBooking()"
                    class="px-4 py-2.5 text-sm font-medium text-red-600 hover:text-red-700 rounded-lg border border-red-200 hover:border-red-300 transition-colors">
                    Cancel Booking
                </button>
                <div class="flex gap-2">
                    <button onclick="closeRescheduleModal()"
                        class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">Close</button>
                    <button onclick="submitReschedule()" id="rm-submit"
                        class="px-5 py-2.5 text-sm font-medium bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Confirm Reschedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== RESET & RESCHEDULE MODAL (Replacement POs only) ===== -->
<div id="resetModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-[460px] max-h-[90vh] overflow-y-auto relative">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-semibold text-slate-800">Reset & Reschedule</h3>
                <p id="rs-ref" class="text-xs text-slate-400 mt-0.5"></p>
            </div>
            <button onclick="closeResetModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="mx-6 mt-5 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-xs text-rose-700">
            <p class="font-medium mb-0.5">Replacement PO</p>
            <p>This will clear the existing truck, driver, plate, and address — you'll need to add delivery details again after setting the new schedule.</p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">New Schedule</p>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Date Scheduled <span class="text-red-500">*</span></label>
                <input type="date" id="rs-date" onchange="validateResetDate(this)"
                    class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent">
                <p id="rs-date-error" class="hidden text-xs text-red-500 mt-1"></p>
                <p class="text-xs text-slate-400 mt-1">Mon–Fri · Sat 8am–12pm · Closed Sundays</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Delivery Date <span class="text-red-500">*</span></label>
                <input type="date" id="rs-delivery-date" onchange="validateResetDeliveryDate(this)"
                    class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent">
                <p id="rs-delivery-date-error" class="hidden text-xs text-red-500 mt-1"></p>
                <p class="text-xs text-slate-400 mt-1">Closed Sundays.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Time From <span class="text-red-500">*</span></label>
                <input type="time" id="rs-time-from"
                    class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent">
            </div>
            <p id="rs-time-error" class="hidden text-xs text-red-500 -mt-2"></p>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button onclick="closeResetModal()"
                class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">Cancel</button>
            <button onclick="submitResetReschedule()" id="rs-submit"
                class="px-5 py-2.5 text-sm font-medium bg-rose-600 hover:bg-rose-700 text-white rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reset & Reschedule
            </button>
        </div>
    </div>
</div>

   <script>
        const HOURS = {
            weekday: { open: '07:00', close: '17:00' },
            saturday: { open: '08:00', close: '12:00' },
        };

        const allBookings = <?= $bookingsJson ?>;
        const suggestedRanges = <?= $suggestedRangesJson ?>;

        let selectedDate = null;
        let activeTab = 'calendar';
        let pollTimer = null;

        // ── Today cell red warning (time-based) ───────────────────────────
        function getTodayStr() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        function getCurrentHHMM() {
            const d = new Date();
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }

        function getDueCountToday() {
            const todayStr = getTodayStr();
            const books = allBookings[todayStr] || [];
            const nowHHMM = getCurrentHHMM();
            return books.filter(b => {
                if (b.status === 'in_transit') return false;
                const t = (b.scheduled_time_from || '').substring(0, 5);
                return t && t <= nowHHMM;
            }).length;
        }

        function updateTodayCellWarning() {
            const cell = document.querySelector('[data-today-cell="1"]');
            if (!cell) return;

            const dueCount = getDueCountToday();
            let badge = document.getElementById('today-due-badge');

            if (dueCount > 0) {
                cell.classList.add('today-due-warning');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.id = 'today-due-badge';
                    badge.className = 'today-due-badge absolute top-1 left-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold leading-none z-10';
                    cell.appendChild(badge);
                }
                badge.textContent = dueCount > 9 ? '9+' : dueCount;
            } else {
                cell.classList.remove('today-due-warning');
                if (badge) badge.remove();
            }
        }

        updateTodayCellWarning();
        setInterval(updateTodayCellWarning, 30000);

        // ── Tab switching ────────────────────────────────────────────────
        function switchTab(tab) {
            activeTab = tab;
            document.getElementById('pane-calendar').classList.toggle('hidden', tab !== 'calendar');
            document.getElementById('pane-list').classList.toggle('hidden', tab !== 'list');
            document.getElementById('month-nav').classList.toggle('hidden', tab !== 'calendar');

            document.getElementById('tab-calendar').className =
                'tab-btn px-5 py-2 text-sm font-medium rounded-lg transition-colors ' +
                (tab === 'calendar' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700');
            document.getElementById('tab-list').className =
                'tab-btn px-5 py-2 text-sm font-medium rounded-lg transition-colors ' +
                (tab === 'list' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700');

            if (tab === 'list') {
                fetchBookings();
                startPolling();
            } else {
                stopPolling();
            }
        }

        // ── Realtime polling ─────────────────────────────────────────────
        function startPolling() {
            stopPolling();
            pollTimer = setInterval(fetchBookings, 8000);
        }

        function stopPolling() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        function fetchBookings() {
            const search = document.getElementById('list-search').value.trim();
            const status = document.getElementById('list-status').value;
            const params = new URLSearchParams({ search, status });

            document.getElementById('realtime-dot').className = 'w-2 h-2 rounded-full bg-amber-400 inline-block';
            document.getElementById('realtime-label').textContent = 'Fetching…';

            fetch(`<?= BASE_URL ?>/logisticstaff-getbookings?${params}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('realtime-dot').className = 'w-2 h-2 rounded-full bg-emerald-400 inline-block';
                    document.getElementById('realtime-label').textContent = 'Live';
                    if (data.success) renderList(data.bookings);
                })
                .catch(() => {
                    document.getElementById('realtime-dot').className = 'w-2 h-2 rounded-full bg-red-400 inline-block';
                    document.getElementById('realtime-label').textContent = 'Offline';
                });
        }

        function bookingNeedsDetails(b) {
            return !b.driver_name || !b.truck_details || !b.delivery_address;
        }

        function renderList(bookings) {
            const tbody = document.getElementById('list-tbody');
            const empty = document.getElementById('list-empty');

            if (!bookings.length) {
                tbody.innerHTML = '';
                empty.classList.remove('hidden');
                return;
            }
            empty.classList.add('hidden');

            const statusBadge = {
                scheduled:   'bg-indigo-50 text-indigo-700 border-indigo-200',
                rescheduled: 'bg-amber-50 text-amber-700 border-amber-200',
                in_transit:  'bg-sky-50 text-sky-700 border-sky-200',
                delivered:   'bg-emerald-50 text-emerald-700 border-emerald-200',
            };

            tbody.innerHTML = bookings.map(b => {
                const needsDetails = bookingNeedsDetails(b);
                const isInTransit = b.status === 'in_transit';
                const isDelivered = b.status === 'delivered';
                const isReplacement = b.po_type === 'replacement';
                // "Locked" = no more actions allowed from this screen.
                // - in_transit is ALWAYS locked (truck is literally on the road).
                // - delivered is locked ONLY for normal POs (final state).
                //   A delivered REPLACEMENT PO stays actionable — that's the
                //   whole point of a replacement: the original was delivered,
                //   and the replacement item still needs its own schedule.
                const isLocked = isInTransit || (isDelivered && !isReplacement);
                const isPast = b.scheduled_date < '<?= $todayStr ?>';

                const badge = isInTransit
                    ? 'bg-sky-50 text-sky-700 border-sky-200'
                    : (isDelivered && !isReplacement)
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : needsDetails
                            ? 'bg-orange-50 text-orange-700 border-orange-200'
                            : (statusBadge[b.status] ?? 'bg-slate-100 text-slate-500 border-slate-200');

                const badgeLabel = isInTransit
                    ? 'In Transit'
                    : (isDelivered && !isReplacement)
                        ? 'Delivered'
                        : needsDetails
                            ? 'Needs Details'
                            : b.status;

                // In Transit / Delivered(non-replacement): non-clickable pill.
                const actionBtn = isLocked
                    ? `<span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border ${isDelivered ? 'border-emerald-200 text-emerald-600 bg-emerald-50' : 'border-sky-200 text-sky-600 bg-sky-50'} cursor-default select-none">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${isDelivered
                                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
                                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3m-1 11l2 2 4-4m-4-7h8M9 9h1m-1 4h1"/>'}
                        </svg>
                        ${isDelivered ? 'Delivered' : 'In Transit'}
                    </span>`
                    : needsDetails
                        ? `<button onclick='openDetailsModal(${JSON.stringify(b)})'
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-orange-200 text-orange-700 hover:bg-orange-50 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Details
                        </button>`
                        : b.po_type === 'replacement'
                            ? `<button onclick='openResetModal(${JSON.stringify(b)})'
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reset & Reschedule
                            </button>`
                            : `<button onclick='openRescheduleModal(${JSON.stringify(b)})'
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-amber-200 text-amber-700 hover:bg-amber-50 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reschedule
                            </button>`;

                // Hide cancel button for locked (in_transit / delivered) bookings
                const cancelBtn = isLocked ? '' : `
                    <button onclick="quickCancelBooking(${b.id})"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Cancel
                    </button>`;

                return `
            <tr class="hover:bg-slate-50 transition-colors ${isPast ? 'opacity-60' : ''}">
                <td class="px-5 py-3 font-semibold text-slate-800 text-xs">
                    ${b.nhccreference}
                    ${b.po_type === 'replacement' ? `<span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>` : ''}
                </td>
                <td class="px-5 py-3">
                    <p class="text-sm text-slate-700">${b.contact_name}</p>
                    <p class="text-xs text-slate-400 capitalize">${b.delivery_method}</p>
                </td>
                <td class="px-5 py-3 text-sm text-slate-600">${formatDateDisplay(b.scheduled_date)}</td>
                <td class="px-5 py-3 text-sm text-slate-600">${b.delivery_date ? formatDateDisplay(b.delivery_date) : '—'}</td>
                <td class="px-5 py-3 text-xs text-slate-500">${formatTime(b.scheduled_time_from)}</td>
                <td class="px-5 py-3">
                    <p class="text-sm text-slate-700">${b.driver_name ?? '—'}</p>
                    <p class="text-xs text-slate-400">${b.truck_details ?? ''}${b.plate_number ? ' · ' + b.plate_number : ''}</p>
                </td>
                <td class="px-5 py-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${badge} capitalize">${badgeLabel}</span>
                </td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        ${actionBtn}
                        ${cancelBtn}
                    </div>
                </td>
            </tr>`;
            }).join('');
        }

        function quickCancelBooking(bookingId) {
            if (!confirm('Cancel this booking? This cannot be undone.')) return;
            fetch('<?= BASE_URL ?>/logisticstaff-cancelbooking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) fetchBookings();
                    else alert(data.message ?? 'Something went wrong.');
                })
                .catch(() => alert('Network error.'));
        }

        // ── Calendar highlight ───────────────────────────────────────────
        function highlightSuggestedRanges() {
            suggestedRanges.forEach(range => {
                const from = new Date(range.from + 'T00:00:00');
                const to = new Date(range.to + 'T00:00:00');
                const cur = new Date(from);
                while (cur <= to) {
                    const dateStr = cur.toISOString().split('T')[0];
                    const cell = document.getElementById('cell-' + dateStr);
                    if (cell) cell.classList.add('suggested-range-highlight', 'bg-indigo-50', 'ring-1', 'ring-inset', 'ring-indigo-200');
                    cur.setDate(cur.getDate() + 1);
                }
            });
        }
        highlightSuggestedRanges();

        // ── Calendar day click ───────────────────────────────────────────
        function selectDay(dateStr) {
            if (selectedDate === dateStr) {
                selectedDate = null;
                document.querySelectorAll('[id^="cell-"]').forEach(el => el.classList.remove('ring-2', 'ring-indigo-400'));
                document.getElementById('detail-panel').classList.add('hidden');
                return;
            }
            selectedDate = dateStr;
            document.querySelectorAll('[id^="cell-"]').forEach(el => el.classList.remove('ring-2', 'ring-indigo-400'));
            const cell = document.getElementById('cell-' + dateStr);
            if (cell) cell.classList.add('ring-2', 'ring-indigo-400');
            renderPanel(dateStr);
        }

        function closePanel() {
            selectedDate = null;
            document.querySelectorAll('[id^="cell-"]').forEach(el => el.classList.remove('ring-2', 'ring-indigo-400'));
            document.getElementById('detail-panel').classList.add('hidden');
        }

        function renderPanel(dateStr) {
            const panel = document.getElementById('detail-panel');
            const books = allBookings[dateStr] || [];
            const d = new Date(dateStr + 'T00:00:00');
            document.getElementById('panel-date-label').textContent = d.toLocaleDateString('en-PH', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
            document.getElementById('panel-count-label').textContent = books.length + ' booking' + (books.length !== 1 ? 's' : '');
            const list = document.getElementById('panel-list');
            list.innerHTML = books.map(b => {
                const needsDetails = bookingNeedsDetails(b);
                const isInTransit = b.status === 'in_transit';
                const isDelivered = b.status === 'delivered';
                const isReplacement = b.po_type === 'replacement';
                // Same rule as renderList(): in_transit always locked;
                // delivered locked only when NOT a replacement PO.
                const isLocked = isInTransit || (isDelivered && !isReplacement);
                const isRescheduled = b.status === 'rescheduled';

                const dotColor = isInTransit
                    ? 'bg-sky-400'
                    : (isDelivered && !isReplacement)
                        ? 'bg-emerald-400'
                        : needsDetails
                            ? 'bg-orange-400'
                            : isRescheduled
                                ? 'bg-amber-400'
                                : 'bg-indigo-500';

                const badgeCls = isInTransit
                    ? 'bg-sky-50 text-sky-700 border border-sky-200'
                    : (isDelivered && !isReplacement)
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                        : needsDetails
                            ? 'bg-orange-50 text-orange-700 border border-orange-200'
                            : isRescheduled
                                ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                : 'bg-indigo-50 text-indigo-700 border border-indigo-200';

                const badgeLabel = isInTransit
                    ? 'In Transit'
                    : (isDelivered && !isReplacement)
                        ? 'Delivered'
                        : needsDetails
                            ? 'Needs Details'
                            : b.status;

                // Locked (in transit / delivered): row is not clickable
                const clickHandler = isLocked
                    ? ''
                    : needsDetails
                        ? `openDetailsModal(${JSON.stringify(b)})`
                        : b.po_type === 'replacement'
                            ? `openResetModal(${JSON.stringify(b)})`
                            : `openRescheduleModal(${JSON.stringify(b)})`;

                const deliveryDateLine = b.delivery_date
                    ? `<p class="text-xs text-indigo-500 mt-0.5">Delivery: ${formatDateDisplay(b.delivery_date)}</p>`
                    : '';

                return `
            <div class="flex items-center gap-4 px-5 py-3.5 ${!isLocked ? 'hover:bg-slate-50 transition-colors cursor-pointer' : ''}"
                 ${clickHandler ? `onclick='${clickHandler}'` : ''}>
                <span class="w-2.5 h-2.5 rounded-full ${dotColor} flex-shrink-0"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800">
                        ${b.nhccreference}
                        ${b.po_type === 'replacement' ? `<span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>` : ''}
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">${b.contact_name}</p>
                    <p class="text-xs text-slate-400 mt-0.5">${formatTime(b.scheduled_time_from)}${b.driver_name ? ' &nbsp;·&nbsp; ' + b.driver_name : ''}${b.truck_details ? ' &nbsp;·&nbsp; ' + b.truck_details : ''}${b.plate_number ? ' &nbsp;·&nbsp; ' + b.plate_number : ''}</p>
                    ${deliveryDateLine}
                </div>
                <span class="text-xs px-2.5 py-1 rounded-lg font-medium ${badgeCls} flex-shrink-0 capitalize">${badgeLabel}</span>
            </div>`;
            }).join('');
            panel.classList.remove('hidden');
        }

        // ── STEP 1: Schedule modal ────────────────────────────────────────
        let bmOrderId = 0, bmPoId = 0, bmRef = '', bmCustomer = '';

        function openBookingModal(orderId, poId, ref, customer) {
            bmOrderId = orderId; bmPoId = poId; bmRef = ref; bmCustomer = customer;
            document.getElementById('bm-ref').textContent = ref;
            document.getElementById('bm-customer').textContent = customer;
            ['bm-date', 'bm-delivery-date', 'bm-time-from']
                .forEach(id => document.getElementById(id).value = '');
            document.getElementById('bm-date-error').classList.add('hidden');
            document.getElementById('bm-delivery-date-error').classList.add('hidden');
            document.getElementById('bm-time-error').classList.add('hidden');

            const range = suggestedRanges.find(r => r.ref === ref);
            const banner = document.getElementById('bm-suggested-banner');
            if (range) {
                document.getElementById('bm-suggested-text').textContent = formatDateDisplay(range.from) + ' – ' + formatDateDisplay(range.to);
                banner.classList.remove('hidden');
                document.getElementById('bm-date').value = range.from;
                document.getElementById('bm-date').min = range.from;
                document.getElementById('bm-date').max = range.to;
            } else {
                banner.classList.add('hidden');
                document.getElementById('bm-date').removeAttribute('min');
                document.getElementById('bm-date').removeAttribute('max');
            }

            document.getElementById('bookingModal').classList.remove('hidden');
            document.getElementById('bookingModal').classList.add('flex');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
            document.getElementById('bookingModal').classList.remove('flex');
        }

        function validateBookingDate(input) {
            const err = document.getElementById('bm-date-error');
            const msg = validateDate(input.value);
            if (msg) { err.textContent = msg; err.classList.remove('hidden'); input.value = ''; }
            else { err.classList.add('hidden'); }
        }

        function validateBookingDeliveryDate(input) {
            const err = document.getElementById('bm-delivery-date-error');
            const msg = validateDate(input.value);
            if (msg) { err.textContent = msg; err.classList.remove('hidden'); input.value = ''; }
            else { err.classList.add('hidden'); }
        }

        function validateRescheduleDate(input) {
            const err = document.getElementById('rm-date-error');
            const msg = validateDate(input.value);
            if (msg) { err.textContent = msg; err.classList.remove('hidden'); input.value = ''; }
            else { err.classList.add('hidden'); }
        }

        function validateRescheduleDeliveryDate(input) {
            const err = document.getElementById('rm-delivery-date-error');
            const msg = validateDate(input.value);
            if (msg) { err.textContent = msg; err.classList.remove('hidden'); input.value = ''; }
            else { err.classList.add('hidden'); }
        }

        function validateDate(dateStr) {
            if (!dateStr) return null;
            const dow = new Date(dateStr + 'T00:00:00').getDay();
            if (dow === 0) return 'Closed on Sundays. Please choose another date.';
            return null;
        }

        function validateTimeRange(fromVal, dateStr) {
            if (!fromVal || !dateStr) return null;
            const dow = new Date(dateStr + 'T00:00:00').getDay();
            const hours = dow === 6 ? HOURS.saturday : HOURS.weekday;
            if (fromVal < hours.open) return `Opening time is ${formatTime(hours.open)} on this day.`;
            if (fromVal >= hours.close) return `Must be before closing time ${formatTime(hours.close)}.`;
            return null;
        }

        function formatTime(t) {
            if (!t) return '';
            const [h, m] = t.split(':');
            const hr = parseInt(h);
            return ((hr % 12) || 12) + ':' + m + (hr >= 12 ? 'pm' : 'am');
        }

        function formatDateDisplay(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function submitBooking() {
            const date = document.getElementById('bm-date').value;
            const deliveryDate = document.getElementById('bm-delivery-date').value;
            const from = document.getElementById('bm-time-from').value;

            const dateErr = validateDate(date);
            if (dateErr) { document.getElementById('bm-date-error').textContent = dateErr; document.getElementById('bm-date-error').classList.remove('hidden'); return; }

            const deliveryDateErr = validateDate(deliveryDate);
            if (deliveryDateErr) { document.getElementById('bm-delivery-date-error').textContent = deliveryDateErr; document.getElementById('bm-delivery-date-error').classList.remove('hidden'); return; }

            const timeErr = validateTimeRange(from, date);
            if (timeErr) { document.getElementById('bm-time-error').textContent = timeErr; document.getElementById('bm-time-error').classList.remove('hidden'); return; }
            document.getElementById('bm-time-error').classList.add('hidden');
            if (!date || !deliveryDate || !from) { alert('Please fill in all required fields.'); return; }

            const btn = document.getElementById('bm-submit');
            btn.disabled = true; btn.textContent = 'Saving…';

            fetch('<?= BASE_URL ?>/logisticstaff-savebooking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: bmOrderId, po_id: bmPoId, nhccreference: bmRef, scheduled_date: date, delivery_date: deliveryDate, scheduled_time_from: from }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { closeBookingModal(); window.location.reload(); }
                    else { alert(data.message ?? 'Something went wrong.'); btn.disabled = false; btn.textContent = 'Confirm Booking'; }
                })
                .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Confirm Booking'; });
        }

        // ── STEP 2: Delivery details modal ────────────────────────────────
        let dmBookingId = 0;

        function openDetailsModal(booking) {
            dmBookingId = booking.id;
            document.getElementById('dm-ref').innerHTML = booking.nhccreference + ' · ' + booking.contact_name +
                (booking.po_type === 'replacement' ? ' <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>' : '');
            document.getElementById('dm-schedule-text').textContent =
                formatDateDisplay(booking.scheduled_date) + '  ·  ' + formatTime(booking.scheduled_time_from) +
                (booking.delivery_date ? '  ·  Delivery: ' + formatDateDisplay(booking.delivery_date) : '');

            const refBanner = document.getElementById('dm-truck-ref-banner');
            const refParts = [];
            if (booking.order_truck_name) refParts.push(booking.order_truck_name);
            if (booking.order_truck_max_cubic_meter) refParts.push(parseFloat(booking.order_truck_max_cubic_meter) + ' m³ max');
            if (booking.order_truck_max_weight_capacity) refParts.push(parseFloat(booking.order_truck_max_weight_capacity) + ' kg max');
            if (refParts.length) {
                document.getElementById('dm-truck-ref-text').textContent = refParts.join('  ·  ');
                refBanner.classList.remove('hidden');
            } else {
                refBanner.classList.add('hidden');
            }

            document.getElementById('dm-truck').value = booking.truck_details || booking.order_truck_name || '';
            document.getElementById('dm-plate').value = booking.plate_number ?? '';
            document.getElementById('dm-driver').value = booking.driver_name ?? '';

            let addressPrefill = booking.delivery_address ?? '';
            if (!addressPrefill) {
                addressPrefill = [
                    booking.order_address_full,
                    booking.order_address_barangay,
                    booking.order_address_city,
                    booking.order_address_postalcode
                ].filter(Boolean).join(', ');
            }
            document.getElementById('dm-address').value = addressPrefill;
            document.getElementById('dm-notes').value = booking.notes ?? '';
            document.getElementById('detailsModal').classList.remove('hidden');
            document.getElementById('detailsModal').classList.add('flex');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
            document.getElementById('detailsModal').classList.remove('flex');
        }

        function submitDetails() {
            const truck = document.getElementById('dm-truck').value.trim();
            const plate = document.getElementById('dm-plate').value.trim();
            const driver = document.getElementById('dm-driver').value.trim();
            const address = document.getElementById('dm-address').value.trim();
            const notes = document.getElementById('dm-notes').value.trim();

            if (!truck || !plate || !driver || !address) { alert('Please fill in all required fields.'); return; }

            const btn = document.getElementById('dm-submit');
            btn.disabled = true; btn.textContent = 'Saving…';

            fetch('<?= BASE_URL ?>/logisticstaff-savebookingdetails', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: dmBookingId, truck_details: truck, plate_number: plate, driver_name: driver, delivery_address: address, notes }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeDetailsModal();
                        if (activeTab === 'list') fetchBookings();
                        else window.location.reload();
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        btn.disabled = false;
                        btn.textContent = 'Confirm Details';
                    }
                })
                .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Confirm Details'; });
        }

        // ── Reschedule modal ─────────────────────────────────────────────
        let rmBookingId = 0;

        function openRescheduleModal(booking) {
            rmBookingId = booking.id;
            document.getElementById('rm-ref').innerHTML = booking.nhccreference + ' · ' + booking.contact_name +
                (booking.po_type === 'replacement' ? ' <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold uppercase bg-rose-100 text-rose-700">Replacement</span>' : '');
            document.getElementById('rm-original-text').textContent =
                booking.scheduled_date + '  ' + formatTime(booking.scheduled_time_from) + '  ·  ' + booking.driver_name +
                (booking.delivery_date ? '  ·  Delivery: ' + booking.delivery_date : '');
            document.getElementById('rm-date').value = booking.scheduled_date;
            document.getElementById('rm-delivery-date').value = booking.delivery_date ?? '';
            document.getElementById('rm-time-from').value = booking.scheduled_time_from.substring(0, 5);
            document.getElementById('rm-truck').value = booking.truck_details ?? '';
            document.getElementById('rm-plate').value = booking.plate_number ?? '';
            document.getElementById('rm-driver').value = booking.driver_name ?? '';
            document.getElementById('rm-address').value = booking.delivery_address ?? '';
            document.getElementById('rm-notes').value = booking.notes ?? '';
            document.getElementById('rm-reason').value = '';
            document.getElementById('rm-date-error').classList.add('hidden');
            document.getElementById('rm-delivery-date-error').classList.add('hidden');
            document.getElementById('rm-time-error').classList.add('hidden');
            document.getElementById('rescheduleModal').classList.remove('hidden');
            document.getElementById('rescheduleModal').classList.add('flex');
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.add('hidden');
            document.getElementById('rescheduleModal').classList.remove('flex');
        }

        // ── Reset & Reschedule modal (Replacement POs only) ───────────────
        let rsBookingId = 0;

        function openResetModal(booking) {
            rsBookingId = booking.id;
            document.getElementById('rs-ref').textContent = booking.nhccreference + ' · ' + booking.contact_name;
            document.getElementById('rs-date').value = '';
            document.getElementById('rs-delivery-date').value = '';
            document.getElementById('rs-time-from').value = '';
            document.getElementById('rs-date-error').classList.add('hidden');
            document.getElementById('rs-delivery-date-error').classList.add('hidden');
            document.getElementById('rs-time-error').classList.add('hidden');
            document.getElementById('resetModal').classList.remove('hidden');
            document.getElementById('resetModal').classList.add('flex');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.add('hidden');
            document.getElementById('resetModal').classList.remove('flex');
        }

        function validateResetDate(input) {
            const err = document.getElementById('rs-date-error');
            const msg = validateDate(input.value);
            if (msg) { err.textContent = msg; err.classList.remove('hidden'); input.value = ''; }
            else { err.classList.add('hidden'); }
        }

        function validateResetDeliveryDate(input) {
            const err = document.getElementById('rs-delivery-date-error');
            const msg = validateDate(input.value);
            if (msg) { err.textContent = msg; err.classList.remove('hidden'); input.value = ''; }
            else { err.classList.add('hidden'); }
        }

        function submitResetReschedule() {
            const date = document.getElementById('rs-date').value;
            const deliveryDate = document.getElementById('rs-delivery-date').value;
            const from = document.getElementById('rs-time-from').value;

            const dateErr = validateDate(date);
            if (dateErr) { document.getElementById('rs-date-error').textContent = dateErr; document.getElementById('rs-date-error').classList.remove('hidden'); return; }

            const deliveryDateErr = validateDate(deliveryDate);
            if (deliveryDateErr) { document.getElementById('rs-delivery-date-error').textContent = deliveryDateErr; document.getElementById('rs-delivery-date-error').classList.remove('hidden'); return; }

            const timeErr = validateTimeRange(from, date);
            if (timeErr) { document.getElementById('rs-time-error').textContent = timeErr; document.getElementById('rs-time-error').classList.remove('hidden'); return; }
            document.getElementById('rs-time-error').classList.add('hidden');
            if (!date || !deliveryDate || !from) { alert('Please fill in all required fields.'); return; }

            const btn = document.getElementById('rs-submit');
            btn.disabled = true; btn.textContent = 'Saving…';

            fetch('<?= BASE_URL ?>/logisticstaff-resetreschedule', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: rsBookingId, scheduled_date: date, delivery_date: deliveryDate, scheduled_time_from: from }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeResetModal();
                        if (activeTab === 'list') fetchBookings();
                        else window.location.reload();
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        btn.disabled = false;
                        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Reset & Reschedule`;
                    }
                })
                .catch(() => { alert('Network error.'); btn.disabled = false; });
        }

        function submitReschedule() {
            const date = document.getElementById('rm-date').value;
            const deliveryDate = document.getElementById('rm-delivery-date').value;
            const from = document.getElementById('rm-time-from').value;
            const truck = document.getElementById('rm-truck').value.trim();
            const plate = document.getElementById('rm-plate').value.trim();
            const driver = document.getElementById('rm-driver').value.trim();
            const addr = document.getElementById('rm-address').value.trim();
            const reason = document.getElementById('rm-reason').value.trim();
            const notes = document.getElementById('rm-notes').value.trim();

            const deliveryDateErr = validateDate(deliveryDate);
            if (deliveryDateErr) { document.getElementById('rm-delivery-date-error').textContent = deliveryDateErr; document.getElementById('rm-delivery-date-error').classList.remove('hidden'); return; }
            document.getElementById('rm-delivery-date-error').classList.add('hidden');

            const timeErr = validateTimeRange(from, date);
            if (timeErr) { document.getElementById('rm-time-error').textContent = timeErr; document.getElementById('rm-time-error').classList.remove('hidden'); return; }
            document.getElementById('rm-time-error').classList.add('hidden');
            if (!date || !deliveryDate || !from || !truck || !plate || !driver || !addr || !reason) { alert('Please fill in all required fields including the reason for rescheduling.'); return; }

            const btn = document.getElementById('rm-submit');
            btn.disabled = true; btn.textContent = 'Saving…';

            fetch('<?= BASE_URL ?>/logisticstaff-reschedulebooking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: rmBookingId, scheduled_date: date, delivery_date: deliveryDate, scheduled_time_from: from, truck_details: truck, plate_number: plate, driver_name: driver, delivery_address: addr, reschedule_reason: reason, notes }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeRescheduleModal();
                        if (activeTab === 'list') fetchBookings();
                        else window.location.reload();
                    } else {
                        alert(data.message ?? 'Something went wrong.');
                        btn.disabled = false;
                        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Confirm Reschedule`;
                    }
                })
                .catch(() => { alert('Network error.'); btn.disabled = false; });
        }

        function cancelBooking() {
            if (!confirm('Cancel this booking? This cannot be undone.')) return;
            fetch('<?= BASE_URL ?>/logisticstaff-cancelbooking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: rmBookingId }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeRescheduleModal();
                        if (activeTab === 'list') fetchBookings();
                        else window.location.reload();
                    } else { alert(data.message ?? 'Something went wrong.'); }
                })
                .catch(() => alert('Network error.'));
        }

        document.getElementById('bookingModal').addEventListener('click', function (e) { if (e.target === this) closeBookingModal(); });
        document.getElementById('detailsModal').addEventListener('click', function (e) { if (e.target === this) closeDetailsModal(); });
        document.getElementById('rescheduleModal').addEventListener('click', function (e) { if (e.target === this) closeRescheduleModal(); });
        document.getElementById('resetModal').addEventListener('click', function (e) { if (e.target === this) closeResetModal(); });
    </script>
</body>

</html>