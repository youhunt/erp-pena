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
            return str_contains($path, 'stock-adjustment')
                || str_contains($path, 'in-out')
                || str_contains($path, 'transfers')
                || str_contains($path, 'stock-opname')
                || $method !== 'GET'
                ? 'inventory.movement.post'
                : 'inventory.stock.view';
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
            return $method === 'GET' && ! str_contains($path, '/new')
                ? 'finance.gl.view'
                : 'finance.gl.post';
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
        if (str_contains($path, '/approve')) {
            return 'sales.order.approve';
        }

        if ($path === 'sales/orders/new' || ($path === 'sales/orders' && $method !== 'GET')) {
            return 'sales.order.create';
        }

        return 'sales.order.view';
    }

    private function purchasePermission(string $path, string $method): string
    {
        if (str_contains($path, '/approve')) {
            return 'purchase.po.approve';
        }

        if ($path === 'purchase/orders/new' || ($path === 'purchase/orders' && $method !== 'GET')) {
            return 'purchase.po.create';
        }

        return 'purchase.po.view';
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

    private function placeholderPermission(string $slug): ?string
    {
        foreach (config(ErpMenu::class)->items() as $item) {
            foreach (($item['children'] ?? [$item]) as $child) {
                if (($child['route'] ?? '') === 'modules/' . $slug) {
                    return $child['permission'] ?? null;
                }
            }
        }

        return null;
    }
}
