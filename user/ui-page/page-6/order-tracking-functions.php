<?php
// user/ui-page/page-6/order-tracking-functions.php

function fetchOrderTrackingData($conn, $userId, $orderId)
{
    $stmt = $conn->prepare("
        SELECT id, nhccreference, grand_total, payment_status, created_at, delivery_method,
               address_full, address_barangay, address_city, address_postalcode,
               address_lat, address_lng
        FROM noblepaidproductlist
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return null;
    }

    $isPickup = $order['delivery_method'] === 'pickup';

    $bookings = [];
    if (!$isPickup) {
        // po_type included so the tracking UI can flag replacement deliveries
        // the same way the pickup tracking card already does.
        $stmt = $conn->prepare("
            SELECT id, nhccreference, po_id, po_type, scheduled_date, delivery_date,
                   scheduled_time_from, truck_details, plate_number, driver_name,
                   delivery_address, notes, status, loading_at, in_transit_at,
                   delivered_at, proof_of_delivery_path, created_at, updated_at
            FROM nobledeliverybooking
            WHERE order_id = ?
            ORDER BY id ASC
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
        $stmt->close();
    }

    $pickupTrackings = [];
    if ($isPickup) {
        $stmt = $conn->prepare("
            SELECT ot.id, ot.po_id, ot.current_step, ot.pickup_location,
                   ot.pickup_driver_name, ot.pickup_plate_number, ot.pickup_truck_details,
                   ot.supplier_address, ot.supplier_latitude, ot.supplier_longitude,
                   ot.proof_of_pickup_path, ot.step_updated_at, npo.po_number, npo.po_type
            FROM nobleordertracking ot
            JOIN noblepurchaseorder npo ON npo.id = ot.po_id
            WHERE ot.order_id = ?
            ORDER BY ot.id ASC
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $pickupTrackings[] = $row;
        }
        $stmt->close();
    }

    $replacementRequest = null;
    $stmtRR = $conn->prepare("
        SELECT id, status, reason, created_at
        FROM noblereplacementrequest
        WHERE order_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtRR->bind_param('i', $orderId);
    $stmtRR->execute();
    $replacementRequest = $stmtRR->get_result()->fetch_assoc();
    $stmtRR->close();

    $officeBase = null;
    if ($isPickup) {
        $stmtOffice = $conn->prepare("SELECT placename, latitude, longitude FROM noblewarehousebase LIMIT 1");
        $stmtOffice->execute();
        $officeBase = $stmtOffice->get_result()->fetch_assoc();
        $stmtOffice->close();
    }

    return [
        'order' => $order,
        'isPickup' => $isPickup,
        'bookings' => $bookings,
        'pickupTrackings' => $pickupTrackings,
        'replacementRequest' => $replacementRequest,
        'officeBase' => $officeBase,
    ];
}

// ─── Stepper config (delivery) ──────────────────────────────────────────────
function deliverySteps()
{
    return [
        'scheduled' => ['label' => 'Scheduled', 'icon' => 'calendar'],
        'loading' => ['label' => 'Loading', 'icon' => 'box'],
        'in_transit' => ['label' => 'In Transit', 'icon' => 'truck'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'check'],
    ];
}

// ─── Stepper config (pickup) ────────────────────────────────────────────────
function pickupStepsConfig()
{
    return [
        0 => ['label' => 'Order is Placed', 'icon' => 'calendar'],
        1 => ['label' => 'loading', 'icon' => 'box'],
        2 => ['label' => 'Item ready', 'icon' => 'truck'],
        3 => ['label' => 'Picked up', 'icon' => 'check'],
    ];
}

function replacementStatusBadge($status)
{
    $map = [
        'pending' => ['label' => 'Under Review', 'icon' => 'fa-hourglass-half', 'classes' => 'text-amber-700 bg-amber-50'],
        'in_progress' => ['label' => 'Approved', 'icon' => 'fa-circle-check', 'classes' => 'text-sky-700 bg-sky-50'],
        'rejected' => ['label' => 'Rejected', 'icon' => 'fa-circle-xmark', 'classes' => 'text-rose-700 bg-rose-50'],
        'completed' => ['label' => 'Completed', 'icon' => 'fa-circle-check', 'classes' => 'text-emerald-700 bg-emerald-50'],
    ];
    return $map[$status] ?? ['label' => 'Under Review', 'icon' => 'fa-hourglass-half', 'classes' => 'text-amber-700 bg-amber-50'];
}

function statusToStepIndex($status)
{
    $order = ['scheduled' => 0, 'rescheduled' => 0, 'loading' => 1, 'in_transit' => 2, 'delivered' => 3];
    return $order[$status] ?? 0;
}

function stepIcon($icon)
{
    $icons = [
        'calendar' => '<i class="fa-solid fa-calendar-days"></i>',
        'box' => '<i class="fa-solid fa-box-open"></i>',
        'truck' => '<i class="fa-solid fa-truck"></i>',
        'check' => '<i class="fa-solid fa-check"></i>',
    ];
    return $icons[$icon] ?? '';
}

// ─── Fingerprint para malaman ng client kung kailan dapat mag-flash ng
// "updated" highlight (e.g. lumipat ng step, may bagong replacement status) ─
function buildTrackingVersion($data)
{
    return md5(json_encode([
        $data['order']['payment_status'],
        $data['bookings'],
        $data['pickupTrackings'],
        $data['replacementRequest'],
    ]));
}