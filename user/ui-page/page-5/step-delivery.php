<?php
// steps/step-delivery.php
// Rendered inside checkout.php — variables from checkout-data.php are in scope
?>
<div id="step-3-panel" class="step-panel hidden">

    <!-- Method selector -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <h2 class="text-sm font-semibold text-gray-900 mb-5">Fulfillment method</h2>

        <!-- Pick up -->
        <div class="method-card border border-gray-100 rounded-xl p-4 cursor-pointer transition hover:border-amber-300 mb-3"
            id="method-pickup" onclick="selectMethod('pickup')">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-store text-amber-500 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800">Pick up in store</p>
                    <p class="text-xs text-gray-400 mt-0.5 truncate"><?= htmlspecialchars($storeName) ?></p>
                </div>
                <span class="text-sm font-bold text-green-500">₱0.00</span>
                <span class="method-check w-5 h-5 rounded-full border-2 border-gray-200 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-check text-[9px] text-white hidden"></i>
                </span>
            </div>
        </div>

        <!-- Delivery -->
        <div class="method-card border border-gray-100 rounded-xl p-4 cursor-pointer transition hover:border-amber-300"
            id="method-delivery" onclick="selectMethod('delivery')">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-truck text-blue-500 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800">Deliver to my address</p>
                    <p class="text-xs text-gray-400 mt-0.5" id="delivery-subtitle-label">Calculated based on distance</p>
                </div>
                <span class="text-sm font-bold text-gray-400" id="delivery-badge">—</span>
                <span class="method-check w-5 h-5 rounded-full border-2 border-gray-200 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-check text-[9px] text-white hidden"></i>
                </span>
            </div>
        </div>

        <p class="text-xs text-red-400 mt-3 hidden" id="err-method">Please select a fulfillment method.</p>
    </div>

    <!-- Delivery breakdown (shown when delivery is selected) -->
    <div id="delivery-detail" class="hidden">

        <!-- Store location -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Store location</p>
            <p class="text-sm text-gray-700"><?= htmlspecialchars($storeName) ?></p>
        </div>

        <!-- Assigned vehicle -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-0.5">Assigned delivery vehicle</p>
                    <p class="text-sm font-semibold text-green-600 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-xs"></i>
                        <span id="truck-name-display"><?= htmlspecialchars($assignedTruck ? ucfirst($assignedTruck['nametruck']) . ' — ' . $assignedTruck['trucktype'] : 'No vehicle available') ?></span>
                    </p>
                </div>
            </div>

            <!-- Volume bar -->
            <div class="mb-3">
                <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                    <span>Volume: <span id="vol-pct" class="font-semibold text-gray-800">0%</span></span>
                    <span><span id="vol-used">0</span> m³ of <span id="vol-max">0</span> m³</span>
                </div>
                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div id="vol-bar" class="h-full bg-green-400 rounded-full transition-all duration-500" style="width:0%"></div>
                </div>
            </div>

            <!-- Weight bar -->
            <div>
                <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                    <span>Weight: <span id="wt-pct" class="font-semibold text-gray-800">0%</span></span>
                    <span><span id="wt-used">0</span> kg of <span id="wt-max">0</span> kg</span>
                </div>
                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div id="wt-bar" class="h-full bg-green-400 rounded-full transition-all duration-500" style="width:0%"></div>
                </div>
            </div>
        </div>

        <!-- Fare breakdown -->
        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 mb-4">
            <p class="text-xs font-semibold text-blue-700 mb-3" id="fare-title">Vehicle — Calculated</p>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Distance</span>
                    <span class="font-medium text-gray-800" id="dist-display">—</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Est. time</span>
                    <span class="font-medium text-gray-800" id="time-display">—</span>
                </div>
                <div class="border-t border-blue-100 my-2"></div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Base fare</span>
                    <span class="font-medium text-gray-800" id="base-fare-display">₱0.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 text-xs" id="add-label">Additional</span>
                    <span class="font-medium text-gray-800" id="add-fare-display">₱0.00</span>
                </div>
                <div class="border-t border-blue-200 pt-2 flex justify-between">
                    <span class="font-semibold text-gray-800">Total delivery</span>
                    <span class="font-bold text-amber-500 text-base" id="total-delivery-display">₱0.00</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-between">
        <button onclick="prevStep(3)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-500
                   hover:text-gray-700 hover:border-gray-300 font-medium transition">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </button>
        <button onclick="nextStep(3)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600
                   text-white text-sm font-semibold transition">
            <i class="fa-solid fa-lock text-xs"></i> Proceed to payment
        </button>
    </div>
</div>

<?php
// Pass truck data and cart totals to JS
?>
<script>
    window._truck = <?= json_encode($assignedTruck) ?>;
    window._cartVolCbm = <?= round($totalVolumeCbm, 6) ?>;
    window._cartWeightKg = <?= round($totalWeightKg, 4) ?>;
    window._storeLat = <?= $storeLat ?>;
    window._storeLng = <?= $storeLng ?>;
</script>