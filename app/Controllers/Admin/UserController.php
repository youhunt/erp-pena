<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CompanyModel;
use App\Models\SiteModel;
use App\Services\AuditLogService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Config\AuthGroups;
use Config\Database;

class UserController extends BaseController
{
    public function index(): string
    {
        $this->authorize('users.view');

        $users = auth()->getProvider()->findAll();
        $db = Database::connect();

        return view('admin/users/index', [
            'title' => 'User Management',
            'users' => array_map(static function ($user) use ($db): array {
                $groups = $db->table('auth_groups_users')->select('group')->where('user_id', $user->id)->get()->getResultArray();

                return [
                    'id' => (int) $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'active' => (int) ($user->active ?? 0),
                    'groups' => implode(', ', array_column($groups, 'group')),
                ];
            }, $users),
        ]);
    }

    public function create(): string
    {
        $this->authorize('users.manage');

        return view('admin/users/form', $this->formData('Create User'));
    }

    public function store()
    {
        $this->authorize('users.manage');

        $rules = [
            'username' => 'required|min_length[3]|max_length[50]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $users = auth()->getProvider();
        $user = new User([
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')),
            'password' => (string) $this->request->getPost('password'),
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->activate();

        $this->syncGroups($user, (array) $this->request->getPost('groups'));
        $this->syncCompanyAccess((int) $user->id, (array) $this->request->getPost('company_ids'), (int) $this->request->getPost('default_company_id'));
        $this->syncSiteAccess((int) $user->id, (array) $this->request->getPost('site_ids'), (int) $this->request->getPost('default_company_id'), (int) $this->request->getPost('default_site_id'));

        $this->audit('users.create', (int) $user->id, $user->email, ['groups' => (array) $this->request->getPost('groups')]);

        return redirect()->to(site_url('admin/users'))->with('message', 'User created.');
    }

    public function edit(int $id): string
    {
        $this->authorize('users.manage');
        $user = auth()->getProvider()->findById($id);
        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('admin/users/form', $this->formData('Edit User', $user));
    }

    public function update(int $id)
    {
        $this->authorize('users.manage');
        $users = auth()->getProvider();
        $user = $users->findById($id);
        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'username' => 'required|min_length[3]|max_length[50]',
            'email' => 'required|valid_email',
            'password' => 'permit_empty|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $user->username = trim((string) $this->request->getPost('username'));
        $user->email = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $user->password = $password;
        }

        $users->save($user);

        $this->syncGroups($user, (array) $this->request->getPost('groups'));
        $this->syncCompanyAccess($id, (array) $this->request->getPost('company_ids'), (int) $this->request->getPost('default_company_id'));
        $this->syncSiteAccess($id, (array) $this->request->getPost('site_ids'), (int) $this->request->getPost('default_company_id'), (int) $this->request->getPost('default_site_id'));

        $this->audit('users.update', $id, $user->email, ['groups' => (array) $this->request->getPost('groups')]);

        return redirect()->to(site_url('admin/users'))->with('message', 'User updated.');
    }

    public function toggle(int $id)
    {
        $this->authorize('users.manage');
        $user = auth()->getProvider()->findById($id);
        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ((int) ($user->active ?? 0) === 1) {
            $user->deactivate();
            $action = 'users.deactivate';
        } else {
            $user->activate();
            $action = 'users.activate';
        }

        $this->audit($action, $id, $user->email);

        return redirect()->to(site_url('admin/users'))->with('message', 'User status updated.');
    }

    private function formData(string $title, ?User $user = null): array
    {
        $db = Database::connect();
        $authGroups = config(AuthGroups::class);
        $userId = $user ? (int) $user->id : 0;

        $selectedGroups = $userId > 0
            ? array_column($db->table('auth_groups_users')->select('group')->where('user_id', $userId)->get()->getResultArray(), 'group')
            : [];

        $companyAccess = $userId > 0
            ? $db->table('user_company_access')->where('user_id', $userId)->get()->getResultArray()
            : [];

        $siteAccess = $userId > 0
            ? $db->table('user_site_access')->where('user_id', $userId)->get()->getResultArray()
            : [];

        return [
            'title' => $title,
            'user' => $user,
            'groups' => $authGroups->groups,
            'selectedGroups' => $selectedGroups,
            'companies' => (new CompanyModel())->orderBy('code', 'ASC')->findAll(),
            'sites' => (new SiteModel())->orderBy('code', 'ASC')->findAll(),
            'selectedCompanyIds' => array_map('intval', array_column($companyAccess, 'company_id')),
            'selectedSiteIds' => array_map('intval', array_column($siteAccess, 'site_id')),
            'defaultCompanyId' => (int) ($this->firstDefault($companyAccess, 'company_id') ?? 0),
            'defaultSiteId' => (int) ($this->firstDefault($siteAccess, 'site_id') ?? 0),
        ];
    }

    private function syncGroups(User $user, array $groups): void
    {
        $db = Database::connect();
        $groups = array_values(array_filter(array_map('strval', $groups)));
        $db->table('auth_groups_users')->where('user_id', $user->id)->delete();

        foreach ($groups as $group) {
            $user->addGroup($group);
        }
    }

    private function syncCompanyAccess(int $userId, array $companyIds, int $defaultCompanyId): void
    {
        $db = Database::connect();
        $db->table('user_company_access')->where('user_id', $userId)->delete();

        $companyIds = array_values(array_unique(array_map('intval', $companyIds)));
        if ($defaultCompanyId > 0 && ! in_array($defaultCompanyId, $companyIds, true)) {
            $companyIds[] = $defaultCompanyId;
        }

        foreach ($companyIds as $companyId) {
            if ($companyId < 1) {
                continue;
            }

            $db->table('user_company_access')->insert([
                'user_id' => $userId,
                'company_id' => $companyId,
                'is_default' => $companyId === $defaultCompanyId ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function syncSiteAccess(int $userId, array $siteIds, int $defaultCompanyId, int $defaultSiteId): void
    {
        $db = Database::connect();
        $db->table('user_site_access')->where('user_id', $userId)->delete();

        $siteIds = array_values(array_unique(array_map('intval', $siteIds)));
        if ($defaultSiteId > 0 && ! in_array($defaultSiteId, $siteIds, true)) {
            $siteIds[] = $defaultSiteId;
        }

        foreach ($siteIds as $siteId) {
            if ($siteId < 1) {
                continue;
            }

            $site = $db->table('sites')->where('id', $siteId)->get()->getRowArray();
            if ($site === null) {
                continue;
            }

            $db->table('user_site_access')->insert([
                'user_id' => $userId,
                'company_id' => (int) ($site['company_id'] ?? $defaultCompanyId),
                'site_id' => $siteId,
                'is_default' => $siteId === $defaultSiteId ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function firstDefault(array $rows, string $field): ?int
    {
        foreach ($rows as $row) {
            if ((int) ($row['is_default'] ?? 0) === 1) {
                return (int) $row[$field];
            }
        }

        return isset($rows[0][$field]) ? (int) $rows[0][$field] : null;
    }

    private function authorize(string $permission): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function audit(string $action, int $userId, string $email, array $values = []): void
    {
        (new AuditLogService())->log('admin.users', $action, [
            'table_name' => 'users',
            'record_id' => $userId,
            'record_code' => $email,
            'description' => 'User management action: ' . $action,
            'new_values' => $values + ['email' => $email],
        ]);
    }
}
