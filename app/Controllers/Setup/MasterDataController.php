<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Models\AddressModel;
use App\Models\CityModel;
use App\Models\CompanyModel;
use App\Models\CountryModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\DepartmentModel;
use App\Models\ItemModel;
use App\Models\ItemVatRateModel;
use App\Models\LocationModel;
use App\Models\PostalCodeModel;
use App\Models\PrefixCodeModel;
use App\Models\ProvinceModel;
use App\Models\SiteModel;
use App\Models\SupplierModel;
use App\Models\TransactionCodeModel;
use App\Models\UomConversionModel;
use App\Models\UomModel;
use App\Models\VatRateModel;
use App\Models\WarehouseModel;
use App\Models\WithholdingTaxRateModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;

class MasterDataController extends BaseController
{
    /** @var array<string, array<string, mixed>> */
    private array $resources;

    public function __construct()
    {
        $this->resources = [
            'transaction-codes' => $this->setupResource('Transaction Codes', TransactionCodeModel::class, 'transaction_codes', true, false),
            'prefix-codes' => $this->setupResource('Prefix Codes', PrefixCodeModel::class, 'prefix_codes', true, false),
            'currencies' => $this->globalResource('Currencies', CurrencyModel::class, 'currencies', [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'rounding' => ['label' => 'Rounding', 'type' => 'number', 'default' => 0],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'companies' => $this->globalResource('Companies', CompanyModel::class, 'companies', [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'legal_name' => ['label' => 'Legal Name', 'type' => 'text'],
                'tax_number' => ['label' => 'Tax Number', 'type' => 'text'],
                'base_currency' => ['label' => 'Base Currency', 'type' => 'text', 'default' => 'IDR'],
                'address' => ['label' => 'Address', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'sites' => $this->tenantResource('Sites / Branches', SiteModel::class, 'sites', false, [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'address' => ['label' => 'Address', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'departments' => $this->tenantResource('Departments', DepartmentModel::class, 'departments', true, $this->simpleFields()),
            'warehouses' => $this->tenantResource('Warehouses', WarehouseModel::class, 'warehouses', true, $this->simpleFields()),
            'locations' => $this->tenantResource('Locations', LocationModel::class, 'locations', true, [
                'warehouse_id' => ['label' => 'Warehouse', 'type' => 'select', 'required' => true, 'options_source' => 'warehouses'],
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'countries' => $this->globalResource('Countries', CountryModel::class, 'countries', [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'provinces' => $this->globalResource('Provinces', ProvinceModel::class, 'provinces', [
                'parent_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]) + ['sync_action' => 'setup/provinces/sync'],
            'cities' => $this->globalResource('Cities', CityModel::class, 'cities', [
                'parent_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces', 'required' => true],
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]) + ['sync_action' => 'setup/cities/sync'],
            'postal-codes' => $this->globalResource('Postal Codes', PostalCodeModel::class, 'postal_codes', [
                'country_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
                'province_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces'],
                'city_id' => ['label' => 'City', 'type' => 'select', 'options_source' => 'cities'],
                'code' => ['label' => 'Postal Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Area Name', 'type' => 'text', 'required' => true],
                'district' => ['label' => 'District', 'type' => 'text'],
                'village' => ['label' => 'Village', 'type' => 'text'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'uoms' => $this->tenantResource('Units of Measure', UomModel::class, 'uoms', false, [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]),
            'uom-conversions' => $this->tenantResource('UoM Conversions', UomConversionModel::class, 'uom_conversions', false, [
                'from_uom_id' => ['label' => 'From UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true],
                'to_uom_id' => ['label' => 'To UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true],
                'multiplier' => ['label' => 'Multiplier', 'type' => 'number', 'default' => 1],
                'divider' => ['label' => 'Divider', 'type' => 'number', 'default' => 1],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]) + ['display' => ['code' => 'from_uom_id', 'name' => 'to_uom_id', 'description' => 'multiplier'], 'order_by' => 'id'],
            'vat' => $this->setupResource('VAT', VatRateModel::class, 'vat_rates', true, false, true),
            'wht' => $this->setupResource('WHT / PPH', WithholdingTaxRateModel::class, 'wht_rates', true, false, true),
            'item-vat' => $this->tenantResource('Item VAT', ItemVatRateModel::class, 'item_vat_rates', false, [
                'item_id' => ['label' => 'Item', 'type' => 'select', 'options_source' => 'items', 'required' => true],
                'vat_rate_id' => ['label' => 'VAT', 'type' => 'select', 'options_source' => 'vat_rates', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]) + ['display' => ['code' => 'item_id', 'name' => 'vat_rate_id'], 'order_by' => 'id'],
            'address-master' => $this->tenantResource('Address Master', AddressModel::class, 'addresses', true, [
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
            ]),
            'customers' => $this->tenantResource('Customers', CustomerModel::class, 'customers', true, $this->partnerFields(), 'sales.customer.view', 'sales.customer.manage'),
            'suppliers' => $this->tenantResource('Suppliers', SupplierModel::class, 'suppliers', true, $this->partnerFields(), 'purchase.supplier.view', 'purchase.supplier.manage'),
            'items' => $this->tenantResource('Items', ItemModel::class, 'items', true, $this->itemFields(), 'inventory.item.view', 'inventory.item.manage')
                + ['display' => ['code' => 'item_code', 'name' => 'item_name', 'description' => 'stockuom'], 'order_by' => 'item_code'],
        ];
    }

    public function index(string $resource): string
    {
        $config = $this->config($resource, 'view');
        return view('setup/master/index', [
            'title' => $config['title'],
            'resource' => $resource,
            'config' => $config,
            'canManage' => $this->can($config['manage_permission']),
            'display' => $config['display'] ?? ['code' => 'code', 'name' => 'name', 'description' => 'description'],
            'rows' => $this->scope($this->model($config), $config)->orderBy($config['order_by'] ?? 'code', 'ASC')->findAll(),
        ]);
    }

    public function create(string $resource): string
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        return view('setup/master/form', ['title' => 'Create ' . $config['title'], 'resource' => $resource, 'config' => $config, 'row' => []]);
    }

    public function store(string $resource)
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        if (! $this->hasRequiredTenant($config)) {
            return redirect()->back()->withInput()->with('error', 'Active company is required for this master data.');
        }
        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = $this->payload($config, true);
        $model = $this->model($config);
        $id = $model->insert($payload, true);
        $this->audit('master.create', $config, (int) $id, null, $payload);

        return redirect()->to(site_url("setup/{$resource}"))->with('message', $config['title'] . ' created.');
    }

    public function edit(string $resource, int $id): string
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        $row = $this->scope($this->model($config), $config)->find($id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        return view('setup/master/form', ['title' => 'Edit ' . $config['title'], 'resource' => $resource, 'config' => $config, 'row' => $row]);
    }

    public function update(string $resource, int $id)
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        if (! $this->hasRequiredTenant($config)) {
            return redirect()->back()->withInput()->with('error', 'Active company is required for this master data.');
        }
        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model = $this->scope($this->model($config), $config);
        $old = $model->find($id);
        if ($old === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $payload = $this->payload($config, false);
        $model->update($id, $payload);
        $this->audit('master.update', $config, $id, $old, $payload);

        return redirect()->to(site_url("setup/{$resource}"))->with('message', $config['title'] . ' updated.');
    }

    public function delete(string $resource, int $id)
    {
        $config = $this->config($resource, 'manage');
        $model = $this->scope($this->model($config), $config);
        $old = $model->find($id);
        if ($old === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        $this->audit('master.delete', $config, $id, $old, null);

        return redirect()->to(site_url("setup/{$resource}"))->with('message', $config['title'] . ' deleted.');
    }

    private function setupResource(string $title, string $model, string $table, bool $tenant, bool $site, bool $withRate = false): array
    {
        $fields = ['code' => ['label' => 'Code', 'type' => 'text', 'required' => true], 'name' => ['label' => 'Name', 'type' => 'text', 'required' => true]];
        if ($withRate) {
            $fields['rate'] = ['label' => 'Rate (%)', 'type' => 'number', 'default' => 0];
        }
        $fields += ['description' => ['label' => 'Description', 'type' => 'textarea'], 'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1]];
        return ['title' => $title, 'model' => $model, 'table' => $table, 'view_permission' => 'setup.master.view', 'manage_permission' => 'setup.master.manage', 'tenant' => $tenant, 'site' => $site, 'fields' => $fields];
    }

    private function globalResource(string $title, string $model, string $table, array $fields): array
    {
        return ['title' => $title, 'model' => $model, 'table' => $table, 'view_permission' => 'setup.master.view', 'manage_permission' => 'setup.master.manage', 'tenant' => false, 'site' => false, 'fields' => $fields];
    }

    private function tenantResource(string $title, string $model, string $table, bool $site, array $fields, string $viewPermission = 'setup.master.view', string $managePermission = 'setup.master.manage'): array
    {
        return ['title' => $title, 'model' => $model, 'table' => $table, 'view_permission' => $viewPermission, 'manage_permission' => $managePermission, 'tenant' => true, 'site' => $site, 'fields' => $fields];
    }

    private function config(string $resource, string $mode): array
    {
        if (! isset($this->resources[$resource])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $config = $this->resources[$resource];
        $permission = $mode === 'view' ? $config['view_permission'] : $config['manage_permission'];
        if (! $this->can($permission)) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $config;
    }

    private function can(string $permission): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->can($permission) || $user->inGroup('superadmin'));
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
            if (($field['type'] ?? 'text') === 'checkbox') {
                $payload[$name] = $this->request->getPost($name) ? 1 : 0;
                continue;
            }
            $value = $this->request->getPost($name);
            if (($field['type'] ?? 'text') === 'number' && $value === '') {
                $value = $field['default'] ?? null;
            }
            if (($field['type'] ?? 'text') === 'select' && isset($field['options_source']) && $value === '') {
                $value = null;
            }
            $payload[$name] = $value;
        }

        if ($config['table'] === 'items') {
            $payload['code'] = $payload['item_code'] ?? null;
            $payload['name'] = $payload['item_name'] ?? null;
            $payload['is_active'] = $payload['active'] ?? 1;
        }

        $tenant = new TenantContext(session());
        if ($config['tenant']) {
            $payload['company_id'] = $tenant->activeCompanyId();
        }
        if ($config['site']) {
            $payload['site_id'] = $tenant->activeSiteId();
        }
        $payload['updated_by'] = (string) auth()->id();
        if ($isCreate) {
            $payload['created_by'] = (string) auth()->id();
        }
        return $payload;
    }

    private function audit(string $action, array $config, int $id, ?array $oldValues, ?array $newValues): void
    {
        $record = $newValues ?? $oldValues ?? [];
        (new AuditLogService())->log('setup.master', $action, [
            'company_id' => $record['company_id'] ?? null,
            'site_id' => $record['site_id'] ?? null,
            'table_name' => $config['table'],
            'record_id' => $id,
            'record_code' => $record['item_code'] ?? $record['code'] ?? $record['name'] ?? null,
            'description' => $config['title'] . ' ' . str_replace('master.', '', $action),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $name => $field) {
            $type = $field['type'] ?? 'text';
            $rule = ! empty($field['required']) ? 'required' : 'permit_empty';
            $rule .= in_array($type, ['number', 'select', 'checkbox'], true) ? '' : '|max_length:500';
            $rules[$name] = $rule;
        }
        return $rules;
    }

    private function hydrateOptions(array $config): array
    {
        foreach ($config['fields'] as $name => $field) {
            if (($field['type'] ?? '') !== 'select') {
                continue;
            }
            if (! empty($field['options_source'])) {
                $config['fields'][$name]['options'] = ['' => 'Select ' . $field['label']] + $this->optionsFor($field['options_source']);
                continue;
            }
            $config['fields'][$name]['options'] ??= ['' => 'Select ' . $field['label']];
        }
        return $config;
    }

    /** @return array<string, string> */
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
            $code = $row['item_code'] ?? $row['code'] ?? $row['id'];
            $name = $row['item_name'] ?? $row['name'] ?? '';
            $options[(string) $row['id']] = trim($code . ' - ' . $name);
        }
        return $options;
    }

    private function hasRequiredTenant(array $config): bool
    {
        return ! $config['tenant'] || (new TenantContext(session()))->activeCompanyId() !== null;
    }

    private function simpleFields(): array
    {
        return ['code' => ['label' => 'Code', 'type' => 'text', 'required' => true], 'name' => ['label' => 'Name', 'type' => 'text', 'required' => true], 'description' => ['label' => 'Description', 'type' => 'textarea'], 'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1]];
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

    private function itemFields(): array
    {
        return [
            'company' => ['label' => 'Company Code', 'type' => 'text'],
            'site' => ['label' => 'Site Code', 'type' => 'text'],
            'item_code' => ['label' => 'Item Code', 'type' => 'text', 'required' => true],
            'item_name' => ['label' => 'Item Name', 'type' => 'text', 'required' => true],
            'item_coded' => ['label' => 'Item Code Detail', 'type' => 'text'],
            'item_named' => ['label' => 'Item Name Detail', 'type' => 'text'],
            'shelf_life' => ['label' => 'Shelf Life', 'type' => 'number', 'default' => 0],
            'stockuom' => ['label' => 'Stock UoM', 'type' => 'text'],
            'purchaseuom' => ['label' => 'Purchase UoM', 'type' => 'text'],
            'sellinguom' => ['label' => 'Selling UoM', 'type' => 'text'],
            'stockwhs' => ['label' => 'Warehouse', 'type' => 'text'],
            'item_price' => ['label' => 'Stock Price', 'type' => 'number', 'default' => 0],
            'purchasep' => ['label' => 'Purchase Price', 'type' => 'number', 'default' => 0],
            'sellingprice' => ['label' => 'Selling Price', 'type' => 'number', 'default' => 0],
            'vat' => ['label' => 'VAT Code', 'type' => 'text'],
            'item_length' => ['label' => 'Item Length', 'type' => 'number', 'default' => 0],
            'item_width' => ['label' => 'Item Width', 'type' => 'number', 'default' => 0],
            'item_heigh' => ['label' => 'Item Height', 'type' => 'number', 'default' => 0],
            'item_diam' => ['label' => 'Item Diameter', 'type' => 'number', 'default' => 0],
            'item_lengt' => ['label' => 'UoM Length', 'type' => 'text'],
            'item_widthh' => ['label' => 'UoM Width', 'type' => 'text'],
            'item_heigh_uom' => ['label' => 'UoM Height', 'type' => 'text'],
            'item_diam_uom' => ['label' => 'UoM Diameter', 'type' => 'text'],
            'out_length' => ['label' => 'Outer Length', 'type' => 'number', 'default' => 0],
            'out_width' => ['label' => 'Outer Width', 'type' => 'number', 'default' => 0],
            'out_height' => ['label' => 'Outer Height', 'type' => 'number', 'default' => 0],
            'out_diame' => ['label' => 'Outer Diameter', 'type' => 'number', 'default' => 0],
            'out_lengt' => ['label' => 'Outer UoM Length', 'type' => 'text'],
            'out_widthh' => ['label' => 'Outer UoM Width', 'type' => 'text'],
            'out_height_uom' => ['label' => 'Outer UoM Height', 'type' => 'text'],
            'out_diame_uom' => ['label' => 'Outer UoM Diameter', 'type' => 'text'],
            'item_group' => ['label' => 'Group', 'type' => 'text'],
            'item_subg' => ['label' => 'SubGroup', 'type' => 'text'],
            'item_class' => ['label' => 'Class', 'type' => 'text'],
            'item_subc' => ['label' => 'Sub Class', 'type' => 'text'],
            'item_type' => ['label' => 'Type', 'type' => 'text'],
            'item_subty' => ['label' => 'Sub Type', 'type' => 'text'],
            'item_atribu' => ['label' => 'Attribute', 'type' => 'text'],
            'active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }
}
