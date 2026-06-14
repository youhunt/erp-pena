<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\AuthGroups;
use Config\ErpMenu;

class RoleController extends BaseController
{
    public function index(): string
    {
        $this->authorize('roles.view');

        $authGroups = config(AuthGroups::class);

        return view('admin/roles/index', [
            'title' => 'Roles & Permissions',
            'groups' => $authGroups->groups,
            'permissions' => $authGroups->permissions,
            'matrix' => $authGroups->matrix,
            'menuAccess' => $this->menuAccessItems(),
            'rolePermissionMap' => $this->rolePermissionMap($authGroups->matrix, array_keys($authGroups->permissions)),
        ]);
    }

    /**
     * @return list<array{module:string,label:string,route:string,permission:string}>
     */
    private function menuAccessItems(): array
    {
        $items = [];
        foreach (config(ErpMenu::class)->items() as $menu) {
            $module = (string) ($menu['label'] ?? 'Menu');
            foreach (($menu['children'] ?? [$menu]) as $child) {
                $permission = (string) ($child['permission'] ?? '');
                if ($permission === '') {
                    continue;
                }

                $items[] = [
                    'module' => $module,
                    'label' => (string) ($child['label'] ?? $module),
                    'route' => (string) ($child['route'] ?? '#'),
                    'permission' => $permission,
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<string, list<string>> $matrix
     * @param list<string>                $permissions
     *
     * @return array<string, list<string>>
     */
    private function rolePermissionMap(array $matrix, array $permissions): array
    {
        $map = [];

        foreach ($matrix as $role => $patterns) {
            $map[$role] = $this->expandPermissions($patterns, $permissions);
        }

        return $map;
    }

    /**
     * @param list<string> $patterns
     * @param list<string> $permissions
     *
     * @return list<string>
     */
    private function expandPermissions(array $patterns, array $permissions): array
    {
        $resolved = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                $resolved = array_merge($resolved, $permissions);
                continue;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($permissions as $permission) {
                    if (str_starts_with($permission, $prefix)) {
                        $resolved[] = $permission;
                    }
                }
                continue;
            }

            if (in_array($pattern, $permissions, true)) {
                $resolved[] = $pattern;
            }
        }

        $resolved = array_values(array_unique($resolved));
        sort($resolved);

        return $resolved;
    }

    private function authorize(string $permission): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
