<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\ChartAccountModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use RuntimeException;

class DataImportController extends BaseController
{
    public function index(): string
    {
        return view('system/data_import/index', [
            'title' => 'Data Import Export Center',
            'masters' => $this->masterModules(),
            'finance' => $this->financeModules(),
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
            $this->auditCoaImport(['created' => 0, 'updated' => 0, 'skipped' => 0], $file->getClientName(), $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->auditCoaImport($result, $file->getClientName());

        return redirect()->to('/system/data-import')->with('message', "COA import finished. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    private function importCoaCsv(string $path, int $companyId): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new RuntimeException('CSV file is empty.');
        }

        $headers = array_map(static fn ($value): string => trim((string) $value), $headers);
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
            $data = [];
            foreach ($headers as $index => $header) {
                if (! in_array($header, $allowed, true)) {
                    continue;
                }
                $value = trim((string) ($row[$index] ?? ''));
                $data[$header] = $value === '' ? null : $value;
            }

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

    private function auditCoaImport(array $result, string $filename, ?string $error = null): void
    {
        (new AuditLogService())->log('system.data_import', $error === null ? 'coa.import' : 'coa.import_failed', [
            'table_name' => 'chart_accounts',
            'description' => $error === null ? 'COA CSV import completed.' : 'COA CSV import failed: ' . $error,
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
