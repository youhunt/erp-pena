<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\ChartAccountModel;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryStockService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class DataImportController extends BaseController
{
    public function index(): string
    {
        return view('system/data_import/index', [
            'title' => 'Data Import Export Center',
            'masters' => $this->masterModules(),
            'finance' => $this->financeModules(),
            'inventory' => $this->inventoryModules(),
        ]);
    }

    public function coaTemplate()
    {
        return $this->csvResponse('coa-template.csv', [
            ['account_no', 'account_name', 'account_type', 'normal_balance', 'parent_account_no', 'is_postable', 'is_active'],
            ['1000', 'Cash and Bank', 'asset', 'debit', '', '0', '1'],
            ['1100', 'Cash on Hand', 'asset', 'debit', '1000', '1', '1'],
            ['2000', 'Accounts Payable', 'liability', 'credit', '', '1', '1'],
            ['4000', 'Sales Revenue', 'revenue', 'credit', '', '1', '1'],
            ['5000', 'Cost of Goods Sold', 'expense', 'debit', '', '1', '1'],
        ]);
    }

    public function coaExport()
    {
        $tenant = new TenantContext(session());
        $model = new ChartAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        $rows = [['account_no', 'account_name', 'account_type', 'normal_balance', 'parent_account_no', 'is_postable', 'is_active']];
        foreach ($model->orderBy('account_no', 'ASC')->findAll() as $account) {
            $rows[] = [
                $account['account_no'] ?? '',
                $account['account_name'] ?? '',
                $account['account_type'] ?? '',
                $account['normal_balance'] ?? '',
                $account['parent_account_no'] ?? '',
                (string) ($account['is_postable'] ?? 1),
                (string) ($account['is_active'] ?? 1),
            ];
        }

        return $this->csvResponse('coa-export.csv', $rows);
    }

    public function coaImportForm(): string
    {
        return view('system/data_import/import', [
            'title' => 'Import Chart of Account',
            'module' => 'Chart of Account',
            'templateUrl' => site_url('system/data-import/coa/template'),
            'actionUrl' => site_url('system/data-import/coa/import'),
            'headers' => ['account_no', 'account_name', 'account_type', 'normal_balance', 'parent_account_no', 'is_postable', 'is_active'],
        ]);
    }

    public function coaImport()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->with('error', 'Active company is required before importing COA.');
        }

        $file = $this->request->getFile('csv_file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Please upload a valid CSV file.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'Only CSV files are supported for now.');
        }

        try {
            $result = $this->importCoaCsv($file->getTempName(), $companyId);
        } catch (RuntimeException $e) {
            $this->auditImport('chart_accounts', 'coa.import_failed', ['created' => 0, 'updated' => 0, 'skipped' => 0], $file->getClientName(), $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->auditImport('chart_accounts', 'coa.import', $result, $file->getClientName());
        return redirect()->to('/system/data-import')->with('message', "COA import finished. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    public function openingStockTemplate()
    {
        return $this->csvResponse('opening-stock-template.csv', [
            ['movement_date', 'warehouse_code', 'location_code', 'item_code', 'item_name', 'uom_code', 'qty', 'unit_cost', 'reference_no', 'notes'],
            [date('Y-m-d'), 'MAIN', 'STAGING', 'ITEM-0001', 'Example Item', 'PCS', '100', '15000', 'OPENING-001', 'Opening stock import'],
        ]);
    }

    public function openingStockImportForm(): string
    {
        return view('system/data_import/import', [
            'title' => 'Import Opening Stock',
            'module' => 'Opening Stock',
            'templateUrl' => site_url('system/data-import/opening-stock/template'),
            'actionUrl' => site_url('system/data-import/opening-stock/import'),
            'headers' => ['movement_date', 'warehouse_code', 'location_code', 'item_code', 'item_name', 'uom_code', 'qty', 'unit_cost', 'reference_no', 'notes'],
        ]);
    }

    public function openingStockImport()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->with('error', 'Active company is required before importing opening stock.');
        }

        $file = $this->request->getFile('csv_file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Please upload a valid CSV file.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'Only CSV files are supported for now.');
        }

        try {
            $result = $this->importOpeningStockCsv($file->getTempName(), $companyId, $tenant->activeSiteId());
        } catch (RuntimeException $e) {
            $this->auditImport('inventory_stock_movements', 'opening_stock.import_failed', ['created' => 0, 'updated' => 0, 'skipped' => 0], $file->getClientName(), $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->auditImport('inventory_stock_movements', 'opening_stock.import', $result, $file->getClientName());
        return redirect()->to('/system/data-import')->with('message', "Opening stock import finished. {$result['created']} movements posted, {$result['skipped']} skipped.");
    }

    public function openingStockExport()
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_stock_movements')
            ->where('movement_type', 'opening_stock')
            ->orderBy('movement_date', 'DESC')
            ->orderBy('id', 'DESC');

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        $rows = [['movement_date', 'warehouse_code', 'location_code', 'item_code', 'item_name', 'uom_code', 'qty', 'unit_cost', 'reference_no', 'notes']];
        foreach ($builder->get()->getResultArray() as $movement) {
            $rows[] = [
                substr((string) ($movement['movement_date'] ?? ''), 0, 10),
                $this->codeById('warehouses', (int) ($movement['warehouse_id'] ?? 0)),
                $this->codeById('locations', (int) ($movement['location_id'] ?? 0)),
                $movement['item_code'] ?? '',
                $movement['item_name'] ?? '',
                $movement['uom_code'] ?? '',
                (string) ($movement['qty'] ?? 0),
                (string) ($movement['unit_cost'] ?? 0),
                $movement['reference_no'] ?? '',
                $movement['notes'] ?? '',
            ];
        }

        return $this->csvResponse('opening-stock-export.csv', $rows);
    }

    private function importCoaCsv(string $path, int $companyId): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $headers = $this->csvHeaders($handle);
        foreach (['account_no', 'account_name', 'account_type', 'normal_balance'] as $required) {
            if (! in_array($required, $headers, true)) {
                fclose($handle);
                throw new RuntimeException('CSV header must include ' . $required . ' column.');
            }
        }

        $allowed = ['account_no', 'account_name', 'account_type', 'normal_balance', 'parent_account_no', 'is_postable', 'is_active'];
        $model = new ChartAccountModel();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = $this->rowData($headers, $row, $allowed);
            if (empty($data['account_no']) || empty($data['account_name'])) {
                $skipped++;
                continue;
            }

            $data['account_type'] = strtolower((string) ($data['account_type'] ?? 'asset'));
            $data['normal_balance'] = strtolower((string) ($data['normal_balance'] ?? 'debit'));
            if (! in_array($data['account_type'], ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
                fclose($handle);
                throw new RuntimeException("Row {$rowNumber}: invalid account_type.");
            }
            if (! in_array($data['normal_balance'], ['debit', 'credit'], true)) {
                fclose($handle);
                throw new RuntimeException("Row {$rowNumber}: invalid normal_balance.");
            }

            $data['company_id'] = $companyId;
            $data['is_postable'] = (int) ($data['is_postable'] ?? 1);
            $data['is_active'] = (int) ($data['is_active'] ?? 1);

            $existing = $model->where('company_id', $companyId)->where('account_no', $data['account_no'])->first();
            if ($existing !== null) {
                $model->update((int) $existing['id'], $data);
                $updated++;
                continue;
            }

            $model->insert($data);
            $created++;
        }

        fclose($handle);
        return compact('created', 'updated', 'skipped');
    }

    private function importOpeningStockCsv(string $path, int $companyId, ?int $siteId): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $headers = $this->csvHeaders($handle);
        foreach (['item_code', 'qty'] as $required) {
            if (! in_array($required, $headers, true)) {
                fclose($handle);
                throw new RuntimeException('CSV header must include ' . $required . ' column.');
            }
        }

        $allowed = ['movement_date', 'warehouse_code', 'location_code', 'item_code', 'item_name', 'uom_code', 'qty', 'unit_cost', 'reference_no', 'notes'];
        $stock = new InventoryStockService();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = $this->rowData($headers, $row, $allowed);
            $qty = (float) ($data['qty'] ?? 0);
            if (empty($data['item_code']) || $qty <= 0) {
                $skipped++;
                continue;
            }

            $item = $this->itemByCode((string) $data['item_code'], $companyId, $siteId);
            if ($item === null) {
                fclose($handle);
                throw new RuntimeException("Row {$rowNumber}: item_code '{$data['item_code']}' was not found.");
            }

            $warehouseId = ! empty($data['warehouse_code']) ? $this->idByCode('warehouses', (string) $data['warehouse_code'], $companyId, $siteId) : null;
            if (! empty($data['warehouse_code']) && $warehouseId === null) {
                fclose($handle);
                throw new RuntimeException("Row {$rowNumber}: warehouse_code '{$data['warehouse_code']}' was not found.");
            }

            $locationId = ! empty($data['location_code']) ? $this->idByCode('locations', (string) $data['location_code'], $companyId, $siteId) : null;
            if (! empty($data['location_code']) && $locationId === null) {
                fclose($handle);
                throw new RuntimeException("Row {$rowNumber}: location_code '{$data['location_code']}' was not found.");
            }

            $stock->stockIn([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'item_id' => $item['id'] ?? null,
                'item_code' => $item['item_code'] ?? $item['code'] ?? $data['item_code'],
                'item_name' => $data['item_name'] ?: ($item['item_name'] ?? $item['name'] ?? null),
                'uom_code' => $data['uom_code'] ?: ($item['stockuom'] ?? 'PCS'),
                'qty' => $qty,
                'unit_cost' => (float) ($data['unit_cost'] ?? $item['item_price'] ?? 0),
                'movement_date' => ($data['movement_date'] ?? date('Y-m-d')) . ' 00:00:00',
                'movement_type' => 'opening_stock',
                'reference_type' => 'opening_stock_import',
                'reference_no' => $data['reference_no'] ?: 'OPENING-' . date('Ymd'),
                'notes' => $data['notes'] ?: 'Opening stock import',
            ], auth()->id());

            $created++;
        }

        fclose($handle);
        return compact('created', 'updated', 'skipped');
    }

    private function masterModules(): array
    {
        return [
            ['group' => 'Setup', 'name' => 'Warehouse', 'route' => 'setup/warehouses'],
            ['group' => 'Setup', 'name' => 'Location', 'route' => 'setup/locations'],
            ['group' => 'Setup', 'name' => 'Unit of Measure', 'route' => 'setup/uoms'],
            ['group' => 'Setup', 'name' => 'VAT', 'route' => 'setup/vat'],
            ['group' => 'Setup', 'name' => 'Address Master', 'route' => 'setup/address-master'],
            ['group' => 'Inventory', 'name' => 'Item Master', 'route' => 'setup/items'],
            ['group' => 'Sales', 'name' => 'Customer Master', 'route' => 'setup/customers'],
            ['group' => 'Purchase', 'name' => 'Supplier Master', 'route' => 'setup/suppliers'],
        ];
    }

    private function financeModules(): array
    {
        return [
            ['group' => 'GL', 'name' => 'Chart of Account', 'route' => 'system/data-import/coa'],
        ];
    }

    private function inventoryModules(): array
    {
        return [
            ['group' => 'Inventory', 'name' => 'Opening Stock', 'route' => 'system/data-import/opening-stock'],
        ];
    }

    private function csvHeaders($handle): array
    {
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new RuntimeException('CSV file is empty.');
        }
        return array_map(static fn ($value): string => trim((string) $value), $headers);
    }

    private function rowData(array $headers, array $row, array $allowed): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            if (! in_array($header, $allowed, true)) {
                continue;
            }
            $value = trim((string) ($row[$index] ?? ''));
            $data[$header] = $value === '' ? null : $value;
        }
        return $data;
    }

    private function itemByCode(string $code, int $companyId, ?int $siteId): ?array
    {
        $db = Database::connect();
        $builder = $db->table('items')->where('item_code', $code);
        if ($db->fieldExists('company_id', 'items')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'items')) {
            $builder->where('site_id', $siteId);
        }
        return $builder->get()->getRowArray();
    }

    private function idByCode(string $table, string $code, int $companyId, ?int $siteId): ?int
    {
        $db = Database::connect();
        $builder = $db->table($table)->where('code', $code);
        if ($db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $siteId);
        }
        $row = $builder->get()->getRowArray();
        return $row !== null ? (int) $row['id'] : null;
    }

    private function codeById(string $table, int $id): string
    {
        if ($id < 1) {
            return '';
        }
        $row = Database::connect()->table($table)->where('id', $id)->get()->getRowArray();
        return (string) ($row['code'] ?? '');
    }

    private function auditImport(string $table, string $action, array $result, string $filename, ?string $error = null): void
    {
        (new AuditLogService())->log('system.data_import', $action, [
            'table_name' => $table,
            'description' => $error === null ? $action . ' completed.' : $action . ' failed: ' . $error,
            'new_values' => [
                'filename' => $filename,
                'created' => $result['created'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'error' => $error,
            ],
        ]);
    }

    private function csvResponse(string $filename, array $rows)
    {
        $handle = fopen('php://temp', 'wb+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }
}
