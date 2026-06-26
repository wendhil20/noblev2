<?php
// index.php
define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/vendor/autoload.php';

// ─── Load .env ────────────────────────────────────────────────────────────────
$envFile = ROOT_PATH . '/.env';

if (!file_exists($envFile)) {
    die('.env file not found.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#'))
        continue;
    if (!str_contains($line, '='))
        continue;

    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// ─── Constants ────────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']     ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// ─── Base URL ─────────────────────────────────────────────────────────────────
$isLocalhost = (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);

define(
    'BASE_URL',
    $isLocalhost
        ? 'http://localhost/noblev2'
        : $_ENV['APP_URL']
);

// ─── Routing ──────────────────────────────────────────────────────────────────
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');
$request = preg_replace('#^noblev2/?#', '', $request);
$request = trim($request, '/');

if ($request === '' || $request === 'home') {
    $request = 'home';
}

// ─── Define Admin Routes ──────────────────────────────────────────────────────
$adminRoutes = [
    'loginadmin',
    'logoutadmin',
    'signaturedinsert',

    //hr
    'hr-main',
    'hr-registration-department',
    'hr-registration-account',
    'hr-backendupdate',
    'hr-backendposition',
    'hr-backendfetch',

    //productspecialist
    'ps-main',
    'ps-insertproduct',
    'ps-updateproduct',
    'ps-updateproductlist',
    'ps-promotion',
    'ps-tracking',
    'ps-category',
    'ps-productlinking',
    'ps-warehousebase',
    'ps-posupplier',
    'ps-supplierlink',
    'ps-promotionwebsite',
    'ps-quantitylimit',
    'ps-backend-insertproduct-handler',
    'ps-backend-category-helpers',
    'ps-backend-category-handler',
    'ps-backend-updateproduct-handler',
    'ps-backend-updateproduct-helpers',
    'ps-backendtrucklist-insert',
    'ps-backendtrucklist-update',
    'ps-backendtrucklist-delete',
    'ps-backendadd-category',
    'ps-backendadd-subcategory',
    'ps-backenddelete-category',
    'ps-backenddelete-subcategory',
    'ps-backendtoggle-subcategory',
    'ps-backendwarehousebase-save',
    'ps-backendsupplier-insert',
    'ps-backendsupplier-update',
    'ps-backendsuppliertype-insert',
    'ps-backendsuppliertype-update',
    'ps-backendsupplierlink-save',
    'ps-backendupdate-category-image',
    'ps-backendupdate-subcategory-image',
    'productlimitorder-fetch',
    'productlimitorder-save',


    //accounting
    'accounting',
    'accountant-approvepo',
    'accountant-includepo',
    'accountant-viewpo',
    'accountant-polist',
    'accountingapprove',
    'accountingfetchitems',
    'accountant-replacement',

    //accountantstaff
    'accountantstaff',
    'accounting-stafflist',
    'accounting-staffpoview',
    'accounting-backendponote',

    //accountantcustodian
    'accountantcustodian',
    'accounting-custodianlist',
    'accounting-custodianpoview',
    'accounting-backendpoacknowledge',
    

    //warehousehead
    'warehousemain',
    'warehouseassign',

    //warehousestaff
    'warehousestaff',
    'warehousestaffpo',
    'warehouse-poview',
    'warehousestaff-trackpo',
    'warehousestaff-pickupproof',
    'warehouse-backendposave',
    'warehouse-backendpo-getuser',
    'warehouse-backendpoupdate',
    'warehouseassignreceiver',
    'warehouse-assignupdate',
    'warehouse-backendstaff-orders',



    //warehousereceiver
    'warehousereceiver',
    'warehousereceiverscan',
    'warehousereceiverscanupdate',
    'warehousereceiver-saveqr',
    'warehousereceiver-pollstatus',
    'warehousereceiverstorage',
    'warehousereceiver-clearslot',
    'warehousereceiver-readyforbooking',
    'warehousereceiver-scheduledpos',
    'warehouse-pickupcomplete',

    //logisticstaff
    'logisticstaff',
    'logisticstaff-savebooking',
    'logisticstaff-cancelbooking',
    'logisticstaff-reschedulebooking',
    'logisticstaff-getbookings',
    'logisticstaff-savebookingdetails',
    'logisticstaff-resetreschedule',

    //logisticdispatcher
    'logisticdispatcher',
    'logisticdispatcher-startloading',
    'logisticdispatcher-intransit',
    'logisticdispatcher-delivered',
    'logisticdispatcher-getbookings',

    //salestaff
    'sales',
    'sales-replacementorder',
    'sales-replacementdetail',
   

    //superadmin
    'superadmin',
    'superadmin-list',
    'superadmin-viewpo',
    'superadmin-backendpoapprove',

    //navigation
    'fetchnotifications',
    'marknotificationread',


];

if (in_array($request, $adminRoutes)) {
    session_name('nobleadmin');
} else {
    session_name('nobleuser');
}

session_start();

// ─── Routes ───────────────────────────────────────────────────────────────────
$routes = [
    // auth
    'google'                                => 'user/auth/google.php',
    'callback'                              => 'user/auth/google-callback.php',
    'logout'                                => 'user/auth/logout.php',

    // pages 1
    'home'                                  => 'user/ui-page/page-1/main.php',


    // pages 2
    'mainproductview'                       => 'user/ui-page/page-2/mainproductview.php',
    'cartadd'                               => 'user/ui-page/backend/backend-page-2/cart-add.php',

    // pages 3
    'cartview'                              => 'user/ui-page/page-3/cartview.php',
    'cartupdate'                            => 'user/ui-page/backend/backend-page-3/cartupdate.php',
    'cartremove'                            => 'user/ui-page/backend/backend-page-3/cartremove.php',

    // pages 4
    'profile'                               => 'user/ui-page/page-4/profile-main.php',
    'profilemap'                            => 'user/ui-page/page-4/profile-map.php',
    'savemap'                               => 'user/ui-page/backend/backend-page-4/save-map.php',

    // pages 5
    'checkout'                             => 'user/ui-page/page-5/checkout-main.php',
    'success'                              => 'user/ui-page/page-5/success.php',
    'createcheckoutsession'                => 'user/ui-page/backend/backend-page-5/create-checkout-session.php',
    'webhook'                              => 'user/ui-page/webhook/paymongo.php',
    'checkoutcancel'                       => 'user/ui-page/backend/backend-page-5/checkout-cancel.php',
    'checkqrph'                            => 'user/ui-page/backend/backend-page-5/check-qrph.php',
    'createqrph'                           => 'user/ui-page/backend/backend-page-5/create-qrph.php',

    // pages 6
    'orders'                               => 'user/ui-page/page-6/orders.php',
    'order-details'                        => 'user/ui-page/page-6/order-tracking.php',
    'request-replacement-submit'           => 'user/ui-page/backend/backend-page-6/request-replacement-submit.php',
    'orders-poll'                         => 'user/ui-page/backend/backend-page-6/orders-poll.php',
    'order-tracking-poll'                  => 'user/ui-page/backend/backend-page-6/order-tracking-poll.php',

    // page 7
    'productcategory' => 'user/ui-page/page-7/categoryfield.php',

    // page 8
    'shop' => 'user/ui-page/page-8/shop.php',
    

    //admin
    'loginadmin'                           => 'admin/authentication/index-login.php',
    'logoutadmin'                          => 'admin/authentication/index-logout.php',
    'signaturedinsert'                     => 'admin/signatured/page-1/signatured-insert.php',

    //hr
    'hr-main'                               => 'admin/ui-hr/humanresource-main.php',
    'hr-registration-department'            => 'admin/ui-hr/humanresource-registration-department.php',
    'hr-registration-account'               => 'admin/ui-hr/humanresource-registration-account.php',
    'hr-backendupdate'                      => 'admin/ui-hr/backend/backend-account/hrupdate.php',
    'hr-backendposition'                    => 'admin/ui-hr/backend/backend-role/humanresource-hrposition.php',
    'hr-backendfetch'                       => 'admin/ui-hr/backend/backend-role/humanresource-hrfetch.php',

    //productspecialist
    'ps-main'                               => 'admin/ui-productspecialist/page-1/specialist-main.php',
    'ps-insertproduct'                      => 'admin/ui-productspecialist/page-2/specialist-insertproduct.php',
    'ps-updateproduct'                      => 'admin/ui-productspecialist/page-2/specialist-updateproductlist.php',
    'ps-updateproductlist'                  => 'admin/ui-productspecialist/page-2/specialist-updateproduct.php',
    'ps-promotion'                          => 'admin/ui-productspecialist/page-3/promotion-main.php',
    'ps-tracking'                           => 'admin/ui-productspecialist/page-4/tracklist-main.php',
    'ps-category'                           => 'admin/ui-productspecialist/page-5/category-main.php',
    'ps-productlinking'                     => 'admin/ui-productspecialist/page-6/product-linkingmanagement.php',
    'ps-warehousebase'                      => 'admin/ui-productspecialist/page-4/tracklist-warehousebase.php',
    'ps-posupplier'                         => 'admin/ui-productspecialist/page-7/po-supplier.php',
    'ps-supplierlink'                       => 'admin/ui-productspecialist/page-7/po-supplier-products.php',
    'ps-promotionwebsite'                   => 'admin/ui-productspecialist/page-8/promotion-website.php',
    'ps-quantitylimit'                      => 'admin/ui-productspecialist/page-9/productlimitorder-main.php',
    'ps-backend-insertproduct-handler'      => 'admin/ui-productspecialist/backend/backend-page-2/specialist-insertproduct-handler.php',
    'ps-backend-category-helpers'           => 'admin/ui-productspecialist/backend/backend-page-2/specialist-insertproduct-helpers.php',
    'ps-backend-category-handler'           => 'admin/ui-productspecialist/backend/backend-page-2/specialist-category-handler.php',
    'ps-backend-updateproduct-handler'      => 'admin/ui-productspecialist/backend/backend-page-2/specialist-updateproduct-handler.php',
    'ps-backend-updateproduct-helpers'      => 'admin/ui-productspecialist/backend/backend-page-2/specialist-updateproduct-helpers.php',
    'ps-backendtrucklist-insert'            => 'admin/ui-productspecialist/backend/backend-page-4/trucklist-insert.php',
    'ps-backendtrucklist-update'            => 'admin/ui-productspecialist/backend/backend-page-4/trucklist-update.php',
    'ps-backendtrucklist-delete'            => 'admin/ui-productspecialist/backend/backend-page-4/trucklist-delete.php',
    'ps-backendadd-category'                => 'admin/ui-productspecialist/backend/backend-page-5/add-category.php',
    'ps-backendadd-subcategory'             => 'admin/ui-productspecialist/backend/backend-page-5/add-subcategory.php',
    'ps-backenddelete-category'             => 'admin/ui-productspecialist/backend/backend-page-5/delete-category.php',
    'ps-backenddelete-subcategory'          => 'admin/ui-productspecialist/backend/backend-page-5/delete-subcategory.php',
    'ps-backendtoggle-subcategory'          => 'admin/ui-productspecialist/backend/backend-page-6/toggle-subcategory.php',
    'ps-backendwarehousebase-save'          => 'admin/ui-productspecialist/backend/backend-page-4/warehouse-base-save.php',
    'ps-backendsupplier-insert'             => 'admin/ui-productspecialist/backend/backend-page-7/po-supplier-insert.php',
    'ps-backendsupplier-update'             => 'admin/ui-productspecialist/backend/backend-page-7/po-supplier-update.php',
    'ps-backendsuppliertype-insert'         => 'admin/ui-productspecialist/backend/backend-page-7/suppliertype-insert.php',
    'ps-backendsuppliertype-update'         => 'admin/ui-productspecialist/backend/backend-page-7/suppliertype-update.php',
    'ps-backendsupplierlink-save'           => 'admin/ui-productspecialist/backend/backend-page-7/po-supplierlink-save.php',
    'ps-backendupdate-category-image'       => 'admin/ui-productspecialist/backend/backend-page-5/update-category-image.php',
    'ps-backendupdate-subcategory-image'    => 'admin/ui-productspecialist/backend/backend-page-5/update-subcategory-image.php',
    'productlimitorder-fetch'               => 'admin/ui-productspecialist/backend/backend-page-9/productlimitorder-fetch.php',
    'productlimitorder-save'                => 'admin/ui-productspecialist/backend/backend-page-9/productlimitorder-save.php',

    //accounting
    'accounting'                            => 'admin/ui-accountant/page-1/accountant-main.php',
    'accountant-includepo'                  => 'admin/ui-accountant/page-1/accountant-includepo.php',
    'accountant-viewpo'                     => 'admin/ui-accountant/page-1/accountant-viewpo.php',
    'accountant-polist'                     => 'admin/ui-accountant/page-1/accountant-polist.php',
    'accountant-replacement'                => 'admin/ui-accountant/page-1/accountant-replacement.php',
    'accountant-approvepo'                  => 'admin/ui-accountant/backend/backend-page-1/accountant-approvepo.php',
    'accountingapprove'                     => 'admin/ui-accountant/backend/backend-page-1/accounting-approve.php',
    'accountingfetchitems'                  => 'admin/ui-accountant/backend/backend-page-1/accounting-fetchitems.php',

    

    //accountantstaff
    'accountantstaff'                       => 'admin/ui-accountant/page-2/accountant-staffmain.php',
    'accounting-stafflist'                  => 'admin/ui-accountant/page-2/accountant-stafflist.php',
    'accounting-staffpoview'                => 'admin/ui-accountant/page-2/accountant-staffpoview.php',
    'accounting-backendponote'              => 'admin/ui-accountant/backend/backend-page-2/accounting-backendponote.php',

    //accountantcustodian
    'accountantcustodian'                   => 'admin/ui-accountant/page-3/accountant-custodianmain.php',
    'accounting-custodianlist'              => 'admin/ui-accountant/page-3/accountant-custodianlist.php',
    'accounting-custodianpoview'            => 'admin/ui-accountant/page-3/accountant-custodianpoview.php',
    'accounting-backendpoacknowledge'       => 'admin/ui-accountant/backend/backend-page-3/accounting-backendpoacknowledge.php',
    

    //warehousehead
    'warehousemain'                     => 'admin/ui-warehouse/page-1/warehouse-main.php',
    'warehouseassign'                   => 'admin/ui-warehouse/backend/backend-page-1/warehouse-assign.php',

    //warehousestaff
    'warehousestaff'                    => 'admin/ui-warehouse/page-2/warehouse-includestaffmain.php',
    'warehousestaffpo'                  => 'admin/ui-warehouse/page-2/warehousestaff-po.php',
    'warehouse-poview'                  => 'admin/ui-warehouse/page-2/warehouse-po-view.php',
    'warehousestaff-trackpo'            => 'admin/ui-warehouse/page-2/warehousestaff-trackpo.php',
    'warehousestaff-pickupproof'        => 'admin/ui-warehouse/page-2/warehousestaff-pickupproof.php',
    'warehouse-backendposave'           => 'admin/ui-warehouse/backend/backend-page-2/warehouse-po-save.php',
    'warehouse-backendpo-getuser'       => 'admin/ui-warehouse/backend/backend-page-2/warehouse-po-getuser.php',
    'warehouse-backendpoupdate'         => 'admin/ui-warehouse/backend/backend-page-2/warehouse-po-update.php',
    'warehouseassignreceiver'           => 'admin/ui-warehouse/backend/backend-page-2/warehouse-assignreceiver.php',
    'warehouse-assignupdate'            => 'admin/ui-warehouse/backend/backend-page-2/warehouse-assignupdate.php',
    'warehouse-backendstaff-orders'     => 'admin/ui-warehouse/backend/backend-page-2/warehouse-backendstaff-orders.php',
    'warehouse-pickupcomplete'          => 'admin/ui-warehouse/backend/backend-page-2/warehouse-pickupcomplete.php',

    //warehousereceiver
    'warehousereceiver'                 => 'admin/ui-warehouse/page-3/warehousereceiver-main.php',
    'warehousereceiverscan'             => 'admin/ui-warehouse/page-3/warehousereceiver-scan.php',
    'warehousereceiverstorage'          => 'admin/ui-warehouse/page-3/warehousereceiver-storage.php',
    'warehousereceiverscanupdate'       => 'admin/ui-warehouse/backend/backend-page-3/warehousereceiver-scanupdate.php',
    'warehousereceiver-saveqr'          => 'admin/ui-warehouse/backend/backend-page-3/warehousereceiver-saveqr.php',
    'warehousereceiver-pollstatus'      => 'admin/ui-warehouse/backend/backend-page-3/warehousereceiver-pollstatus.php',
    'warehousereceiver-clearslot'       => 'admin/ui-warehouse/backend/backend-page-3/warehousereceiver-clearslot.php',
    'warehousereceiver-readyforbooking' => 'admin/ui-warehouse/backend/backend-page-3/warehousereceiver-readyforbooking.php',
    'warehousereceiver-scheduledpos'    => 'admin/ui-warehouse/backend/backend-page-3/warehousereceiver-scheduledpos.php',

    //logisticstaff
    'logisticstaff'                      => 'admin/ui-logistic/page-1/logisticstaff-main.php',
    'logisticstaff-savebooking'          => 'admin/ui-logistic/backend/backend-page-1/logisticstaff-savebooking.php',
    'logisticstaff-cancelbooking'        => 'admin/ui-logistic/backend/backend-page-1/logisticstaff-cancelbooking.php',
    'logisticstaff-reschedulebooking'    => 'admin/ui-logistic/backend/backend-page-1/logisticstaff-reschedulebooking.php',
    'logisticstaff-getbookings'          => 'admin/ui-logistic/backend/backend-page-1/logisticstaff-getbookings.php',
    'logisticstaff-savebookingdetails'   => 'admin/ui-logistic/backend/backend-page-1/logisticstaff-savebookingdetails.php',
    'logisticstaff-resetreschedule'      => 'admin/ui-logistic/backend/backend-page-1/logisticstaff-resetreschedule.php',

    //logisticdispatcher
    'logisticdispatcher'                 => 'admin/ui-logistic/page-2/logisticdispatcher-main.php',
    'logisticdispatcher-startloading'    => 'admin/ui-logistic/backend/backend-page-2/logisticdispatcher-startloading.php',
    'logisticdispatcher-intransit'       => 'admin/ui-logistic/backend/backend-page-2/logisticdispatcher-intransit.php',
    'logisticdispatcher-delivered'       => 'admin/ui-logistic/backend/backend-page-2/logisticdispatcher-delivered.php',
    'logisticdispatcher-getbookings'     => 'admin/ui-logistic/backend/backend-page-2/logisticdispatcher-getbookings.php',

    //salestaff
    'sales'                             => 'admin/ui-sale/page-1/sales-main.php',
    'sales-replacementorder'            => 'admin/ui-sale/page-1/sales-replacement.php',
    'sales-replacementdetail'           => 'admin/ui-sale/page-1/sales-replacement-detail.php',
    
    //superadmin
    'superadmin'                        => 'admin/ui-superadmin/page-1/superadmin-main.php',
    'superadmin-list'                   => 'admin/ui-superadmin/page-1/superadmin-list.php',
    'superadmin-viewpo'                 => 'admin/ui-superadmin/page-1/superadmin-viewpo.php',
    'superadmin-backendpoapprove'       => 'admin/ui-superadmin/backend/backend-page-1/superadmin-approve.php',

    //navigation
    'fetchnotifications'                    => 'admin/navigation/fetch-notifications.php',
    'marknotificationread'                  => 'admin/navigation/mark-notification-read.php',
    'search-suggest'                        => 'user/navigation/search-suggest.php',
    'cart-mini'                             => 'user/navigation/backend/backend-page-1/cart-mini.php',
    'system-notifications'                  => 'user/navigation/system-notifications.php',
    'submit-review'                         => 'user/navigation/backend/backend-page-2/submit-review.php',

];

if (preg_match('#^mainproductview/(\d+)$#', $request, $m)) {
    $_GET['id'] = $m[1];
    $request = 'mainproductview';
}

$file = $routes[$request] ?? null;

if ($file === null) {
    http_response_code(404);
    include ROOT_PATH . '/404.php';
    exit;
}

$filepath = ROOT_PATH . '/' . $file;

if (file_exists($filepath)) {
    include $filepath;
} else {
    http_response_code(404);
    include ROOT_PATH . '/404.php';
    exit;
}