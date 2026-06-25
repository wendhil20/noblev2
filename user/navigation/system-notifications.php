<?php
// user/navigation/system-notifications.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/navigation/system-notifications-functions.php';

$userId = (int) $_SESSION['user_id'];
$items = fetchDeliveredItemsForReview($conn, $userId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Notifications — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <div class="max-w-3xl mx-auto px-4 py-6 flex-1 w-full">

        <h1 class="text-lg font-semibold text-gray-900 mb-4">Rate & Review Your Orders</h1>

        <?php if (empty($items)): ?>
            <div class="bg-white rounded-lg border border-gray-100 py-12 text-center text-sm text-gray-400">
                No delivered items yet to review.
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($items as $item): ?>
                    <?php $isSubmitted = !empty($item['review_id']); ?>
                    <div class="bg-white rounded-lg border border-gray-100 p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($item['product_name']) ?></p>
                                <p class="text-xs text-gray-400">
                                    <?= htmlspecialchars($item['colorname'] ?? '—') ?>
                                    <?php if (!empty($item['sizename'])): ?>
                                        · <?= htmlspecialchars($item['sizename']) ?>
                                    <?php endif; ?>
                                    · Order #<?= htmlspecialchars($item['nhccreference'] ?: $item['order_id']) ?>
                                </p>
                            </div>
                            <?php if ($isSubmitted): ?>
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full shrink-0">
                                    <i class="fa-solid fa-circle-check"></i> Reviewed
                                </span>
                            <?php endif; ?>
                        </div>

                        <form class="review-form <?= $isSubmitted ? 'is-locked' : '' ?>"
                            data-order-id="<?= (int) $item['order_id'] ?>"
                            data-order-item-id="<?= (int) $item['order_item_id'] ?>"
                            data-product-id="<?= (int) $item['product_id'] ?>">

                            <div class="flex items-center gap-1 mb-2 star-rating <?= $isSubmitted ? 'locked' : '' ?>"
                                data-value="<?= (int) ($item['rating'] ?? 0) ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-star <?= $i <= (int) ($item['rating'] ?? 0) ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?> text-lg <?= $isSubmitted ? 'cursor-default' : 'cursor-pointer' ?>"
                                       data-star="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>

                            <textarea name="comment" rows="2" placeholder="Share your experience with this item..."
                                <?= $isSubmitted ? 'disabled' : '' ?>
                                class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-amber-300 <?= $isSubmitted ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : '' ?>"><?= htmlspecialchars($item['comment'] ?? '') ?></textarea>

                            <div class="flex items-center justify-between">
                                <span class="review-status text-xs <?= $isSubmitted ? 'text-emerald-600 font-medium' : 'text-gray-400' ?>">
                                    <?= $isSubmitted
                                        ? '<i class="fa-solid fa-lock text-[10px] mr-1"></i> Submitted — can no longer be edited'
                                        : '' ?>
                                </span>
                                <button type="submit"
                                    <?= $isSubmitted ? 'disabled' : '' ?>
                                    class="text-xs font-semibold px-4 py-2 rounded-md transition <?= $isSubmitted
                                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                        : 'bg-amber-500 hover:bg-amber-600 text-white' ?>">
                                    <?= $isSubmitted ? 'Submitted' : 'Submit Review' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <script>
        // ─── Star rating click — hindi gagana kung locked ───
        document.querySelectorAll('.star-rating').forEach(function (group) {
            if (group.classList.contains('locked')) return; // bawal na i-click pag locked na

            const stars = group.querySelectorAll('[data-star]');
            stars.forEach(function (star) {
                star.addEventListener('click', function () {
                    const val = parseInt(star.dataset.star, 10);
                    group.dataset.value = val;
                    stars.forEach(function (s) {
                        const sv = parseInt(s.dataset.star, 10);
                        s.classList.toggle('fa-solid', sv <= val);
                        s.classList.toggle('text-amber-400', sv <= val);
                        s.classList.toggle('fa-regular', sv > val);
                        s.classList.toggle('text-gray-300', sv > val);
                    });
                });
            });
        });

        // ─── Submit handler — hindi rin gagana kung locked na ───
        document.querySelectorAll('.review-form').forEach(function (form) {
            if (form.classList.contains('is-locked')) return; // bawal na i-submit ulit

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const rating = parseInt(form.querySelector('.star-rating').dataset.value || '0', 10);
                if (rating < 1) {
                    alert('Please select a star rating first.');
                    return;
                }

                const btn = form.querySelector('button[type="submit"]');
                const statusEl = form.querySelector('.review-status');
                btn.disabled = true;

                try {
                    const res = await fetch(BASE_URL + '/submit-review', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_id: form.dataset.orderId,
                            order_item_id: form.dataset.orderItemId,
                            product_id: form.dataset.productId,
                            rating: rating,
                            comment: form.querySelector('textarea[name="comment"]').value.trim()
                        })
                    });
                    const data = await res.json();

                    if (data.ok) {
                        // ─── I-lock agad ang form pagkatapos mag-submit ───
                        lockForm(form, statusEl, btn);
                    } else {
                        alert(data.message || 'Something went wrong.');
                        btn.disabled = false;
                    }
                } catch (err) {
                    alert('Network error. Please try again.');
                    btn.disabled = false;
                }
            });
        });

        // ─── Tinatanggal ang lahat ng interaction sa form pagkatapos masubmit ───
        function lockForm(form, statusEl, btn) {
            form.classList.add('is-locked');

            // I-disable ang textarea
            const textarea = form.querySelector('textarea[name="comment"]');
            textarea.disabled = true;
            textarea.classList.add('bg-gray-50', 'text-gray-500', 'cursor-not-allowed');

            // I-lock ang star rating (alisin ang click listener visually via class lang,
            // dahil ang event listener ay naka-bind na sa load — gagamitin na lang natin
            // ang CSS pointer-events para hindi na ma-click pa)
            const starGroup = form.querySelector('.star-rating');
            starGroup.classList.add('locked');
            starGroup.style.pointerEvents = 'none';
            starGroup.querySelectorAll('i').forEach(s => s.classList.remove('cursor-pointer'));
            starGroup.querySelectorAll('i').forEach(s => s.classList.add('cursor-default'));

            // Update status text + button
            statusEl.innerHTML = '<i class="fa-solid fa-lock text-[10px] mr-1"></i> Submitted — can no longer be edited';
            statusEl.classList.remove('text-gray-400');
            statusEl.classList.add('text-emerald-600', 'font-medium');

            btn.disabled = true;
            btn.textContent = 'Submitted';
            btn.classList.remove('bg-amber-500', 'hover:bg-amber-600', 'text-white');
            btn.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
        }
    </script>

</body>

</html>