<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Libraries\XlsxSheetReader;
use App\Libraries\XlsxSheetWriter;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class ExcelLiteTransferController extends BaseController
{
    private const MAX_UPLOAD_BYTES = 10485760;
    private const PREVIEW_DIR = 'excel_lite_import_previews';
    private const PREVIEW_TTL_SECONDS = 21600;

    private array $resources;

    private array $relations = [
        'warehouse_code' => ['field' => 'warehouse_id', 'table' => 'warehouses', 'tenant' => true, 'site' => true],
        'country_code' => ['field' => 'country_id', 'table' => 'countries', 'tenant' => false, 'site' => false],
        'province_code' => ['field' => 'province_id', 'table' => 'provinces', 'tenant' => false, 'site' => false],
        'city_code' => ['field' => 'city_id', 'table' => 'cities', 'tenant' => false, 'site' => false],
        'postal_code' => ['field' => 'postal_code_id', 'table' => 'postal_codes', 'tenant' => false, 'site' => false],
        'from_uom_code' => ['field' => 'from_uom_id', 'table' => 'uoms', 'tenant' => true, 'site' => false],
        'to_uom_code' => ['field' => 'to_uom_id', 'table' => 'uoms', 'tenant' => true, 'site' => false],
        'item_code_ref' => ['field' => 'item_id', 'table' => 'items', 'tenant' => true, 'site' => true],
        'vat_code' => ['field' => 'vat_rate_id', 'table' => 'vat_rates', 'tenant' => true, 'site' => false],
    ];

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
            'uom-conversions' => ['title' => 'UoM Conversions', 'table' => 'uom_conversions', 'tenant' => true, 'site' => false, 'fields' => ['from_uom_code', 'to_uom_code', 'multiplier', 'divider', 'is_active'], 'unique' => ['from_uom_id', 'to_uom_id'], 'permission' => 'setup.master'],
            'vat' => ['title' => 'VAT', 'table' => 'vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'wht' => ['title' => 'WHT / PPH', 'table' => 'wht_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active'], 'key' => 'code', 'permission' => 'setup.master'],
            'item-vat' => ['title' => 'Item VAT', 'table' => 'item_vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['item_code_ref', 'vat_code', 'is_active'], 'unique' => ['item_id', 'vat_rate_id'], 'permission' => 'setup.master'],
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
        return view('system/excel_transfer/index', ['title' => 'Excel Import Export', 'resources' => $this->resources]);
    }

    public function importForm(string $resource): string
    {
        $config = $this->config($resource, 'manage');
        return view('system/excel_transfer/import', ['title' => 'Import ' . $config['title'] . ' from Excel', 'resource' => $resource, 'config' => $config, 'headers' => $this->excelHeaders($config)]);
    }

    public function template(string $resource)
    {
        $config = $this->config($resource, 'manage');
        return $this->xlsxResponse($this->slug($config['title']) . '-template.xlsx', [$this->excelHeaders($config), $this->sampleRow($config)], $config['title'] . ' Template');
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
        $rows = [$this->excelHeaders($config)];
        foreach ($builder->orderBy('id', 'ASC')->get()->getResultArray() as $row) $rows[] = $this->exportRow($config, $row);
        return $this->xlsxResponse($this->slug($config['title']) . '-export.xlsx', $rows, $config['title'] . ' Export');
    }

    public function import(string $resource)
    {
        $config = $this->config($resource, 'manage');
        $file = $this->request->getFile('excel_file');
        $uploadError = $this->validateUpload($file);
        if ($uploadError !== null) return redirect()->back()->with('error', $uploadError);
        try { $preview = $this->previewUploadedFile($config, $file->getTempName()); }
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
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) return redirect()->to(site_url('system/excel-transfer'))->with('error', 'Import preview expired. Upload ulang file Excel.');
        if (! empty($preview['errors'])) return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('error', 'Tidak bisa post data yang masih punya error.');
        try { $result = $this->persistRows($config, $preview['valid_rows']); }
        catch (RuntimeException $exception) { return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('error', $exception->getMessage()); }
        $this->clearPreview($token);
        return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('message', "Excel import posted. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
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
        return $this->xlsxResponse($this->slug($config['title']) . '-import-errors.xlsx', $rows, 'Import Errors');
    }

    private function previewUploadedFile(array $config, string $path): array
    {
        $rows = $this->readRows($path);
        if ($rows === [] || ! isset($rows[0])) throw new RuntimeException('Uploaded file is empty.');
        $headers = array_map(static fn ($value): string => trim((string) $value), $rows[0]);
        $allowed = $this->excelHeaders($config);
        $required = $this->requiredHeader($config);
        if ($required !== null && ! in_array($required, $headers, true)) throw new RuntimeException('Header harus memiliki kolom ' . $required . '.');
        $validRows = $rawValidRows = $rawRowsByNumber = $errors = [];
        $total = 0;
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            $data = [];
            foreach ($headers as $i => $header) {
                if ($header !== '' && in_array($header, $allowed, true)) {
                    $value = trim((string) ($row[$i] ?? ''));
                    $data[$header] = $value === '' ? null : $value;
                }
            }
            if ($data === []) continue;
            $total++;
            $rawRowsByNumber[$rowNumber] = $data;
            try {
                $normalized = $this->normalizeBeforeSave($config, $data, $rowNumber);
                $this->validateRequiredBusinessKey($config, $normalized);
                $validRows[] = $normalized;
                $rawValidRows[] = ['_row_number' => $rowNumber] + $data;
            } catch (RuntimeException $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }
        }
        return ['headers' => $headers, 'valid_rows' => $validRows, 'raw_valid_rows' => $rawValidRows, 'raw_rows_by_number' => $rawRowsByNumber, 'errors' => $errors, 'total' => $total];
    }

    private function readRows(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || $content === '') return [];
        if (str_starts_with($content, 'PK')) return (new XlsxSheetReader())->readFirstSheet($path);
        if (stripos($content, '<table') !== false) return $this->readHtmlTableRows($content);
        return $this->readTabRows($content);
    }

    private function readTabRows(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($content, "\xEF\xBB\xBF"));
        $rows = [];
        foreach ($lines as $line) {
            if (trim((string) $line) === '') continue;
            $rows[] = str_getcsv((string) $line, "\t");
        }
        return $rows;
    }

    private function readHtmlTableRows(string $content): array
    {
        $rows = [];
        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $trMatches)) return [];
        foreach ($trMatches[1] as $trHtml) {
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $trHtml, $tdMatches);
            $row = [];
            foreach ($tdMatches[1] as $cellHtml) $row[] = html_entity_decode(trim(strip_tags($cellHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($row !== []) $rows[] = $row;
        }
        return $rows;
    }

    private function persistRows(array $config, array $rows): array
    {
        $db = Database::connect();
        $created = $updated = $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            foreach ($rows as $data) {
                if (array_key_exists('is_active', $data) && $data['is_active'] === null) $data['is_active'] = 1;
                if (array_key_exists('active', $data) && $data['active'] === null) $data['active'] = 1;
                $data['updated_by'] = (string) auth()->id();
                $data['updated_at'] = $now;
                $existing = $this->findExisting($config, $data);
                if ($existing !== null) { $db->table($config['table'])->where('id', $existing['id'])->update($data); $updated++; continue; }
                $data['created_by'] = (string) auth()->id();
                $data['created_at'] = $now;
                $db->table($config['table'])->insert($data);
                $created++;
            }
        } catch (\Throwable $e) { $db->transRollback(); throw new RuntimeException($e->getMessage(), 0, $e); }
        if ($db->transStatus() === false) { $db->transRollback(); throw new RuntimeException('Import failed. No data was saved.'); }
        $db->transCommit();
        return compact('created', 'updated', 'skipped');
    }

    private function normalizeBeforeSave(array $config, array $data, int $rowNumber): array
    {
        $companyCode = $data['company_code'] ?? null;
        $siteCode = $data['site_code'] ?? null;

        if (! empty($config['tenant'])) {
            $data['company_id'] = $this->resolveCompanyId($companyCode, $rowNumber);
            unset($data['company_code']);
        }
        if (! empty($config['site'])) {
            $data['site_id'] = $this->resolveSiteId($siteCode, $data['company_id'] ?? null, $rowNumber);
            unset($data['site_code']);
        }
        $this->syncLegacyScopeCodes($config, $data, $companyCode, $siteCode);

        foreach ($this->relations as $alias => $relation) {
            if (! array_key_exists($alias, $data)) continue;
            $value = $data[$alias];
            unset($data[$alias]);
            if ($value === null || $value === '') continue;
            $id = $this->lookupIdByCode($relation['table'], (string) $value, (bool) $relation['tenant'], (bool) $relation['site'], $data['company_id'] ?? null, $data['site_id'] ?? null);
            if ($id === null) throw new RuntimeException("{$alias} '{$value}' tidak ditemukan.");
            $data[$relation['field']] = $id;
        }

        if ($config['table'] === 'provinces' && isset($data['country_id'])) $data['parent_id'] = $data['country_id'];
        if ($config['table'] === 'cities' && isset($data['province_id'])) $data['parent_id'] = $data['province_id'];
        unset($data['country_id'], $data['province_id']);

        if ($config['table'] === 'items') $data += ['code' => $data['item_code'] ?? null, 'name' => $data['item_name'] ?? null, 'is_active' => $data['active'] ?? 1];
        elseif ($config['table'] === 'customers') $data += ['code' => $data['customer'] ?? null, 'name' => $data['customern'] ?? null, 'terms_code' => $data['terms'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        elseif ($config['table'] === 'suppliers') $data += ['code' => $data['supplier'] ?? null, 'name' => $data['supplierna'] ?? null, 'terms_code' => $data['terms'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        return $data;
    }

    private function syncLegacyScopeCodes(array $config, array &$data, ?string $companyCode, ?string $siteCode): void
    {
        $db = Database::connect();
        if (! empty($config['tenant']) && $db->fieldExists('company', $config['table'])) {
            $data['company'] = (string) ($companyCode ?: $this->lookupCodeById('companies', (int) ($data['company_id'] ?? 0)));
        }
        if (! empty($config['site']) && $db->fieldExists('site', $config['table'])) {
            $data['site'] = (string) ($siteCode ?: $this->lookupCodeById('sites', (int) ($data['site_id'] ?? 0)));
        }
    }

    private function exportRow(array $config, array $row): array
    {
        $line = [];
        foreach ($this->excelHeaders($config) as $field) {
            if ($field === 'company_code') { $line[] = $this->lookupCodeById('companies', (int) ($row['company_id'] ?? 0)); continue; }
            if ($field === 'site_code') { $line[] = $this->lookupCodeById('sites', (int) ($row['site_id'] ?? 0)); continue; }
            if ($config['table'] === 'provinces' && $field === 'country_code') { $line[] = $this->lookupCodeById('countries', (int) ($row['parent_id'] ?? 0)); continue; }
            if ($config['table'] === 'cities' && $field === 'province_code') { $line[] = $this->lookupCodeById('provinces', (int) ($row['parent_id'] ?? 0)); continue; }
            if (isset($this->relations[$field])) { $line[] = $this->lookupCodeById($this->relations[$field]['table'], (int) ($row[$this->relations[$field]['field']] ?? 0)); continue; }
            $line[] = (string) ($row[$field] ?? '');
        }
        return $line;
    }

    private function excelHeaders(array $config): array
    {
        $headers = [];
        if (! empty($config['tenant'])) $headers[] = 'company_code';
        if (! empty($config['site'])) $headers[] = 'site_code';
        return array_values(array_unique(array_merge($headers, $config['fields'])));
    }

    private function resolveCompanyId(?string $code, int $rowNumber): int
    {
        $tenant = new TenantContext(session());
        if ($code !== null && $code !== '') {
            $id = $this->lookupIdByCode('companies', $code, false, false, null, null);
            if ($id !== null) return $id;
            throw new RuntimeException("company_code '{$code}' tidak ditemukan.");
        }
        $id = $tenant->activeCompanyId();
        if ($id !== null) return (int) $id;
        throw new RuntimeException('company_code wajib diisi atau active company harus dipilih.');
    }

    private function resolveSiteId(?string $code, ?int $companyId, int $rowNumber): int
    {
        $tenant = new TenantContext(session());
        if ($code !== null && $code !== '') {
            $id = $this->lookupIdByCode('sites', $code, $companyId !== null, false, $companyId, null);
            if ($id !== null) return $id;
            throw new RuntimeException("site_code '{$code}' tidak ditemukan untuk company terkait.");
        }
        $id = $tenant->activeSiteId();
        if ($id !== null) return (int) $id;
        throw new RuntimeException('site_code wajib diisi atau active site harus dipilih.');
    }

    private function lookupIdByCode(string $table, string $code, bool $tenant, bool $site, ?int $companyId, ?int $siteId): ?int
    {
        $db = Database::connect();
        $builder = $db->table($table)->where($this->codeColumn($table), $code);
        $tenantContext = new TenantContext(session());
        $company = $companyId ?? $tenantContext->activeCompanyId();
        $activeSite = $siteId ?? $tenantContext->activeSiteId();
        if ($tenant && $company !== null && $db->fieldExists('company_id', $table)) $builder->where('company_id', $company);
        if ($site && $activeSite !== null && $db->fieldExists('site_id', $table)) $builder->where('site_id', $activeSite);
        $row = $builder->get()->getRowArray();
        return $row !== null ? (int) $row['id'] : null;
    }

    private function lookupCodeById(string $table, int $id): string
    {
        if ($id < 1) return '';
        $row = Database::connect()->table($table)->where('id', $id)->get()->getRowArray();
        return (string) ($row[$this->codeColumn($table)] ?? $row['code'] ?? '');
    }

    private function codeColumn(string $table): string
    {
        return match ($table) {
            'items' => 'item_code',
            'customers' => 'customer',
            'suppliers' => 'supplier',
            'customer_terms', 'supplier_terms' => 'terms_code',
            'customer_promotions', 'supplier_promotions' => 'promo_code',
            default => 'code',
        };
    }

    private function xlsxResponse(string $filename, array $rows, string $sheetName)
    {
        $path = (new XlsxSheetWriter())->writeFirstSheet($rows, $sheetName);
        $content = file_get_contents($path) ?: '';
        @unlink($path);
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }

    private function validateRequiredBusinessKey(array $config, array $data): void { $key = $this->requiredHeader($config); if ($key !== null && (($data[$key] ?? null) === null || ($data[$key] ?? '') === '')) throw new RuntimeException('Required field ' . $key . ' is empty.'); }
    private function config(string $resource, string $mode): array { if (! isset($this->resources[$resource])) throw PageNotFoundException::forPageNotFound(); $config = $this->resources[$resource]; $permission = $config['permission'] . ($mode === 'view' ? '.view' : '.manage'); $user = auth()->user(); if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) throw PageNotFoundException::forPageNotFound(); return $config; }
    private function requiredHeader(array $config): ?string { if (! empty($config['key'])) return (string) $config['key']; return ! empty($config['unique']) ? (string) $config['fields'][0] : (in_array('code', $config['fields'], true) ? 'code' : null); }
    private function findExisting(array $config, array $data): ?array { $builder = Database::connect()->table($config['table']); if (! empty($config['key']) && ! empty($data[$config['key']])) $builder->where($config['key'], $data[$config['key']]); elseif (! empty($config['unique'])) { foreach ($config['unique'] as $field) { if (! array_key_exists($field, $data)) return null; $builder->where($field, $data[$field]); } } else return null; if ($config['tenant']) $builder->where('company_id', $data['company_id'] ?? 0); if ($config['site']) $builder->where('site_id', $data['site_id'] ?? null); return $builder->get()->getRowArray() ?: null; }
    private function validateUpload($file): ?string { if ($file === null || ! $file->isValid()) return 'Please upload a valid Excel file.'; if ($file->getSize() < 1) return 'Uploaded file is empty.'; if ($file->getSize() > self::MAX_UPLOAD_BYTES) return 'File is too large. Maximum 10 MB.'; if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'tsv', 'txt'], true)) return 'Gunakan file Excel .xlsx, .xls, .tsv, atau .txt.'; return null; }
    private function storePreview(string $token, array $preview): void
    {
        $path = $this->previewPath($token);
        if ($path === null) throw new RuntimeException('Token preview import Excel tidak valid.');

        $dir = $this->previewDir();
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Tidak bisa membuat folder cache import Excel.');
        }

        $json = json_encode(['created_at' => time(), 'preview' => $preview], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Tidak bisa menyimpan preview import Excel.');
        }
    }

    private function getPreview(string $token): ?array
    {
        $path = $this->previewPath($token);
        if ($path === null || ! is_file($path)) return null;

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! is_array($payload['preview'] ?? null)) return null;
        if ((int) ($payload['created_at'] ?? 0) < time() - self::PREVIEW_TTL_SECONDS) {
            @unlink($path);
            return null;
        }

        return $payload['preview'];
    }

    private function clearPreview(string $token): void
    {
        $path = $this->previewPath($token);
        if ($path !== null && is_file($path)) @unlink($path);
    }

    private function previewDir(): string
    {
        return rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . self::PREVIEW_DIR;
    }

    private function previewPath(string $token): ?string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        return $this->previewDir() . DIRECTORY_SEPARATOR . $token . '.json';
    }
    private function sampleRow(array $config): array { return array_map(function (string $header) use ($config): string { return match ($header) { 'company_code' => 'PENA', 'site_code' => 'HO', 'code' => 'EXAMPLE', 'name' => 'Example ' . $config['title'], 'description' => 'Example description', 'warehouse_code', 'stockwhs', 'shipwhs' => 'MAIN', 'country_code' => 'IDN', 'province_code' => 'DKI', 'city_code' => 'JKT', 'postal_code' => '12190', 'from_uom_code', 'to_uom_code', 'stockuom', 'purchaseuom', 'sellinguom' => 'PCS', 'item_code_ref', 'item_code' => 'ITEM001', 'vat_code', 'vat' => 'VAT11', 'customer' => 'CUST-999', 'customern' => 'Example Customer', 'supplier' => 'SUP-999', 'supplierna' => 'Example Supplier', 'terms_code', 'terms' => 'NET30', 'promo_code' => 'PROMO-001', 'line_no' => '1', 'email' => 'demo@example.com', 'is_active', 'active' => '1', 'rate', 'item_price', 'purchasep', 'sellingprice', 'multiplier', 'divider', 'terms_days' => '0', default => '', }; }, $this->excelHeaders($config)); }
    private function slug(string $value): string { return trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-'); }
    private function itemFields(): array { return ['item_code','item_name','item_coded','item_named','shelf_life','stockuom','purchaseuom','sellinguom','stockwhs','item_price','purchasep','sellingprice','vat','item_length','item_width','item_heigh','item_diam','item_lengt','item_widthh','item_heigh_uom','item_diam_uom','out_length','out_width','out_height','out_diame','out_lengt','out_widthh','out_height_uom','out_diame_uom','item_group','item_subg','item_class','item_subc','item_type','item_subty','item_atribu','active']; }
    private function customerFields(): array { return ['customer','customern','customerr','contactnar','description','shipwhs','officeaddre','officecity','officeprovir','officecount','officeposta','officeconta','officephon','officehp','email','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','salescode','salesname','bank1','bankaccou','bank2','bankaccou2','billingcust','billingtoc','billingaddre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','mailcustom','mailcode','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','shiptocust','shiptocode','shiptoaddr','shiptocity','shiptoprovi','shiptocour','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
    private function supplierFields(): array { return ['supplier','supplierna','supplierref','contactnar','description','officeaddre','officecity','officeprovir','officecoun','officeposta','officeconta','officephon','officehp','email','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','billingadre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','employee','purchasing','bank1','bankaccou','bank2','bankaccou2','shiptoaddr','shiptocity','shiptoprovi','shiptocoun','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
}
