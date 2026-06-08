<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class MasterDataTransferController extends BaseController
{
    private array $resources;

    private array $relations = [
        'warehouse_id' => ['alias' => 'warehouse_code', 'table' => 'warehouses', 'tenant' => true, 'site' => true],
        'parent_id' => ['alias' => 'parent_code', 'table' => null, 'tenant' => false, 'site' => false],
        'country_id' => ['alias' => 'country_code', 'table' => 'countries', 'tenant' => false, 'site' => false],
        'province_id' => ['alias' => 'province_code', 'table' => 'provinces', 'tenant' => false, 'site' => false],
        'city_id' => ['alias' => 'city_code', 'table' => 'cities', 'tenant' => false, 'site' => false],
        'postal_code_id' => ['alias' => 'postal_code', 'table' => 'postal_codes', 'tenant' => false, 'site' => false],
        'from_uom_id' => ['alias' => 'from_uom_code', 'table' => 'uoms', 'tenant' => true, 'site' => false],
        'to_uom_id' => ['alias' => 'to_uom_code', 'table' => 'uoms', 'tenant' => true, 'site' => false],
        'item_id' => ['alias' => 'item_code', 'table' => 'items', 'tenant' => true, 'site' => true],
        'vat_rate_id' => ['alias' => 'vat_code', 'table' => 'vat_rates', 'tenant' => true, 'site' => false],
    ];

    public function __construct()
    {
        $this->resources = [
            'transaction-codes' => ['title' => 'Transaction Codes', 'table' => 'transaction_codes', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active']],
            'prefix-codes' => ['title' => 'Prefix Codes', 'table' => 'prefix_codes', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active']],
            'companies' => ['title' => 'Companies', 'table' => 'companies', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'legal_name', 'tax_number', 'base_currency', 'address', 'is_active']],
            'sites' => ['title' => 'Sites', 'table' => 'sites', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'address', 'is_active']],
            'departments' => ['title' => 'Departments', 'table' => 'departments', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'description', 'is_active']],
            'warehouses' => ['title' => 'Warehouses', 'table' => 'warehouses', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'description', 'is_active']],
            'locations' => ['title' => 'Locations', 'table' => 'locations', 'tenant' => true, 'site' => true, 'fields' => ['warehouse_id', 'code', 'name', 'description', 'is_active']],
            'countries' => ['title' => 'Countries', 'table' => 'countries', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'is_active']],
            'provinces' => ['title' => 'Provinces', 'table' => 'provinces', 'tenant' => false, 'site' => false, 'fields' => ['parent_id', 'code', 'name', 'is_active']],
            'cities' => ['title' => 'Cities', 'table' => 'cities', 'tenant' => false, 'site' => false, 'fields' => ['parent_id', 'code', 'name', 'is_active']],
            'postal-codes' => ['title' => 'Postal Codes', 'table' => 'postal_codes', 'tenant' => false, 'site' => false, 'fields' => ['country_id', 'province_id', 'city_id', 'code', 'name', 'district', 'village', 'is_active']],
            'currencies' => ['title' => 'Currencies', 'table' => 'currencies', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'rounding', 'is_active']],
            'uoms' => ['title' => 'UoM', 'table' => 'uoms', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active']],
            'uom-conversions' => ['title' => 'UoM Conversions', 'table' => 'uom_conversions', 'tenant' => true, 'site' => false, 'fields' => ['from_uom_id', 'to_uom_id', 'multiplier', 'divider', 'is_active'], 'unique' => ['from_uom_id', 'to_uom_id']],
            'vat' => ['title' => 'VAT', 'table' => 'vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active']],
            'wht' => ['title' => 'WHT / PPH', 'table' => 'wht_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active']],
            'item-vat' => ['title' => 'Item VAT', 'table' => 'item_vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['item_id', 'vat_rate_id', 'is_active'], 'unique' => ['item_id', 'vat_rate_id']],
            'address-master' => ['title' => 'Address Master', 'table' => 'addresses', 'tenant' => true, 'site' => true, 'fields' => ['address_type', 'owner_type', 'owner_code', 'code', 'name', 'country_id', 'province_id', 'city_id', 'postal_code_id', 'address_line1', 'address_line2', 'contact_name', 'phone', 'mobile', 'email', 'is_active']],
            'customer-terms' => ['title' => 'Customer Terms', 'table' => 'customer_terms', 'tenant' => true, 'site' => true, 'fields' => ['company', 'site', 'terms_code', 'terms_name', 'terms_days', 'promo_code', 'is_active'], 'view_permission' => 'sales.customer.view', 'manage_permission' => 'sales.customer.manage', 'unique' => ['terms_code']],
            'customer-promos' => ['title' => 'Customer Promotions', 'table' => 'customer_promotions', 'tenant' => true, 'site' => true, 'fields' => ['company', 'site', 'promo_code', 'promo_description', 'customer', 'customer_name', 'item_parent', 'item_parent_name', 'line_no', 'promo_type', 'from_qty', 'to_qty', 'uom', 'promo_price', 'pct', 'disc_amount', 'free_item', 'free_item_name', 'free_qty', 'active_date', 'active_hour', 'inactive_date', 'inactive_hour', 'is_active'], 'view_permission' => 'sales.customer.view', 'manage_permission' => 'sales.customer.manage', 'unique' => ['promo_code', 'line_no']],
            'customers' => ['title' => 'Customers', 'table' => 'customers', 'tenant' => true, 'site' => true, 'fields' => $this->customerFields(), 'view_permission' => 'sales.customer.view', 'manage_permission' => 'sales.customer.manage', 'unique' => ['customer']],
            'supplier-terms' => ['title' => 'Supplier Terms', 'table' => 'supplier_terms', 'tenant' => true, 'site' => true, 'fields' => ['company', 'site', 'terms_code', 'terms_name', 'terms_days', 'promo_code', 'is_active'], 'view_permission' => 'purchase.supplier.view', 'manage_permission' => 'purchase.supplier.manage', 'unique' => ['terms_code']],
            'supplier-promos' => ['title' => 'Supplier Promotions', 'table' => 'supplier_promotions', 'tenant' => true, 'site' => true, 'fields' => ['company', 'site', 'promo_code', 'promo_description', 'supplier', 'supplier_name', 'item_parent', 'item_parent_name', 'line_no', 'promo_type', 'from_qty', 'to_qty', 'uom', 'promo_price', 'pct', 'disc_amount', 'free_item', 'free_item_name', 'free_qty', 'active_date', 'active_hour', 'inactive_date', 'inactive_hour', 'is_active'], 'view_permission' => 'purchase.supplier.view', 'manage_permission' => 'purchase.supplier.manage', 'unique' => ['promo_code', 'line_no']],
            'suppliers' => ['title' => 'Suppliers', 'table' => 'suppliers', 'tenant' => true, 'site' => true, 'fields' => $this->supplierFields(), 'view_permission' => 'purchase.supplier.view', 'manage_permission' => 'purchase.supplier.manage', 'unique' => ['supplier']],
            'items' => ['title' => 'Items', 'table' => 'items', 'tenant' => true, 'site' => true, 'fields' => $this->itemFields(), 'view_permission' => 'inventory.item.view', 'manage_permission' => 'inventory.item.manage', 'unique' => ['item_code']],
        ];
    }

    public function importForm(string $resource): string
    {
        $config = $this->config($resource, 'manage');
        return view('setup/master/import', ['title' => 'Import ' . $config['title'], 'resource' => $resource, 'config' => $config, 'headers' => $this->importHeaders($config)]);
    }

    public function template(string $resource)
    {
        $config = $this->config($resource, 'manage');
        return $this->csvResponse($this->slug($config['title']) . '-template.csv', [$this->importHeaders($config), $this->sampleRow($config)]);
    }

    public function export(string $resource)
    {
        $config = $this->config($resource, 'view');
        $db = Database::connect();
        $builder = $db->table($config['table']);
        $headers = $this->exportHeaders($config);
        $tenant = new TenantContext(session());

        if ($config['tenant'] && $tenant->activeCompanyId() !== null) $builder->where('company_id', $tenant->activeCompanyId());
        if ($config['site'] && $tenant->activeSiteId() !== null) $builder->where('site_id', $tenant->activeSiteId());
        if ($db->fieldExists('deleted_at', $config['table'])) $builder->where('deleted_at', null);

        $rows = [$headers];
        foreach ($builder->orderBy('id', 'ASC')->get()->getResultArray() as $row) {
            $line = [];
            foreach ($headers as $header) $line[] = $this->exportValue($config, $row, $header);
            $rows[] = $line;
        }

        return $this->csvResponse($this->slug($config['title']) . '-export.csv', $rows);
    }

    public function import(string $resource)
    {
        $config = $this->config($resource, 'manage');
        if (! $this->hasRequiredTenant($config)) return redirect()->back()->with('error', $this->tenantRequirementMessage($config));

        $file = $this->request->getFile('csv_file');
        if ($file === null || ! $file->isValid()) return redirect()->back()->with('error', 'Please upload a valid CSV file.');
        if (! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) return redirect()->back()->with('error', 'Only CSV files are supported for now.');

        try {
            $result = $this->importCsv($config, $file->getTempName());
        } catch (RuntimeException $exception) {
            $this->auditImport($config, ['created' => 0, 'updated' => 0, 'skipped' => 0], $file->getClientName(), $exception->getMessage());
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $this->auditImport($config, $result, $file->getClientName());
        return redirect()->to(site_url('setup/' . $resource))->with('message', "Import finished. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    private function importCsv(array $config, string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('Unable to read uploaded CSV file.');
        $headers = fgetcsv($handle);
        if ($headers === false) { fclose($handle); throw new RuntimeException('CSV file is empty.'); }

        $headers = array_map(static fn ($value) => trim((string) $value), $headers);
        $allowed = array_merge($config['fields'], $this->relationAliases($config));
        $missing = $this->requiredImportHeader($config, $headers);
        if ($missing !== null) { fclose($handle); throw new RuntimeException('CSV header must include ' . $missing . ' column.'); }

        $db = Database::connect();
        $tenant = new TenantContext(session());
        $created = $updated = $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($row === [null] || $row === false) continue;

            $data = [];
            foreach ($headers as $index => $header) {
                if (! in_array($header, $allowed, true)) continue;
                $value = trim((string) ($row[$index] ?? ''));
                $data[$header] = $value === '' ? null : $value;
            }
            if ($data === []) { $skipped++; continue; }

            $data = $this->normalizeRelationCodes($config, $data, $rowNumber);
            $data = $this->compatibilityFields($config, $data);
            if (isset($data['is_active']) && $data['is_active'] === null) $data['is_active'] = 1;
            if ($config['tenant']) $data['company_id'] = $tenant->activeCompanyId();
            if ($config['site']) $data['site_id'] = $tenant->activeSiteId();
            $data['updated_by'] = (string) auth()->id();
            $data['updated_at'] = $now;

            $existing = $this->findExisting($config, $data);
            if ($existing !== null) {
                $db->table($config['table'])->where('id', $existing['id'])->update($data);
                $updated++;
                continue;
            }

            $data['created_by'] = (string) auth()->id();
            $data['created_at'] = $now;
            $db->table($config['table'])->insert($data);
            $created++;
        }

        fclose($handle);
        return compact('created', 'updated', 'skipped');
    }

    private function requiredImportHeader(array $config, array $headers): ?string
    {
        return match ($config['table']) {
            'items' => in_array('item_code', $headers, true) ? null : 'item_code',
            'customers' => in_array('customer', $headers, true) ? null : 'customer',
            'suppliers' => in_array('supplier', $headers, true) ? null : 'supplier',
            default => in_array('code', $config['fields'], true) && ! in_array('code', $headers, true) ? 'code' : null,
        };
    }

    private function compatibilityFields(array $config, array $data): array
    {
        if ($config['table'] === 'items') {
            $data += ['code' => $data['item_code'] ?? null, 'name' => $data['item_name'] ?? null, 'is_active' => $data['active'] ?? 1];
        } elseif ($config['table'] === 'customers') {
            $data += ['code' => $data['customer'] ?? null, 'name' => $data['customern'] ?? null, 'terms_code' => $data['terms'] ?? null, 'tax_number' => $data['taxnumber'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        } elseif ($config['table'] === 'suppliers') {
            $data += ['code' => $data['supplier'] ?? null, 'name' => $data['supplierna'] ?? null, 'terms_code' => $data['terms'] ?? null, 'tax_number' => $data['taxnumber'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        }

        return $data;
    }

    private function normalizeRelationCodes(array $config, array $data, int $rowNumber): array
    {
        foreach ($config['fields'] as $field) {
            if (! isset($this->relations[$field])) continue;
            $relation = $this->relationFor($config, $field);
            $alias = $relation['alias'];
            if (! empty($data[$field]) || empty($data[$alias])) { unset($data[$alias]); continue; }
            $id = $this->lookupIdByCode($relation, (string) $data[$alias]);
            if ($id === null) throw new RuntimeException("Row {$rowNumber}: {$alias} '{$data[$alias]}' was not found.");
            $data[$field] = $id;
            unset($data[$alias]);
        }
        foreach ($this->relationAliases($config) as $alias) unset($data[$alias]);
        return $data;
    }

    private function lookupIdByCode(array $relation, string $code): ?int
    {
        if ($relation['table'] === null) return null;
        $builder = Database::connect()->table($relation['table'])->where('code', $code);
        $tenant = new TenantContext(session());
        if (! empty($relation['tenant']) && $tenant->activeCompanyId() !== null) $builder->where('company_id', $tenant->activeCompanyId());
        if (! empty($relation['site']) && $tenant->activeSiteId() !== null && Database::connect()->fieldExists('site_id', $relation['table'])) $builder->where('site_id', $tenant->activeSiteId());
        $row = $builder->get()->getRowArray();
        return $row !== null ? (int) $row['id'] : null;
    }

    private function exportValue(array $config, array $row, string $header): string
    {
        foreach ($config['fields'] as $field) {
            if (! isset($this->relations[$field])) continue;
            $relation = $this->relationFor($config, $field);
            if ($header === $relation['alias']) return $this->lookupCodeById($relation, (int) ($row[$field] ?? 0)) ?? '';
        }
        return (string) ($row[$header] ?? '');
    }

    private function lookupCodeById(array $relation, int $id): ?string
    {
        if ($id < 1 || $relation['table'] === null) return null;
        $row = Database::connect()->table($relation['table'])->where('id', $id)->get()->getRowArray();
        return $row['code'] ?? null;
    }

    private function findExisting(array $config, array $data): ?array
    {
        $builder = Database::connect()->table($config['table']);
        if ($config['table'] === 'items' && ! empty($data['item_code'])) $builder->where('item_code', $data['item_code']);
        elseif ($config['table'] === 'customers' && ! empty($data['customer'])) $builder->where('customer', $data['customer']);
        elseif ($config['table'] === 'suppliers' && ! empty($data['supplier'])) $builder->where('supplier', $data['supplier']);
        elseif (! empty($data['code'])) $builder->where('code', $data['code']);
        elseif (! empty($config['unique'])) { foreach ($config['unique'] as $field) { if (! array_key_exists($field, $data)) return null; $builder->where($field, $data[$field]); } }
        else return null;
        if ($config['tenant']) $builder->where('company_id', $data['company_id'] ?? 0);
        if ($config['site']) $builder->where('site_id', $data['site_id'] ?? null);
        return $builder->get()->getRowArray() ?: null;
    }

    private function config(string $resource, string $mode): array
    {
        if (! isset($this->resources[$resource])) throw PageNotFoundException::forPageNotFound();
        $config = $this->resources[$resource] + ['view_permission' => 'setup.master.view', 'manage_permission' => 'setup.master.manage'];
        $permission = $mode === 'view' ? $config['view_permission'] : $config['manage_permission'];
        $user = auth()->user();
        if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) throw PageNotFoundException::forPageNotFound();
        return $config;
    }

    private function importHeaders(array $config): array { return array_map(fn (string $field): string => $this->relations[$field]['alias'] ?? $field, $config['fields']); }
    private function exportHeaders(array $config): array { return $this->importHeaders($config); }

    private function relationAliases(array $config): array
    {
        $aliases = [];
        foreach ($config['fields'] as $field) if (isset($this->relations[$field])) $aliases[] = $this->relationFor($config, $field)['alias'];
        return $aliases;
    }

    private function relationFor(array $config, string $field): array
    {
        $relation = $this->relations[$field];
        if ($field === 'parent_id') { $relation['table'] = $config['table'] === 'provinces' ? 'countries' : 'provinces'; $relation['alias'] = $config['table'] === 'provinces' ? 'country_code' : 'province_code'; }
        return $relation;
    }

    private function auditImport(array $config, array $result, string $filename, ?string $error = null): void
    {
        (new AuditLogService())->log('setup.master', $error === null ? 'master.import' : 'master.import_failed', ['table_name' => $config['table'], 'description' => $error === null ? $config['title'] . ' CSV import completed.' : $config['title'] . ' CSV import failed: ' . $error, 'new_values' => ['title' => $config['title'], 'table' => $config['table'], 'filename' => $filename, 'created' => $result['created'] ?? 0, 'updated' => $result['updated'] ?? 0, 'skipped' => $result['skipped'] ?? 0, 'error' => $error]]);
    }

    private function sampleRow(array $config): array
    {
        $sample = [];
        foreach ($this->importHeaders($config) as $header) {
            $sample[] = match ($header) {
                'code' => 'EXAMPLE', 'name' => 'Example ' . $config['title'],
                'customer' => 'CUST-999', 'customern' => 'Example Customer', 'customerr' => 'Example Customer Ref',
                'supplier' => 'SUP-999', 'supplierna' => 'Example Supplier', 'supplierref' => 'Example Supplier Ref',
                'contactnar', 'officeconta', 'billingconta', 'mailcontac', 'shiptocont' => 'Demo Contact',
                'officeaddre', 'billingaddre', 'mailaddres', 'shiptoaddr', 'billingadre' => 'Jl. Demo Address No. 1',
                'officecity', 'billingcity', 'mailcity', 'shiptocity' => 'Jakarta Selatan',
                'officeprovir', 'billingprovi', 'mailprovin', 'shiptoprovi' => 'DKI Jakarta',
                'officecount', 'officecoun', 'billingcoun', 'mailcountr', 'shiptocour', 'shiptocoun' => 'Indonesia',
                'officeposta', 'billingposta', 'mailpostal', 'shiptopost' => '12190',
                'officephon', 'billingphon', 'mailphone', 'shiptophon' => '021-50881234',
                'officehp', 'billinghp', 'mailhp', 'shiptohp' => '08119001001',
                'terms' => 'NET30', 'taxcode' => 'PKP', 'taxnumber' => '01.999.000.0-000.000',
                'vat_code', 'vat' => 'VAT11', 'company' => 'PENA', 'site' => 'HO', 'shipwhs' => 'MAIN',
                'active', 'is_active' => '1', 'limitamound', 'limitqty', 'limitdays', 'rate', 'multiplier', 'divider' => '0',
                'item_code' => 'ITEM001', 'item_name' => 'Example Item', 'stockuom', 'purchaseuom', 'sellinguom' => 'PCS', 'stockwhs' => 'MAIN',
                'country_code' => 'ID', 'province_code' => 'DKI', 'city_code' => 'JKT',
                default => '',
            };
        }
        return $sample;
    }

    private function hasRequiredTenant(array $config): bool
    {
        $tenant = new TenantContext(session());
        if ($config['tenant'] && $tenant->activeCompanyId() === null) return false;
        if ($config['site'] && $tenant->activeSiteId() === null) return false;
        return true;
    }

    private function tenantRequirementMessage(array $config): string
    {
        return $config['site']
            ? 'Active company and active site are required before importing this master data.'
            : 'Active company is required before importing this master data.';
    }

    private function csvResponse(string $filename, array $rows)
    {
        $handle = fopen('php://temp', 'wb+');
        foreach ($rows as $row) fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setBody("\xEF\xBB\xBF" . $csv);
    }

    private function slug(string $value): string { return trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-'); }
    private function itemFields(): array { return ['company','site','item_code','item_name','item_coded','item_named','shelf_life','stockuom','purchaseuom','sellinguom','stockwhs','item_price','purchasep','sellingprice','vat','item_length','item_width','item_heigh','item_diam','item_lengt','item_widthh','item_heigh_uom','item_diam_uom','out_length','out_width','out_height','out_diame','out_lengt','out_widthh','out_height_uom','out_diame_uom','item_group','item_subg','item_class','item_subc','item_type','item_subty','item_atribu','active']; }
    private function customerFields(): array { return ['company','site','customer','customern','customerr','contactnar','description','shipwhs','officeaddre','officecity','officeprovir','officecount','officeposta','officeconta','officephon','officehp','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','salescode','salesname','bank1','bankaccou','bank2','bankaccou2','billingcust','billingtoc','billingaddre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','mailcustom','mailcode','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','shiptocust','shiptocode','shiptoaddr','shiptocity','shiptoprovi','shiptocour','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
    private function supplierFields(): array { return ['company','site','supplier','supplierna','supplierref','contactnar','description','officeaddre','officecity','officeprovir','officecoun','officeposta','officeconta','officephon','officehp','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','billingadre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','employee','purchasing','bank1','bankaccou','bank2','bankaccou2','shiptoaddr','shiptocity','shiptoprovi','shiptocoun','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
}
