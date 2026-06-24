<?php
// user/ui-page/page-6/orders-list-partial.php

?>
<?php if (empty($orders)): ?>
    <div class="bg-white rounded-lg border border-gray-100 py-12 text-center text-sm text-gray-400">
        You haven't placed any orders yet.
    </div>
<?php else: ?>

    <!-- Filter tabs -->
    <div id="statusTabs" class="flex items-center gap-1 mb-4 overflow-x-auto border-b border-gray-100">
        <button type="button" data-tab="all"
            class="status-tab flex-shrink-0 px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors border-indigo-500 text-indigo-600">
            All
        </button>
        <?php foreach ($statusTabs as $s): ?>
            <button type="button" data-tab="<?= htmlspecialchars($s) ?>"
                class="status-tab flex-shrink-0 px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors border-transparent text-gray-400 hover:text-gray-600">
                <?= htmlspecialchars(statusLabel($s)) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div id="ordersList" class="bg-white rounded-lg border border-gray-100 divide-y divide-gray-100">
        <?php foreach ($orders as $order): ?>
            <?php
            $searchBlob = strtolower($order['nhccreference'] . ' ' . implode(' ', array_column($order['items'], 'product_name')));
            ?>
            <details class="group order-row" data-order-id="<?= (int) $order['id'] ?>"
                data-status="<?= htmlspecialchars($order['payment_status']) ?>"
                data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES) ?>">

                <summary
                    class="cursor-pointer list-none flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50/80 transition-colors [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-col min-w-0">
                        <a href="<?= BASE_URL ?>/order-details?order_id=<?= $order['id'] ?>"
                            onclick="event.stopPropagation()"
                            class="text-sm font-medium text-gray-900 hover:text-indigo-600 truncate">
                            #<?= htmlspecialchars($order['nhccreference'] ?: $order['id']) ?>
                        </a>
                        <span class="text-xs text-gray-400">
                            <?= htmlspecialchars(date('M d, Y · h:i A', strtotime($order['created_at']))) ?>
                            &middot; <?= htmlspecialchars(ucfirst($order['delivery_method'])) ?>
                        </span>
                    </div>

                    <div class="flex items-center gap-3 flex-shrink-0">
                        <div class="flex flex-col items-end gap-0.5">
                            <?php $replacement = getReplacementBadge($order); ?>
                            <?php $fulfillment = getFulfillmentBadge($order); ?>

                            <?php if ($replacement): ?>
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $replacement['text'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $replacement['dot'] ?>"></span>
                                    <?= htmlspecialchars($replacement['label']) ?>
                                </span>
                            <?php elseif ($fulfillment): ?>
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $fulfillment['text'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $fulfillment['dot'] ?>"></span>
                                    <?= htmlspecialchars($fulfillment['label']) ?>
                                </span>
                            <?php else: ?>
                                <?= statusBadge($order['payment_status']) ?>
                            <?php endif; ?>

                            <span class="text-sm font-semibold text-gray-900 tabular-nums">
                                ₱<?= number_format($order['grand_total'], 2) ?>
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 transition-transform group-open:rotate-180" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </summary>

                <div class="px-4 pb-4 -mt-1">
                    <div class="overflow-x-auto rounded-md border border-gray-100">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-400 bg-gray-50/70">
                                    <th class="py-2 px-3 font-medium">Product</th>
                                    <th class="py-2 px-3 font-medium">Color</th>
                                    <th class="py-2 px-3 font-medium">Size</th>
                                    <th class="py-2 px-3 font-medium text-center">Qty</th>
                                    <th class="py-2 px-3 font-medium text-right">Price</th>
                                    <th class="py-2 px-3 font-medium text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td class="py-2 px-3 text-gray-800"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="py-2 px-3 text-gray-500"><?= htmlspecialchars($item['colorname'] ?? '—') ?></td>
                                        <td class="py-2 px-3 text-gray-500"><?= htmlspecialchars($item['sizename'] ?? '—') ?></td>
                                        <td class="py-2 px-3 text-center text-gray-500"><?= (int) $item['quantity'] ?></td>
                                        <td class="py-2 px-3 text-right text-gray-500 tabular-nums">₱<?= number_format($item['unit_price'], 2) ?></td>
                                        <td class="py-2 px-3 text-right font-medium text-gray-900 tabular-nums">₱<?= number_format($item['line_total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex flex-col items-end gap-0.5 text-xs text-gray-500">
                        <div class="flex justify-between w-full sm:w-52">
                            <span>Subtotal</span>
                            <span class="tabular-nums">₱<?= number_format($order['subtotal'], 2) ?></span>
                        </div>
                        <div class="flex justify-between w-full sm:w-52">
                            <span>VAT</span>
                            <span class="tabular-nums">₱<?= number_format($order['vat_amount'], 2) ?></span>
                        </div>
                        <div class="flex justify-between w-full sm:w-52">
                            <span>Delivery fee</span>
                            <span class="tabular-nums">₱<?= number_format($order['delivery_fee'], 2) ?></span>
                        </div>
                        <div class="flex justify-between w-full sm:w-52 text-sm font-semibold text-gray-900 pt-1 mt-1 border-t border-gray-100">
                            <span>Total</span>
                            <span class="tabular-nums">₱<?= number_format($order['grand_total'], 2) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($order['receiving']) && !hasActiveBooking($order['bookings'])): ?>
                        <div class="mt-3 space-y-1.5">
                            <?php foreach ($order['receiving'] as $r): ?>
                                <?php if (!empty($r['suggested_date_from']) && !empty($r['suggested_date_to'])): ?>
                                    <div class="bg-indigo-50/60 rounded-md px-3 py-2 flex items-center justify-between gap-2 text-xs">
                                        <span class="text-indigo-700 font-medium">
                                            Expected: <?= htmlspecialchars(date('M d', strtotime($r['suggested_date_from']))) ?>
                                            – <?= htmlspecialchars(date('M d, Y', strtotime($r['suggested_date_to']))) ?>
                                        </span>
                                        <span class="text-indigo-400">We'll confirm with you</span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (hasActiveBooking($order['bookings'])): ?>
                        <?php
                        $latestBooking = getLatestActiveBooking($order['bookings']);
                        $dateToShow = $latestBooking['delivery_date'] ?? $latestBooking['scheduled_date'];
                        ?>
                        <?php if (!empty($dateToShow)): ?>
                            <div class="mt-3 flex justify-between items-center text-xs">
                                <span class="text-gray-400">Delivery date</span>
                                <span class="text-gray-900 font-medium">
                                    <?= htmlspecialchars(date('M d, Y', strtotime($dateToShow))) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </details>
        <?php endforeach; ?>
    </div>

    <div id="noResults"
        class="hidden bg-white rounded-lg border border-gray-100 py-12 text-center text-sm text-gray-400">
        No orders match your search.
    </div>

<?php endif; ?>