<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index');

    $routes->group('setup', static function (RouteCollection $routes): void {
        $routes->get('companies', 'Setup\CompanyController::index');
        $routes->get('sites', 'Setup\SiteController::index');
    });

    $routes->group('ai-documents', static function (RouteCollection $routes): void {
        $routes->get('/', 'Ai\DocumentController::index');
        $routes->get('upload', 'Ai\DocumentController::upload');
        $routes->post('upload', 'Ai\DocumentController::store');
    });
});
