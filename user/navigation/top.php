<?php
// top.php - Main Navigation Bar
// NOTE: Login logic is handled in /user/auth/google.php
$isLoggedIn = !empty($_SESSION['user_id']);
?>

<nav class="w-full bg-white border-b border-gray-200 shadow-sm relative z-40">
    <div class="max-w-screen-xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">

            <!-- Left: Logo + Desktop Nav -->
            <div class="flex items-center gap-6">

                <!-- Logo -->
                <a href="<?= BASE_URL ?>" class="flex items-center gap-2 shrink-0">
                    <div class="w-10 h-10">
                        <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo"
                            class="w-full h-full object-contain">
                    </div>
                </a>

                <!-- Desktop Nav Links -->
                <div class="hidden md:flex items-center gap-6">
                    <a href="<?= BASE_URL ?>/find-professional"
                        class="text-sm font-medium text-gray-700 hover:text-orange-500 transition-colors duration-150 whitespace-nowrap">
                        Find Professional
                    </a>
                    <a href="<?= BASE_URL ?>/inspiration"
                        class="text-sm font-medium text-gray-700 hover:text-orange-500 transition-colors duration-150">
                        Inspiration
                    </a>

                    <?php include ROOT_PATH . '/user/navigation/navproductscategory.php'; ?>
                </div>
            </div>

            <!-- Right: Search + Icons + Hamburger -->
            <div class="flex items-center gap-2">

                <!-- Desktop Search Bar -->
                <form action="<?= BASE_URL ?>/shop" method="GET" class="hidden lg:block relative"
                    id="desktop-search-form">
                    <div
                        class="flex items-center p-1 rounded-lg border overflow-hidden focus-within:ring-1 focus-within:ring-orange-400 focus-within:border-orange-400 transition-all duration-150">
                        <div class="flex items-center pl-3 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8" />
                                <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                            </svg>
                        </div>
                        <input type="text" name="search" id="desktop-search-input" autocomplete="off"
                            placeholder="Search for products..."
                            class="text-sm text-gray-700 placeholder-gray-400 px-3 py-2 w-52 outline-none bg-white" />
                        <button type="submit"
                            class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 transition-colors duration-150 whitespace-nowrap rounded-lg">
                            Search
                        </button>
                    </div>

                    <!-- Suggestions dropdown -->
                    <div id="desktop-search-suggestions"
                        class="hidden absolute top-full left-0 mt-1 w-full bg-white rounded-lg shadow-lg border border-gray-100 z-50 max-h-80 overflow-y-auto">
                    </div>

                   <div id="desktop-search-error" class="hidden absolute -bottom-9 left-3 z-50">
    <div class="relative bg-gray-800 text-white text-xs px-3 py-1.5 rounded-md shadow-lg whitespace-nowrap">
        Please fill in the blank.
        <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-800 rotate-45"></div>
    </div>
</div>
                </form>

                <!-- Icon: Search (mobile only) -->
                <button id="mobile-search-toggle"
                    class="lg:hidden p-2 text-gray-600 hover:text-orange-500 transition-colors duration-150">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" />
                        <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                    </svg>
                </button>

                <!-- Cart Icon with Hover Dropdown -->
                <div class="relative group" id="cart-icon-wrapper">
                    <a href="<?= BASE_URL ?>/cartview"
                        class="relative p-2 text-gray-600 hover:text-orange-500 transition-colors duration-150 block">
                        <i class="fa-solid fa-cart-flatbed"></i>
                        <span id="cart-count"
                            class="hidden absolute -top-0.5 -right-0.5 bg-orange-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">
                        </span>
                    </a>

                    <?php if ($isLoggedIn): ?>
                        <?php include ROOT_PATH . '/user/navigation/cart-dropdown.php'; ?>
                    <?php endif; ?>
                </div>

                <!-- User Avatar / Login Button -->
                <?php if ($isLoggedIn): ?>
                    <!-- User Dropdown (desktop) -->
                    <div class="hidden md:block relative group">
                        <button
                            class="p-1 rounded-full hover:ring-2 hover:ring-orange-400 transition-all duration-150 focus:outline-none">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>"
                                    alt="<?= htmlspecialchars($_SESSION['user_name']) ?>"
                                    class="w-8 h-8 rounded-full object-cover" />
                            <?php else: ?>
                                <div
                                    class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown Panel -->
                        <div
                            class="absolute top-full right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                                </p>
                                <?php if (!empty($_SESSION['user_email'])): ?>
                                    <p class="text-xs text-gray-500 truncate mt-0.5">
                                        <?= htmlspecialchars($_SESSION['user_email']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="py-1">
                                <a href="<?= BASE_URL ?>/profile"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                    <i class="fa-sharp fa-solid fa-id-badge w-4 text-center"></i>
                                    My Profile
                                </a>
                                <a href="<?= BASE_URL ?>/orders"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                    <i class="fa-sharp fa-solid fa-cart-arrow-down w-4 text-center"></i>
                                    My Orders
                                </a>
                                <a href="<?= BASE_URL ?>/saved"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                    <i class="fa-sharp fa-solid fa-bookmark w-4 text-center"></i>
                                    Saved Items
                                </a>
                                <a href="<?= BASE_URL ?>/system-notifications"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                    <i class="fa-sharp fa-solid fa-message w-4 text-center"></i>
                                    System Notifications
                                </a>
                            </div>
                            <div class="border-t border-gray-100 py-1">
                                <a href="<?= BASE_URL ?>/user/auth/logout"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 transition-colors duration-150">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login Button (desktop) -->
                    <a href="<?= BASE_URL ?>/google"
                        class="hidden md:flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors duration-150">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                fill="#fff" opacity=".9" />
                            <path
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                fill="#fff" opacity=".9" />
                            <path
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                                fill="#fff" opacity=".9" />
                            <path
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                fill="#fff" opacity=".9" />
                        </svg>
                        Login
                    </a>
                <?php endif; ?>

                <!-- Hamburger Button (mobile only) -->
                <button id="mobile-menu-toggle"
                    class="md:hidden p-2 text-gray-600 hover:text-orange-500 transition-colors duration-150 focus:outline-none"
                    aria-label="Toggle menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-search-bar" class="hidden pb-3 lg:hidden relative">
            <div
                class="flex items-center border border-gray-300 rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-orange-400">
                <div class="flex items-center pl-3 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" />
                        <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                    </svg>
                </div>
                <input type="text" id="mobile-search-input" autocomplete="off" placeholder="Search for products..."
                    class="text-sm text-gray-700 placeholder-gray-400 px-3 py-2 flex-1 outline-none bg-white" />
                <button
                    class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 transition-colors duration-150">
                    Search
                </button>
            </div>

            <div id="mobile-search-suggestions"
                class="hidden absolute top-full left-0 mt-1 w-full bg-white rounded-lg shadow-lg border border-gray-100 z-50 max-h-80 overflow-y-auto">
            </div>

           <div id="mobile-search-error" class="hidden absolute -bottom-9 left-3 z-50">
    <div class="relative bg-gray-800 text-white text-xs px-3 py-1.5 rounded-md shadow-lg whitespace-nowrap">
        Please fill in the blank.
        <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-800 rotate-45"></div>
    </div>
</div>
        </div>
    </div>
</nav>

<!-- ===================== MOBILE SIDEBAR ===================== -->

<!-- Backdrop -->
<div id="sidebar-backdrop"
    class="fixed inset-0 bg-black/40 z-50 hidden opacity-0 transition-opacity duration-300 md:hidden"></div>

<!-- Sidebar panel (slides in from left) -->
<div id="mobile-sidebar" class="fixed top-0 left-0 h-full w-72 max-w-[85vw] bg-white z-[60] shadow-2xl
            -translate-x-full transition-transform duration-300 ease-in-out
            flex flex-col md:hidden">

    <!-- Sidebar Header -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100 shrink-0">
        <a href="<?= BASE_URL ?>" class="flex items-center gap-2">
            <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="h-8 object-contain"
                onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'font-bold text-lg text-gray-900',textContent:'NobleHome'}))">
        </a>
        <button id="sidebar-close" class="p-2 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none"
            aria-label="Close menu">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Scrollable Body -->
    <div class="flex-1 overflow-y-auto">

        <?php if ($isLoggedIn): ?>
            <!-- User Info -->
            <div class="flex items-center gap-3 px-4 py-4 bg-orange-50 border-b border-orange-100">
                <?php if (!empty($_SESSION['user_avatar'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>"
                        alt="<?= htmlspecialchars($_SESSION['user_name']) ?>"
                        class="w-10 h-10 rounded-full object-cover shrink-0 ring-2 ring-orange-300" />
                <?php else: ?>
                    <div
                        class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white text-sm font-bold shrink-0">
                        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($_SESSION['user_name']) ?>
                    </p>
                    <?php if (!empty($_SESSION['user_email'])): ?>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Nav Section -->
        <div class="px-3 py-3 space-y-0.5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Menu</p>

            <a href="<?= BASE_URL ?>/find-professional"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Find Professional
            </a>

            <a href="<?= BASE_URL ?>/inspiration"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                Inspiration
            </a>

            <!-- ===================== MOBILE PRODUCTS ACCORDION ===================== -->
            <!-- Replace the existing mobile products accordion block in top.php with this -->
            <div id="mobile-products-section">
                <button id="mobile-products-toggle" class="w-full flex items-center justify-between gap-3 px-3 py-3 rounded-lg
                   text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500
                   transition-colors duration-150 focus:outline-none">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Products
                    </span>
                    <svg id="products-chevron" class="w-4 h-4 transition-transform duration-200 shrink-0" fill="none"
                        stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Accordion body -->
                <div id="mobile-products-menu" class="hidden pl-4 pb-1 space-y-1">

                    <?php foreach ($categories as $cid => $cat): ?>
                        <?php if (empty($cat['subcategories']))
                            continue; ?>

                        <!-- Category label -->
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-3 pb-1">
                            <?= htmlspecialchars($cat['name']) ?>
                        </p>

                        <?php foreach ($cat['subcategories'] as $sid => $sub): ?>
                            <!-- Subcategory accordion row -->
                            <div>
                                <button class="mobile-sub-toggle w-full flex items-center justify-between
                                   px-3 py-2.5 rounded-lg text-sm text-gray-700
                                   hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150"
                                    data-target="mobile-sub-<?= $sid ?>">
                                    <span><?= htmlspecialchars($sub['name']) ?></span>
                                    <?php if (!empty($sub['products'])): ?>
                                        <svg class="w-3.5 h-3.5 transition-transform duration-200 mobile-chevron" fill="none"
                                            stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    <?php endif; ?>
                                </button>

                                <?php if (!empty($sub['products'])): ?>
                                    <div id="mobile-sub-<?= $sid ?>" class="hidden pl-4 space-y-0.5 pb-1">
                                        <?php foreach ($sub['products'] as $prod): ?>
                                            <a href="<?= BASE_URL ?>/product/<?= $prod['id'] ?>" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs
                                          text-gray-600 hover:bg-orange-50 hover:text-orange-500
                                          transition-colors duration-150">
                                                <div
                                                    class="w-7 h-7 rounded bg-gray-100 shrink-0 overflow-hidden border border-gray-200">
                                                    <?php if (!empty($prod['imageproduct'])): ?>
                                                        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($prod['imageproduct']) ?>"
                                                            alt="<?= htmlspecialchars($prod['name']) ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                                class="w-3 h-3">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?= htmlspecialchars($prod['name']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                        <a href="<?= BASE_URL ?>/products/subcategory/<?= $sid ?>" class="block px-3 py-1.5 text-[11px] font-semibold text-orange-500
                                      hover:text-orange-600 transition-colors duration-150">
                                            View all →
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                    <?php endforeach; ?>

                    <a href="<?= BASE_URL ?>/products/all" class="flex items-center gap-2 px-3 py-2.5 mt-1 rounded-lg text-sm font-semibold
                  text-orange-500 hover:bg-orange-50 transition-colors duration-150">
                        View All Products →
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links (small mobile only — sm:hidden icons in navbar) -->
        <div class="px-3 py-2 space-y-0.5 sm:hidden">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Quick Links</p>
            <a href="<?= BASE_URL ?>/cartview"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Cart
            </a>
            <a href="<?= BASE_URL ?>/saved"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-4-7 4V5z" />
                </svg>
                Saved Items
            </a>
            <a href="<?= BASE_URL ?>/notifications"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                Notifications
            </a>
        </div>

        <?php if ($isLoggedIn): ?>
            <!-- Account Links -->
            <div class="px-3 py-2 space-y-0.5 border-t border-gray-100 mt-2">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Account</p>
                <a href="<?= BASE_URL ?>/profile"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M5.121 17.804A9 9 0 1119 12a9 9 0 01-13.879 5.804zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    My Profile
                </a>
                <a href="<?= BASE_URL ?>/orders"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    My Orders
                </a>
                <a href="<?= BASE_URL ?>/settings"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Sidebar Footer: Login / Logout pinned at bottom -->
    <div class="shrink-0 px-4 py-4 border-t border-gray-100">
        <?php if ($isLoggedIn): ?>
            <a href="<?= BASE_URL ?>/user/auth/logout"
                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-medium text-red-500 border border-red-200 hover:bg-red-50 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Logout
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/google"
                class="flex items-center justify-center gap-2 w-full bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors duration-150">
                <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                        fill="#fff" opacity=".9" />
                    <path
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                        fill="#fff" opacity=".9" />
                    <path
                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                        fill="#fff" opacity=".9" />
                    <path
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                        fill="#fff" opacity=".9" />
                </svg>
                Login with Google
            </a>
        <?php endif; ?>
    </div>

</div>

<script>
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.getElementById('mobile-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const sidebarClose = document.getElementById('sidebar-close');
    const searchToggle = document.getElementById('mobile-search-toggle');
    const searchBar = document.getElementById('mobile-search-bar');
    const productsToggle = document.getElementById('mobile-products-toggle');
    const productsMenu = document.getElementById('mobile-products-menu');
    const productsChevron = document.getElementById('products-chevron');
    const BASE_URL = '<?= BASE_URL ?>';


    function openSidebar() {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => {
            backdrop.classList.add('opacity-100');
            sidebar.classList.remove('-translate-x-full');
        });
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        backdrop.classList.remove('opacity-100');
        sidebar.classList.add('-translate-x-full');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
        document.body.style.overflow = '';
    }

    menuToggle.addEventListener('click', openSidebar);
    sidebarClose.addEventListener('click', closeSidebar);
    backdrop.addEventListener('click', closeSidebar);

    searchToggle.addEventListener('click', () => {
        searchBar.classList.toggle('hidden');
    });

    productsToggle.addEventListener('click', () => {
        productsMenu.classList.toggle('hidden');
        productsChevron.classList.toggle('rotate-180');
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) closeSidebar();
    });

    (function () {
        const searchSuggestUrl = '<?= BASE_URL ?>/search-suggest';

        function formatPrice(num) {
            return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }

        function renderSuggestions(dropdown, suggestions) {
            if (!suggestions || suggestions.length === 0) {
                dropdown.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No matching products.</p>';
                dropdown.classList.remove('hidden');
                return;
            }
            dropdown.innerHTML = suggestions.map(item => `
            <a href="<?= BASE_URL ?>/mainproductview?id=${item.id}"
               class="flex items-center gap-3 px-3 py-2.5 hover:bg-orange-50 transition-colors duration-150 border-b border-gray-50 last:border-0">
                <div class="w-10 h-10 rounded-lg bg-gray-50 overflow-hidden flex items-center justify-center border border-gray-100 shrink-0">
                    ${item.image
                    ? `<img src="${item.image}" class="w-full h-full object-contain p-1">`
                    : `<i class="fa-solid fa-image text-gray-300"></i>`}
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-gray-800 font-medium truncate">${item.name}</p>
                    <p class="text-xs text-gray-400 truncate">
                        ${item.category ?? ''}${item.price !== null ? ' · ₱' + formatPrice(item.price) : ''}
                    </p>
                </div>
            </a>
        `).join('');
            dropdown.classList.remove('hidden');
        }

        function attachSearchBox(inputEl, dropdownEl) {
            let debounceTimer = null;
            let activeController = null;

            inputEl.addEventListener('input', function () {
                const q = inputEl.value.trim();
                clearTimeout(debounceTimer);

                if (q.length < 2) {
                    dropdownEl.classList.add('hidden');
                    dropdownEl.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(async () => {
                    if (activeController) activeController.abort();
                    activeController = new AbortController();
                    try {
                        const res = await fetch(`${searchSuggestUrl}?q=${encodeURIComponent(q)}`, {
                            signal: activeController.signal
                        });
                        const data = await res.json();
                        renderSuggestions(dropdownEl, data.suggestions);
                    } catch (e) {
                        if (e.name !== 'AbortError') dropdownEl.classList.add('hidden');
                    }
                }, 250);
            });

            inputEl.addEventListener('focus', function () {
                if (inputEl.value.trim().length >= 2 && dropdownEl.innerHTML !== '') {
                    dropdownEl.classList.remove('hidden');
                }
            });

            document.addEventListener('click', function (e) {
                if (!dropdownEl.contains(e.target) && e.target !== inputEl) {
                    dropdownEl.classList.add('hidden');
                }
            });
        }

        attachSearchBox(
            document.getElementById('desktop-search-input'),
            document.getElementById('desktop-search-suggestions')
        );
        attachSearchBox(
            document.getElementById('mobile-search-input'),
            document.getElementById('mobile-search-suggestions')
        );
    })();

document.getElementById('desktop-search-form').addEventListener('submit', function (e) {
    const input = document.getElementById('desktop-search-input');
    const tooltip = document.getElementById('desktop-search-error');
    if (input.value.trim() === '') {
        e.preventDefault();
        tooltip.classList.remove('hidden');
        input.focus();
    } else {
        tooltip.classList.add('hidden');
    }
});

document.getElementById('desktop-search-input').addEventListener('input', function () {
    document.getElementById('desktop-search-error').classList.add('hidden');
});

document.querySelector('#mobile-search-bar button').addEventListener('click', function (e) {
    const input = document.getElementById('mobile-search-input');
    const tooltip = document.getElementById('mobile-search-error');
    if (input.value.trim() === '') {
        tooltip.classList.remove('hidden');
        input.focus();
        return;
    }
    tooltip.classList.add('hidden');
    window.location.href = `<?= BASE_URL ?>/shop?search=${encodeURIComponent(input.value.trim())}`;
});

document.getElementById('mobile-search-input').addEventListener('input', function () {
    document.getElementById('mobile-search-error').classList.add('hidden');
});

// Bagong dagdag: mawala ang tooltip pag click sa labas
document.addEventListener('click', function (e) {
    const desktopInput = document.getElementById('desktop-search-input');
    const desktopTooltip = document.getElementById('desktop-search-error');
    if (desktopTooltip && !desktopInput.contains(e.target) && e.target !== desktopInput) {
        desktopTooltip.classList.add('hidden');
    }

    const mobileInput = document.getElementById('mobile-search-input');
    const mobileTooltip = document.getElementById('mobile-search-error');
    if (mobileTooltip && !mobileInput.contains(e.target) && e.target !== mobileInput) {
        mobileTooltip.classList.add('hidden');
    }
});
</script>