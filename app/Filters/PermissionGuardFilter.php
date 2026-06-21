<?php

namespace App\Filters;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\ErpMenu;

class PermissionGuardFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! function_exists('auth') || ! auth()->loggedIn()) {
            return null;
        }

        $permission = $this->requiredPermission(trim(uri_string(), '/'), strtoupper($request->getMethod()));
        if ($permission === null) {
            return null;
        }

        $user = auth()->user();
        if ($user !== null && ($user->inGroup('superadmin') || $user->can($permission))) {
            return null;
        }

        throw PageNotFoundException::forPageNotFound();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function requiredPermission(string $path, string $method): ?string
    {
        if ($path === 'dashboard') {
            return 'dashboard.view';
        }

        if ($path === 'audit-logs' || str_starts_with($path, 'audit-logs/')) {
            return 'audit.logs.view';
        }

        if (str_starts_with($path, 'system/')) {
            return $this->systemPermission($path, $method);
        }

        if ($path === 'admin/roles') {
            return 'users.view';
        }

        if ($path === 'admin/users') {
            return $method === 'GET' ? 'users.view' : 'users.manage';
        }

        if (str_starts_with($path, 'admin/users/')) {
            return 'users.manage';
        }

        if (str_starts_with($path, 'setup/')) {
            return $this->setupPermission($path, $method);
        }

        if (str_starts_with($path, 'sales/')) {
            return $this->salesPermission($path, $method);
        }

        if (str_starts_with($path, 'purchase/')) {
            return $this->purchasePermission($path, $method);
        }

        if (str_starts_with($path, 'inventory/')) {
            return $this->inventoryPermission($path, $method);
        }

        if (str_starts_with($path, 'production/')) {
            return $method === 'GET' && ! str_contains($path, '/new')
                ? 'production.view'
                : 'production.manage';
        }

        if (str_starts_with($path, 'ap/')) {
            return $method === 'GET' && ! str_contains($path, '/payment') && ! str_contains($path, '/new')
                ? 'finance.ap.view'
                : 'finance.ap.manage';
        }

        if (str_starts_with($path, 'ar/')) {
            return $method === 'GET' && ! str_contains($path, '/receipt') && ! str_contains($path, '/new')
                ? 'finance.ar.view'
                : 'finance.ar.manage';
        }

        if (str_starts_with($path, 'gl/')) {
            if ($path === 'gl/posting-profiles') {
                return $method === 'GET' ? 'finance.gl.view' : 'finance.gl.post';
            }

            return $method === 'GET' && ! str_contains($path, '/new')
                ? 'finance.gl.view'
                : 'finance.gl.post';
        }

        if ($path === 'period-close' || str_starts_with($path, 'period-close/')) {
            return $this->periodClosePermission($path, $method);
        }

        if (str_starts_with($path, 'cash-bank/')) {
            return $method === 'GET' && ! str_contains($path, '/new')
                ? 'cashbank.view'
                : 'cashbank.manage';
        }

        if ($path === 'ai-documents' || str_starts_with($path, 'ai-documents/')) {
            return $this->aiDocumentPermission($path, $method);
        }

        if (str_starts_with($path, 'ai-ocr/')) {
            return 'ai.document.review';
        }

        if (str_starts_with($path, 'modules/')) {
            return $this->placeholderPermission(substr($path, strlen('modules/')));
        }

        return null;
    }

    private function systemPermission(string $path, string $method): string
    {
        if ($path === 'system/development-status') {
            return 'dashboard.view';
        }

        if (str_starts_with($path, 'system/data-import') || str_starts_with($path, 'system/excel-transfer')) {
            if ($method !== 'GET' || str_contains($path, '/import') || str_contains($path, '/commit')) {
                return 'setup.master.manage';
            }

            return 'setup.master.view';
        }

        return 'users.manage';
    }

    private function setupPermission(string $path, string $method): string
    {
        if ($method !== 'GET') {
            return 'setup.master.manage';
        }

        foreach (['/new', '/edit', '/import', '/template'] as $manageSegment) {
            if (str_contains($path, $manageSegment)) {
                return 'setup.master.manage';
            }
        }

        return 'setup.master.view';
    }

    private function salesPermission(string $path, string $method): string
    {
        if (preg_match('~^sales/deliveries/\d+/invoice$~', $path)) {
            return $method === 'GET' ? 'finance.ar.view' : 'finance.ar.manage';
        }

        if (preg_match('~^sales/orders/\d+/approve$~', $path)) {
            return 'sales.order.approve';
        }

        if (preg_match('~^sales/orders/\d+/(submit|reserve|cancel|allocate|deliver)$~', $path)
            || preg_match('~^sales/allocations/\d+/(cancel|reverse)$~', $path)
            || preg_match('~^sales/deliveries/\d+/reverse$~', $path)) {
            return 'sales.order.create';
        }

        if ($path === 'sales/orders/import'
            || $path === 'sales/orders/import/commit'
            || $path === 'sales/orders/import-template'
            || $path === 'sales/deliveries/import'
            || $path === 'sales/deliveries/import/commit'
            || $path === 'sales/deliveries/import-template'
            || $path === 'sales/orders/new'
            || str_contains($path, '/edit')
            || (preg_match('~^sales/orders/\d+$~', $path) && $method !== 'GET')
            || ($path === 'sales/orders' && $method !== 'GET')) {
            return 'sales.order.create';
        }

        return 'sales.order.view';
    }

    private function purchasePermission(string $path, string $method): string
    {
        if (preg_match('~^purchase/receipts/\d+/invoice$~', $path)) {
            return $method === 'GET' ? 'finance.ap.view' : 'finance.ap.manage';
        }

        if (preg_match('~^purchase/orders/\d+/approve$~', $path)) {
            return 'purchase.po.approve';
        }

        if (preg_match('~^purchase/orders/\d+/(submit|close|cancel|activate|receive)$~', $path)
            || preg_match('~^purchase/receipts/\d+/reverse$~', $path)) {
            return 'purchase.po.create';
        }

        if ($path === 'purchase/orders/import'
            || $path === 'purchase/orders/import/commit'
            || $path === 'purchase/orders/import-template'
            || $path === 'purchase/receipts/import'
            || $path === 'purchase/receipts/import/commit'
            || $path === 'purchase/receipts/import-template'
            || $path === 'purchase/orders/new'
            || str_contains($path, '/edit')
            || (preg_match('~^purchase/orders/\d+$~', $path) && $method !== 'GET')
            || ($path === 'purchase/orders' && $method !== 'GET')) {
            return 'purchase.po.create';
        }

        return 'purchase.po.view';
    }

    private function inventoryPermission(string $path, string $method): string
    {
        if ($method !== 'GET') {
            return 'inventory.movement.post';
        }

        if (str_contains($path, 'stock-adjustment')
            || str_contains($path, 'in-out')
            || str_contains($path, 'transfers/new')
            || str_contains($path, 'stock-opname')) {
            return 'inventory.movement.post';
        }

        return 'inventory.stock.view';
    }

    private function aiDocumentPermission(string $path, string $method): string
    {
        if (str_contains($path, '/convert-')) {
            return 'ai.document.convert';
        }

        if (str_contains($path, '/process') || str_contains($path, '/review')) {
            return 'ai.document.review';
        }

        if (str_contains($path, '/upload')) {
            return 'ai.document.upload';
        }

        return 'ai.document.review';
    }

    private function periodClosePermission(string $path, string $method): string
    {
        if ($method !== 'GET') {
            return 'finance.gl.post';
        }

        $segments = explode('/', $path);
        $module = $segments[1] ?? null;
        if ($module === 'new') {
            $module = $segments[2] ?? null;
        }

        return match ($module) {
            'sales' => 'sales.order.view',
            'purchase' => 'purchase.po.view',
            'inventory' => 'inventory.stock.view',
            'production' => 'production.view',
            default => 'finance.gl.view',
        };
    }

    private function placeholderPermission(string $module): ?string
    {
        $menu = ErpMenu::modules();
        if (! isset($menu[$module])) {
            return null;
        }

        $firstPermission = null;
        foreach ($menu[$module]['sections'] as $section) {
            foreach ($section['links'] as $link) {
                if (! empty($link['permission'])) {
                    $firstPermission = $link['permission'];
                    break 2;
                }
            }
        }

        return $firstPermission;
    }
}
