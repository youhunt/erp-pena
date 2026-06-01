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

        return array_values(array_filter($items, static function (array $item): bool {
            if (empty($item['permission'])) {
                return true;
            }

            return function_exists('auth') && auth()->user()?->can($item['permission']);
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

                if ($item['children'] !== [] || ! empty($item['route'])) {
                    $branch[] = $item;
                }
            }

            return $branch;
        };

        return $build(0);
    }
}
