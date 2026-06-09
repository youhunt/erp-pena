<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ExcelTransferController extends BaseController
{
    private const MAX_XLSX_UPLOAD_BYTES = 10485760;
    private const SESSION_KEY = 'excel_import_previews';

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

    public function index(): string { return view('system/excel_transfer/index', ['title' => 'Excel Import Export', 'resources' => $this->resources]); }
    public function importForm(string $resource): string { $config = $this->config($resource, 'manage'); return view('system/excel_transfer/import', ['title' => 'Import ' . $config['title'] . ' from Excel', 'resource' => $resource, 'config' => $config, 'headers' => $config['fields']]); }

    public function template(string $resource)
    {
        $config = $this->config($resource, 'manage');
        $sheet = $this->baseWorkbook($config['title'] . ' Template');
        $this->writeRows($sheet, [$config['fields'], $this->sampleRow($config)]);
        return $this->xlsxResponse($this->slug($config['title']) . '-template.xlsx', $sheet->getParent());
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
        $sheet = $this->baseWorkbook($config['title'] . ' Export');
        $this->writeRows($sheet, $rows);
        return $this->xlsxResponse($this->slug($config['title']) . '-export.xlsx', $sheet->getParent());
    }

    public function import(string $resource)
    {
        $config = $this->config($resource, 'manage');
        if (! $this->hasRequiredTenant($config)) return redirect()->back()->with('error', $this->tenantRequirementMessage($config));
        $file = $this->request->getFile('excel_file');
        $uploadError = $this->validateExcelUpload($file);
        if ($uploadError !== null) return redirect()->back()->with('error', $uploadError);
        try { $preview = $this->previewXlsx($config, $file->getTempName()); }
        catch (RuntimeException $exception) { return redirect()->back()->with('error', $exception->getMessage()); }

        $token = bin2hex(random_bytes(16));
        $returnTo = trim((string) $this->request->getPost('return_to'), '/') ?: 'system/excel-transfer';
        $this->storePreview($token, ['resource' => $resource, 'config' => $config, 'return_to' => $returnTo] + $preview);

        return view('system/excel_transfer/preview', [
            'title' => 'Preview Import ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'headers' => $preview['headers'],
            'summary' => ['total' => $preview['total'], 'valid' => count($preview['valid_rows']), 'error' => count($preview['errors'])],
            'errors' => $preview['errors'],
            'previewRows' => array_slice($preview['raw_valid_rows'], 0, 25),
            'returnTo' => $returnTo,
            'previewToken' => $token,
            'commitUrl' => 'system/excel-transfer/' . $resource . '/commit',
            'downloadErrorUrl' => 'system/excel-transfer/' . $resource . '/errors/' . $token,
        ]);
    }

    public function commit(string $resource)
    {
        $config = $this->config($resource, 'manage');
        $token = (string) $this->request->getPost('preview_token');
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) return redirect()->to(site_url('system/excel-transfer'))->with('error', 'Import preview expired. Please upload the Excel file again.');
        if (! empty($preview['errors'])) return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('error', 'Cannot post import with validation errors.');
        try { $result = $this->persistRows($config, $preview['valid_rows']); }
        catch (RuntimeException $exception) { return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('error', $exception->getMessage()); }
        $this->clearPreview($token);
        $this->audit($config, 'excel.import', $result, 'preview:' . $token);
        return redirect()->to(site_url($preview['return_to'] ?? 'system/excel-transfer'))->with('message', "Excel import posted. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    public function downloadErrors(string $resource, string $token)
    {
        $config = $this->config($resource, 'manage');
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) return redirect()->to(site_url('system/excel-transfer'))->with('error', 'Import preview expired. Please upload the Excel file again.');
        $rows = [array_merge(['excel_row', 'error_message'], $preview['headers'] ?? [])];
        foreach ($preview['errors'] as $error) {
            $raw = $preview['raw_rows_by_number'][$error['row']] ?? [];
            $line = [(string) $error['row'], $error['message']];
            foreach (($preview['headers'] ?? []) as $header) $line[] = (string) ($raw[$header] ?? '');
            $rows[] = $line;
        }
        $sheet = $this->baseWorkbook('Import Errors');
        $this->writeRows($sheet, $rows);
        return $this->xlsxResponse($this->slug($config['title']) . '-import-errors.xlsx', $sheet->getParent());
    }

    private function previewXlsx(array $config, string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        if ($rows === [] || ! isset($rows[0])) throw new RuntimeException('Excel file is empty.');
        $headers = array_map(static fn ($value): string => trim((string) $value), $rows[0]);
        $required = $this->requiredHeader($config);
        if ($required !== null && ! in_array($required, $headers, true)) throw new RuntimeException('Excel header must include ' . $required . ' column.');
        $validRows = [];
        $rawValidRows = [];
        $rawRowsByNumber = [];
        $errors = [];
        $total = 0;
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            $data = [];
            foreach ($headers as $columnIndex => $header) {
                if ($header === '' || ! in_array($header, $config['fields'], true)) continue;
                $value = trim((string) ($row[$columnIndex] ?? ''));
                $data[$header] = $value === '' ? null : $value;
            }
            if ($data === []) continue;
            $total++;
            $raw = ['_row_number' => $rowNumber] + $data;
            $rawRowsByNumber[$rowNumber] = $data;
            try {
                $normalized = $this->normalizeImportRow($config, $data, $rowNumber);
                $this->validateRequiredBusinessKey($config, $normalized, $rowNumber);
                $validRows[] = $normalized;
                $rawValidRows[] = $raw;
            } catch (RuntimeException $exception) {
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }
        return compact('headers', 'validRows', 'rawValidRows', 'rawRowsByNumber', 'errors', 'total') + ['valid_rows' => $validRows, 'raw_valid_rows' => $rawValidRows, 'raw_rows_by_number' => $rawRowsByNumber];
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
                if ($config['tenant']) $data['company_id'] = $tenant->activeCompanyId();
                if ($config['site']) $data['site_id'] = $tenant->activeSiteId();
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
        } catch (\Throwable $exception) { $db->transRollback(); throw new RuntimeException($exception->getMessage(), 0, $exception); }
        if ($db->transStatus() === false) { $db->transRollback(); throw new RuntimeException('Excel import transaction failed. No data was saved.'); }
        $db->transCommit();
        return compact('created', 'updated', 'skipped');
    }

    private function normalizeImportRow(array $config, array $data, int $rowNumber): array
    {
        foreach ($this->relations as $alias => $relation) {
            if (! array_key_exists($alias, $data)) continue;
            $value = $data[$alias]; unset($data[$alias]);
            if ($value === null || $value === '') continue;
            $id = $this->lookupIdByCode($relation['table'], (string) $value, (bool) $relation['tenant'], (bool) $relation['site']);
            if ($id === null) throw new RuntimeException("{$alias} '{$value}' was not found.");
            $data[$relation['field']] = $id;
        }
        if ($config['table'] === 'provinces' && isset($data['country_id'])) $data['parent_id'] = $data['country_id'];
        if ($config['table'] === 'cities' && isset($data['province_id'])) $data['parent_id'] = $data['province_id'];
        unset($data['country_id'], $data['province_id']);
        if ($config['table'] === 'items') $data += ['code' => $data['item_code'] ?? null, 'name' => $data['item_name'] ?? null, 'is_active' => $data['active'] ?? 1];
        elseif ($config['table'] === 'customers') $data += ['code' => $data['customer'] ?? null, 'name' => $data['customern'] ?? null, 'terms_code' => $data['terms'] ?? null, 'tax_number' => $data['taxnumber'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        elseif ($config['table'] === 'suppliers') $data += ['code' => $data['supplier'] ?? null, 'name' => $data['supplierna'] ?? null, 'terms_code' => $data['terms'] ?? null, 'tax_number' => $data['taxnumber'] ?? null, 'phone' => $data['officephon'] ?? null, 'email' => $data['email'] ?? null, 'address' => $data['officeaddre'] ?? null, 'is_active' => $data['active'] ?? 1];
        return $data;
    }

    private function validateRequiredBusinessKey(array $config, array $data, int $rowNumber): void
    {
        $key = $this->requiredHeader($config);
        if ($key !== null) {
            $normalizedKey = $this->relations[$key]['field'] ?? $key;
            if (! array_key_exists($normalizedKey, $data) || $data[$normalizedKey] === null || $data[$normalizedKey] === '') throw new RuntimeException("Required field {$key} is empty.");
        }
    }

    private function exportRow(array $config, array $row): array
    {
        $line = [];
        foreach ($config['fields'] as $field) {
            if (isset($this->relations[$field])) { $line[] = $this->lookupCodeById($this->relations[$field]['table'], (int) ($row[$this->relations[$field]['field']] ?? 0)); continue; }
            if ($config['table'] === 'provinces' && $field === 'country_code') { $line[] = $this->lookupCodeById('countries', (int) ($row['parent_id'] ?? 0)); continue; }
            if ($config['table'] === 'cities' && $field === 'province_code') { $line[] = $this->lookupCodeById('provinces', (int) ($row['parent_id'] ?? 0)); continue; }
            $line[] = (string) ($row[$field] ?? '');
        }
        return $line;
    }

    private function storePreview(string $token, array $preview): void { $all = session(self::SESSION_KEY) ?? []; $all[$token] = $preview; session()->set(self::SESSION_KEY, $all); }
    private function getPreview(string $token): ?array { $all = session(self::SESSION_KEY) ?? []; return is_array($all[$token] ?? null) ? $all[$token] : null; }
    private function clearPreview(string $token): void { $all = session(self::SESSION_KEY) ?? []; unset($all[$token]); session()->set(self::SESSION_KEY, $all); }

    private function baseWorkbook(string $sheetName): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet { $spreadsheet = new Spreadsheet(); $sheet = $spreadsheet->getActiveSheet(); $sheet->setTitle(substr($sheetName, 0, 31)); return $sheet; }
    private function writeRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $rows): void { foreach ($rows as $rowIndex => $row) $sheet->fromArray($row, null, 'A' . ($rowIndex + 1)); $highestColumn = $sheet->getHighestColumn(); $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true); $sheet->getStyle('A1:' . $highestColumn . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF2FF'); $sheet->freezePane('A2'); foreach (range('A', $highestColumn) as $column) $sheet->getColumnDimension($column)->setAutoSize(true); }
    private function xlsxResponse(string $filename, Spreadsheet $spreadsheet) { $path = tempnam(sys_get_temp_dir(), 'pena_excel_'); (new Xlsx($spreadsheet))->save($path); $content = file_get_contents($path) ?: ''; @unlink($path); return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setBody($content); }
    private function config(string $resource, string $mode): array { if (! isset($this->resources[$resource])) throw PageNotFoundException::forPageNotFound(); $config = $this->resources[$resource]; $permission = $config['permission'] . ($mode === 'view' ? '.view' : '.manage'); $user = auth()->user(); if (! $user || (! $user->can($permission) && ! $user->inGroup('superadmin'))) throw PageNotFoundException::forPageNotFound(); return $config; }
    private function requiredHeader(array $config): ?string { if (! empty($config['key'])) return (string) $config['key']; return ! empty($config['unique']) ? (string) $config['fields'][0] : (in_array('code', $config['fields'], true) ? 'code' : null); }
    private function findExisting(array $config, array $data): ?array { $builder = Database::connect()->table($config['table']); if (! empty($config['key']) && ! empty($data[$config['key']])) $builder->where($config['key'], $data[$config['key']]); elseif (! empty($config['unique'])) { foreach ($config['unique'] as $field) { if (! array_key_exists($field, $data)) return null; $builder->where($field, $data[$field]); } } else return null; if ($config['tenant']) $builder->where('company_id', $data['company_id'] ?? 0); if ($config['site']) $builder->where('site_id', $data['site_id'] ?? null); return $builder->get()->getRowArray() ?: null; }
    private function lookupIdByCode(string $table, string $code, bool $tenant, bool $site): ?int { $db = Database::connect(); $builder = $db->table($table)->where('code', $code); $tenantContext = new TenantContext(session()); if ($tenant && $tenantContext->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) $builder->where('company_id', $tenantContext->activeCompanyId()); if ($site && $tenantContext->activeSiteId() !== null && $db->fieldExists('site_id', $table)) $builder->where('site_id', $tenantContext->activeSiteId()); $row = $builder->get()->getRowArray(); return $row !== null ? (int) $row['id'] : null; }
    private function lookupCodeById(string $table, int $id): string { if ($id < 1) return ''; $row = Database::connect()->table($table)->where('id', $id)->get()->getRowArray(); return (string) ($row['code'] ?? ''); }
    private function hasRequiredTenant(array $config): bool { $tenant = new TenantContext(session()); if ($config['tenant'] && $tenant->activeCompanyId() === null) return false; if ($config['site'] && $tenant->activeSiteId() === null) return false; return true; }
    private function tenantRequirementMessage(array $config): string { return $config['site'] ? 'Active company and active site are required before importing this Excel file.' : 'Active company is required before importing this Excel file.'; }
    private function validateExcelUpload($file): ?string { if ($file === null || ! $file->isValid()) return 'Please upload a valid Excel file.'; if ($file->getSize() < 1) return 'Uploaded Excel file is empty.'; if ($file->getSize() > self::MAX_XLSX_UPLOAD_BYTES) return 'Excel file is too large. Maximum allowed size is 10 MB.'; if (! in_array(strtolower($file->getClientExtension()), ['xlsx'], true)) return 'Only .xlsx Excel files are supported.'; return null; }
    private function audit(array $config, string $action, array $result, string $filename, ?string $error = null): void { (new AuditLogService())->log('system.excel_transfer', $action, ['table_name' => $config['table'], 'description' => $error === null ? $config['title'] . ' Excel import completed.' : $config['title'] . ' Excel import failed: ' . $error, 'new_values' => ['title' => $config['title'], 'table' => $config['table'], 'filename' => $filename, 'created' => $result['created'] ?? 0, 'updated' => $result['updated'] ?? 0, 'skipped' => $result['skipped'] ?? 0, 'error' => $error]]); }
    private function sampleRow(array $config): array { return array_map(function (string $header) use ($config): string { return match ($header) { 'code' => 'EXAMPLE', 'name' => 'Example ' . $config['title'], 'description' => 'Example description', 'warehouse_code', 'stockwhs', 'shipwhs' => 'MAIN', 'country_code' => 'IDN', 'province_code' => 'DKI', 'city_code' => 'JKT', 'postal_code' => '12190', 'from_uom_code', 'to_uom_code', 'stockuom', 'purchaseuom', 'sellinguom' => 'PCS', 'item_code_ref', 'item_code' => 'ITEM001', 'vat_code', 'vat' => 'VAT11', 'customer' => 'CUST-999', 'customern' => 'Example Customer', 'supplier' => 'SUP-999', 'supplierna' => 'Example Supplier', 'terms_code', 'terms' => 'NET30', 'promo_code' => 'PROMO-001', 'line_no' => '1', 'email' => 'demo@example.com', 'is_active', 'active' => '1', 'rate', 'item_price', 'purchasep', 'sellingprice', 'multiplier', 'divider', 'terms_days' => '0', default => '', }; }, $config['fields']); }
    private function slug(string $value): string { return trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-'); }
    private function itemFields(): array { return ['item_code','item_name','item_coded','item_named','shelf_life','stockuom','purchaseuom','sellinguom','stockwhs','item_price','purchasep','sellingprice','vat','item_length','item_width','item_heigh','item_diam','item_lengt','item_widthh','item_heigh_uom','item_diam_uom','out_length','out_width','out_height','out_diame','out_lengt','out_widthh','out_height_uom','out_diame_uom','item_group','item_subg','item_class','item_subc','item_type','item_subty','item_atribu','active']; }
    private function customerFields(): array { return ['customer','customern','customerr','contactnar','description','shipwhs','officeaddre','officecity','officeprovir','officecount','officeposta','officeconta','officephon','officehp','email','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','salescode','salesname','bank1','bankaccou','bank2','bankaccou2','billingcust','billingtoc','billingaddre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','mailcustom','mailcode','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','shiptocust','shiptocode','shiptoaddr','shiptocity','shiptoprovi','shiptocour','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
    private function supplierFields(): array { return ['supplier','supplierna','supplierref','contactnar','description','officeaddre','officecity','officeprovir','officecoun','officeposta','officeconta','officephon','officehp','email','mailaddres','mailcity','mailprovin','mailcountr','mailpostal','mailcontac','mailphone','mailhp','billingadre','billingcity','billingprovi','billingcoun','billingposta','billingconta','billingphon','billinghp','taxcode','taxnumber','vat','limitamound','limitqty','terms','limitdays','employee','purchasing','bank1','bankaccou','bank2','bankaccou2','shiptoaddr','shiptocity','shiptoprovi','shiptocoun','shiptopost','shiptocont','shiptophon','shiptohp','active']; }
}
