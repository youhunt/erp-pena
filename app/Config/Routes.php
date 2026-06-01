<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->post('tenant/switch', 'TenantController::switch');

    $routes->group('setup', static function (RouteCollection $routes): void {
        foreach ([
            'transaction-codes',
            'companies',
            'sites',
            'departments',
            'warehouses',
            'locations',
            'countries',
            'provinces',
            'cities',
            'postal-codes',
            'uoms',
            'uom-conversions',
            'vat',
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
        }

        $routes->post('provinces/sync', 'Setup\WilayahSyncController::provinces');
        $routes->post('cities/sync', 'Setup\WilayahSyncController::cities');
    });

    $routes->group('ai-documents', static function (RouteCollection $routes): void {
        $routes->get('/', 'Ai\DocumentController::index');
        $routes->get('upload', 'Ai\DocumentController::upload');
        $routes->post('upload', 'Ai\DocumentController::store');
    });
});
