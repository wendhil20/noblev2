<?php
// top.php - Main Navigation Bar
// NOTE: Login logic is handled in /user/auth/google.php
$isLoggedIn = !empty($_SESSION['user_id']);

// Determine active nav link based on current path
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$isFindProfessionalActive = $currentPath === rtrim(parse_url(BASE_URL . '/find-professional', PHP_URL_PATH), '/');
$isInspirationActive = $currentPath === rtrim(parse_url(BASE_URL . '/inspiration', PHP_URL_PATH), '/');
$isHomeActive = $currentPath === rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
$isCartPageActive = $currentPath === rtrim(parse_url(BASE_URL . '/cartview', PHP_URL_PATH), '/');

// Reusable squiggly underline SVG (hand-drawn style)
function squigglyUnderline()
{
    return '<svg class="absolute left-0 -bottom-2 w-full h-3 pointer-events-none" viewBox="0 0 100 12" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M2,6 C15,2 25,9 38,5 C50,1 60,9 72,4 C82,0 90,7 98,5"
              stroke="#f97316" stroke-width="2.5" fill="none" stroke-linecap="round" />
    </svg>';
}
?>

<!-- ===================== GLOBAL PAGE LOADER (Uiverse.io by boryanakrasteva) ===================== -->
<!-- NOTE: this stays plain CSS (not Tailwind arbitrary-value classes) because sites with a
     compiled/purged Tailwind build won't generate classes like animate-[pulse_4923_2s_linear_infinite]
     unless that exact string was already scanned at build time — plain CSS always renders regardless. -->
<style>
    #page-loader-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(2px);
        align-items: center;
        justify-content: center;
    }

    .loader-circle-wrap {
        position: relative;
        width: 100px;
        height: 100px;
    }

    .loader-circle-wrap .circle {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 0px;
        height: 0px;
        border-radius: 100%;
        opacity: 0;
        animation: pulse_4923 2s infinite linear;
        border: 0.5px solid #f97316;
        box-shadow: 0px 0px 5px #fdba74;
    }

    .loader-circle-wrap .circle:nth-child(1) {
        animation-delay: .2s;
    }

    .loader-circle-wrap .circle:nth-child(2) {
        animation-delay: .4s;
    }

    .loader-circle-wrap .circle:nth-child(3) {
        animation-delay: .8s;
    }

    .loader-circle-wrap .circle:nth-child(4) {
        animation-delay: 1s;
    }

    @keyframes pulse_4923 {
        0% {
            opacity: 0.0;
            width: 0px;
            height: 0px;
            transform: translate(-50%, -50%) scale(1);
        }

        10% {
            opacity: 0.5;
            transform: translate(-50%, -50%) scale(2);
        }

        100% {
            opacity: 0.0;
            width: 100px;
            height: 100px;
            transform: translate(-50%, -50%) scale(1);
        }
    }
</style>

<div id="page-loader-overlay" class="hidden">
    <div class="loader-circle-wrap">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
</div>

<nav class="hidden md:block w-full bg-white border-b border-gray-200 shadow-sm relative z-40">
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
                        class="relative text-sm font-medium <?= $isFindProfessionalActive ? 'text-orange-500' : 'text-gray-700 hover:text-orange-500' ?> transition-colors duration-150 whitespace-nowrap">
                        Find Professional
                        <?php if ($isFindProfessionalActive)
                            echo squigglyUnderline(); ?>
                    </a>
                    <a href="<?= BASE_URL ?>/inspiration"
                        class="relative text-sm font-medium <?= $isInspirationActive ? 'text-orange-500' : 'text-gray-700 hover:text-orange-500' ?> transition-colors duration-150">
                        Inspiration
                        <?php if ($isInspirationActive)
                            echo squigglyUnderline(); ?>
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
                        <div
                            class="relative bg-gray-800 text-white text-xs px-3 py-1.5 rounded-md shadow-lg whitespace-nowrap">
                            Please fill in the blank.
                            <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-800 rotate-45"></div>
                        </div>
                    </div>
                </form>

                <!-- Cart Icon with Hover Dropdown -->
                <?php
                $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $isCheckoutPage = rtrim($currentPath, '/') === rtrim(parse_url(BASE_URL . '/checkout', PHP_URL_PATH), '/');
                ?>
                <div class="relative group <?= $isCheckoutPage ? 'hidden' : '' ?>" id="cart-icon-wrapper">
                    <a href="<?= BASE_URL ?>/cartview"
                        class="relative p-2 text-gray-600 hover:text-orange-500 transition-colors duration-150 block">
                        <i class="fa-solid fa-cart-flatbed"></i>
                        <span id="cart-count"
                            class="hidden absolute -top-0.5 -right-0.5 bg-orange-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">
                        </span>
                    </a>

                    <?php if ($isLoggedIn): ?>
                        <!-- Arrow/Caret (chat-bubble style, tumuturo pataas sa cart icon) -->
                        <div class="absolute top-full right-4 mt-1 w-3 h-3 bg-black border-t border-l border-gray-100
                    rotate-45 opacity-0 invisible group-hover:opacity-100 group-hover:visible
                    transition-all duration-200 z-50"></div>

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
                                    alt="<?= htmlspecialchars($_SESSION['user_name']) ?>" referrerpolicy="no-referrer"
                                    onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold',textContent:'<?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>'}))"
                                    class="w-8 h-8 rounded-full object-cover" />
                            <?php else: ?>
                                <div
                                    class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </button>

                        <!-- Overlay backdrop -->
                        <div class="fixed top-16 left-0 right-0 bottom-0 bg-black/40 z-30 opacity-0 invisible
                group-hover:opacity-100 group-hover:visible
                transition-opacity duration-200 pointer-events-none"></div>

                        <!-- Arrow/Caret (chat-bubble style, tumuturo pataas sa avatar) -->
                        <div class="absolute top-full right-4 mt-1 w-3 h-3 bg-black border-t border-l border-gray-100
                rotate-45 opacity-0 invisible group-hover:opacity-100 group-hover:visible
                transition-all duration-200 z-50"></div>


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
                                <a href="<?= BASE_URL ?>/logout"
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
    </div>
</nav>

<!-- ===================== MOBILE SEARCH OVERLAY (triggered from bottom nav) ===================== -->
<div id="mobile-search-bar"
    class="hidden md:hidden fixed top-0 left-0 right-0 z-[70] bg-white border-b border-gray-200 shadow-md px-4 pt-4 pb-3">
    <div class="flex items-center gap-2">
        <div
            class="flex-1 flex items-center border border-gray-300 rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-orange-400 relative">
            <div class="flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8" />
                    <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                </svg>
            </div>
            <input type="text" id="mobile-search-input" autocomplete="off" placeholder="Search for products..."
                class="text-sm text-gray-700 placeholder-gray-400 px-3 py-2 flex-1 outline-none bg-white" />
            <button id="mobile-search-submit"
                class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 transition-colors duration-150">
                Search
            </button>
        </div>
        <button id="mobile-search-close"
            class="p-2 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none shrink-0"
            aria-label="Close search">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div id="mobile-search-suggestions"
        class="hidden absolute top-full left-4 right-4 mt-1 bg-white rounded-lg shadow-lg border border-gray-100 z-50 max-h-80 overflow-y-auto">
    </div>

    <div id="mobile-search-error" class="hidden absolute top-full left-6 mt-1 z-50">
        <div class="relative bg-gray-800 text-white text-xs px-3 py-1.5 rounded-md shadow-lg whitespace-nowrap">
            Please fill in the blank.
            <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-800 rotate-45"></div>
        </div>
    </div>
</div>
<div id="mobile-search-backdrop" class="hidden fixed inset-0 bg-black/40 z-[65] md:hidden"></div>

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
                        alt="<?= htmlspecialchars($_SESSION['user_name']) ?>" referrerpolicy="no-referrer"
                        onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white text-sm font-bold shrink-0',textContent:'<?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>'}))"
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

                Find Professional
            </a>

            <a href="<?= BASE_URL ?>/inspiration"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-burst text-lg"></i>
                Inspiration
            </a>

            <a href="<?= BASE_URL ?>/shop"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-bag-shopping text-lg"></i>
                Shop
            </a>
        </div>

        <!-- Quick Links (small mobile only — sm:hidden icons in navbar) -->
        <div class="px-3 py-2 space-y-0.5 sm:hidden">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Quick Links</p>
            <a href="<?= BASE_URL ?>/cartview"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-cart-flatbed text-lg w-5 text-center"></i>
                Cart
            </a>
            <a href="<?= BASE_URL ?>/saved"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-bookmark text-lg w-5 text-center"></i>
                Saved Items
            </a>
            <a href="<?= BASE_URL ?>/system-notifications"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-sharp fa-solid fa-message text-lg w-5 text-center"></i>
                System Notifications
            </a>
        </div>


        <?php if ($isLoggedIn): ?>
            <!-- Account Links -->
            <div class="px-3 py-2 space-y-0.5 border-t border-gray-100 mt-2">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Account</p>
                <a href="<?= BASE_URL ?>/profile"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <i class="fa-solid fa-circle-user text-lg"></i>
                    My Profile
                </a>
                <a href="<?= BASE_URL ?>/orders"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <i class="fa-solid fa-cart-flatbed"></i>
                    My Orders
                </a>
                <a href="<?= BASE_URL ?>/settings"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <i class="fa-solid fa-gear text-lg"></i>
                    Settings
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Sidebar Footer: Login / Logout pinned at bottom -->
    <div class="shrink-0 px-4 py-4 border-t border-gray-100">
        <?php if ($isLoggedIn): ?>
            <a href="<?= BASE_URL ?>/logout"
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

<!-- ===================== MOBILE BOTTOM NAV (TikTok Shop style) ===================== -->
<nav id="mobile-bottom-nav"
    class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200
           flex items-stretch justify-between px-1 pb-[env(safe-area-inset-bottom)] shadow-[0_-1px_6px_rgba(0,0,0,0.06)]">

    <!-- Home -->
    <a href="<?= BASE_URL ?>" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium
               <?= $isHomeActive ? 'text-orange-500' : 'text-gray-500' ?>">
        <i class="fa-sharp fa-solid fa-house-chimney-window text-lg"></i>
        Home
    </a>

    <!-- Search -->
    <button id="mobile-search-toggle"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium text-gray-500 focus:outline-none">
        <i class="fa-solid fa-magnifying-glass text-lg"></i>
        Search
    </button>

    <!-- Cart (elevated center action button) -->
    <a href="<?= BASE_URL ?>/cartview" class="flex-1 flex flex-col items-center justify-center relative -mt-3">
        <div
            class="w-12 h-12 rounded-full bg-orange-500 flex items-center justify-center shadow-lg shadow-orange-200 relative">
            <i class="fa-solid fa-cart-flatbed text-white text-lg"></i>
            <span id="cart-count-bottom"
                class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">
            </span>
        </div>
        <span
            class="text-[10px] font-medium mt-0.5 <?= $isCartPageActive ? 'text-orange-500' : 'text-gray-500' ?>">Cart</span>
    </a>

    <!-- Inspiration -->
    <a href="<?= BASE_URL ?>/inspiration" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium
               <?= $isInspirationActive ? 'text-orange-500' : 'text-gray-500' ?>">
        <i class="fa-solid fa-burst text-lg"></i>
        Inspiration
    </a>

    <!-- Account / Menu (opens same sidebar) -->
    <button id="mobile-bottomnav-menu-toggle"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium text-gray-500 focus:outline-none">
        <?php if ($isLoggedIn && !empty($_SESSION['user_avatar'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="" referrerpolicy="no-referrer"
                onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'fa-solid fa-circle-user text-lg'}))"
                class="w-5 h-5 rounded-full object-cover" />
        <?php else: ?>
            <i class="fa-solid fa-circle-user text-lg"></i>
        <?php endif; ?>
        <?= $isLoggedIn ? 'Account' : 'Menu' ?>
    </button>
</nav>

<script>
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.getElementById('mobile-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const sidebarClose = document.getElementById('sidebar-close');
    const searchToggle = document.getElementById('mobile-search-toggle');
    const searchBar = document.getElementById('mobile-search-bar');
    const searchClose = document.getElementById('mobile-search-close');
    const searchBackdrop = document.getElementById('mobile-search-backdrop');
    const bottomNavMenuToggle = document.getElementById('mobile-bottomnav-menu-toggle');
    const BASE_URL = '<?= BASE_URL ?>';


    function openSidebar() {
        if (!backdrop || !sidebar) return;
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => {
            backdrop.classList.add('opacity-100');
            sidebar.classList.remove('-translate-x-full');
        });
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!backdrop || !sidebar) return;
        backdrop.classList.remove('opacity-100');
        sidebar.classList.add('-translate-x-full');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
        document.body.style.overflow = '';
    }

    function warnMissing(name) {
        console.warn('[top.php] Expected element not found on this page:', name);
    }

    if (menuToggle) { menuToggle.addEventListener('click', openSidebar); } else { warnMissing('#mobile-menu-toggle'); }
    if (sidebarClose) { sidebarClose.addEventListener('click', closeSidebar); } else { warnMissing('#sidebar-close'); }
    if (backdrop) { backdrop.addEventListener('click', closeSidebar); } else { warnMissing('#sidebar-backdrop'); }

    if (bottomNavMenuToggle) {
        bottomNavMenuToggle.addEventListener('click', openSidebar);
    }

    function openMobileSearch() {
        if (!searchBar || !searchBackdrop) return;
        searchBar.classList.remove('hidden');
        searchBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const input = document.getElementById('mobile-search-input');
            if (input) input.focus();
        }, 50);
    }

    function closeMobileSearch() {
        if (!searchBar || !searchBackdrop) return;
        searchBar.classList.add('hidden');
        searchBackdrop.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (searchToggle) searchToggle.addEventListener('click', openMobileSearch);
    if (searchClose) searchClose.addEventListener('click', closeMobileSearch);
    if (searchBackdrop) searchBackdrop.addEventListener('click', closeMobileSearch);

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            closeSidebar();
            closeMobileSearch();
        }
    });

    // Sync cart count badge between top navbar and bottom nav
    (function () {
        const cartCountTop = document.getElementById('cart-count');
        const cartCountBottom = document.getElementById('cart-count-bottom');
        if (!cartCountTop || !cartCountBottom) return;

        const syncCartBadge = () => {
            cartCountBottom.textContent = cartCountTop.textContent;
            cartCountBottom.classList.toggle('hidden', cartCountTop.classList.contains('hidden'));
        };

        const observer = new MutationObserver(syncCartBadge);
        observer.observe(cartCountTop, { attributes: true, childList: true, characterData: true, subtree: true });
        syncCartBadge();
    })();

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
            if (!inputEl || !dropdownEl) return;
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

    const desktopSearchForm = document.getElementById('desktop-search-form');
    if (desktopSearchForm) {
        desktopSearchForm.addEventListener('submit', function (e) {
            const input = document.getElementById('desktop-search-input');
            const tooltip = document.getElementById('desktop-search-error');
            if (input && input.value.trim() === '') {
                e.preventDefault();
                if (tooltip) tooltip.classList.remove('hidden');
                input.focus();
            } else if (tooltip) {
                tooltip.classList.add('hidden');
            }
        });
    }

    const desktopSearchInput = document.getElementById('desktop-search-input');
    if (desktopSearchInput) {
        desktopSearchInput.addEventListener('input', function () {
            const tooltip = document.getElementById('desktop-search-error');
            if (tooltip) tooltip.classList.add('hidden');
        });
    }

    const mobileSearchSubmit = document.getElementById('mobile-search-submit');
    if (mobileSearchSubmit) {
        mobileSearchSubmit.addEventListener('click', function (e) {
            const input = document.getElementById('mobile-search-input');
            const tooltip = document.getElementById('mobile-search-error');
            if (!input) return;
            if (input.value.trim() === '') {
                if (tooltip) tooltip.classList.remove('hidden');
                input.focus();
                return;
            }
            if (tooltip) tooltip.classList.add('hidden');
            closeMobileSearch();
            window.location.href = `<?= BASE_URL ?>/shop?search=${encodeURIComponent(input.value.trim())}`;
        });
    }

    const mobileSearchInput = document.getElementById('mobile-search-input');
    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function () {
            const tooltip = document.getElementById('mobile-search-error');
            if (tooltip) tooltip.classList.add('hidden');
        });
    }

    // Bagong dagdag: mawala ang tooltip pag click sa labas
    document.addEventListener('click', function (e) {
        const desktopInput = document.getElementById('desktop-search-input');
        const desktopTooltip = document.getElementById('desktop-search-error');
        if (desktopTooltip && desktopInput && !desktopInput.contains(e.target) && e.target !== desktopInput) {
            desktopTooltip.classList.add('hidden');
        }

        const mobileInput = document.getElementById('mobile-search-input');
        const mobileTooltip = document.getElementById('mobile-search-error');
        if (mobileTooltip && mobileInput && !mobileInput.contains(e.target) && e.target !== mobileInput) {
            mobileTooltip.classList.add('hidden');
        }
    });

    // ===================== NAV LINK LOADING OVERLAY (2s) =====================
    (function () {
        const loaderOverlay = document.getElementById('page-loader-overlay');
        if (!loaderOverlay) return;

        const navContainers = [
            document.querySelector('nav.hidden.md\\:block'), // desktop top nav
            document.getElementById('mobile-sidebar'),
            document.getElementById('mobile-bottom-nav')
        ].filter(Boolean);

        navContainers.forEach(container => {
            container.querySelectorAll('a[href]').forEach(link => {
                link.addEventListener('click', function (e) {
                    const href = link.getAttribute('href');

                    // Skip empty/hash links, new-tab links, or links explicitly opting out
                    if (!href || href.startsWith('#') || link.target === '_blank' || link.hasAttribute('data-no-loader')) {
                        return;
                    }

                    e.preventDefault();
                    loaderOverlay.classList.remove('hidden');
                    loaderOverlay.style.display = 'flex';

                    setTimeout(() => {
                        window.location.href = href;
                    }, 2000);
                });
            });
        });
    })();
</script>