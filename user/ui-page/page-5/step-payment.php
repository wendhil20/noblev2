<?php
// user/ui-page/page-5/step-payment.php
// Step 4 — Payment: QR Ph or PayMongo Checkout (both redirect to PayMongo's hosted page)
?>

<div id="step-4-panel" class="step-panel hidden">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">

        <h2 class="text-sm font-bold text-gray-900 mb-1">Payment</h2>
        <p class="text-xs text-gray-400 mb-6">Review your order then choose how to pay.</p>

        <!-- ── Order review ── -->
        <div class="space-y-3 mb-6">

            <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div class="w-7 h-7 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-user text-green-500 text-[10px]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Contact</p>
                    <p class="text-xs font-semibold text-gray-800" id="review-name">—</p>
                    <p class="text-[11px] text-gray-500" id="review-email">—</p>
                    <p class="text-[11px] text-gray-500" id="review-phone">—</p>
                </div>
                <button onclick="goToStep(1)" class="text-[10px] text-amber-500 hover:underline flex-shrink-0">Edit</button>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-location-dot text-blue-400 text-[10px]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Deliver to</p>
                    <p class="text-xs font-semibold text-gray-800" id="review-address">—</p>
                </div>
                <button onclick="goToStep(2)" class="text-[10px] text-amber-500 hover:underline flex-shrink-0">Edit</button>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-truck text-amber-400 text-[10px]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Delivery</p>
                    <p class="text-xs font-semibold text-gray-800" id="review-method">—</p>
                    <p class="text-[11px] text-gray-500" id="review-delivery-fee">—</p>
                </div>
                <button onclick="goToStep(3)" class="text-[10px] text-amber-500 hover:underline flex-shrink-0">Edit</button>
            </div>

        </div>

        <!-- ── Amount breakdown ── -->
        <div class="rounded-xl border border-gray-100 p-4 mb-6 space-y-2">
            <div class="flex justify-between text-xs text-gray-500">
                <span>Subtotal <span class="text-[10px]">(VAT excl.)</span></span>
                <span class="font-medium text-gray-700">₱<span id="pay-subtotal">0.00</span></span>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span>VAT (12%)</span>
                <span class="font-medium text-gray-700">₱<span id="pay-vat">0.00</span></span>
            </div>
            <div class="flex justify-between text-xs text-gray-500" id="pay-delivery-row" style="display:none !important;">
                <span>Delivery fee</span>
                <span class="font-medium text-amber-600">₱<span id="pay-delivery">0.00</span></span>
            </div>
            <div class="flex justify-between text-sm font-bold border-t border-gray-100 pt-2">
                <span class="text-gray-800">Total</span>
                <span class="text-gray-900">₱<span id="pay-total">0.00</span></span>
            </div>
        </div>

        <!-- ── Payment method selector ── -->
<!-- ── Payment method selector (list style) ── -->
<p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-3">Choose payment method</p>

<div class="flex flex-col gap-3 mb-5">

    <!-- QR Ph -->
    <button id="pm-qrph"
        onclick="selectPaymentMethod('qrph')"
        class="pay-method-card flex items-center gap-3 p-3 rounded-xl border-2 border-gray-100
               bg-white hover:border-amber-300 hover:bg-amber-50 transition text-left cursor-pointer">
        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center overflow-hidden flex-shrink-0">
            <img src="https://upload.wikimedia.org/wikipedia/commons/3/35/QR_Ph_Logo.svg"
                alt="QR Ph"
                class="w-7 h-7 object-contain"
                onerror="this.outerHTML='<i class=&quot;fa-solid fa-qrcode text-blue-600 text-base&quot;></i>'">
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-gray-800">QR Ph</p>
            <p class="text-[10px] text-gray-400 leading-snug mt-0.5">InstaPay · Any PH bank or e-wallet</p>
        </div>
        <i class="fa-solid fa-chevron-right text-[10px] text-gray-300 flex-shrink-0"></i>
    </button>

    <!-- PayMongo -->
    <button id="pm-paymongo"
        onclick="selectPaymentMethod('paymongo')"
        class="pay-method-card flex items-center gap-3 p-3 rounded-xl border-2 border-gray-100
               bg-white hover:border-amber-300 hover:bg-amber-50 transition text-left cursor-pointer">
        <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center overflow-hidden flex-shrink-0">
            <img src="https://cdn.brandfetch.io/id6ufs89ty/w/200/h/200/theme/dark/icon.jpeg?c=1bxid64Mup7aczewSAYMX&t=1743658983062"
                alt="PayMongo"
                class="w-7 h-7 object-contain rounded-md"
                onerror="this.outerHTML='<i class=&quot;fa-solid fa-credit-card text-amber-500 text-base&quot;></i>'">
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-gray-800">Cards & Wallets</p>
            <p class="text-[10px] text-gray-400 leading-snug mt-0.5">GCash · Maya · Cards · GrabPay</p>
        </div>
        <i class="fa-solid fa-chevron-right text-[10px] text-gray-300 flex-shrink-0"></i>
    </button>

</div>
        <!-- ── Note: customer will be redirected to PayMongo to complete payment ── -->
        <div id="redirect-note" class="hidden rounded-xl border border-blue-100 bg-blue-50 p-3 mb-5 text-center">
            <p class="text-[11px] text-blue-600">
                <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>
                You'll be redirected to PayMongo's secure page to <span id="redirect-note-action">complete your payment</span>.
            </p>
        </div>

        <!-- ── Error ── -->
        <p id="err-payment" class="hidden text-xs text-red-500 mb-3">
            <i class="fa-solid fa-circle-exclamation mr-1"></i>
            <span id="err-payment-msg">Something went wrong. Please try again.</span>
        </p>
        <p id="err-pay-method" class="hidden text-xs text-red-500 mb-3">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> Please choose a payment method.
        </p>

        <!-- ── Actions ── -->
        <div class="flex gap-3">
            <button onclick="prevStep(4)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600
                       hover:bg-gray-50 transition">
                <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i> Back
            </button>

            <!-- Single CTA — routes to the right endpoint based on chosenPaymentMethod -->
            <button onclick="startPayment()" id="pay-btn"
                class="flex-2 flex-grow py-3 px-6 rounded-xl bg-amber-500 hover:bg-amber-600 text-white
                       text-sm font-bold transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-lock text-xs"></i>
                <span id="pay-btn-label">Pay Now</span>
            </button>
        </div>

        <p class="text-[10px] text-gray-400 text-center mt-3" id="pay-secure-note">
            <i class="fa-solid fa-shield-halved mr-1"></i>
            Secured by <span class="font-semibold">PayMongo</span> — All payments are encrypted
        </p>

    </div>
</div>