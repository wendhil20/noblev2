<?php
// user/ui-page/page-6/order-tracking-partial.php

?>
<?php if ($isPickup): ?>

    <!-- ════════════════ PICKUP TRACKING ════════════════ -->
    <?php if (empty($pickupTrackings)): ?>
        <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-500">
            Your order is being prepared. Pickup updates will be available soon.
        </div>
    <?php else: ?>

        <?php $pickupSteps = pickupStepsConfig(); ?>
        <?php foreach ($pickupTrackings as $tracking): ?>
            <?php
            $currentIndex = (int) $tracking['current_step'];
            $stepKeys = array_keys($pickupSteps);
            $maxIndex = max($stepKeys);
            $isCompleted = $currentIndex >= $maxIndex;
            $isReplacementPO = ($tracking['po_type'] ?? 'normal') === 'replacement';
            ?>
            <div class="bg-white rounded-lg border <?= $isReplacementPO ? 'border-rose-200' : 'border-gray-200' ?> p-6 mb-6" data-tracking-id="<?= (int) $tracking['id'] ?>">

                <?php if ($isReplacementPO): ?>
                    <div class="mb-4 px-3 py-2 bg-rose-50 border border-rose-200 rounded-md flex items-center gap-2">
                        <i class="fa-solid fa-rotate-left text-rose-500 text-xs"></i>
                        <p class="text-xs text-rose-700 font-medium">
                            This is a <strong>Replacement Pickup</strong>
                            <?php if (!empty($tracking['po_number'])): ?>
                                (PO <?= htmlspecialchars($tracking['po_number']) ?>)
                            <?php endif; ?>
                            for a previous order.
                        </p>
                    </div>
                <?php endif; ?>

                <div class="flex items-center mb-6">
                    <?php foreach ($stepKeys as $i => $key): ?>
                        <?php
                        $step = $pickupSteps[$key];
                        $isDone = $i < $currentIndex;
                        $isCurrent = $i === $currentIndex;

                        $circleClasses = $isDone
                            ? 'bg-green-500 text-white'
                            : ($isCurrent ? ($isReplacementPO ? 'bg-rose-500 text-white' : 'bg-blue-500 text-white') : 'bg-gray-100 text-gray-400');
                        $labelClasses = $isCurrent ? 'text-gray-900 font-semibold' : 'text-gray-500';
                        $lineClasses = $i < $currentIndex ? 'bg-green-500' : 'bg-gray-200';
                        ?>
                        <div class="flex flex-col items-center flex-1">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center <?= $circleClasses ?>">
                                <span class="text-sm leading-none flex items-center justify-center">
                                    <?= stepIcon($step['icon']) ?>
                                </span>
                            </div>
                            <span class="text-xs mt-2 text-center <?= $labelClasses ?>">
                                <?= htmlspecialchars($step['label']) ?>
                            </span>
                        </div>
                        <?php if ($i < count($stepKeys) - 1): ?>
                            <div class="h-0.5 flex-1 -mt-5 <?= $lineClasses ?>"></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-gray-100 pt-4 space-y-3 text-sm">

                    <?php if (!empty($tracking['po_number'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 shrink-0">PO Number</span>
                            <span class="text-gray-900 font-medium text-right flex items-center gap-1.5">
                                <?= htmlspecialchars($tracking['po_number']) ?>
                                <?php if ($isReplacementPO): ?>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold uppercase bg-rose-100 text-rose-700 border border-rose-200">
                                         Replacement
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php
                    $pickupAddressDisplay = $tracking['pickup_location'] === 'office'
                        ? ($officeBase['placename'] ?? $tracking['supplier_address'] ?? null)
                        : ($tracking['supplier_address'] ?? null);

                    $pickupLat = $tracking['pickup_location'] === 'office'
                        ? ($officeBase['latitude'] ?? null)
                        : ($tracking['supplier_latitude'] ?? null);
                    $pickupLng = $tracking['pickup_location'] === 'office'
                        ? ($officeBase['longitude'] ?? null)
                        : ($tracking['supplier_longitude'] ?? null);
                    $hasPickupCoords = $currentIndex >= 2 && $pickupLat !== null && $pickupLng !== null;
                    ?>
                    <?php if ($currentIndex >= 2 && !empty($pickupAddressDisplay)): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 shrink-0">Pickup Address</span>
                            <span
                                class="text-gray-900 font-medium text-right"><?= htmlspecialchars($pickupAddressDisplay) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($currentIndex >= 2 && !empty($order['address_full'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 shrink-0">Your Address</span>
                            <span
                                class="text-gray-900 font-medium text-right"><?= htmlspecialchars($order['address_full']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasPickupCoords): ?>
                        <div id="pickupMap_<?= (int) $tracking['id'] ?>" class="pickup-map"
                            data-lat="<?= htmlspecialchars($pickupLat) ?>" data-lng="<?= htmlspecialchars($pickupLng) ?>"
                            data-client-lat="<?= htmlspecialchars($order['address_lat'] ?? '') ?>"
                            data-client-lng="<?= htmlspecialchars($order['address_lng'] ?? '') ?>"
                            data-client-address="<?= htmlspecialchars($order['address_full'] ?? '') ?>"
                            data-pickup-address="<?= htmlspecialchars($pickupAddressDisplay ?? '') ?>"></div>
                    <?php endif; ?>

                    <?php if ($currentIndex >= 2 && (!empty($tracking['pickup_truck_details']) || !empty($tracking['pickup_plate_number']))): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Truck</span>
                            <span class="text-gray-900 font-medium">
                                <?= htmlspecialchars($tracking['pickup_truck_details'] ?? '—') ?>
                                <?php if (!empty($tracking['pickup_plate_number'])): ?>
                                    (<?= htmlspecialchars($tracking['pickup_plate_number']) ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($currentIndex >= 2 && !empty($tracking['pickup_driver_name'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Driver</span>
                            <span
                                class="text-gray-900 font-medium"><?= htmlspecialchars($tracking['pickup_driver_name']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($tracking['step_updated_at'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Last Updated</span>
                            <span class="text-gray-900 font-medium">
                                <?= htmlspecialchars(date('M d, Y · h:i A', strtotime($tracking['step_updated_at']))) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($tracking['proof_of_pickup_path'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Proof of Pickup</span>
                            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($tracking['proof_of_pickup_path']) ?>" target="_blank"
                                class="text-blue-600 hover:underline font-medium">View</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($isCompleted): ?>
                        <div class="flex justify-end pt-2">
                            <?php if ($replacementRequest): ?>
                                <?php $rrBadge = replacementStatusBadge($replacementRequest['status']); ?>
                                <span
                                    class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-md <?= $rrBadge['classes'] ?>">
                                    <i class="fa-solid <?= $rrBadge['icon'] ?>"></i>
                                    <?= htmlspecialchars($rrBadge['label']) ?>
                                </span>
                            <?php elseif (!$isReplacementPO): ?>
                                <button type="button"
                                    onclick="openReplacementModal(<?= (int) $order['id'] ?>, <?= (int) $tracking['po_id'] ?>, null)"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md transition">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    Request Replacement
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

<?php else: ?>

    <!-- ════════════════ DELIVERY TRACKING ════════════════ -->
    <?php if (empty($bookings)): ?>
        <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-500">
            Your order is being prepared. Delivery updates will be available soon.
        </div>
    <?php else: ?>

        <?php $steps = deliverySteps(); ?>
        <?php foreach ($bookings as $booking): ?>
            <?php $isReplacementBooking = ($booking['po_type'] ?? 'normal') === 'replacement'; ?>
            <div class="bg-white rounded-lg border <?= $isReplacementBooking ? 'border-rose-200' : 'border-gray-200' ?> p-6 mb-6" data-booking-id="<?= (int) $booking['id'] ?>">

                <?php if ($isReplacementBooking && $booking['status'] !== 'cancelled'): ?>
                    <div class="mb-4 px-3 py-2 bg-rose-50 border border-rose-200 rounded-md flex items-center gap-2">
                        <i class="fa-solid fa-rotate-left text-rose-500 text-xs"></i>
                        <p class="text-xs text-rose-700 font-medium">
                            This is a <strong>Replacement Delivery</strong> for a previous order.
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($booking['status'] === 'cancelled'): ?>
                    <div class="bg-red-50 border border-red-100 rounded-md px-4 py-3 mb-6 text-red-700 text-sm font-medium">
                        This delivery has been cancelled.
                    </div>
                <?php else: ?>

                    <?php
                    $currentIndex = statusToStepIndex($booking['status']);
                    $stepKeys = array_keys($steps);
                    ?>

                    <div class="flex items-center mb-6">
                        <?php foreach ($stepKeys as $i => $key): ?>
                            <?php
                            $step = $steps[$key];
                            $isDone = $i < $currentIndex;
                            $isCurrent = $i === $currentIndex;
                            $isRescheduled = ($key === 'scheduled' && $booking['status'] === 'rescheduled');

                            if ($isRescheduled) {
                                $circleClasses = 'bg-yellow-500 text-white';
                                $labelClasses = 'text-yellow-700 font-semibold';
                                $label = 'Rescheduled';
                            } else {
                                $circleClasses = $isDone
                                    ? 'bg-green-500 text-white'
                                    : ($isCurrent ? ($isReplacementBooking ? 'bg-rose-500 text-white' : 'bg-blue-500 text-white') : 'bg-gray-100 text-gray-400');
                                $labelClasses = $isCurrent ? 'text-gray-900 font-semibold' : 'text-gray-500';
                                $label = $step['label'];
                            }

                            $lineClasses = $i < $currentIndex ? 'bg-green-500' : 'bg-gray-200';
                            ?>
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center <?= $circleClasses ?>">
                                    <span class="text-sm leading-none flex items-center justify-center">
                                        <?= stepIcon($step['icon']) ?>
                                    </span>
                                </div>
                                <span class="text-xs mt-2 text-center <?= $labelClasses ?>">
                                    <?= htmlspecialchars($label) ?>
                                </span>
                            </div>
                            <?php if ($i < count($stepKeys) - 1): ?>
                                <div class="h-0.5 flex-1 -mt-5 <?= $lineClasses ?>"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

                <div class="border-t border-gray-100 pt-4 space-y-3 text-sm">

                    <?php if (!empty($booking['nhccreference']) || $isReplacementBooking): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 shrink-0">Reference</span>
                            <span class="text-gray-900 font-medium text-right flex items-center gap-1.5">
                                <?= htmlspecialchars($booking['nhccreference'] ?? '—') ?>
                                <?php if ($isReplacementBooking): ?>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold uppercase bg-rose-100 text-rose-700 border border-rose-200">
                                         Replacement
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['scheduled_date'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Prepared Date</span>
                            <span class="text-gray-900 font-medium">
                                <?= htmlspecialchars(date('M d, Y', strtotime($booking['scheduled_date']))) ?>
                                <?php if (!empty($booking['scheduled_time_from'])): ?>
                                    &middot;
                                    <?= htmlspecialchars(date('h:i A', strtotime($booking['scheduled_time_from']))) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['delivery_date'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Delivery Date</span>
                            <span class="text-gray-900 font-medium">
                                <?= htmlspecialchars(date('M d, Y', strtotime($booking['delivery_date']))) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['truck_details']) || !empty($booking['plate_number'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Truck</span>
                            <span class="text-gray-900 font-medium">
                                <?= htmlspecialchars($booking['truck_details'] ?? '—') ?>
                                <?php if (!empty($booking['plate_number'])): ?>
                                    (<?= htmlspecialchars($booking['plate_number']) ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['driver_name'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Driver</span>
                            <span class="text-gray-900 font-medium"><?= htmlspecialchars($booking['driver_name']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['delivery_address'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 shrink-0">Delivery Address</span>
                            <span
                                class="text-gray-900 font-medium text-right"><?= htmlspecialchars($booking['delivery_address']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['notes'])): ?>
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 shrink-0">Notes</span>
                            <span class="text-gray-900 text-right"><?= htmlspecialchars($booking['notes']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['proof_of_delivery_path'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Proof of Delivery</span>
                            <a href="<?= htmlspecialchars($booking['proof_of_delivery_path']) ?>" target="_blank"
                                class="text-blue-600 hover:underline font-medium">View</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($booking['status'] === 'delivered'): ?>
                        <div class="flex justify-end pt-2">
                            <?php if ($replacementRequest): ?>
                                <?php $rrBadge = replacementStatusBadge($replacementRequest['status']); ?>
                                <span
                                    class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-md <?= $rrBadge['classes'] ?>">
                                    <i class="fa-solid <?= $rrBadge['icon'] ?>"></i>
                                    <?= htmlspecialchars($rrBadge['label']) ?>
                                </span>
                            <?php elseif (!$isReplacementBooking): ?>
                                <button type="button"
                                    onclick="openReplacementModal(<?= (int) $order['id'] ?>, null, <?= (int) $booking['id'] ?>)"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md transition">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    Request Replacement
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

<?php endif; ?>