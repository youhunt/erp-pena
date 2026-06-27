<?php

namespace App\Services;

use App\Models\MenuItemModel;

class MenuService
{
    public function __construct(private readonly MenuItemModel $menus = new MenuItemModel())
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visibleMenuItems(): array
    {
        $items = $this->menus
            ->where('is_active', 1)
            ->orderBy('parent_id', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('label', 'ASC')
            ->findAll();

        $user = function_exists('auth') ? auth()->user() : null;

        $items = array_values(array_filter($items, static function (array $item) use ($user): bool {
            if (empty($item['permission'])) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            if (method_exists($user, 'inGroup') && $user->inGroup('superadmin')) {
                return true;
            }

            return method_exists($user, 'can') && $user->can($item['permission']);
        }));

        return array_map([$this, 'normalizeMenuItem'], $items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visibleMenuTree(): array
    {
        $items = $this->visibleMenuItems();
        $childrenByParent = [];

        foreach ($items as $item) {
            $parentId = $item['parent_id'] ?? 0;
            $childrenByParent[(int) $parentId][] = $item;
        }

        $build = static function (int $parentId) use (&$build, &$childrenByParent): array {
            $branch = [];

            foreach ($childrenByParent[$parentId] ?? [] as $item) {
                $item['children'] = $build((int) $item['id']);
                $route = trim((string) ($item['route'] ?? ''));
                $isClickable = $route !== '' && $route !== '#';

                if ($item['children'] !== [] || $isClickable) {
                    $branch[] = $item;
                }
            }

            return $branch;
        };

        return $build(0);
    }

    /** @param array<string, mixed> $item */
    private function normalizeMenuItem(array $item): array
    {
        $currentIcon = trim((string) ($item['icon'] ?? ''));
        if ($currentIcon !== '' && $currentIcon !== 'bx-circle') {
            return $item;
        }

        $label = strtolower(trim((string) ($item['label'] ?? '')));
        $route = strtolower(trim((string) ($item['route'] ?? '')));
        $key = $label . ' ' . $route;

        $item['icon'] = match (true) {
            str_contains($key, 'dashboard') => 'bx-home-circle',
            str_contains($key, 'setup') || str_contains($key, 'master') => 'bx-slider-alt',
            str_contains($key, 'purchase') => 'bx-cart',
            str_contains($key, 'sales') => 'bx-store',
            str_contains($key, 'inventory') => 'bx-package',
            str_contains($key, 'production') => 'bx-cog',
            str_contains($key, 'manufact') => 'bx-cog',
            str_contains($key, 'work order') => 'bx-task',
            str_contains($key, 'finance') => 'bx-wallet',
            str_contains($key, 'cash') || str_contains($key, 'bank') => 'bx-credit-card',
            str_contains($key, 'gl') || str_contains($key, 'ledger') => 'bx-book-content',
            str_contains($key, 'ap') || str_contains($key, 'payable') => 'bx-receipt',
            str_contains($key, 'ar') || str_contains($key, 'receivable') => 'bx-money',
            str_contains($key, 'report') => 'bx-bar-chart-alt-2',
            str_contains($key, 'system') || str_contains($key, 'admin') => 'bx-shield-quarter',
            default => $currentIcon !== '' ? $currentIcon : 'bx-circle',
        };

        return $item;
    }
}
