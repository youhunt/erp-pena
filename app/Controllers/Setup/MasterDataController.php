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
    private array $resources;

    public function __construct()
    {
        $this->resources = [
            'transaction-codes' => $this->setupResource('Transaction Codes', TransactionCodeModel::class, 'transaction_codes', true, false),
            'prefix-codes' => $this->setupResource('Prefix Codes', PrefixCodeModel::class, 'prefix_codes', true, false),
            'currencies' => $this->globalResource('Currencies', CurrencyModel::class, 'currencies', $this->fields(['code' => 'Code*', 'name' => 'Name*', 'rounding' => 'Rounding#', 'is_active' => 'Active!'])),
            'companies' => $this->globalResource('Companies', CompanyModel::class, 'companies', $this->fields(['code' => 'Code*', 'name' => 'Name*', 'legal_name' => 'Legal Name', 'tax_number' => 'Tax Number', 'base_currency' => 'Base Currency', 'address' => 'Address~', 'is_active' => 'Active!'])),
            'sites' => $this->tenantResource('Sites / Branches', SiteModel::class, 'sites', false, $this->fields(['code' => 'Code*', 'name' => 'Name*', 'address' => 'Address~', 'is_active' => 'Active!'])),
            'departments' => $this->tenantResource('Departments', DepartmentModel::class, 'departments', true, $this->simpleFields()),
            'warehouses' => $this->tenantResource('Warehouses', WarehouseModel::class, 'warehouses', true, $this->simpleFields()),
            'locations' => $this->tenantResource('Locations', LocationModel::class, 'locations', true, ['warehouse_id' => ['label' => 'Warehouse', 'type' => 'select', 'required' => true, 'options_source' => 'warehouses']] + $this->simpleFields()),
            'countries' => $this->globalResource('Countries', CountryModel::class, 'countries', $this->fields(['code' => 'Code*', 'name' => 'Name*', 'is_active' => 'Active!'])),
            'provinces' => $this->globalResource('Provinces', ProvinceModel::class, 'provinces', ['parent_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries']] + $this->fields(['code' => 'Code*', 'name' => 'Name*', 'is_active' => 'Active!'])) + ['sync_action' => 'setup/provinces/sync'],
            'cities' => $this->globalResource('Cities', CityModel::class, 'cities', ['parent_id' => ['label' => 'Province', 'type' => 'select', 'required' => true, 'options_source' => 'provinces']] + $this->fields(['code' => 'Code*', 'name' => 'Name*', 'is_active' => 'Active!'])) + ['sync_action' => 'setup/cities/sync'],
            'postal-codes' => $this->globalResource('Postal Codes', PostalCodeModel::class, 'postal_codes', [
                'country_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
                'province_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces'],
                'city_id' => ['label' => 'City', 'type' => 'select', 'options_source' => 'cities', 'depends_on' => 'province_id', 'options_endpoint' => 'setup/options/cities'],
            ] + $this->fields(['code' => 'Postal Code*', 'name' => 'Area Name*', 'district' => 'District', 'village' => 'Village', 'is_active' => 'Active!'])),
            'uoms' => $this->tenantResource('Units of Measure', UomModel::class, 'uoms', false, $this->fields(['code' => 'Code*', 'name' => 'Name*', 'description' => 'Description~', 'is_active' => 'Active!'])),
            'uom-conversions' => $this->tenantResource('UoM Conversions', UomConversionModel::class, 'uom_conversions', false, [
                'from_uom_id' => ['label' => 'From UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true],
                'to_uom_id' => ['label' => 'To UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true],
            ] + $this->fields(['multiplier' => 'Multiplier#', 'divider' => 'Divider#', 'is_active' => 'Active!'])) + ['display' => ['code' => 'from_uom_id', 'name' => 'to_uom_id', 'description' => 'multiplier'], 'order_by' => 'id'],
            'vat' => $this->setupResource('VAT', VatRateModel::class, 'vat_rates', true, false, true),
            'wht' => $this->setupResource('WHT / PPH', WithholdingTaxRateModel::class, 'wht_rates', true, false, true),
            'item-vat' => $this->tenantResource('Item VAT', ItemVatRateModel::class, 'item_vat_rates', false, [
                'item_id' => ['label' => 'Item', 'type' => 'select', 'options_source' => 'items', 'required' => true],
                'vat_rate_id' => ['label' => 'VAT', 'type' => 'select', 'options_source' => 'vat_rates', 'required' => true],
                'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
            ]) + ['display' => ['code' => 'item_id', 'name' => 'vat_rate_id'], 'order_by' => 'id'],
            'address-master' => $this->tenantResource('Address Master', AddressModel::class, 'addresses', true, $this->addressFields()) + ['list_fields' => ['code', 'name', 'address_type', 'owner_type', 'address_line1', 'phone', 'mobile']],
            'customers' => $this->tenantResource('Customers', CustomerModel::class, 'customers', true, $this->customerFields(), 'sales.customer.view', 'sales.customer.manage') + ['display' => ['code' => 'customer', 'name' => 'customern', 'description' => 'officecity'], 'list_fields' => ['customer', 'customern', 'customerr', 'contactnar', 'officecity', 'officephon'], 'order_by' => 'customer'],
            'suppliers' => $this->tenantResource('Suppliers', SupplierModel::class, 'suppliers', true, $this->supplierFields(), 'purchase.supplier.view', 'purchase.supplier.manage') + ['display' => ['code' => 'supplier', 'name' => 'supplierna', 'description' => 'officecity'], 'list_fields' => ['supplier', 'supplierna', 'supplierref', 'contactnar', 'officecity', 'officephon'], 'order_by' => 'supplier'],
            'items' => $this->tenantResource('Items', ItemModel::class, 'items', true, $this->itemFields(), 'inventory.item.view', 'inventory.item.manage') + ['display' => ['code' => 'item_code', 'name' => 'item_name', 'description' => 'stockuom'], 'order_by' => 'item_code'],
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

        return view('setup/master/form', [
            'title' => 'Create ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => [],
            'addressTemplates' => $this->addressTemplates($resource),
        ]);
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
        $id = $this->model($config)->insert($payload, true);
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

        return view('setup/master/form', [
            'title' => 'Edit ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => $row,
            'addressTemplates' => $this->addressTemplates($resource),
        ]);
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

    public function cityOptions()
    {
        if (! $this->can('setup.master.view')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $provinceId = (int) $this->request->getGet('province_id');
        $model = new CityModel();

        if ($provinceId > 0) {
            $model->where('parent_id', $provinceId);
        }

        $options = [];
        foreach ($model->where('is_active', 1)->orderBy('name', 'ASC')->findAll() as $row) {
            $options[] = [
                'value' => (string) $row['id'],
                'label' => trim(($row['code'] ?? $row['id']) . ' - ' . ($row['name'] ?? '')),
            ];
        }

        return $this->response->setJSON($options);
    }

    private function setupResource(string $title, string $model, string $table, bool $tenant, bool $site, bool $withRate = false): array
    {
        $fields = $this->fields(['code' => 'Code*', 'name' => 'Name*']);
        if ($withRate) {
            $fields['rate'] = ['label' => 'Rate (%)', 'type' => 'number', 'default' => 0];
        }
        $fields += $this->fields(['description' => 'Description~', 'is_active' => 'Active!']);

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
            if (($field['persist'] ?? true) === false) {
                continue;
            }

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
            $payload += ['code' => $payload['item_code'] ?? null, 'name' => $payload['item_name'] ?? null, 'is_active' => $payload['active'] ?? 1];
        }
        if ($config['table'] === 'customers') {
            $payload += [
                'code' => $payload['customer'] ?? null,
                'name' => $payload['customern'] ?? null,
                'terms_code' => $payload['terms'] ?? null,
                'tax_number' => $payload['taxnumber'] ?? null,
                'address' => $payload['officeaddre'] ?? null,
                'phone' => $payload['officephon'] ?? null,
                'email' => $payload['email'] ?? null,
                'is_active' => $payload['active'] ?? 1,
            ];
        }
        if ($config['table'] === 'suppliers') {
            $payload += [
                'code' => $payload['supplier'] ?? null,
                'name' => $payload['supplierna'] ?? null,
                'terms_code' => $payload['terms'] ?? null,
                'tax_number' => $payload['taxnumber'] ?? null,
                'address' => $payload['officeaddre'] ?? null,
                'phone' => $payload['officephon'] ?? null,
                'email' => $payload['email'] ?? null,
                'is_active' => $payload['active'] ?? 1,
            ];
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
            'record_code' => $record['item_code'] ?? $record['customer'] ?? $record['supplier'] ?? $record['code'] ?? $record['name'] ?? null,
            'description' => $config['title'] . ' ' . str_replace('master.', '', $action),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $name => $field) {
            if (($field['persist'] ?? true) === false) {
                continue;
            }

            $type = $field['type'] ?? 'text';
            $rule = ! empty($field['required']) ? 'required' : 'permit_empty';
            $rule .= in_array($type, ['number', 'select', 'checkbox'], true) ? '' : '|max_length[500]';
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
                $valueField = $field['option_value'] ?? 'id';
                $config['fields'][$name]['options'] = ['' => 'Select ' . $field['label']] + $this->optionsFor($field['options_source'], $valueField);
                continue;
            }
            $config['fields'][$name]['options'] ??= ['' => 'Select ' . $field['label']];
        }

        return $config;
    }

    private function addressTemplates(string $resource): array
    {
        if (! in_array($resource, ['customers', 'suppliers'], true)) {
            return [];
        }

        $tenant = new TenantContext(session());
        $builder = db_connect()->table('addresses a')
            ->select('a.id, a.code, a.name, a.address_type, a.address_line1, a.address_line2, a.contact_name, a.phone, a.mobile, a.email')
            ->select('co.name AS country_name, p.name AS province_name, c.name AS city_name, pc.code AS postal_code')
            ->join('countries co', 'co.id = a.country_id', 'left')
            ->join('provinces p', 'p.id = a.province_id', 'left')
            ->join('cities c', 'c.id = a.city_id', 'left')
            ->join('postal_codes pc', 'pc.id = a.postal_code_id', 'left')
            ->where('a.deleted_at', null)
            ->where('a.is_active', 1)
            ->groupStart()
                ->where('a.owner_type', null)
                ->orWhere('a.owner_type', '')
                ->orWhere('a.owner_type', $resource === 'customers' ? 'customer' : 'supplier')
                ->orWhere('a.owner_type', 'company')
            ->groupEnd();

        if ($tenant->activeCompanyId() !== null) {
            $builder->groupStart()
                ->where('a.company_id', $tenant->activeCompanyId())
                ->orWhere('a.company_id', null)
                ->groupEnd();
        }

        if ($tenant->activeSiteId() !== null) {
            $builder->groupStart()
                ->where('a.site_id', $tenant->activeSiteId())
                ->orWhere('a.site_id', null)
                ->groupEnd();
        }

        $templates = [];
        foreach ($builder->orderBy('a.code', 'ASC')->get()->getResultArray() as $row) {
            $address = trim((string) ($row['address_line1'] ?? ''));
            $address2 = trim((string) ($row['address_line2'] ?? ''));
            if ($address2 !== '') {
                $address = trim($address . "\n" . $address2);
            }

            $templates[(string) $row['id']] = [
                'label' => trim(($row['code'] ?? $row['id']) . ' - ' . ($row['name'] ?? '')),
                'address' => $address,
                'city' => (string) ($row['city_name'] ?? ''),
                'province' => (string) ($row['province_name'] ?? ''),
                'country' => (string) ($row['country_name'] ?? ''),
                'postal_code' => (string) ($row['postal_code'] ?? ''),
                'contact' => (string) ($row['contact_name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'mobile' => (string) ($row['mobile'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }

        return $templates;
    }

    private function optionsFor(string $source, string $valueField = 'id'): array
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
            'addresses' => [AddressModel::class, true, true],
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
            $value = $valueField === 'code' ? (string) $code : (string) $row['id'];
            $options[$value] = trim($code . ' - ' . $name);
        }

        return $options;
    }

    private function hasRequiredTenant(array $config): bool
    {
        return ! $config['tenant'] || (new TenantContext(session()))->activeCompanyId() !== null;
    }

    private function fields(array $defs): array
    {
        $fields = [];
        foreach ($defs as $name => $label) {
            $value = (string) $label;
            $fields[$name] = [
                'label' => trim($value, '*#!~'),
                'type' => str_contains($value, '!') ? 'checkbox' : (str_contains($value, '#') ? 'number' : (str_contains($value, '~') ? 'textarea' : 'text')),
                'required' => str_contains($value, '*'),
                'default' => str_contains($value, '!') ? 1 : (str_contains($value, '#') ? 0 : null),
            ];
        }

        return $fields;
    }

    private function simpleFields(): array
    {
        return $this->fields(['code' => 'Code*', 'name' => 'Name*', 'description' => 'Description~', 'is_active' => 'Active!']);
    }

    private function uomCodeSelect(string $label): array
    {
        return ['label' => $label, 'type' => 'select', 'options_source' => 'uoms', 'option_value' => 'code'];
    }

    private function codeSelect(string $label, string $source): array
    {
        return ['label' => $label, 'type' => 'select', 'options_source' => $source, 'option_value' => 'code'];
    }

    private function addressFields(): array
    {
        return [
            'address_type' => ['label' => 'Address Type', 'type' => 'select', 'options' => ['general' => 'General', 'bill_to' => 'Bill To', 'ship_to' => 'Ship To', 'mail_to' => 'Mail To'], 'default' => 'general'],
            'owner_type' => ['label' => 'Owner Type', 'type' => 'select', 'options' => ['' => 'None', 'customer' => 'Customer', 'supplier' => 'Supplier', 'company' => 'Company', 'site' => 'Site']],
            'owner_code' => ['label' => 'Owner Code', 'type' => 'text'],
            'code' => ['label' => 'Address Code', 'type' => 'text', 'required' => true],
            'name' => ['label' => 'Address Name', 'type' => 'text', 'required' => true],
            'country_id' => ['label' => 'Country', 'type' => 'select', 'options_source' => 'countries'],
            'province_id' => ['label' => 'Province', 'type' => 'select', 'options_source' => 'provinces'],
            'city_id' => ['label' => 'City', 'type' => 'select', 'options_source' => 'cities', 'depends_on' => 'province_id', 'options_endpoint' => 'setup/options/cities'],
            'postal_code_id' => ['label' => 'Postal Code', 'type' => 'select', 'options_source' => 'postal_codes'],
            'address_line1' => ['label' => 'Address Line 1', 'type' => 'textarea'],
            'address_line2' => ['label' => 'Address Line 2', 'type' => 'textarea'],
            'contact_name' => ['label' => 'Contact Name', 'type' => 'text'],
            'phone' => ['label' => 'Phone', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile', 'type' => 'text'],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    private function customerFields(): array
    {
        $fields = $this->fields([
            'company' => 'Company Code',
            'site' => 'Site Code',
            'customer' => 'Customer Code*',
            'customern' => 'Customer Name*',
            'customerr' => 'Customer Reference',
            'contactnar' => 'Main Contact',
            'description' => 'Description~',
            'terms' => 'Terms',
            'taxcode' => 'Tax Code',
            'taxnumber' => 'Tax Number',
            'limitamound' => 'Credit Limit Amount#',
            'limitqty' => 'Credit Limit Qty#',
            'limitdays' => 'Credit Limit Days#',
            'salescode' => 'Sales Code',
            'salesname' => 'Sales Name',
            'email' => 'Main Email',
        ]);
        $fields['shipwhs'] = $this->codeSelect('Ship Warehouse', 'warehouses');
        $fields['vat'] = $this->codeSelect('VAT Code', 'vat_rates');
        $fields['office_address_template'] = $this->addressTemplateField('Use Office Address Template', $this->addressTargets('officeaddre', 'officecity', 'officeprovir', 'officecount', 'officeposta', 'officeconta', 'officephon', 'officehp', true));
        $fields += $this->fields(['officeaddre' => 'Office Address~', 'officecity' => 'Office City', 'officeprovir' => 'Office Province', 'officecount' => 'Office Country', 'officeposta' => 'Office Postal Code', 'officeconta' => 'Office Contact', 'officephon' => 'Office Phone', 'officehp' => 'Office Mobile']);
        $fields['billing_address_template'] = $this->addressTemplateField('Use Billing Address Template', $this->addressTargets('billingaddre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp'));
        $fields += $this->fields(['billingcust' => 'Billing Customer', 'billingtoc' => 'Billing To Code', 'billingaddre' => 'Billing Address~', 'billingcity' => 'Billing City', 'billingprovi' => 'Billing Province', 'billingcoun' => 'Billing Country', 'billingposta' => 'Billing Postal Code', 'billingconta' => 'Billing Contact', 'billingphon' => 'Billing Phone', 'billinghp' => 'Billing Mobile']);
        $fields['mail_address_template'] = $this->addressTemplateField('Use Mailing Address Template', $this->addressTargets('mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp'));
        $fields += $this->fields(['mailcustom' => 'Mail Customer', 'mailcode' => 'Mail Code', 'mailaddres' => 'Mailing Address~', 'mailcity' => 'Mailing City', 'mailprovin' => 'Mailing Province', 'mailcountr' => 'Mailing Country', 'mailpostal' => 'Mailing Postal Code', 'mailcontac' => 'Mailing Contact', 'mailphone' => 'Mailing Phone', 'mailhp' => 'Mailing Mobile']);
        $fields['ship_address_template'] = $this->addressTemplateField('Use Shipping Address Template', $this->addressTargets('shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocour', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp'));
        $fields += $this->fields(['shiptocust' => 'Ship To Customer', 'shiptocode' => 'Ship To Code', 'shiptoaddr' => 'Ship To Address~', 'shiptocity' => 'Ship To City', 'shiptoprovi' => 'Ship To Province', 'shiptocour' => 'Ship To Country', 'shiptopost' => 'Ship To Postal Code', 'shiptocont' => 'Ship To Contact', 'shiptophon' => 'Ship To Phone', 'shiptohp' => 'Ship To Mobile', 'bank1' => 'Bank 1', 'bankaccou' => 'Bank Account 1', 'bank2' => 'Bank 2', 'bankaccou2' => 'Bank Account 2', 'active' => 'Active!']);

        return $fields;
    }

    private function supplierFields(): array
    {
        $fields = $this->fields([
            'company' => 'Company Code',
            'site' => 'Site Code',
            'supplier' => 'Supplier Code*',
            'supplierna' => 'Supplier Name*',
            'supplierref' => 'Supplier Reference',
            'contactnar' => 'Main Contact',
            'description' => 'Description~',
            'terms' => 'Terms',
            'taxcode' => 'Tax Code',
            'taxnumber' => 'Tax Number',
            'limitamound' => 'Limit Amount#',
            'limitqty' => 'Limit Qty#',
            'limitdays' => 'Limit Days#',
            'employee' => 'Employee',
            'purchasing' => 'Purchasing',
            'email' => 'Main Email',
        ]);
        $fields['vat'] = $this->codeSelect('VAT Code', 'vat_rates');
        $fields['office_address_template'] = $this->addressTemplateField('Use Office Address Template', $this->addressTargets('officeaddre', 'officecity', 'officeprovir', 'officecoun', 'officeposta', 'officeconta', 'officephon', 'officehp', true));
        $fields += $this->fields(['officeaddre' => 'Office Address~', 'officecity' => 'Office City', 'officeprovir' => 'Office Province', 'officecoun' => 'Office Country', 'officeposta' => 'Office Postal Code', 'officeconta' => 'Office Contact', 'officephon' => 'Office Phone', 'officehp' => 'Office Mobile']);
        $fields['billing_address_template'] = $this->addressTemplateField('Use Billing Address Template', $this->addressTargets('billingadre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp'));
        $fields += $this->fields(['billingadre' => 'Billing Address~', 'billingcity' => 'Billing City', 'billingprovi' => 'Billing Province', 'billingcoun' => 'Billing Country', 'billingposta' => 'Billing Postal Code', 'billingconta' => 'Billing Contact', 'billingphon' => 'Billing Phone', 'billinghp' => 'Billing Mobile']);
        $fields['mail_address_template'] = $this->addressTemplateField('Use Mailing Address Template', $this->addressTargets('mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp'));
        $fields += $this->fields(['mailaddres' => 'Mailing Address~', 'mailcity' => 'Mailing City', 'mailprovin' => 'Mailing Province', 'mailcountr' => 'Mailing Country', 'mailpostal' => 'Mailing Postal Code', 'mailcontac' => 'Mailing Contact', 'mailphone' => 'Mailing Phone', 'mailhp' => 'Mailing Mobile']);
        $fields['ship_address_template'] = $this->addressTemplateField('Use Shipping Address Template', $this->addressTargets('shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocoun', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp'));
        $fields += $this->fields(['shiptoaddr' => 'Ship To Address~', 'shiptocity' => 'Ship To City', 'shiptoprovi' => 'Ship To Province', 'shiptocoun' => 'Ship To Country', 'shiptopost' => 'Ship To Postal Code', 'shiptocont' => 'Ship To Contact', 'shiptophon' => 'Ship To Phone', 'shiptohp' => 'Ship To Mobile', 'bank1' => 'Bank 1', 'bankaccou' => 'Bank Account 1', 'bank2' => 'Bank 2', 'bankaccou2' => 'Bank Account 2', 'active' => 'Active!']);

        return $fields;
    }

    private function addressTemplateField(string $label, array $targets): array
    {
        return ['label' => $label, 'type' => 'address_template', 'persist' => false, 'targets' => $targets];
    }

    private function addressTargets(string $address, string $city, string $province, string $country, string $postalCode, string $contact, string $phone, string $mobile, bool $alsoMain = false): array
    {
        $targets = ['address' => $address, 'city' => $city, 'province' => $province, 'country' => $country, 'postal_code' => $postalCode, 'contact' => $contact, 'phone' => $phone, 'mobile' => $mobile];

        if ($alsoMain) {
            $targets += ['main_address' => 'address', 'main_phone' => 'phone', 'main_email' => 'email'];
        }

        return $targets;
    }

    private function itemFields(): array
    {
        return $this->fields(['company' => 'Company Code', 'site' => 'Site Code', 'item_code' => 'Item Code*', 'item_name' => 'Item Name*', 'item_coded' => 'Item Code Detail', 'item_named' => 'Item Name Detail', 'shelf_life' => 'Shelf Life#'])
            + ['stockuom' => $this->uomCodeSelect('Stock UoM'), 'purchaseuom' => $this->uomCodeSelect('Purchase UoM'), 'sellinguom' => $this->uomCodeSelect('Selling UoM'), 'stockwhs' => $this->codeSelect('Warehouse', 'warehouses'), 'item_price' => ['label' => 'Stock Price', 'type' => 'number', 'default' => 0], 'purchasep' => ['label' => 'Purchase Price', 'type' => 'number', 'default' => 0], 'sellingprice' => ['label' => 'Selling Price', 'type' => 'number', 'default' => 0], 'vat' => $this->codeSelect('VAT Code', 'vat_rates')]
            + $this->fields(['item_length' => 'Item Length#', 'item_width' => 'Item Width#', 'item_heigh' => 'Item Height#', 'item_diam' => 'Item Diameter#'])
            + ['item_lengt' => $this->uomCodeSelect('UoM Length'), 'item_widthh' => $this->uomCodeSelect('UoM Width'), 'item_heigh_uom' => $this->uomCodeSelect('UoM Height'), 'item_diam_uom' => $this->uomCodeSelect('UoM Diameter')]
            + $this->fields(['out_length' => 'Outer Length#', 'out_width' => 'Outer Width#', 'out_height' => 'Outer Height#', 'out_diame' => 'Outer Diameter#'])
            + ['out_lengt' => $this->uomCodeSelect('Outer UoM Length'), 'out_widthh' => $this->uomCodeSelect('Outer UoM Width'), 'out_height_uom' => $this->uomCodeSelect('Outer UoM Height'), 'out_diame_uom' => $this->uomCodeSelect('Outer UoM Diameter')]
            + $this->fields(['item_group' => 'Group', 'item_subg' => 'SubGroup', 'item_class' => 'Class', 'item_subc' => 'Sub Class', 'item_type' => 'Type', 'item_subty' => 'Sub Type', 'item_atribu' => 'Attribute', 'active' => 'Active!']);
    }
}
