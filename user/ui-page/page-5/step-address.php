<?php
// steps/step-address.php
// Rendered inside checkout.php — variables from checkout-data.php are in scope
?>
<div id="step-2-panel" class="step-panel hidden">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Delivery address</h2>
                <p class="text-xs text-gray-400 mt-0.5">Choose a saved address below.</p>
            </div>
            <a href="<?= BASE_URL ?>/profile/address/add"
                class="inline-flex items-center gap-1.5 text-xs text-amber-500 hover:text-amber-600 font-semibold transition">
                <i class="fa-solid fa-plus text-[10px]"></i> Add new
            </a>
        </div>

        <div class="space-y-3" id="addr-list">
            <?php if (empty($savedAddresses)): ?>
                <div class="py-10 text-center">
                    <i class="fa-solid fa-location-dot text-3xl text-gray-200 mb-3 block"></i>
                    <p class="text-sm text-gray-400 mb-4">No saved addresses yet.</p>
                    <a href="<?= BASE_URL ?>/profilemap"
                        class="inline-block px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold transition">
                        Add an address
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($savedAddresses as $i => $addr): ?>
                    <div class="addr-card border border-gray-100 rounded-xl p-4 cursor-pointer transition hover:border-amber-300
                                <?= $i === 0 ? 'border-amber-400 bg-amber-50' : '' ?>"
                        id="addr-card-<?= $addr['id'] ?>"
                        data-id="<?= $addr['id'] ?>"
                        data-lat="<?= floatval($addr['latitude']) ?>"
                        data-lng="<?= floatval($addr['longitude']) ?>"
                        onclick="selectAddress(this)">

                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 leading-tight truncate">
                                    <?= htmlspecialchars($addr['address']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= htmlspecialchars($addr['barangay']) ?>,
                                    <?= htmlspecialchars($addr['city']) ?>
                                    <?php if (!empty($addr['postalcode'])): ?>
                                        · <?= htmlspecialchars($addr['postalcode']) ?>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    <?= htmlspecialchars($addr['contact_number']) ?>
                                </p>
                            </div>
                            <span class="addr-check flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition
                                         <?= $i === 0 ? 'border-amber-500 bg-amber-500' : 'border-gray-200' ?>">
                                <i class="fa-solid fa-check text-[9px] text-white <?= $i !== 0 ? 'hidden' : '' ?>"></i>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <p class="text-xs text-red-400 mt-3 hidden" id="err-addr">Please select an address.</p>
    </div>

    <div class="flex justify-between">
        <button onclick="prevStep(2)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-500
                   hover:text-gray-700 hover:border-gray-300 font-medium transition">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </button>
        <button onclick="nextStep(2)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600
                   text-white text-sm font-semibold transition">
            Continue <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </div>
</div>

<?php
// Pre-select first address in JS
$firstAddr = !empty($savedAddresses) ? $savedAddresses[0] : null;
?>
<script>
    // Pre-select first address on load
    <?php if ($firstAddr): ?>
    window._selectedAddr = {
        id: <?= intval($firstAddr['id']) ?>,
        lat: <?= floatval($firstAddr['latitude']) ?>,
        lng: <?= floatval($firstAddr['longitude']) ?>,
        label: <?= json_encode(
            htmlspecialchars($firstAddr['address']) . ', ' .
            htmlspecialchars($firstAddr['barangay']) . ', ' .
            htmlspecialchars($firstAddr['city'])
        ) ?>
    };
    <?php else: ?>
    window._selectedAddr = null;
    <?php endif; ?>
</script>