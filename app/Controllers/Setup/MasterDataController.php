<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Models\AddressModel;
use App\Models\CityModel;
use App\Models\CompanyModel;
use App\Models\CountryModel;
use App\Models\CustomerModel;
use App\Models\DepartmentModel;
use App\Models\ItemModel;
use App\Models\ItemVatRateModel;
use App\Models\LocationModel;
use App\Models\PostalCodeModel;
use App\Models\ProvinceModel;
use App\Models\SiteModel;
use App\Models\SupplierModel;
use App\Models\TransactionCodeModel;
use App\Models\UomConversionModel;
use App\Models\UomModel;
use App\Models\VatRateModel;
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
        'transaction-codes' => [
            'title' => 'Transaction Codes',
            'model' => TransactionCodeModel::class,
            'table' => 'transaction_codes',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => false,
            'fields' => $this->setupCodeFields(),
        ],
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
        'locations' => [
            'title' => 'Locations',
            'model' => LocationModel::class,
            'table' => 'locations',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => true,
            'fields' => [
                'warehouse_id' => ['label' => 'Warehouse', 'type' => 'select', 'required' => true, 'options_source' => 'warehouses'],
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'countries' => [
            'title' => 'Countries',
            'model' => CountryModel::class,
            'table' => 'countries',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => false,
            'site' => false,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'provinces' => [
            'title' => 'Provinces',
            'model' => ProvinceModel::class,
            'table' => 'provinces',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => false,
            'site' => false,
            'sync_action' => 'setup/provinces/sync',
            'fields' => [
                'parent_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'cities' => [
            'title' => 'Cities',
            'model' => CityModel::class,
            'table' => 'cities',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => false,
            'site' => false,
            'sync_action' => 'setup/cities/sync',
            'fields' => [
                'parent_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces', 'required' => true],
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'postal-codes' => [
            'title' => 'Postal Codes',
            'model' => PostalCodeModel::class,
            'table' => 'postal_codes',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => false,
            'site' => false,
            'fields' => [
                'country_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
                'province_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces'],
                'city_id' => ['label' => 'City', 'type' => 'select', 'options_source' => 'cities'],
                'code' => ['label' => 'Postal Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Area Name', 'type' => 'text', 'required' => true],
                'district' => ['label' => 'District', 'type' => 'text'],
                'village' => ['label' => 'Village', 'type' => 'text'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
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
        'uom-conversions' => [
            'title' => 'UoM Conversions',
            'model' => UomConversionModel::class,
            'table' => 'uom_conversions',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => false,
            'fields' => [
                'from_uom_id' => ['label' => 'From UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true],
                'to_uom_id' => ['label' => 'To UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true],
                'multiplier' => ['label' => 'Multiplier', 'type' => 'number', 'default' => 1],
                'divider' => ['label' => 'Divider', 'type' => 'number', 'default' => 1],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
            'display' => ['code' => 'from_uom_id', 'name' => 'to_uom_id', 'description' => 'multiplier'],
            'order_by' => 'id',
        ],
        'vat' => [
            'title' => 'VAT',
            'model' => VatRateModel::class,
            'table' => 'vat_rates',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => false,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'rate' => ['label' => 'Rate (%)', 'type' => 'number', 'default' => 0],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'item-vat' => [
            'title' => 'Item VAT',
            'model' => ItemVatRateModel::class,
            'table' => 'item_vat_rates',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => false,
            'fields' => [
                'item_id' => ['label' => 'Item', 'type' => 'select', 'options_source' => 'items', 'required' => true],
                'vat_rate_id' => ['label' => 'VAT', 'type' => 'select', 'options_source' => 'vat_rates', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ],
            'display' => ['code' => 'item_id', 'name' => 'vat_rate_id'],
            'order_by' => 'id',
        ],
        'address-master' => [
            'title' => 'Address Master',
            'model' => AddressModel::class,
            'table' => 'addresses',
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
            'tenant' => true,
            'site' => true,
            'fields' => [
                'address_type' => ['label' => 'Address Type', 'type' => 'select', 'options' => ['general' => 'General', 'bill_to' => 'Bill To', 'ship_to' => 'Ship To', 'mail_to' => 'Mail To'], 'default' => 'general'],
                'owner_type' => ['label' => 'Owner Type', 'type' => 'select', 'options' => ['' => 'None', 'customer' => 'Customer', 'supplier' => 'Supplier', 'company' => 'Company', 'site' => 'Site']],
                'owner_code' => ['label' => 'Owner Code', 'type' => 'text'],
                'code' => ['label' => 'Address Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Address Name', 'type' => 'text', 'required' => true],
                'country_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
                'province_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces'],
                'city_id' => ['label' => 'City', 'type' => 'select', 'options_source' => 'cities'],
                'postal_code_id' => ['label' => 'Postal Code', 'type' => 'select', 'options_source' => 'postal_codes'],
                'address_line1' => ['label' => 'Address Line 1', 'type' => 'textarea'],
                'address_line2' => ['label' => 'Address Line 2', 'type' => 'textarea'],
                'phone' => ['label' => 'Phone', 'type' => 'text'],
                'email' => ['label' => 'Email', 'type' => 'email'],
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
            'display' => $config['display'] ?? ['code' => 'code', 'name' => 'name', 'description' => 'description'],
            'rows' => $this->scope($model, $config)->orderBy($config['order_by'] ?? 'code', 'ASC')->findAll(),
        ]);
    }

    public function create(string $resource): string
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));

        return view('setup/master/form', [
            'title' => 'Create ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => [],
        ]);
    }

    public function store(string $resource)
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
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
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
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
            if ($field['type'] === 'select' && isset($field['options_source']) && $value === '') {
                $value = null;
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

    private function hydrateOptions(array $config): array
    {
        foreach ($config['fields'] as $name => $field) {
            if (($field['type'] ?? '') !== 'select' || empty($field['options_source'])) {
                continue;
            }

            $config['fields'][$name]['options'] = ['' => 'Select ' . $field['label']] + $this->optionsFor($field['options_source']);
        }

        return $config;
    }

    /**
     * @return array<string, string>
     */
    private function optionsFor(string $source): array
    {
        $map = [
            'countries' => [CountryModel::class, false, false],
            'provinces' => [ProvinceModel::class, false, false],
            'cities' => [CityModel::class, false, false],
            'postal_codes' => [PostalCodeModel::class, false, false],
            'warehouses' => [WarehouseModel::class, true, true],
            'uoms' => [UomModel::class, true, false],
            'items' => [ItemModel::class, true, true],
            'vat_rates' => [VatRateModel::class, true, false],
        ];

        if (! isset($map[$source])) {
            return [];
        }

        [$class, $tenantScoped, $siteScoped] = $map[$source];
        $model = new $class();
        $tenant = new TenantContext(session());

        if ($tenantScoped && $tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        if ($siteScoped && $tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        $rows = $model->orderBy('code', 'ASC')->findAll();
        $options = [];

        foreach ($rows as $row) {
            $label = trim(($row['code'] ?? $row['id']) . ' - ' . ($row['name'] ?? ''));
            $options[(string) $row['id']] = $label;
        }

        return $options;
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

    private function setupCodeFields(): array
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
