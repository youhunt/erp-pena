<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->post('tenant/switch', 'TenantController::switch');
    $routes->get('modules/(:segment)', 'ModulePlaceholderController::show/$1');
    $routes->get('audit-logs', 'AuditLogController::index');
    $routes->get('audit-logs/(:num)', 'AuditLogController::show/$1');

    $routes->group('admin', static function (RouteCollection $routes): void {
        $routes->get('users', 'Admin\UserController::index');
        $routes->get('users/new', 'Admin\UserController::create');
        $routes->post('users', 'Admin\UserController::store');
        $routes->get('users/(:num)/edit', 'Admin\UserController::edit/$1');
        $routes->post('users/(:num)', 'Admin\UserController::update/$1');
        $routes->post('users/(:num)/toggle', 'Admin\UserController::toggle/$1');
        $routes->get('roles', 'Admin\RoleController::index');
    });

    $routes->group('sales', static function (RouteCollection $routes): void {
        $routes->get('orders', 'Sales\SalesOrderController::index');
        $routes->get('orders/new', 'Sales\SalesOrderController::create');
        $routes->post('orders', 'Sales\SalesOrderController::store');
        $routes->get('orders/(:num)', 'Sales\SalesOrderController::show/$1');
    });

    $routes->group('purchase', static function (RouteCollection $routes): void {
        $routes->get('orders', 'Purchase\PurchaseOrderController::index');
        $routes->get('orders/new', 'Purchase\PurchaseOrderController::create');
        $routes->post('orders', 'Purchase\PurchaseOrderController::store');
        $routes->get('orders/(:num)', 'Purchase\PurchaseOrderController::show/$1');
    });

    $routes->group('setup', static function (RouteCollection $routes): void {
        foreach ([
            'transaction-codes',
            'prefix-codes',
            'companies',
            'sites',
            'departments',
            'warehouses',
            'locations',
            'countries',
            'provinces',
            'cities',
            'postal-codes',
            'currencies',
            'uoms',
            'uom-conversions',
            'vat',
            'wht',
            'item-vat',
            'address-master',
            'customers',
            'suppliers',
            'items',
        ] as $resource) {
            $routes->get($resource, 'Setup\MasterDataController::index/' . $resource);
            $routes->get($resource . '/new', 'Setup\MasterDataController::create/' . $resource);
            $routes->post($resource, 'Setup\MasterDataController::store/' . $resource);
            $routes->get($resource . '/(:num)/edit', 'Setup\MasterDataController::edit/' . $resource . '/$1');
            $routes->post($resource . '/(:num)', 'Setup\MasterDataController::update/' . $resource . '/$1');
            $routes->post($resource . '/(:num)/delete', 'Setup\MasterDataController::delete/' . $resource . '/$1');
            $routes->get($resource . '/export', 'Setup\MasterDataTransferController::export/' . $resource);
            $routes->get($resource . '/import', 'Setup\MasterDataTransferController::importForm/' . $resource);
            $routes->post($resource . '/import', 'Setup\MasterDataTransferController::import/' . $resource);
            $routes->get($resource . '/template', 'Setup\MasterDataTransferController::template/' . $resource);
        }

        $routes->post('provinces/sync', 'Setup\WilayahSyncController::provinces');
        $routes->post('cities/sync', 'Setup\WilayahSyncController::cities');
    });

    $routes->group('ai-documents', static function (RouteCollection $routes): void {
        $routes->get('/', 'Ai\DocumentController::index');
        $routes->get('upload', 'Ai\DocumentController::upload');
        $routes->post('upload', 'Ai\DocumentController::store');
        $routes->get('(:num)', 'Ai\DocumentController::show/$1');
        $routes->post('(:num)/process', 'Ai\DocumentController::process/$1');
        $routes->get('(:num)/review', 'Ai\DocumentController::review/$1');
        $routes->post('(:num)/review', 'Ai\DocumentController::saveReview/$1');
        $routes->post('(:num)/convert-po', 'Ai\DocumentController::convertToPo/$1');
    });
});
