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
            'postal-codes' => $this->globalResource('Postal Codes', PostalCodeModel::class, 'postal_codes', $this->fields(['country_id' => 'Country', 'province_id' => 'Province', 'city_id' => 'City', 'code' => 'Postal Code*', 'name' => 'Area Name*', 'district' => 'District', 'village' => 'Village', 'is_active' => 'Active!'])),
            'uoms' => $this->tenantResource('Units of Measure', UomModel::class, 'uoms', false, $this->fields(['code' => 'Code*', 'name' => 'Name*', 'description' => 'Description~', 'is_active' => 'Active!'])),
            'uom-conversions' => $this->tenantResource('UoM Conversions', UomConversionModel::class, 'uom_conversions', false, ['from_uom_id' => ['label' => 'From UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true], 'to_uom_id' => ['label' => 'To UoM', 'type' => 'select', 'options_source' => 'uoms', 'required' => true]] + $this->fields(['multiplier' => 'Multiplier#', 'divider' => 'Divider#', 'is_active' => 'Active!'])) + ['display' => ['code' => 'from_uom_id', 'name' => 'to_uom_id', 'description' => 'multiplier'], 'order_by' => 'id'],
            'vat' => $this->setupResource('VAT', VatRateModel::class, 'vat_rates', true, false, true),
            'wht' => $this->setupResource('WHT / PPH', WithholdingTaxRateModel::class, 'wht_rates', true, false, true),
            'item-vat' => $this->tenantResource('Item VAT', ItemVatRateModel::class, 'item_vat_rates', false, ['item_id' => ['label' => 'Item', 'type' => 'select', 'options_source' => 'items', 'required' => true], 'vat_rate_id' => ['label' => 'VAT', 'type' => 'select', 'options_source' => 'vat_rates', 'required' => true], 'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1]]) + ['display' => ['code' => 'item_id', 'name' => 'vat_rate_id'], 'order_by' => 'id'],
            'address-master' => $this->tenantResource('Address Master', AddressModel::class, 'addresses', true, $this->addressFields()),
            'customers' => $this->tenantResource('Customers', CustomerModel::class, 'customers', true, $this->customerFields(), 'sales.customer.view', 'sales.customer.manage') + ['display' => ['code' => 'customer', 'name' => 'customern', 'description' => 'officecity'], 'order_by' => 'customer'],
            'suppliers' => $this->tenantResource('Suppliers', SupplierModel::class, 'suppliers', true, $this->supplierFields(), 'purchase.supplier.view', 'purchase.supplier.manage') + ['display' => ['code' => 'supplier', 'name' => 'supplierna', 'description' => 'officecity'], 'order_by' => 'supplier'],
            'items' => $this->tenantResource('Items', ItemModel::class, 'items', true, $this->itemFields(), 'inventory.item.view', 'inventory.item.manage') + ['display' => ['code' => 'item_code', 'name' => 'item_name', 'description' => 'stockuom'], 'order_by' => 'item_code'],
        ];
    }

    public function index(string $resource): string
    {
        $config = $this->config($resource, 'view');
        return view('setup/master/index', ['title' => $config['title'], 'resource' => $resource, 'config' => $config, 'canManage' => $this->can($config['manage_permission']), 'display' => $config['display'] ?? ['code' => 'code', 'name' => 'name', 'description' => 'description'], 'rows' => $this->scope($this->model($config), $config)->orderBy($config['order_by'] ?? 'code', 'ASC')->findAll()]);
    }

    public function create(string $resource): string
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        return view('setup/master/form', ['title' => 'Create ' . $config['title'], 'resource' => $resource, 'config' => $config, 'row' => []]);
    }

    public function store(string $resource)
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        if (! $this->hasRequiredTenant($config)) return redirect()->back()->withInput()->with('error', 'Active company is required for this master data.');
        if (! $this->validate($this->rules($config))) return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        $payload = $this->payload($config, true);
        $id = $this->model($config)->insert($payload, true);
        $this->audit('master.create', $config, (int) $id, null, $payload);
        return redirect()->to(site_url("setup/{$resource}"))->with('message', $config['title'] . ' created.');
    }

    public function edit(string $resource, int $id): string
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        $row = $this->scope($this->model($config), $config)->find($id);
        if ($row === null) throw PageNotFoundException::forPageNotFound();
        return view('setup/master/form', ['title' => 'Edit ' . $config['title'], 'resource' => $resource, 'config' => $config, 'row' => $row]);
    }

    public function update(string $resource, int $id)
    {
        $config = $this->hydrateOptions($this->config($resource, 'manage'));
        if (! $this->hasRequiredTenant($config)) return redirect()->back()->withInput()->with('error', 'Active company is required for this master data.');
        if (! $this->validate($this->rules($config))) return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        $model = $this->scope($this->model($config), $config);
        $old = $model->find($id);
        if ($old === null) throw PageNotFoundException::forPageNotFound();
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
        if ($old === null) throw PageNotFoundException::forPageNotFound();
        $model->delete($id);
        $this->audit('master.delete', $config, $id, $old, null);
        return redirect()->to(site_url("setup/{$resource}"))->with('message', $config['title'] . ' deleted.');
    }

    private function setupResource(string $title, string $model, string $table, bool $tenant, bool $site, bool $withRate = false): array
    {
        $fields = $this->fields(['code' => 'Code*', 'name' => 'Name*']);
        if ($withRate) $fields['rate'] = ['label' => 'Rate (%)', 'type' => 'number', 'default' => 0];
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
        if (! isset($this->resources[$resource])) throw PageNotFoundException::forPageNotFound();
        $config = $this->resources[$resource];
        $permission = $mode === 'view' ? $config['view_permission'] : $config['manage_permission'];
        if (! $this->can($permission)) throw PageNotFoundException::forPageNotFound();
        return $config;
    }

    private function can(string $permission): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->can($permission) || $user->inGroup('superadmin'));
    }

    private function model(array $config): Model { return new $config['model'](); }

    private function scope(Model $model, array $config): Model
    {
        $tenant = new TenantContext(session());
        if ($config['tenant'] && $tenant->activeCompanyId() !== null) $model->where($config['table'] . '.company_id', $tenant->activeCompanyId());
        if ($config['site'] && $tenant->activeSiteId() !== null) $model->where($config['table'] . '.site_id', $tenant->activeSiteId());
        return $model;
    }

    private function payload(array $config, bool $isCreate): array
    {
        $payload = [];
        foreach ($config['fields'] as $name => $field) {
            if (($field['type'] ?? 'text') === 'checkbox') { $payload[$name] = $this->request->getPost($name) ? 1 : 0; continue; }
            $value = $this->request->getPost($name);
            if (($field['type'] ?? 'text') === 'number' && $value === '') $value = $field['default'] ?? null;
            if (($field['type'] ?? 'text') === 'select' && isset($field['options_source']) && $value === '') $value = null;
            $payload[$name] = $value;
        }
        if ($config['table'] === 'items') $payload += ['code' => $payload['item_code'] ?? null, 'name' => $payload['item_name'] ?? null, 'is_active' => $payload['active'] ?? 1];
        if ($config['table'] === 'customers') $payload += ['code' => $payload['customer'] ?? null, 'name' => $payload['customern'] ?? null, 'terms_code' => $payload['terms'] ?? null, 'tax_number' => $payload['taxnumber'] ?? null, 'address' => $payload['officeaddre'] ?? null, 'phone' => $payload['officephon'] ?? null, 'is_active' => $payload['active'] ?? 1];
        if ($config['table'] === 'suppliers') $payload += ['code' => $payload['supplier'] ?? null, 'name' => $payload['supplierna'] ?? null, 'terms_code' => $payload['terms'] ?? null, 'tax_number' => $payload['taxnumber'] ?? null, 'address' => $payload['officeaddre'] ?? null, 'phone' => $payload['officephon'] ?? null, 'is_active' => $payload['active'] ?? 1];
        $tenant = new TenantContext(session());
        if ($config['tenant']) $payload['company_id'] = $tenant->activeCompanyId();
        if ($config['site']) $payload['site_id'] = $tenant->activeSiteId();
        $payload['updated_by'] = (string) auth()->id();
        if ($isCreate) $payload['created_by'] = (string) auth()->id();
        return $payload;
    }

    private function audit(string $action, array $config, int $id, ?array $oldValues, ?array $newValues): void
    {
        $record = $newValues ?? $oldValues ?? [];
        (new AuditLogService())->log('setup.master', $action, ['company_id' => $record['company_id'] ?? null, 'site_id' => $record['site_id'] ?? null, 'table_name' => $config['table'], 'record_id' => $id, 'record_code' => $record['item_code'] ?? $record['customer'] ?? $record['supplier'] ?? $record['code'] ?? $record['name'] ?? null, 'description' => $config['title'] . ' ' . str_replace('master.', '', $action), 'old_values' => $oldValues, 'new_values' => $newValues]);
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $name => $field) { $type = $field['type'] ?? 'text'; $rule = ! empty($field['required']) ? 'required' : 'permit_empty'; $rule .= in_array($type, ['number', 'select', 'checkbox'], true) ? '' : '|max_length:500'; $rules[$name] = $rule; }
        return $rules;
    }

    private function hydrateOptions(array $config): array
    {
        foreach ($config['fields'] as $name => $field) { if (($field['type'] ?? '') !== 'select') continue; if (! empty($field['options_source'])) { $config['fields'][$name]['options'] = ['' => 'Select ' . $field['label']] + $this->optionsFor($field['options_source'], $field['option_value'] ?? 'id'); continue; } $config['fields'][$name]['options'] ??= ['' => 'Select ' . $field['label']]; }
        return $config;
    }

    private function optionsFor(string $source, string $valueField = 'id'): array
    {
        $map = ['countries' => [CountryModel::class, false, false], 'provinces' => [ProvinceModel::class, false, false], 'cities' => [CityModel::class, false, false], 'postal_codes' => [PostalCodeModel::class, false, false], 'warehouses' => [WarehouseModel::class, true, true], 'uoms' => [UomModel::class, true, false], 'items' => [ItemModel::class, true, true], 'vat_rates' => [VatRateModel::class, true, false], 'addresses' => [AddressModel::class, true, true]];
        if (! isset($map[$source])) return [];
        [$class, $tenantScoped, $siteScoped] = $map[$source]; $model = new $class(); $tenant = new TenantContext(session());
        if ($tenantScoped && $tenant->activeCompanyId() !== null) $model->where('company_id', $tenant->activeCompanyId());
        if ($siteScoped && $tenant->activeSiteId() !== null) $model->where('site_id', $tenant->activeSiteId());
        $rows = $model->orderBy('code', 'ASC')->findAll(); $options = [];
        foreach ($rows as $row) { $code = $row['item_code'] ?? $row['code'] ?? $row['id']; $name = $row['item_name'] ?? $row['name'] ?? ''; $value = $valueField === 'code' ? (string) $code : (string) $row['id']; $options[$value] = trim($code . ' - ' . $name); }
        return $options;
    }

    private function hasRequiredTenant(array $config): bool { return ! $config['tenant'] || (new TenantContext(session()))->activeCompanyId() !== null; }

    private function fields(array $defs): array
    {
        $fields = [];
        foreach ($defs as $name => $label) { $s = (string) $label; $fields[$name] = ['label' => trim($s, '*#!~'), 'type' => str_contains($s, '!') ? 'checkbox' : (str_contains($s, '#') ? 'number' : (str_contains($s, '~') ? 'textarea' : 'text')), 'required' => str_contains($s, '*'), 'default' => str_contains($s, '!') ? 1 : (str_contains($s, '#') ? 0 : null)]; }
        return $fields;
    }

    private function simpleFields(): array { return $this->fields(['code' => 'Code*', 'name' => 'Name*', 'description' => 'Description~', 'is_active' => 'Active!']); }
    private function uomCodeSelect(string $label): array { return ['label' => $label, 'type' => 'select', 'options_source' => 'uoms', 'option_value' => 'code']; }
    private function codeSelect(string $label, string $source): array { return ['label' => $label, 'type' => 'select', 'options_source' => $source, 'option_value' => 'code']; }

    private function addressFields(): array
    {
        return $this->fields(['address_type' => 'Address Type', 'owner_type' => 'Owner Type', 'owner_code' => 'Owner Code', 'code' => 'Address Code*', 'name' => 'Address Name*', 'address_line1' => 'Address Line 1~', 'address_line2' => 'Address Line 2~', 'phone' => 'Phone', 'email' => 'Email', 'is_active' => 'Active!']);
    }

    private function customerFields(): array
    {
        return $this->fields(array_fill_keys(['company','site','customer','customern','customerr','contactnar','description','officeaddre','officecity','officeprovir','officecount','officeposta','officeconta','officephon','officehp','taxcode','taxnumber','limitamound','limitqty','terms','limitdays','salescode','salesname','bank1','bankaccou','bank2','bankaccou2','billingcust','billingtoc','billingaddre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','mailcustom','mailcode','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','shiptocust','shiptocode','shiptoaddr','shiptocity','shiptoprovi','shiptocour','shiptopost','shiptocont','shiptophon','shiptohp'], '')) + ['customer' => 'Customer Code*', 'customern' => 'Customer Name*', 'shipwhs' => 'Ship Warehouse', 'vat' => 'VAT Code', 'active' => 'Active!'];
    }

    private function supplierFields(): array
    {
        return $this->fields(array_fill_keys(['company','site','supplier','supplierna','supplierref','contactnar','description','officeaddre','officecity','officeprovir','officecoun','officeposta','officeconta','officephon','officehp','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','billingadre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','taxcode','taxnumber','limitamound','limitqty','terms','limitdays','employee','purchasing','bank1','bankaccou','bank2','bankaccou2','shiptoaddr','shiptocity','shiptoprovi','shiptocoun','shiptopost','shiptocont','shiptophon','shiptohp'], '')) + ['supplier' => ['label' => 'Supplier Code', 'type' => 'text', 'required' => true], 'supplierna' => ['label' => 'Supplier Name', 'type' => 'text', 'required' => true], 'vat' => ['label' => 'VAT Code', 'type' => 'text'], 'active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1]];
    }

    private function itemFields(): array
    {
        return $this->fields(['company' => 'Company Code', 'site' => 'Site Code', 'item_code' => 'Item Code*', 'item_name' => 'Item Name*', 'item_coded' => 'Item Code Detail', 'item_named' => 'Item Name Detail', 'shelf_life' => 'Shelf Life#']) + ['stockuom' => $this->uomCodeSelect('Stock UoM'), 'purchaseuom' => $this->uomCodeSelect('Purchase UoM'), 'sellinguom' => $this->uomCodeSelect('Selling UoM'), 'stockwhs' => $this->codeSelect('Warehouse', 'warehouses'), 'item_price' => ['label' => 'Stock Price', 'type' => 'number', 'default' => 0], 'purchasep' => ['label' => 'Purchase Price', 'type' => 'number', 'default' => 0], 'sellingprice' => ['label' => 'Selling Price', 'type' => 'number', 'default' => 0], 'vat' => $this->codeSelect('VAT Code', 'vat_rates')] + $this->fields(['item_length' => 'Item Length#', 'item_width' => 'Item Width#', 'item_heigh' => 'Item Height#', 'item_diam' => 'Item Diameter#']) + ['item_lengt' => $this->uomCodeSelect('UoM Length'), 'item_widthh' => $this->uomCodeSelect('UoM Width'), 'item_heigh_uom' => $this->uomCodeSelect('UoM Height'), 'item_diam_uom' => $this->uomCodeSelect('UoM Diameter')] + $this->fields(['out_length' => 'Outer Length#', 'out_width' => 'Outer Width#', 'out_height' => 'Outer Height#', 'out_diame' => 'Outer Diameter#']) + ['out_lengt' => $this->uomCodeSelect('Outer UoM Length'), 'out_widthh' => $this->uomCodeSelect('Outer UoM Width'), 'out_height_uom' => $this->uomCodeSelect('Outer UoM Height'), 'out_diame_uom' => $this->uomCodeSelect('Outer UoM Diameter')] + $this->fields(['item_group' => 'Group', 'item_subg' => 'SubGroup', 'item_class' => 'Class', 'item_subc' => 'Sub Class', 'item_type' => 'Type', 'item_subty' => 'Sub Type', 'item_atribu' => 'Attribute', 'active' => 'Active!']);
    }
}
