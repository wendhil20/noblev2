<?php
// checkout.php

include ROOT_PATH . '/user/ui-page/backend/backend-page-5/checkout-data.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen">

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 lg:py-10">

        <!-- ── Page header ── -->
        <div class="flex items-center gap-3 mb-8">
            <a href="javascript:void(0)" onclick="goBack()"
                class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-amber-500 transition cursor-pointer">
                <i class="fa-solid fa-arrow-left text-xs"></i> Back to Cart
            </a>
            <span class="text-gray-200">|</span>
            <h1 class="text-xl font-bold text-gray-900">Checkout</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <!-- ─────────────────────────────────────────────────────────────
                 LEFT — Stepper + Steps
            ───────────────────────────────────────────────────────────────── -->
            <div class="lg:col-span-2">

                <!-- ─────────────────────────────────────────────────────────────────────────
     REPLACE the entire step indicator <div> in checkout.php with this.
     Adds Step 4 (Payment) as the final step.
──────────────────────────────────────────────────────────────────────────── -->

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
                    <div class="flex items-center gap-2">

                        <!-- Step 1 -->
                        <div class="flex items-center gap-2">
                            <div id="dot-1" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                       bg-amber-500 text-white flex-shrink-0">1</div>
                            <span id="label-1"
                                class="text-xs font-semibold text-amber-500 whitespace-nowrap hidden sm:block">
                                Contact
                            </span>
                        </div>

                        <div id="line-1" class="flex-1 h-px bg-gray-100"></div>

                        <!-- Step 2 -->
                        <div class="flex items-center gap-2">
                            <div id="dot-2" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                       bg-gray-100 text-gray-400 flex-shrink-0">2</div>
                            <span id="label-2"
                                class="text-xs font-medium text-gray-400 whitespace-nowrap hidden sm:block">
                                Address
                            </span>
                        </div>

                        <div id="line-2" class="flex-1 h-px bg-gray-100"></div>

                        <!-- Step 3 -->
                        <div class="flex items-center gap-2">
                            <div id="dot-3" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                       bg-gray-100 text-gray-400 flex-shrink-0">3</div>
                            <span id="label-3"
                                class="text-xs font-medium text-gray-400 whitespace-nowrap hidden sm:block">
                                Delivery
                            </span>
                        </div>

                        <div id="line-3" class="flex-1 h-px bg-gray-100"></div>

                        <!-- Step 4 -->
                        <div class="flex items-center gap-2">
                            <div id="dot-4" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                       bg-gray-100 text-gray-400 flex-shrink-0">4</div>
                            <span id="label-4"
                                class="text-xs font-medium text-gray-400 whitespace-nowrap hidden sm:block">
                                Payment
                            </span>
                        </div>

                    </div>
                </div>

                <!-- Step panels -->
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-contact.php'; ?>
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-address.php'; ?>
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-delivery.php'; ?>
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-payment.php'; ?>

            </div>

            <!-- ─────────────────────────────────────────────────────────────
                 RIGHT — Order Summary
            ───────────────────────────────────────────────────────────────── -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-6">

                    <h2 class="text-sm font-bold text-gray-900 mb-5">Order Summary</h2>

                    <!-- Cart items list -->
                    <div class="space-y-3 mb-5 max-h-64 overflow-y-auto pr-1">
                        <?php foreach ($cartItems as $item):
                            $price = floatval($item['pricesize']);
                            $discount = floatval($item['discountvariant']);
                            $finalPrice = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                            $lineTotal = $finalPrice * intval($item['quantity']);
                            $image = !empty($item['imagecolor']) ? $item['imagecolor'] : $item['imageproduct'];
                            ?>
                            <div class="flex items-center gap-3">
                                <!-- Thumbnail -->
                                <div
                                    class="w-11 h-11 rounded-xl bg-gray-50 border border-gray-100 flex-shrink-0 overflow-hidden flex items-center justify-center">
                                    <?php if ($image): ?>
                                        <img src="<?= $uploadUrl . htmlspecialchars($image) ?>"
                                            alt="<?= htmlspecialchars($item['product_name']) ?>"
                                            class="w-full h-full object-contain p-0.5">
                                    <?php else: ?>
                                        <i class="fa-solid fa-image text-gray-200"></i>
                                    <?php endif; ?>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 truncate leading-tight">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </p>
                                    <p class="text-[10px] text-gray-400 mt-0.5 truncate">
                                        <?= htmlspecialchars($item['colorname']) ?>
                                        <?php if (!empty($item['sizename'])): ?>
                                            · <?= htmlspecialchars($item['sizename']) ?>
                                        <?php endif; ?>
                                        <?php if ($discount > 0): ?>
                                            <span class="text-red-400 font-semibold">-<?= $discount ?>%</span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <!-- Price × qty -->
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-bold text-gray-900">₱<?= number_format($lineTotal, 2) ?></p>
                                    <p class="text-[10px] text-gray-400">× <?= intval($item['quantity']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Subtotal -->
                    <div class="border-t border-gray-100 pt-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Subtotal <span class="text-[10px] text-gray-400">(VAT
                                    incl.)</span></span>
                            <span class="font-semibold text-gray-900">₱<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">VAT (12%)</span>
                            <span class="font-medium text-gray-700">₱<?= number_format($vatAmount, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-sm" id="summary-delivery-row"
                            style="display:none !important;">
                            <span class="text-gray-500">Delivery fee</span>
                            <span class="font-semibold text-amber-500" id="summary-delivery-fee">₱0.00</span>
                        </div>
                        <div class="flex justify-between text-base font-bold pt-2 border-t border-gray-100">
                            <span class="text-gray-800">Total</span>
                            <span class="text-gray-900" id="summary-total">₱<?= number_format($grandTotal, 2) ?></span>
                        </div>
                    </div>

                    <!-- Delivery fee note (shows after step 3) -->
                    <p class="text-[10px] text-gray-400 mt-3 hidden" id="pickup-note">
                        <i class="fa-solid fa-store mr-1"></i> Store pickup — no delivery fee.
                    </p>
                    <p class="text-[10px] text-gray-400 mt-3 hidden" id="delivery-note">
                        <i class="fa-solid fa-truck mr-1"></i> Delivery fee added above.
                    </p>

                </div>
            </div>

        </div>
    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <script>
        // ── Constants passed from PHP ─────────────────────────────────────────
        const SUBTOTAL = <?= round($subtotal, 2) ?>;
        const VAT_AMOUNT = <?= round($vatAmount, 2) ?>;
        const GRAND_TOTAL = <?= round($grandTotal, 2) ?>;
        window._storeName = <?= json_encode($storeName) ?>;

        // ── Step state ────────────────────────────────────────────────────────
        let currentStep = 1;
        let chosenMethod = null; // 'pickup' | 'delivery'
        let chosenPaymentMethod = null; // 'qrph' | 'paymongo'

        // ── Stepper nav ───────────────────────────────────────────────────────
        function nextStep(from) {
            if (from === 1 && !validateStep1()) return;
            if (from === 2 && !validateStep2()) return;
            if (from === 3 && !validateStep3()) return;

            if (from === 3) {
                goToStep(4);
                populateReview();
                return;
            }
            goToStep(from + 1);
        }

        function prevStep(from) {
            goToStep(from - 1);
        }

        function goToStep(n) {
            currentStep = n;
            document.querySelectorAll('.step-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById('step-' + n + '-panel').classList.remove('hidden');
            updateStepIndicator(n);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateStepIndicator(n) {
            [1, 2, 3, 4].forEach(i => {
                const dot = document.getElementById('dot-' + i);
                const label = document.getElementById('label-' + i);
                const line = document.getElementById('line-' + i);

                dot.className = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 ';

                if (i < n) {
                    dot.className += 'bg-green-500 text-white';
                    dot.innerHTML = '<i class="fa-solid fa-check text-[10px]"></i>';
                    label.className = label.className.replace(/text-\S+/g, '') + ' text-green-500 text-xs font-semibold';
                    if (line) line.className = line.className.replace('bg-gray-100', 'bg-green-300');
                } else if (i === n) {
                    dot.className += 'bg-amber-500 text-white';
                    dot.textContent = i;
                    label.className = label.className.replace(/text-\S+/g, '') + ' text-amber-500 text-xs font-semibold';
                } else {
                    dot.className += 'bg-gray-100 text-gray-400';
                    dot.textContent = i;
                    label.className = label.className.replace(/text-\S+/g, '') + ' text-gray-400 text-xs font-medium';
                    if (line) line.className = line.className.replace('bg-green-300', 'bg-gray-100');
                }
            });
        }

        // ── Validations ───────────────────────────────────────────────────────
        function validateStep1() {
            const name = document.getElementById('f-name').value.trim();
            const email = document.getElementById('f-email').value.trim();
            const phone = document.getElementById('f-phone').value.trim();
            let ok = true;
            toggleErr('err-name', !name);
            toggleErr('err-email', !email || !email.includes('@'));
            toggleErr('err-phone', !phone);
            if (!name || !email || !email.includes('@') || !phone) ok = false;
            return ok;
        }

        function validateStep2() {
            if (!window._selectedAddr) {
                toggleErr('err-addr', true);
                return false;
            }
            toggleErr('err-addr', false);
            return true;
        }

        function validateStep3() {
            if (!chosenMethod) {
                toggleErr('err-method', true);
                return false;
            }
            toggleErr('err-method', false);
            return true;
        }

        function toggleErr(id, show) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !show);
        }

        // ── Address selection ─────────────────────────────────────────────────
        function selectAddress(card) {
            document.querySelectorAll('.addr-card').forEach(c => {
                c.classList.remove('border-amber-400', 'bg-amber-50');
                c.classList.add('border-gray-100');
                const chk = c.querySelector('.addr-check');
                const icon = c.querySelector('.fa-check');
                if (chk) chk.classList.remove('border-amber-500', 'bg-amber-500');
                if (icon) icon.classList.add('hidden');
            });

            card.classList.add('border-amber-400', 'bg-amber-50');
            card.classList.remove('border-gray-100');
            const chk = card.querySelector('.addr-check');
            const icon = card.querySelector('.fa-check');
            if (chk) chk.classList.add('border-amber-500', 'bg-amber-500');
            if (icon) icon.classList.remove('hidden');

            window._selectedAddr = {
                id: card.dataset.id,
                lat: parseFloat(card.dataset.lat),
                lng: parseFloat(card.dataset.lng),
            };

            toggleErr('err-addr', false);

            if (chosenMethod === 'delivery') calcDelivery();
        }

        // ── Method selection ──────────────────────────────────────────────────
        function selectMethod(m) {
            chosenMethod = m;
            toggleErr('err-method', false);

            document.querySelectorAll('.method-card').forEach(c => {
                c.classList.remove('border-amber-400', 'bg-amber-50');
                c.classList.add('border-gray-100');
                const chk = c.querySelector('.method-check');
                const icon = c.querySelector('.fa-check');
                if (chk) chk.classList.remove('border-amber-500', 'bg-amber-500');
                if (icon) icon.classList.add('hidden');
            });

            const chosen = document.getElementById('method-' + m);
            chosen.classList.add('border-amber-400', 'bg-amber-50');
            chosen.classList.remove('border-gray-100');
            const chk = chosen.querySelector('.method-check');
            const icon = chosen.querySelector('.fa-check');
            if (chk) chk.classList.add('border-amber-500', 'bg-amber-500');
            if (icon) icon.classList.remove('hidden');

            const detailEl = document.getElementById('delivery-detail');
            const pickupNote = document.getElementById('pickup-note');
            const deliveryNote = document.getElementById('delivery-note');
            const summaryRow = document.getElementById('summary-delivery-row');

            if (m === 'delivery') {
                detailEl.classList.remove('hidden');
                calcDelivery();
                pickupNote.classList.add('hidden');
                deliveryNote.classList.remove('hidden');
            } else {
                detailEl.classList.add('hidden');
                summaryRow.style.display = 'none';
                document.getElementById('summary-total').textContent = '₱' + fmtP(GRAND_TOTAL);
                document.getElementById('delivery-badge').textContent = '₱0.00';
                document.getElementById('delivery-badge').className = 'text-sm font-bold text-green-500';
                document.getElementById('delivery-subtitle-label').textContent = 'Calculated based on distance';
                pickupNote.classList.remove('hidden');
                deliveryNote.classList.add('hidden');
                window._deliveryFee = 0;
                window._distKm = 0;
            }
        }

        // ── Delivery fare calc ────────────────────────────────────────────────
        function calcDelivery() {
            const truck = window._truck;
            if (!truck) return;

            const addr = window._selectedAddr;
            const lat2 = addr ? addr.lat : window._storeLat;
            const lng2 = addr ? addr.lng : window._storeLng;

            const dist = haversine(window._storeLat, window._storeLng, lat2, lng2);
            const distKm = parseFloat(dist.toFixed(2));
            const estMin = Math.max(1, Math.round(distKm / 0.5));

            const baseFare = parseFloat(truck.basefare);
            const addFare = parseFloat((distKm * parseFloat(truck.perkmrate)).toFixed(2));
            const totalFare = parseFloat((baseFare + addFare).toFixed(2));

            const volMax = parseFloat(truck.maxcubicmeter);
            const wtMax = parseFloat(truck.maxweightcapacity);
            const volPct = parseFloat(((window._cartVolCbm / volMax) * 100).toFixed(1));
            const wtPct = parseFloat(((window._cartWeightKg / wtMax) * 100).toFixed(1));

            const tName = ucFirst(truck.nametruck) + ' — ' + truck.trucktype;
            set('truck-name-display', tName);
            set('fare-title', tName + ' — Calculated');

            set('vol-pct', volPct + '%');
            set('vol-used', window._cartVolCbm.toFixed(3));
            set('vol-max', volMax.toFixed(3));
            document.getElementById('vol-bar').style.width = Math.min(volPct, 100) + '%';

            set('wt-pct', wtPct + '%');
            set('wt-used', window._cartWeightKg.toFixed(2));
            set('wt-max', wtMax.toFixed(2));
            document.getElementById('wt-bar').style.width = Math.min(wtPct, 100) + '%';

            set('dist-display', distKm + ' km');
            set('time-display', estMin + ' minute' + (estMin !== 1 ? 's' : ''));
            set('base-fare-display', '₱' + fmtP(baseFare));
            set('add-label', 'Additional (' + distKm + ' km × ₱' + parseFloat(truck.perkmrate).toFixed(2) + ')');
            set('add-fare-display', '₱' + fmtP(addFare));
            set('total-delivery-display', '₱' + fmtP(totalFare));

            document.getElementById('delivery-badge').textContent = '₱' + fmtP(totalFare);
            document.getElementById('delivery-badge').className = 'text-sm font-bold text-amber-500';
            document.getElementById('delivery-subtitle-label').textContent = distKm + ' km from store';

            const summaryRow = document.getElementById('summary-delivery-row');
            summaryRow.style.removeProperty('display');
            set('summary-delivery-fee', '₱' + fmtP(totalFare));
            set('summary-total', '₱' + fmtP(GRAND_TOTAL + totalFare));

            window._deliveryFee = totalFare;
            window._distKm = distKm;
        }

        // ── Populate step 4 review panel ──────────────────────────────────────
        function populateReview() {
            set('review-name', document.getElementById('f-name').value.trim());
            set('review-email', document.getElementById('f-email').value.trim());
            set('review-phone', document.getElementById('f-phone').value.trim());

            const addrCard = document.querySelector('.addr-card.border-amber-400');
            if (addrCard) {
                const lines = addrCard.querySelectorAll('p');
                set('review-address', lines[0]?.textContent?.trim() ?? '—');
            }

            const fee = window._deliveryFee ?? 0;
            if (chosenMethod === 'pickup') {
                set('review-method', 'Store Pickup');
                set('review-delivery-fee', 'Free — collect at ' + (window._storeName ?? 'store'));
            } else {
                set('review-method', 'Home Delivery — ' + (window._truck?.nametruck ?? ''));
                set('review-delivery-fee', '₱' + fmtP(fee) + ' (' + (window._distKm ?? 0) + ' km)');
            }

            set('pay-subtotal', fmtP(SUBTOTAL));
            set('pay-vat', fmtP(VAT_AMOUNT));
            set('pay-total', fmtP(GRAND_TOTAL + fee));

            const delivRow = document.getElementById('pay-delivery-row');
            if (fee > 0) {
                set('pay-delivery', fmtP(fee));
                delivRow.style.removeProperty('display');
            } else {
                delivRow.style.display = 'none';
            }
        }

        // ── Select payment method (QR Ph or Cards/Wallets) ────────────────────
        function selectPaymentMethod(method) {
            chosenPaymentMethod = method;
            toggleErr('err-pay-method', false);

            document.querySelectorAll('.pay-method-card').forEach(c => {
                c.classList.remove('border-amber-400', 'bg-amber-50', 'border-blue-400', 'bg-blue-50');
                c.classList.add('border-gray-100');
            });

            const note = document.getElementById('pay-secure-note');
            const redirectNote = document.getElementById('redirect-note');
            const redirectAction = document.getElementById('redirect-note-action');
            redirectNote.classList.remove('hidden');

            if (method === 'qrph') {
                const card = document.getElementById('pm-qrph');
                card.classList.add('border-blue-400', 'bg-blue-50');
                card.classList.remove('border-gray-100');

                redirectAction.textContent = 'scan and pay via QR Ph';
                note.innerHTML = '<i class="fa-solid fa-shield-halved mr-1"></i>'
                    + 'QR Ph via <span class="font-semibold">InstaPay</span> — BSP-regulated, instant settlement';
            } else {
                const card = document.getElementById('pm-paymongo');
                card.classList.add('border-amber-400', 'bg-amber-50');
                card.classList.remove('border-gray-100');

                redirectAction.textContent = 'complete your payment';
                note.innerHTML = '<i class="fa-solid fa-shield-halved mr-1"></i>'
                    + 'Secured by <span class="font-semibold">PayMongo</span> — GCash, Maya, Cards accepted';
            }
        }

        // ── Start payment — POST to the right handler then redirect to PayMongo ───
        async function startPayment() {
            if (!chosenPaymentMethod) {
                toggleErr('err-pay-method', true);
                return;
            }

            const btn = document.getElementById('pay-btn');
            const label = document.getElementById('pay-btn-label');
            document.getElementById('err-payment').classList.add('hidden');

            btn.disabled = true;
            label.textContent = 'Redirecting…';

            const endpoint = chosenPaymentMethod === 'qrph' ? '/createqrph' : '/createcheckoutsession';

            const payload = {
                contact_name: document.getElementById('f-name').value.trim(),
                contact_email: document.getElementById('f-email').value.trim(),
                contact_phone: document.getElementById('f-phone').value.trim(),
                address_id: chosenMethod === 'delivery' ? (window._selectedAddr?.id ?? 0) : 0,
                method: chosenMethod,
                truck_id: chosenMethod === 'delivery' ? (window._truck?.id ?? 0) : 0,
                delivery_fee: chosenMethod === 'delivery' ? (window._deliveryFee ?? 0) : 0,
                distance_km: chosenMethod === 'delivery' ? (window._distKm ?? 0) : 0,
            };

            try {
                const res = await fetch(BASE_URL + endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.ok && data.checkout_url) {
                    window.location.href = data.checkout_url;
                } else {
                    const errEl = document.getElementById('err-payment');
                    document.getElementById('err-payment-msg').textContent = data.error ?? 'Something went wrong. Please try again.';
                    errEl.classList.remove('hidden');
                    btn.disabled = false;
                    label.textContent = 'Pay Now';
                }
            } catch (e) {
                document.getElementById('err-payment').classList.remove('hidden');
                btn.disabled = false;
                label.textContent = 'Pay Now';
            }
        }

        // ── Helpers ───────────────────────────────────────────────────────────
        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2
                + Math.cos(lat1 * Math.PI / 180)
                * Math.cos(lat2 * Math.PI / 180)
                * Math.sin(dLng / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        function fmtP(n) {
            return parseFloat(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function set(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        function ucFirst(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        }

        function goBack() {
            if (window.history.length > 1 && document.referrer && document.referrer.includes(window.location.hostname)) {
                window.history.back();
            } else {
                window.location.href = '<?= BASE_URL ?>/';
            }
        }
    </script>
</body>

</html>