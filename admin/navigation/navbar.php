<?php
// navbar.php - Admin Sidebar Navigation
$currentUrl = $_SERVER['REQUEST_URI'];

$role = $_SESSION['role'] ?? '';
$position = $_SESSION['position'] ?? POSITION_STAFF;
$isHead = $position === POSITION_HEAD;
$isStaff = $position === POSITION_STAFF;
$isCustodian = $position === POSITION_CUSTODIAN;
$isCustooAssistant = $position === POSITION_CUSTOASSISTANT;

function isActive(string $path): string
{
    global $currentUrl;
    $currentPath = rtrim(parse_url($currentUrl, PHP_URL_PATH), '/');
    $path = rtrim($path, '/');
    return $currentPath === $path || str_ends_with($currentPath, $path)
        ? 'bg-gray-300 text-black font-semibold'
        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 font-small';
}
?>

<!-- Sidebar -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-60 bg-white border-r border-gray-100 shadow-sm flex flex-col z-40 transition-transform duration-300">

    <!-- Logo -->
    <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
        <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="h-9 w-9 object-contain shrink-0">
        <div class="w-px h-8 bg-gray-200 shrink-0"></div>
        <div class="leading-tight">
            <p class="text-sm font-bold text-gray-800 tracking-tight">Noble<span class="text-amber-500">Home</span></p>
            <p class="text-[10px] text-gray-400 font-normal">Department</p>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">

        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-3 pb-2 pt-1">Main</p>

        <!-- HR -->
        <?php if (in_array($role, [ROLE_HR])): ?>
            <a href="<?= BASE_URL ?>/hr-main"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/hr-main') ?>">
                <i class="fa-solid fa-sliders w-4 text-center"></i>
                Dashboard
            </a>

            <a href="<?= BASE_URL ?>/hr-registration-department"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/hr-registration-department') ?>">
                <i class="fa-solid fa-building-user w-4 text-center"></i>
                Create Department
            </a>

            <a href="<?= BASE_URL ?>/hr-registration-account"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/hr-registration-account') ?>">
                <i class="fa-solid fa-user w-4 text-center"></i>
                Create Account
            </a>

            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-3 pb-2 pt-4">Reports</p>

            <a href="<?= BASE_URL ?>/hr-logs"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/hr-logs') ?>">
                <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
                Activity Logs
            </a>

        <?php endif; ?>

        <!-- PRODUCTSPECIALIST -->
        <?php if (in_array($role, [ROLE_PRODUCTSPECIALIST])): ?>

            <a href="<?= BASE_URL ?>/ps-main"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-main') ?>">
                <i class="fa-solid fa-sliders w-4 text-center"></i>
                Dashboard
            </a>

            <a href="<?= BASE_URL ?>/ps-tracking"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-tracking') ?>">
                <i class="fa-solid fa-truck-fast w-4 text-center"></i>
                Truck List
            </a>

            <a href="<?= BASE_URL ?>/ps-insertproduct"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-insertproduct') ?>">
                <i class="fa-solid fa-arrow-right-to-bracket w-4 text-center"></i>
                Insert Product
            </a>

            <a href="<?= BASE_URL ?>/ps-updateproduct"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-updateproduct') ?>">
                <i class="fa-solid fa-arrow-right-to-bracket w-4 text-center"></i>
                Update Product
            </a>



            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-3 pb-2 pt-4">Management</p>

            <a href="<?= BASE_URL ?>/ps-quantitylimit"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-quantitylimit') ?>">
                <i class="fa-solid fa-ruler-combined w-4 text-center"></i>
                Quantity Limit
            </a>

            <a href="<?= BASE_URL ?>/ps-promotiontimer"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-promotiontimer') ?>">
                <i class="fa-solid fa-clock w-4 text-center"></i>
                Promotion Timer
            </a>

            <a href="<?= BASE_URL ?>/ps-promotion"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-promotion') ?>">
                <i class="fa-solid fa-bullhorn w-4 text-center"></i>
                Promotion
            </a>

            <a href="<?= BASE_URL ?>/ps-promotionwebsite"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-promotionwebsite') ?>">
                <i class="fa-solid fa-link w-4 text-center"></i>
                Promotion Website
            </a>

            <a href="<?= BASE_URL ?>/ps-category"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-category') ?>">
                <i class="fa-solid fa-list w-4 text-center"></i>
                Category
            </a>

            <a href="<?= BASE_URL ?>/ps-productlinking"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-productlinking') ?>">
                <i class="fa-solid fa-link w-4 text-center"></i>
                Product Linking
            </a>

            <a href="<?= BASE_URL ?>/ps-posupplier"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/ps-posupplier') ?>">
                <i class="fa-solid fa-truck-ramp-box w-4 text-center"></i>
                PO Supplier
            </a>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ACCOUNTING])): ?>
            <?php if ($position === 'head'): ?>
                <a href="<?= BASE_URL ?>/accounting"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/accounting') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    Order List
                </a>

                <a href="<?= BASE_URL ?>/accountant-includepo"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/accountant-includepo') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    PO List Pending
                </a>

                <a href="<?= BASE_URL ?>/accountant-replacement"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/accountant-replacement') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    Replacement Order
                </a>
            <?php endif; ?>


            <?php if ($position === 'staff'): ?>

                <a href="<?= BASE_URL ?>/accountantstaff"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/accountantstaff') ?>">
                    <i class="fa-solid fa-truck-ramp-box w-4 text-center"></i>
                    Po Supplier Approval
                </a>
            <?php endif; ?>

            <?php if ($position === 'custodian'): ?>
                <a href="<?= BASE_URL ?>/accountantcustodian"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/accountantcustodian') ?>">
                    <i class="fa-solid fa-truck-ramp-box w-4 text-center"></i>
                    Po Supplier Approval
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (in_array($role, [ROLE_WAREHOUSE])): ?>

            <?php if ($position === 'head'): ?>
                <a href="<?= BASE_URL ?>/warehousemain"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/warehousemain') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    Warehouse list order
                </a>
            <?php endif; ?>

            <?php if ($position === 'warehousestaff'): ?>
                <a href="<?= BASE_URL ?>/warehousestaff"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/warehousestaff') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    My Assigned Orders
                </a>
            <?php endif; ?>

            <?php if ($position === 'warehousereceiver'): ?>

                <a href="<?= BASE_URL ?>/warehousereceiver"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/warehousereceiver') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    My Assigned Orders
                </a>

                <a href="<?= BASE_URL ?>/warehousereceiverstorage"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/warehousereceiverstorage') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center"></i>
                    Storage
                </a>
            <?php endif; ?>


        <?php endif; ?>

        <?php if (in_array($role, [ROLE_SUPERADMIN])): ?>

            <a href="<?= BASE_URL ?>/superadmin"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/superadmin') ?>">
                <i class="fa-solid fa-sliders w-4 text-center"></i>
                PO List Pending
            </a>

        <?php endif; ?>

        <?php if (in_array($role, [ROLE_LOGISTIC])): ?>
            <?php if ($position === 'logisticstaff'): ?>
                <a href="<?= BASE_URL ?>/logisticstaff"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/logisticstaff') ?>">
                    <i class="fa-solid fa-sliders w-4 text-center"></i>
                    Dashboard
                </a>
            <?php endif; ?>

            <?php if ($position === 'logisticdispatcher'): ?>
                <a href="<?= BASE_URL ?>/logisticdispatcher"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/logisticdispatcher') ?>">
                    <i class="fa-solid fa-sliders w-4 text-center"></i>
                    Dashboard
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (in_array($role, [ROLE_SALES])): ?>
            <?php if ($position === 'staff'): ?>
                <a href="<?= BASE_URL ?>/sales"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/sales') ?>">
                    <i class="fa-solid fa-sliders w-4 text-center"></i>
                    Dashboard
                </a>

                <a href="<?= BASE_URL ?>/sales-replacementorder"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/sales-replacementorder') ?>">
                    <i class="fa-solid fa-sliders w-4 text-center"></i>
                    Replacement Order
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-3 pb-2 pt-4">Signatured</p>

        <a href="<?= BASE_URL ?>/signaturedinsert"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('/signaturedinsert') ?>">
            <i class="fa-solid fa-pen-to-square w-4 text-center"></i>
            Insert Signatured
        </a>


        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-3 pb-2 pt-4">Notifications</p>
        <!-- Notifications trigger button -->
        <button id="notif-toggle" onclick="toggleNotif()" class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium w-full
            text-gray-500 hover:bg-amber-50 hover:text-amber-600 transition-all duration-150">

            <!-- Bell with badge -->
            <span class="relative inline-flex items-center justify-center w-4">
                <i class="fa-solid fa-bell"></i>
                <span id="notif-badge" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[8px] font-bold
            min-w-[14px] h-[14px] rounded-full flex items-center justify-center px-0.5 hidden">0</span>
            </span>

            Notifications
        </button>

        <!-- Flyout Panel (overlay, outside sidebar flow) -->
        <div id="notif-panel" class="fixed top-0 right-0 h-screen w-72 bg-white border-l border-gray-100 shadow-lg
           z-50 flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out">

            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-bell text-gray-500 text-sm"></i>
                    <span class="text-sm font-semibold text-gray-800">Notifications</span>
                </div>
                <button onclick="toggleNotif()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Sub-header -->
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Recent</span>
                <button onclick="markAllRead()"
                    class="text-[10px] font-semibold text-amber-500 hover:text-amber-600 transition">
                    Mark all as read
                </button>
            </div>

            <!-- List -->
            <div id="notif-list" class="flex-1 overflow-y-auto divide-y divide-gray-50">
                <div class="px-4 py-4 text-xs text-gray-400 text-center">Loading…</div>
            </div>
        </div>

        <!-- Backdrop -->
        <div id="notif-backdrop" class="fixed inset-0 z-40 bg-black/10 hidden" onclick="toggleNotif()"></div>

    </nav>



    <!-- Bottom: User Info + Logout -->
    <div class="border-t border-gray-100 px-4 py-4">
        <div class="flex items-center gap-3 mb-3">
            <div
                class="w-8 h-8 rounded-full bg-amber-400 flex items-center justify-center text-white text-xs font-bold shrink-0">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-gray-800 truncate">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                </p>
                <p class="text-[10px] text-gray-400 truncate">
                    <?= htmlspecialchars($_SESSION['email'] ?? '') ?>
                </p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logoutadmin"
            class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-xs font-medium text-red-500 hover:bg-red-50 transition-all duration-150">
            <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
            Logout
        </a>
    </div>

</aside>

<script src="<?= BASE_URL ?>/js/notifications.js"></script>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>