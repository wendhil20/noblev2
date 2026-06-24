<?php
// bottom.php
?>
<footer class="bg-white border-t border-gray-100 mt-auto">

    <!-- Main footer -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-10">

            <!-- Brand — full width on mobile, 1 col on lg -->
            <div class="col-span-2 sm:col-span-2 lg:col-span-1">
                <a href="<?= BASE_URL ?>" class="inline-block mb-3">
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome" class="h-7 sm:h-8 object-contain"
                         onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'font-bold text-lg text-gray-900',textContent:'NobleHome'}))">
                </a>
                <p class="text-xs sm:text-sm text-gray-400 leading-relaxed max-w-xs">
                    Quality home products crafted for every lifestyle.
                </p>
                <!-- Socials -->
                <div class="flex gap-2.5 mt-4">
                    <a href="#" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gray-100 hover:bg-amber-100 hover:text-amber-600
                                      flex items-center justify-center text-gray-500 transition-colors duration-200">
                        <i class="fa-brands fa-facebook-f text-xs"></i>
                    </a>
                    <a href="#" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gray-100 hover:bg-amber-100 hover:text-amber-600
                                      flex items-center justify-center text-gray-500 transition-colors duration-200">
                        <i class="fa-brands fa-instagram text-xs"></i>
                    </a>
                    <a href="#" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gray-100 hover:bg-amber-100 hover:text-amber-600
                                      flex items-center justify-center text-gray-500 transition-colors duration-200">
                        <i class="fa-brands fa-tiktok text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Shop -->
            <div class="col-span-1">
                <h4 class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 sm:mb-4">Shop</h4>
                <ul class="space-y-2 sm:space-y-2.5">
                    <li><a href="<?= BASE_URL ?>/products" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">All Products</a></li>
                    <li><a href="<?= BASE_URL ?>/products?category=new" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">New Arrivals</a></li>
                    <li><a href="<?= BASE_URL ?>/products?category=sale" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">On Sale</a></li>
                    <li><a href="<?= BASE_URL ?>/products?category=bestseller" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">Best Sellers</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="col-span-1">
                <h4 class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 sm:mb-4">Support</h4>
                <ul class="space-y-2 sm:space-y-2.5">
                    <li><a href="<?= BASE_URL ?>/faq" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">FAQ</a></li>
                    <li><a href="<?= BASE_URL ?>/track-order" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">Track Order</a></li>
                    <li><a href="<?= BASE_URL ?>/returns" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">Returns & Exchanges</a></li>
                    <li><a href="<?= BASE_URL ?>/contact" class="text-xs sm:text-sm text-gray-600 hover:text-amber-500 transition-colors">Contact Us</a></li>
                </ul>
            </div>

            <!-- Contact — full width on mobile -->
            <div class="col-span-2 sm:col-span-2 lg:col-span-1">
                <h4 class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 sm:mb-4">Contact</h4>
                <ul class="space-y-2 sm:space-y-3">
                    <li class="flex items-start gap-2 text-xs sm:text-sm text-gray-500">
                        <i class="fa-solid fa-location-dot text-amber-400 mt-0.5 shrink-0"></i>
                        <span>123 Noble St., Quezon City, Metro Manila</span>
                    </li>
                    <li class="flex items-center gap-2 text-xs sm:text-sm text-gray-500">
                        <i class="fa-solid fa-phone text-amber-400 shrink-0"></i>
                        <a href="tel:+639123456789" class="hover:text-amber-500 transition-colors">+63 912 345 6789</a>
                    </li>
                    <li class="flex items-center gap-2 text-xs sm:text-sm text-gray-500">
                        <i class="fa-solid fa-envelope text-amber-400 shrink-0"></i>
                        <a href="mailto:support@noblehome.ph" class="hover:text-amber-500 transition-colors">support@noblehome.ph</a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-1.5 sm:gap-2">
            <p class="text-[10px] sm:text-xs text-gray-400">
                &copy; <?= date('Y') ?> NobleHome. All rights reserved.
            </p>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="<?= BASE_URL ?>/privacy" class="text-[10px] sm:text-xs text-gray-400 hover:text-amber-500 transition-colors">Privacy Policy</a>
                <a href="<?= BASE_URL ?>/terms" class="text-[10px] sm:text-xs text-gray-400 hover:text-amber-500 transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>

</footer>