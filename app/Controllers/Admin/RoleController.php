<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\AuthGroups;

class RoleController extends BaseController
{
    public function index(): string
    {
        $this->authorize('users.view');

        $authGroups = config(AuthGroups::class);

        return view('admin/roles/index', [
            'title' => 'Roles & Permissions',
            'groups' => $authGroups->groups,
            'permissions' => $authGroups->permissions,
            'matrix' => $authGroups->matrix,
        ]);
    }

    private function authorize(string $permission): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
