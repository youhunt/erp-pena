<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class ExcelLiteTransferController extends BaseController
{
    private const MAX_UPLOAD_BYTES = 10485760;
    private const SESSION_KEY = 'excel_lite_import_previews';

    private array $resources;

    public function __construct()
    {
        $this->resources = [
            'transaction-codes' => ['title' => 'Transaction Codes', 'table' => 'transaction_codes', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'prefix-codes' => ['title' => 'Prefix Codes', 'table' => 'prefix_codes', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'companies' => ['title' => 'Companies', 'table' => 'companies', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'legal_name', 'tax_number', 'base_currency', 'address', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'sites' => ['title' => 'Sites', 'table' => 'sites', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'address', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'departments' => ['title' => 'Departments', 'table' => 'departments', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'warehouses' => ['title' => 'Warehouses', 'table' => 'warehouses', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'locations' => ['title' => 'Locations', 'table' => 'locations', 'tenant' => true, 'site' => true, 'fields' => ['warehouse_code', 'code', 'name', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'countries' => ['title' => 'Countries', 'table' => 'countries', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'provinces' => ['title' => 'Provinces', 'table' => 'provinces', 'tenant' => false, 'site' => false, 'fields' => ['country_code', 'code', 'name', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'cities' => ['title' => 'Cities', 'table' => 'cities', 'tenant' => false, 'site' => false, 'fields' => ['province_code', 'code', 'name', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'postal-codes' => ['title' => 'Postal Codes', 'table' => 'postal_codes', 'tenant' => false, 'site' => false, 'fields' => ['country_code', 'province_code', 'city_code', 'code', 'name', 'district', 'village', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'currencies' => ['title' => 'Currencies', 'table' => 'currencies', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'rounding', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'uoms' => ['title' => 'Unit of Measure', 'table' => 'uoms', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'uom-conversions' => ['title' => 'UoM Conversions', 'table' => 'uom_conversions', 'tenant' => true, 'site' => false, 'fields' => ['from_uom_code', 'to_uom_code', 'multiplier', 'divider', 'is_active'], 'unique' => ['from_uom_code', 'to_uom_code'], 'permission' => 'setup.master'],
            'vat' => ['title' => 'VAT', 'table' => 'vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'wht' => ['title' => 'WHT / PPH', 'table' => 'wht_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'item-vat' => ['title' => 'Item VAT', 'table' => 'item_vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['item_code_ref', 'vat_code', 'is_active'], 'unique' => ['item_code_ref', 'vat_code'], 'permission' => 'setup.master'],
            'address-master' => ['title' => 'Address Master', 'table' => 'addresses', 'tenant' => true, 'site' => true, 'fields' => ['address_type', 'owner_type', 'owner_code', 'code', 'name', 'country_code', 'province_code', 'city_code', 'postal_code', 'address_line1', 'address_line2', 'contact_name', 'phone', 'mobile', 'email', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'customer-terms' => ['title' => 'Customer Terms', 'table' => 'customer_terms', 'tenant' => true, 'site' => true, 'fields' => ['terms_code', 'terms_name', 'terms_days', 'promo_code', 'is_active'], 'key' => 'terms_code', 'permission' => 'sales.customer'],
            'customer-promos' => ['title' => 'Customer Promotions', 'table' => 'customer_promotions', 'tenant' => true, 'site' => true, 'fields' => ['promo_code', 'promo_description', 'customer', 'customer_name', 'item_parent', 'item_parent_name', 'line_no', 'promo_type', 'from_qty', 'to_qty', 'uom', 'promo_price', 'pct', 'disc_amount', 'free_item', 'free_item_name', 'free_qty', 'active_date', 'active_hour', 'inactive_date', 'inactive_hour', 'is_active'], 'unique' => ['promo_code', 'line_no'], 'permission' => 'sales.customer'],
            'customers' => ['title' => 'Customers', 'table' => 'customers', 'tenant' => true, 'site' => true, 'fields' => $this->customerFields(), 'key' => 'customer', 'permission' => 'sales.customer'],
            'supplier-terms' => ['title' => 'Supplier Terms', 'table' => 'supplier_terms', 'tenant' => true, 'site' => true, 'fields' => ['terms_code', 'terms_name', 'terms_days', 'promo_code', 'is_active'], 'key' => 'terms_code', 'permission' => 'purchase.supplier'],
            'supplier-promos' => ['title' => 'Supplier Promotions', 'table' => 'supplier_promotions', 'tenant' => true, 'site' => true, 'fields' => ['promo_code', 'promo_description', 'supplier', 'supplier_name', 'item_parent', 'item_parent_name', 'line_no', 'promo_type', 'from_qty', 'to_qty', 'uom', 'promo_price', 'pct', 'disc_amount', 'free_item', 'free_item_name', 'free_qty', 'active_date', 'active_hour', 'inactive_date', 'inactive_hour', 'is_active'], 'unique' => ['promo_code', 'line_no'], 'permission' => 'purchase.supplier'],
            'suppliers' => ['title' => 'Suppliers', 'table' => 'suppliers', 'tenant' => true, 'site' => true, 'fields' => $this->supplierFields(), 'key' => 'supplier', 'permission' => 'purchase.supplier'],
            'items' => ['title' => 'Items', 'table' => 'items', 'tenant' => true, 'site' => true, 'fields' => $this->itemFields(), 'key' => 'item_code', 'permission' => 'inventory.item'],
        ];
    }

    public function index(): string
    {
        return view('system/excel_transfer/index', ['title' => 'Excel Lite Import Export', 'resources' => $this->resources]);
    }

    public function importForm(string $resource): string
    {
        $config = $this->config($resource, 'manage');
        return view('system/excel_transfer/import', ['title' => 'Import ' . $config['title'] . ' from Excel Lite', 'resource' => $resource, 'config' => $config, 'headers' => $config['fields']]);
    }

    public function template(string $resource)
    {
        $config = $this->config($resource, 'manage');
        return $this->tabResponse($this->slug($config['title']) . '-template.xls', [$config['fields'], $this->sampleRow($config)]);
    }

    public function export(string $resource)
    {
        $config = $this->config($resource, 'view');
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $builder = $db->table($config['table']);
        if ($config['tenant'] && $tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $config['table'])) $builder->where('company_id', $tenant->activeCompanyId());
        if ($config['site'] && $tenant->activeSiteId() !== null && $db->fieldExists('site_id', $config['table'])) $builder->where('site_id', $tenant->activeSiteId());
        if ($db->fieldExists('deleted_at', $config['table'])) $builder->where('deleted_at', null);
        $rows = [$config['fields']];
        foreach ($builder->orderBy('id', 'ASC')->get()->getResultArray() as $row) $rows[] = $this->exportRow($config, $row);
        return $this->tabResponse($this->slug($config['title']) . '-export.xls', $rows);
    }

    public function import(string $resource)
    {
        $config = $this->config($resource, 'manage');
        if (! $this->hasRequiredTenant($config)) return redirect()->back()->with('error', $this->tenantRequirementMessage($config));
        $file = $this->request->getFile('excel_file');
        $uploadError = $this->validateUpload($file);
        if ($uploadError !== null) return redirect()->back()->with('error', $uploadError);
        try { $preview = $this->previewTabFile($config, $file->getTempName()); }
        catch (RuntimeException $exception) { return redirect()->back()->with('error', $exception->getMessage()); }
        $token = bin2hex(random_bytes(16));
        $returnTo = trim((string) $this->request->getPost('return_to'), '/') ?: 'system/excel-transfer';
        $this->storePreview($token, ['resource' => $resource, 'config' => $config, 'return_to' => $returnTo] + $preview);
        return view('system/excel_transfer/preview', [
            'title' => 'Preview Import ' . $config['title'], 'resource' => $resource, 'config' => $config,
            'headers' => $preview['headers'], 'summary' => ['total' => $preview['total'], 'valid' => count($preview['valid_rows']), 'error' => count($preview['errors'])],
            'errors' => $preview['errors'], 'previewRows' => array_slice($preview['raw_valid_rows'], 0, 25),
            'returnTo' => $returnTo, 'previewToken' => $token,
            'commitUrl' => 'system/excel-transfer/' . $resource . '/commit',
            'downloadErrorUrl' => 'system/excel-transfer/' . $resource . '/errors/' . $token,
        ]);
    }

    public function commit(string $resource)
    {
        $config = $this->config($resource, 'manage');
        $token = (string) $this->request->getPost('preview_token');
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) return redirect()->to(site_url('system/excel-transfer'))->with('error', 'Import preview expired. Upload ulang file Excel Lite.');
        if (! empty($preview['errors'])) return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('error', 'Tidak bisa post data yang masih punya error.');
        try { $result = $this->persistRows($config, $preview['valid_rows']); }
        catch (RuntimeException $exception) { return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('error', $exception->getMessage()); }
        $this->clearPreview($token);
        return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('message', "Excel Lite import posted. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    public function downloadErrors(string $resource, string $token)
    {
        $config = $this->config($resource, 'manage');
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) return redirect()->to(site_url('system/excel-transfer'))->with('error', 'Import preview expired.');
        $rows = [array_merge(['excel_row', 'error_message'], $preview['headers'] ?? [])];
        foreach ($preview['errors'] as $error) {
            $raw = $preview['raw_rows_by_number'][$error['row']] ?? [];
            $line = [(string) $error['row'], $error['message']];
            foreach (($preview['headers'] ?? []) as $header) $line[] = (string) ($raw[$header] ?? '');
            $rows[] = $line;
        }
        return $this->tabResponse($this->slug($config['title']) . '-import-errors.xls', $rows);
    }

    private function previewTabFile(array $config, string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') throw new RuntimeException('Uploaded file is empty.');
        if (str_starts_with($content, 'PK')) throw new RuntimeException('File .xlsx asli belum bisa diproses tanpa PHP ZipArchive. Download template ulang dari aplikasi ini lalu simpan tetap sebagai Excel Lite / Tab Delimited.');
        $lines = preg_split('/\r\n|\n|\r/', ltrim($content, "\xEF\xBB\xBF"));
        $headers = array_map('trim', str_getcsv((string) array_shift($lines), "\t"));
        $required = $this->requiredHeader($config);
        if ($required !== null && ! in_array($required, $headers, true)) throw new RuntimeException('Header harus memiliki kolom ' . $required . '.');
        $validRows = $rawValidRows = $rawRowsByNumber = $errors = [];
        $total = 0;
        foreach ($lines as $index => $line) {
            if (trim((string) $line) === '') continue;
            $rowNumber = $index + 2;
            $values = str_getcsv((string) $line, "\t");
            $data = [];
            foreach ($headers as $i => $header) if ($header !== '' && in_array($header, $config['fields'], true)) $data[$header] = trim((string) ($values[$i] ?? '')) ?: null;
            if ($data === []) continue;
            $total++;
            $rawRowsByNumber[$rowNumber] = $data;
            try { $this->validateRequiredBusinessKey($config, $data); $validRows[] = $data; $rawValidRows[] = ['_row_number' => $rowNumber] + $data; }
            catch (RuntimeException $e) { $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()]; }
        }
        return ['headers' => $headers, 'valid_rows' => $validRows, 'raw_valid_rows' => $rawValidRows, 'raw_rows_by_number' => $rawRowsByNumber, 'errors' => $errors, 'total' => $total];
    }

    private function persistRows(array $config, array $rows): array
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $created = $updated = $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            foreach ($rows as $data) {
                $data = $this->normalizeBeforeSave($config, $data);
                if ($config['tenant']) $data['company_id'] = $tenant->activeCompanyId();
                if ($config['site']) $data['site_id'] = $tenant->activeSiteId();
                if (array_key_exists('is_active', $data) && $data['is_active'] === null) $data['is_active'] = 1;
                if (array_key_exists('active', $data) && $data['active'] === null) $data['active'] = 1;
                $data['updated_by'] = (string) auth()->id(); $data['updated_at'] = $now;
                $existing = $this->findExisting($config, $data);
                if ($existing !== null) { $db->table($config['table'])->where('id', $existing['id'])->update($data); $updated++; continue; }
                $data['created_by'] = (string) auth()->id(); $data['created_at'] = $now;
                $db->table($config['table'])->insert($data); $created++;
            }
        } catch (\Throwable $e) { $db->transRollback(); throw new RuntimeException($e->getMessage(), 0, $e); }
        if ($db->transStatus() === false) { $db->transRollback(); throw new RuntimeException('Import failed. No data was saved.'); }
        $db->transCommit();
        return compact('created', 'updated', 'skipped');
    }

    private function normalizeBeforeSave(array $config, array $data): array
    {
        if ($config['table'] === 'items') $data += ['code' => $data['item_code'] ?? null, 'name' => $data['item_name'] ?? null, 'is_active' => $data['active'] ?? 1];
        elseif ($config['table'] === 'customers') $data += ['code' => $data['customer'] ?? null, 'name' => $data['customern'] ?? null, 'terms_code' => $data['terms'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        elseif ($config['table'] === 'suppliers') $data += ['code' => $data['supplier'] ?? null, 'name' => $data['supplierna'] ?? null, 'terms_code' => $data['terms'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        return $data;
    }

    private function exportRow(array $config, array $row): array { return array_map(static fn ($field) => (string) ($row[$field] ?? ''), $config['fields']); }
    private function tabResponse(string $filename, array $rows) { $body = "\xEF\xBB\xBF"; foreach ($rows as $row) $body .= implode("\t", array_map([$this, 'safeCell'], $row)) . "\r\n"; return $this->response->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setBody($body); }
    private function safeCell(mixed $value): string { return str_replace(["\t", "\r", "\n"], ' ', (string) $value); }
    private function validateRequiredBusinessKey(array $config, array $data): void { $key = $this->requiredHeader($config); if ($key !== null && (($data[$key] ?? null) === null || ($data[$key] ?? '') === '')) throw new RuntimeException('Required field ' . $key . ' is empty.'); }
    private function config(string $resource, string $mode): array { if (! isset($this->resources[$resource])) throw PageNotFoundException::forPageNotFound(); $config = $this->resources[$resource]; $permission = $config['permission'] . ($mode === 'view' ? '.view' : '.manage'); $user = auth()->user(); if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) throw PageNotFoundException::forPageNotFound(); return $config; }
    private function requiredHeader(array $config): ?string { if (! empty($config['key'])) return (string) $config['key']; return ! empty($config['unique']) ? (string) $config['unique'][0] : (in_array('code', $config['fields'], true) ? 'code' : null); }
    private function findExisting(array $config, array $data): ?array { $builder = Database::connect()->table($config['table']); if (! empty($config['key']) && ! empty($data[$config['key']])) $builder->where($config['key'], $data[$config['key']]); elseif (! empty($config['unique'])) { foreach ($config['unique'] as $field) { if (! array_key_exists($field, $data)) return null; $builder->where($field, $data[$field]); } } else return null; if ($config['tenant']) $builder->where('company_id', $data['company_id'] ?? 0); if ($config['site']) $builder->where('site_id', $data['site_id'] ?? null); return $builder->get()->getRowArray() ?: null; }
    private function hasRequiredTenant(array $config): bool { $tenant = new TenantContext(session()); if ($config['tenant'] && $tenant->activeCompanyId() === null) return false; if ($config['site'] && $tenant->activeSiteId() === null) return false; return true; }
    private function tenantRequirementMessage(array $config): string { return $config['site'] ? 'Active company and active site are required before importing.' : 'Active company is required before importing.'; }
    private function validateUpload($file): ?string { if ($file === null || ! $file->isValid()) return 'Please upload a valid Excel Lite file.'; if ($file->getSize() < 1) return 'Uploaded file is empty.'; if ($file->getSize() > self::MAX_UPLOAD_BYTES) return 'File is too large. Maximum 10 MB.'; if (! in_array(strtolower($file->getClientExtension()), ['xls', 'tsv', 'txt'], true)) return 'Gunakan file template Excel Lite dari aplikasi (.xls). File .xlsx membutuhkan PHP ZipArchive.'; return null; }
    private function storePreview(string $token, array $preview): void { $all = session(self::SESSION_KEY) ?? []; $all[$token] = $preview; session()->set(self::SESSION_KEY, $all); }
    private function getPreview(string $token): ?array { $all = session(self::SESSION_KEY) ?? []; return is_array($all[$token] ?? null) ? $all[$token] : null; }
    private function clearPreview(string $token): void { $all = session(self::SESSION_KEY) ?? []; unset($all[$token]); session()->set(self::SESSION_KEY, $all); }
    private function sampleRow(array $config): array { return array_map(function (string $header) use ($config): string { return match ($header) { 'code' => 'EXAMPLE', 'name' => 'Example ' . $config['title'], 'description' => 'Example description', 'warehouse_code', 'stockwhs', 'shipwhs' => 'MAIN', 'country_code' => 'IDN', 'province_code' => 'DKI', 'city_code' => 'JKT', 'postal_code' => '12190', 'from_uom_code', 'to_uom_code', 'stockuom', 'purchaseuom', 'sellinguom' => 'PCS', 'item_code_ref', 'item_code' => 'ITEM001', 'vat_code', 'vat' => 'VAT11', 'customer' => 'CUST-999', 'customern' => 'Example Customer', 'supplier' => 'SUP-999', 'supplierna' => 'Example Supplier', 'terms_code', 'terms' => 'NET30', 'promo_code' => 'PROMO-001', 'line_no' => '1', 'email' => 'demo@example.com', 'is_active', 'active' => '1', 'rate', 'item_price', 'purchasep', 'sellingprice', 'multiplier', 'divider', 'terms_days' => '0', default => '', }; }, $config['fields']); }
    private function slug(string $value): string { return trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-'); }
    private function itemFields(): array { return ['item_code','item_name','item_coded','item_named','shelf_life','stockuom','purchaseuom','sellinguom','stockwhs','item_price','purchasep','sellingprice','vat','item_length','item_width','item_heigh','item_diam','item_lengt','item_widthh','item_heigh_uom','item_diam_uom','out_length','out_width','out_height','out_diame','out_lengt','out_widthh','out_height_uom','out_diame_uom','item_group','item_subg','item_class','item_subc','item_type','item_subty','item_atribu','active']; }
    private function customerFields(): array { return ['customer','customern','customerr','contactnar','description','shipwhs','officeaddre','officecity','officeprovir','officecount','officeposta','officeconta','officephon','officehp','email','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','salescode','salesname','bank1','bankaccou','bank2','bankaccou2','billingcust','billingtoc','billingaddre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','mailcustom','mailcode','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','shiptocust','shiptocode','shiptoaddr','shiptocity','shiptoprovi','shiptocour','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
    private function supplierFields(): array { return ['supplier','supplierna','supplierref','contactnar','description','officeaddre','officecity','officeprovir','officecoun','officeposta','officeconta','officephon','officehp','email','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','billingadre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','employee','purchasing','bank1','bankaccou','bank2','bankaccou2','shiptoaddr','shiptocity','shiptoprovi','shiptocoun','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
}
