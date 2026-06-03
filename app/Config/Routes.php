<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index', ['filter' => 'permission:dashboard.view']);
    $routes->post('tenant/switch', 'TenantController::switch', ['filter' => 'permission:dashboard.view']);
    $routes->get('modules/(:segment)', 'ModulePlaceholderController::show/$1', ['filter' => 'permission:dashboard.view']);
    $routes->get('audit-logs', 'AuditLogController::index', ['filter' => 'permission:audit.logs.view']);
    $routes->get('audit-logs/(:num)', 'AuditLogController::show/$1', ['filter' => 'permission:audit.logs.view']);

    $routes->group('admin', ['filter' => 'permission:users.view'], static function (RouteCollection $routes): void {
        $routes->get('users', 'Admin\UserController::index');
        $routes->get('users/new', 'Admin\UserController::create', ['filter' => 'permission:users.manage']);
        $routes->post('users', 'Admin\UserController::store', ['filter' => 'permission:users.manage']);
        $routes->get('users/(:num)/edit', 'Admin\UserController::edit/$1', ['filter' => 'permission:users.manage']);
        $routes->post('users/(:num)', 'Admin\UserController::update/$1', ['filter' => 'permission:users.manage']);
        $routes->post('users/(:num)/toggle', 'Admin\UserController::toggle/$1', ['filter' => 'permission:users.manage']);
        $routes->get('roles', 'Admin\RoleController::index');
    });

    $routes->group('sales', ['filter' => 'permission:sales.order.view'], static function (RouteCollection $routes): void {
        $routes->get('orders', 'Sales\SalesOrderController::index');
        $routes->get('orders/new', 'Sales\SalesOrderController::create', ['filter' => 'permission:sales.order.create']);
        $routes->post('orders', 'Sales\SalesOrderController::store', ['filter' => 'permission:sales.order.create']);
        $routes->get('orders/(:num)', 'Sales\SalesOrderController::show/$1');
    });

    $routes->group('purchase', ['filter' => 'permission:purchase.po.view'], static function (RouteCollection $routes): void {
        $routes->get('orders', 'Purchase\PurchaseOrderController::index');
        $routes->get('orders/new', 'Purchase\PurchaseOrderController::create', ['filter' => 'permission:purchase.po.create']);
        $routes->post('orders', 'Purchase\PurchaseOrderController::store', ['filter' => 'permission:purchase.po.create']);
        $routes->get('orders/(:num)', 'Purchase\PurchaseOrderController::show/$1');
    });

    $routes->group('setup', ['filter' => 'permission:setup.master.view'], static function (RouteCollection $routes): void {
        foreach (['transaction-codes','prefix-codes','companies','sites','departments','warehouses','locations','countries','provinces','cities','postal-codes','currencies','uoms','uom-conversions','vat','wht','item-vat','address-master','customers','suppliers','items'] as $resource) {
            $routes->get($resource, 'Setup\MasterDataController::index/' . $resource);
            $routes->get($resource . '/new', 'Setup\MasterDataController::create/' . $resource, ['filter' => 'permission:setup.master.manage']);
            $routes->post($resource, 'Setup\MasterDataController::store/' . $resource, ['filter' => 'permission:setup.master.manage']);
            $routes->get($resource . '/(:num)/edit', 'Setup\MasterDataController::edit/' . $resource . '/$1', ['filter' => 'permission:setup.master.manage']);
            $routes->post($resource . '/(:num)', 'Setup\MasterDataController::update/' . $resource . '/$1', ['filter' => 'permission:setup.master.manage']);
            $routes->post($resource . '/(:num)/delete', 'Setup\MasterDataController::delete/' . $resource . '/$1', ['filter' => 'permission:setup.master.manage']);
            $routes->get($resource . '/export', 'Setup\MasterDataTransferController::export/' . $resource);
            $routes->get($resource . '/import', 'Setup\MasterDataTransferController::importForm/' . $resource, ['filter' => 'permission:setup.master.manage']);
            $routes->post($resource . '/import', 'Setup\MasterDataTransferController::import/' . $resource, ['filter' => 'permission:setup.master.manage']);
            $routes->get($resource . '/template', 'Setup\MasterDataTransferController::template/' . $resource, ['filter' => 'permission:setup.master.manage']);
        }
        $routes->post('provinces/sync', 'Setup\WilayahSyncController::provinces', ['filter' => 'permission:setup.master.manage']);
        $routes->post('cities/sync', 'Setup\WilayahSyncController::cities', ['filter' => 'permission:setup.master.manage']);
    });

    $routes->get('ai-ocr/diagnostics', 'Ai\OcrDiagnosticsController::index', ['filter' => 'permission:ai.document.review']);
    $routes->get('ai-ocr/samples/purchase-order', 'Ai\SampleDocumentController::purchaseOrder', ['filter' => 'permission:ai.document.upload']);
    $routes->get('ai-ocr/samples/sales-order', 'Ai\SampleDocumentController::salesOrder', ['filter' => 'permission:ai.document.upload']);

    $routes->group('ai-documents', ['filter' => 'permission:ai.document.review,ai.document.upload'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Ai\DocumentController::index');
        $routes->get('upload', 'Ai\DocumentController::upload', ['filter' => 'permission:ai.document.upload']);
        $routes->post('upload', 'Ai\DocumentController::store', ['filter' => 'permission:ai.document.upload']);
        $routes->get('(:num)', 'Ai\DocumentController::show/$1');
        $routes->post('(:num)/process', 'Ai\DocumentController::process/$1', ['filter' => 'permission:ai.document.review']);
        $routes->get('(:num)/review', 'Ai\DocumentController::review/$1', ['filter' => 'permission:ai.document.review']);
        $routes->post('(:num)/review', 'Ai\DocumentController::saveReview/$1', ['filter' => 'permission:ai.document.review']);
        $routes->post('(:num)/convert-po', 'Ai\DocumentController::convertToPo/$1', ['filter' => 'permission:ai.document.convert']);
        $routes->post('(:num)/convert-so', 'Ai\DocumentController::convertToSo/$1', ['filter' => 'permission:ai.document.convert']);
    });
});
