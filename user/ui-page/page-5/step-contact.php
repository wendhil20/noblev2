
<?php
// steps/step-contact.php
// Rendered inside checkout.php — variables from checkout-data.php are in scope
?>
<div id="step-1-panel" class="step-panel">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
        <h2 class="text-sm font-semibold text-gray-900 mb-5">Contact details</h2>

        <div class="space-y-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1.5" for="f-name">Full name</label>
                <input type="text" id="f-name"
                    value="<?= htmlspecialchars($userAccount['name'] ?? '') ?>"
                    placeholder="e.g. Juan dela Cruz"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                <p class="text-xs text-red-400 mt-1 hidden" id="err-name">Please enter your name.</p>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1.5" for="f-email">Email address</label>
                <input type="email" id="f-email"
                    value="<?= htmlspecialchars($userAccount['email'] ?? '') ?>"
                    placeholder="you@example.com"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                <p class="text-xs text-red-400 mt-1 hidden" id="err-email">Please enter a valid email.</p>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1.5" for="f-phone">Contact number</label>
                <input type="tel" id="f-phone"
                    value="<?= htmlspecialchars(!empty($savedAddresses[0]['contact_number']) ? $savedAddresses[0]['contact_number'] : '') ?>"
                    placeholder="09XXXXXXXXX"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                <p class="text-xs text-red-400 mt-1 hidden" id="err-phone">Please enter a contact number.</p>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button onclick="nextStep(1)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600
                   text-white text-sm font-semibold transition">
            Continue <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </div>
</div>