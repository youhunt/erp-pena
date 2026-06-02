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
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $user = function_exists('auth') ? auth()->user() : null;

        return array_values(array_filter($items, static function (array $item) use ($user): bool {
            if (empty($item['permission'])) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            if (method_exists($user, 'inGroup') && $user->inGroup('superadmin')) {
                return true;
            }

            return $user->can($item['permission']);
        }));
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
}
