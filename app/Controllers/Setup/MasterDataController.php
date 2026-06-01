<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Models\CompanyModel;
use App\Models\CustomerModel;
use App\Models\DepartmentModel;
use App\Models\ItemModel;
use App\Models\SiteModel;
use App\Models\SupplierModel;
use App\Models\UomModel;
use App\Models\WarehouseModel;
use App\Services\TenantContext;
use CodeIgniter\Model;

class MasterDataController extends BaseController
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $resources;

    public function __construct()
    {
        $this->resources = [
        'companies' => [
            'title' => 'Companies',
            'model' => CompanyModel::class,
            'table' => 'companies',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => false,
            'site' => false,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'legal_name' => ['label' => 'Legal Name', 'type' => 'text'],
                'tax_number' => ['label' => 'Tax Number', 'type' => 'text'],
                'base_currency' => ['label' => 'Base Currency', 'type' => 'text', 'default' => 'IDR'],
                'address' => ['label' => 'Address', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'sites' => [
            'title' => 'Sites / Branches',
            'model' => SiteModel::class,
            'table' => 'sites',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => false,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'address' => ['label' => 'Address', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'departments' => [
            'title' => 'Departments',
            'model' => DepartmentModel::class,
            'table' => 'departments',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => true,
            'fields' => $this->simpleFields(),
        ],
        'warehouses' => [
            'title' => 'Warehouses',
            'model' => WarehouseModel::class,
            'table' => 'warehouses',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => true,
            'fields' => $this->simpleFields(),
        ],
        'uoms' => [
            'title' => 'Units of Measure',
            'model' => UomModel::class,
            'table' => 'uoms',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => false,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'customers' => [
            'title' => 'Customers',
            'model' => CustomerModel::class,
            'table' => 'customers',
            'view_permission' => 'sales.customer.view',
            'manage_permission' => 'sales.customer.manage',
            'tenant' => true,
            'site' => true,
            'fields' => $this->partnerFields(),
        ],
        'suppliers' => [
            'title' => 'Suppliers',
            'model' => SupplierModel::class,
            'table' => 'suppliers',
            'view_permission' => 'purchase.supplier.view',
            'manage_permission' => 'purchase.supplier.manage',
            'tenant' => true,
            'site' => true,
            'fields' => $this->partnerFields(),
        ],
        'items' => [
            'title' => 'Items',
            'model' => ItemModel::class,
            'table' => 'items',
            'view_permission' => 'inventory.item.view',
            'manage_permission' => 'inventory.item.manage',
            'tenant' => true,
            'site' => true,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'item_type' => ['label' => 'Item Type', 'type' => 'select', 'options' => ['stock' => 'Stock', 'service' => 'Service', 'asset' => 'Asset'], 'default' => 'stock'],
                'brand' => ['label' => 'Brand', 'type' => 'text'],
                'standard_cost' => ['label' => 'Standard Cost', 'type' => 'number', 'default' => 0],
                'sales_price' => ['label' => 'Sales Price', 'type' => 'number', 'default' => 0],
                'shelf_life_days' => ['label' => 'Shelf Life Days', 'type' => 'number'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        ];
    }

    public function index(string $resource): string
    {
        $config = $this->config($resource, 'view');
        $model = $this->model($config);

        return view('setup/master/index', [
            'title' => $config['title'],
            'resource' => $resource,
            'config' => $config,
            'canManage' => auth()->user()?->can($config['manage_permission']) ?? false,
            'rows' => $this->scope($model, $config)->orderBy('code', 'ASC')->findAll(),
        ]);
    }

    public function create(string $resource): string
    {
        $config = $this->config($resource, 'manage');

        return view('setup/master/form', [
            'title' => 'Create ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => [],
        ]);
    }

    public function store(string $resource)
    {
        $config = $this->config($resource, 'manage');
        $data = $this->payload($config, true);

        if (! $this->hasRequiredTenant($config)) {
            return redirect()->back()->withInput()->with('error', 'Active company is required for this master data.');
        }

        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $this->model($config)->insert($data);

        return redirect()->to("setup/{$resource}")->with('message', $config['title'] . ' created.');
    }

    public function edit(string $resource, int $id): string
    {
        $config = $this->config($resource, 'manage');
        $row = $this->scope($this->model($config), $config)->find($id);

        if ($row === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('setup/master/form', [
            'title' => 'Edit ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => $row,
        ]);
    }

    public function update(string $resource, int $id)
    {
        $config = $this->config($resource, 'manage');

        if (! $this->hasRequiredTenant($config)) {
            return redirect()->back()->withInput()->with('error', 'Active company is required for this master data.');
        }

        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model = $this->scope($this->model($config), $config);
        if ($model->find($id) === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->update($id, $this->payload($config, false));

        return redirect()->to("setup/{$resource}")->with('message', $config['title'] . ' updated.');
    }

    public function delete(string $resource, int $id)
    {
        $config = $this->config($resource, 'manage');
        $model = $this->scope($this->model($config), $config);

        if ($model->find($id) === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);

        return redirect()->to("setup/{$resource}")->with('message', $config['title'] . ' deleted.');
    }

    private function config(string $resource, string $mode): array
    {
        if (! isset($this->resources[$resource])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $config = $this->resources[$resource];
        $permission = $mode === 'view' ? $config['view_permission'] : $config['manage_permission'];
        if (! auth()->user()?->can($permission)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $config;
    }

    private function model(array $config): Model
    {
        return new $config['model']();
    }

    private function scope(Model $model, array $config): Model
    {
        $tenant = new TenantContext(session());

        if ($config['tenant'] && $tenant->activeCompanyId() !== null) {
            $model->where($config['table'] . '.company_id', $tenant->activeCompanyId());
        }

        if ($config['site'] && $tenant->activeSiteId() !== null) {
            $model->where($config['table'] . '.site_id', $tenant->activeSiteId());
        }

        return $model;
    }

    private function payload(array $config, bool $isCreate): array
    {
        $payload = [];
        foreach ($config['fields'] as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $payload[$name] = $this->request->getPost($name) ? 1 : 0;
                continue;
            }

            $value = $this->request->getPost($name);
            if ($field['type'] === 'number' && $value === '') {
                $value = $field['default'] ?? null;
            }

            $payload[$name] = $value;
        }

        $tenant = new TenantContext(session());
        if ($config['tenant']) {
            $payload['company_id'] = $tenant->activeCompanyId();
        }

        if ($config['site']) {
            $payload['site_id'] = $tenant->activeSiteId();
        }

        $payload['updated_by'] = auth()->id();
        if ($isCreate) {
            $payload['created_by'] = auth()->id();
        }

        return $payload;
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $name => $field) {
            $rules[$name] = ! empty($field['required']) ? 'required|max_length:255' : 'permit_empty|max_length:500';
        }

        return $rules;
    }

    private function hasRequiredTenant(array $config): bool
    {
        return ! $config['tenant'] || (new TenantContext(session()))->activeCompanyId() !== null;
    }

    private function simpleFields(): array
    {
        return [
            'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
            'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            'description' => ['label' => 'Description', 'type' => 'textarea'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    private function partnerFields(): array
    {
        return [
            'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
            'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            'terms_code' => ['label' => 'Terms Code', 'type' => 'text'],
            'currency_code' => ['label' => 'Currency', 'type' => 'text', 'default' => 'IDR'],
            'tax_number' => ['label' => 'Tax Number', 'type' => 'text'],
            'phone' => ['label' => 'Phone', 'type' => 'text'],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'address' => ['label' => 'Address', 'type' => 'textarea'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }
}
