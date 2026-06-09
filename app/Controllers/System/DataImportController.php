<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\ChartAccountModel;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryStockService;
use App\Services\TenantContext;
use Config\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class DataImportController extends BaseController
{
    private const MAX_IMPORT_UPLOAD_BYTES = 5242880;

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
        return $this->xlsxResponse('coa-template.xlsx', [
            ['account_no', 'account_name', 'account_type', 'normal_balance', 'parent_account_no', 'is_postable', 'is_active'],
            ['1000', 'Cash and Bank', 'asset', 'debit', '', '0', '1'],
            ['1100', 'Cash on Hand', 'asset', 'debit', '1000', '1', '1'],
            ['2000', 'Accounts Payable', 'liability', 'credit', '', '1', '1'],
            ['4000', 'Sales Revenue', 'revenue', 'credit', '', '1', '1'],
            ['5000', 'Cost of Goods Sold', 'expense', 'debit', '', '1', '1'],
        ], 'COA Template');
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

        return $this->xlsxResponse('coa-export.xlsx', $rows, 'COA Export');
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
        $uploadError = $this->validateSpreadsheetUpload($file);
        if ($uploadError !== null) {
            return redirect()->back()->with('error', $uploadError);
        }

        try {
            $result = $this->importCoaSheet($file->getTempName(), $companyId, $file->getClientExtension());
        } catch (RuntimeException $e) {
            $this->auditImport('chart_accounts', 'coa.import_failed', ['created' => 0, 'updated' => 0, 'skipped' => 0], $file->getClientName(), $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->auditImport('chart_accounts', 'coa.import', $result, $file->getClientName());
        return redirect()->to('/system/data-import')->with('message', "COA import finished. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    public function openingStockTemplate()
    {
        return $this->xlsxResponse('opening-stock-template.xlsx', [
            ['movement_date', 'warehouse_code', 'location_code', 'item_code', 'item_name', 'uom_code', 'qty', 'unit_cost', 'reference_no', 'notes'],
            [date('Y-m-d'), 'MAIN', 'STAGING', 'ITEM-0001', 'Example Item', 'PCS', '100', '15000', 'OPENING-001', 'Opening stock import'],
        ], 'Opening Stock Template');
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
        $siteId = $tenant->activeSiteId();
        if ($companyId === null || $companyId < 1 || $siteId === null || $siteId < 1) {
            return redirect()->back()->with('error', 'Active company and active site are required before importing opening stock.');
        }

        $file = $this->request->getFile('csv_file');
        $uploadError = $this->validateSpreadsheetUpload($file);
        if ($uploadError !== null) {
            return redirect()->back()->with('error', $uploadError);
        }

        try {
            $result = $this->importOpeningStockSheet($file->getTempName(), $companyId, $siteId, $file->getClientExtension());
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

        return $this->xlsxResponse('opening-stock-export.xlsx', $rows, 'Opening Stock Export');
    }

    private function importCoaSheet(string $path, int $companyId, string $extension): array
    {
        $rows = $this->sheetRows($path, $extension);
        [$headers, $bodyRows] = $this->splitHeaderRows($rows);

        foreach (['account_no', 'account_name', 'account_type', 'normal_balance'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw new RuntimeException('Spreadsheet header must include ' . $required . ' column.');
            }
        }

        $allowed = ['account_no', 'account_name', 'account_type', 'normal_balance', 'parent_account_no', 'is_postable', 'is_active'];
        $model = new ChartAccountModel();
        $db = Database::connect();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNumber = 1;

        $db->transBegin();

        try {
            foreach ($bodyRows as $row) {
                $rowNumber++;
                $data = $this->rowData($headers, $row, $allowed);
                if (empty($data['account_no']) || empty($data['account_name'])) {
                    $skipped++;
                    continue;
                }

                $data['account_type'] = strtolower((string) ($data['account_type'] ?? 'asset'));
                $data['normal_balance'] = strtolower((string) ($data['normal_balance'] ?? 'debit'));
                if (! in_array($data['account_type'], ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
                    throw new RuntimeException("Row {$rowNumber}: invalid account_type.");
                }
                if (! in_array($data['normal_balance'], ['debit', 'credit'], true)) {
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
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('COA import transaction failed. No data was saved.');
        }

        $db->transCommit();
        return compact('created', 'updated', 'skipped');
    }

    private function importOpeningStockSheet(string $path, int $companyId, int $siteId, string $extension): array
    {
        $prepared = $this->prepareOpeningStockRows($path, $companyId, $siteId, $extension);
        $stock = new InventoryStockService();
        $created = 0;

        foreach ($prepared['movements'] as $movement) {
            $stock->stockIn($movement, auth()->id());
            $created++;
        }

        $skipped = $prepared['skipped'];
        $updated = 0;

        return compact('created', 'updated', 'skipped');
    }

    /**
     * @return array{movements: list<array<string, mixed>>, skipped: int}
     */
    private function prepareOpeningStockRows(string $path, int $companyId, int $siteId, string $extension): array
    {
        $rows = $this->sheetRows($path, $extension);
        [$headers, $bodyRows] = $this->splitHeaderRows($rows);

        foreach (['item_code', 'qty'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw new RuntimeException('Spreadsheet header must include ' . $required . ' column.');
            }
        }

        $allowed = ['movement_date', 'warehouse_code', 'location_code', 'item_code', 'item_name', 'uom_code', 'qty', 'unit_cost', 'reference_no', 'notes'];
        $movements = [];
        $skipped = 0;
        $rowNumber = 1;

        foreach ($bodyRows as $row) {
            $rowNumber++;
            $data = $this->rowData($headers, $row, $allowed);
            $qty = (float) ($data['qty'] ?? 0);
            if (empty($data['item_code']) || $qty <= 0) {
                $skipped++;
                continue;
            }

            $item = $this->itemByCode((string) $data['item_code'], $companyId, $siteId);
            if ($item === null) {
                throw new RuntimeException("Row {$rowNumber}: item_code '{$data['item_code']}' was not found.");
            }

            $warehouseId = ! empty($data['warehouse_code']) ? $this->idByCode('warehouses', (string) $data['warehouse_code'], $companyId, $siteId) : null;
            if (! empty($data['warehouse_code']) && $warehouseId === null) {
                throw new RuntimeException("Row {$rowNumber}: warehouse_code '{$data['warehouse_code']}' was not found.");
            }

            $locationId = ! empty($data['location_code']) ? $this->idByCode('locations', (string) $data['location_code'], $companyId, $siteId) : null;
            if (! empty($data['location_code']) && $locationId === null) {
                throw new RuntimeException("Row {$rowNumber}: location_code '{$data['location_code']}' was not found.");
            }

            $movements[] = [
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
            ];
        }

        return ['movements' => $movements, 'skipped' => $skipped];
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

    private function validateSpreadsheetUpload($file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Please upload a valid spreadsheet file.';
        }

        if ($file->getSize() < 1) {
            return 'Uploaded spreadsheet file is empty.';
        }

        if ($file->getSize() > self::MAX_IMPORT_UPLOAD_BYTES) {
            return 'Spreadsheet file is too large. Maximum allowed size is 5 MB.';
        }

        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv', 'txt'], true)) {
            return 'Only XLSX, XLS, CSV, or TXT files are supported.';
        }

        return null;
    }

    /**
     * @return list<list<mixed>>
     */
    private function sheetRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['csv', 'txt'], true)) {
            $rows = [];
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Unable to read uploaded CSV file.');
            }
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);

            return $rows;
        }

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    /**
     * @param list<list<mixed>> $rows
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function splitHeaderRows(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('Spreadsheet file is empty.');
        }

        $headers = array_map(static fn ($value): string => trim((string) $value), array_shift($rows));
        if (implode('', $headers) === '') {
            throw new RuntimeException('Spreadsheet header row is empty.');
        }

        return [$headers, $rows];
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

    private function xlsxResponse(string $filename, array $rows, string $sheetTitle)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));
        $sheet->fromArray($rows, null, 'A1');

        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean() ?: '';
        $spreadsheet->disconnectWorksheets();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }
}
