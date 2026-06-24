<?php
// user/ui-page/page-6/orders-functions.php

// ─── Fetch orders + all related data for a user ───────────────────────────
function fetchUserOrders($conn, $userId)
{
    $orders = [];
    $stmt = $conn->prepare("
        SELECT id, nhccreference, delivery_method, delivery_fee,
               subtotal, vat_amount, grand_total, payment_status,
               paid_at, created_at
        FROM noblepaidproductlist
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[$row['id']] = $row;
        $orders[$row['id']]['items'] = [];
        $orders[$row['id']]['receiving'] = [];
        $orders[$row['id']]['bookings'] = [];
        $orders[$row['id']]['pickup_tracking'] = [];
        $orders[$row['id']]['replacement_requests'] = [];
    }
    $stmt->close();

    if (!empty($orders)) {
        $orderIds = array_keys($orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $types = str_repeat('i', count($orderIds));

        // Items
        $sql = "
            SELECT order_id, product_id, product_name, colorname, sizename,
                   unit, quantity, unit_price, discount_pct, line_total
            FROM noblepaidproductitems
            WHERE order_id IN ($placeholders)
            ORDER BY id ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$orderIds);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        while ($item = $itemsResult->fetch_assoc()) {
            $orders[$item['order_id']]['items'][] = $item;
        }
        $stmt->close();

        // Suggested delivery windows
        $sql = "
            SELECT order_id, status, ready_for_booking, ready_for_booking_at,
                   suggested_date_from, suggested_date_to, location
            FROM noblereceivingreceiver
            WHERE order_id IN ($placeholders)
            ORDER BY id ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$orderIds);
        $stmt->execute();
        $receivingResult = $stmt->get_result();
        while ($r = $receivingResult->fetch_assoc()) {
            $orders[$r['order_id']]['receiving'][] = $r;
        }
        $stmt->close();

        // Delivery bookings
        $sql = "
            SELECT order_id, status, scheduled_date, scheduled_time_from, delivery_date
            FROM nobledeliverybooking
            WHERE order_id IN ($placeholders)
            ORDER BY id ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$orderIds);
        $stmt->execute();
        $bookingsResult = $stmt->get_result();
        while ($b = $bookingsResult->fetch_assoc()) {
            $orders[$b['order_id']]['bookings'][] = $b;
        }
        $stmt->close();

        // Pickup tracking
        $sql = "
            SELECT order_id, current_step
            FROM nobleordertracking
            WHERE order_id IN ($placeholders)
            ORDER BY id ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$orderIds);
        $stmt->execute();
        $trackingResult = $stmt->get_result();
        while ($t = $trackingResult->fetch_assoc()) {
            $orders[$t['order_id']]['pickup_tracking'][] = $t;
        }
        $stmt->close();

        // Replacement requests
        $sql = "
            SELECT order_id, status, reason, created_at
            FROM noblereplacementrequest
            WHERE order_id IN ($placeholders)
            ORDER BY id DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$orderIds);
        $stmt->execute();
        $replacementResult = $stmt->get_result();
        while ($rr = $replacementResult->fetch_assoc()) {
            $orders[$rr['order_id']]['replacement_requests'][] = $rr;
        }
        $stmt->close();
    }

    return $orders;
}

// ─── Display-only label overrides ──────────────────────────────────────────
function statusLabel($status)
{
    $labelMap = [
        'pending' => 'Pending confirmation',
        'approved' => 'Order placed',
    ];
    return $labelMap[$status] ?? ucfirst($status);
}

function statusBadge($status)
{
    $map = [
        'pending' => ['dot' => 'bg-amber-400', 'text' => 'text-amber-700'],
        'paid' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700'],
        'failed' => ['dot' => 'bg-rose-500', 'text' => 'text-rose-700'],
        'expired' => ['dot' => 'bg-gray-400', 'text' => 'text-gray-500'],
        'approved' => ['dot' => 'bg-sky-500', 'text' => 'text-sky-700'],
    ];
    $style = $map[$status] ?? ['dot' => 'bg-gray-400', 'text' => 'text-gray-600'];
    return "<span class=\"inline-flex items-center gap-1.5 text-xs font-medium {$style['text']}\">"
        . "<span class=\"w-1.5 h-1.5 rounded-full {$style['dot']}\"></span>" . statusLabel($status) . "</span>";
}

function buildStatusTabs($orders)
{
    $present = [];
    foreach ($orders as $o) {
        $present[$o['payment_status']] = true;
    }
    $order = ['pending', 'approved', 'paid', 'failed', 'expired'];
    $tabs = array_values(array_filter($order, fn($s) => isset($present[$s])));
    foreach (array_keys($present) as $s) {
        if (!in_array($s, $tabs, true)) {
            $tabs[] = $s;
        }
    }
    return $tabs;
}

function getReplacementBadge($order)
{
    if (empty($order['replacement_requests'])) {
        return null;
    }
    $latest = $order['replacement_requests'][0];
    $map = [
        'pending' => ['label' => 'Replacement Requested', 'dot' => 'bg-amber-400', 'text' => 'text-amber-700'],
        'in_progress' => ['label' => 'Replacement Approved', 'dot' => 'bg-sky-500', 'text' => 'text-sky-700'],
        'rejected' => ['label' => 'Replacement Rejected', 'dot' => 'bg-rose-500', 'text' => 'text-rose-700'],
        'completed' => ['label' => 'Replacement Completed', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700'],
    ];
    return $map[$latest['status']] ?? ['label' => 'Replacement Requested', 'dot' => 'bg-amber-400', 'text' => 'text-amber-700'];
}

function hasActiveBooking($bookings)
{
    foreach ($bookings as $b) {
        if ($b['status'] !== 'cancelled') {
            return true;
        }
    }
    return false;
}

function getLatestActiveBooking($bookings)
{
    $latest = null;
    foreach ($bookings as $b) {
        if ($b['status'] !== 'cancelled') {
            $latest = $b;
        }
    }
    return $latest;
}

function getFulfillmentBadge($order)
{
    if ($order['delivery_method'] === 'pickup') {
        foreach ($order['pickup_tracking'] as $t) {
            if ((int) $t['current_step'] >= 3) {
                return ['label' => 'Picked up', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700'];
            }
        }
        return null;
    }

    $latest = getLatestActiveBooking($order['bookings']);
    if ($latest && $latest['status'] === 'delivered') {
        return ['label' => 'Delivered', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700'];
    }
    return null;
}

// ─── Lightweight per-order fingerprint, ginagamit ng client para malaman
// kung anong orders talagang nag-iba (para sa "highlight ng update" effect) ──
function buildOrderVersionMap($orders)
{
    $map = [];
    foreach ($orders as $id => $o) {
        $map[$id] = md5(json_encode([
            $o['payment_status'],
            $o['bookings'],
            $o['pickup_tracking'],
            $o['replacement_requests'],
            $o['receiving'],
        ]));
    }
    return $map;
}