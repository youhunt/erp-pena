<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\BankReconciliationModel;
use App\Models\BankStatementImportModel;
use App\Models\BankStatementLineModel;
use App\Models\CashBankAccountModel;
use App\Models\CashBankEntryModel;
use App\Models\ChartAccountModel;
use App\Services\Finance\BankReconciliationService;
use App\Services\Finance\BankStatementImportService;
use App\Services\Finance\CashBankService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class CashBankController extends BaseController
{
    public function accounts(): string
    {
        $tenant = new TenantContext(session());
        $model = new CashBankAccountModel();
        $this->scope($model, $tenant);

        return view('finance/cash_bank/accounts', [
            'title' => 'Cash Bank ID',
            'accounts' => $model->orderBy('cash_bank_code', 'ASC')->findAll(200),
        ]);
    }

    public function entries(string $type = 'cash'): string
    {
        $tenant = new TenantContext(session());
        $model = new CashBankEntryModel();
        $this->scope($model, $tenant);
        $type === 'cash'
            ? $model->whereIn('entry_type', ['cash_in', 'cash_out'])
            : $model->whereIn('entry_type', ['bank_in', 'bank_out']);

        return view('finance/cash_bank/entries/index', [
            'title' => $type === 'cash' ? 'Cash Entry' : 'Bank Entry',
            'type' => $type,
            'entries' => $model->orderBy('entry_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function newEntry(string $type = 'cash'): string
    {
        $defaults = [
            'statement_line_id' => null,
            'entry_no' => strtoupper($type) . '-' . date('Ymd-His'),
            'entry_date' => date('Y-m-d'),
            'direction' => 'in',
            'currency_code' => 'IDR',
            'cash_bank_code' => '',
            'rate_type' => 'BI',
            'exchange_rate' => '',
            'amount' => '0.00',
            'reference_no' => '',
            'description' => '',
        ];

        if ($type === 'bank') {
            $defaults = array_merge($defaults, $this->entryDefaultsFromStatementLine(new TenantContext(session())));
        }

        return view('finance/cash_bank/entries/form', [
            'title' => $type === 'cash' ? 'Create Cash Entry' : 'Create Bank Entry',
            'type' => $type,
            'defaults' => $defaults,
            'accounts' => $this->cashBankAccounts($type),
            'chartAccounts' => $this->chartAccounts(),
        ]);
    }

    public function storeEntry(string $type = 'cash')
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'entry_no' => 'required|max_length[60]',
            'entry_date' => 'required|valid_date[Y-m-d]',
            'direction' => 'required|in_list[in,out]',
            'cash_bank_code' => 'required|max_length[60]',
            'amount' => 'required|numeric|greater_than[0]',
            'counter_account_no' => 'required|max_length[60]',
            'rate_type' => 'permit_empty|max_length[12]',
            'exchange_rate' => 'permit_empty|numeric',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $direction = (string) $this->request->getPost('direction');
        $entryType = $type . '_' . $direction;

        try {
            $entryData = [
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'entry_no' => trim((string) $this->request->getPost('entry_no')),
                'entry_date' => (string) $this->request->getPost('entry_date'),
                'entry_type' => $entryType,
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
                'rate_type' => trim((string) ($this->request->getPost('rate_type') ?: 'BI')),
                'exchange_rate' => (float) ($this->request->getPost('exchange_rate') ?: 0),
                'amount' => (float) $this->request->getPost('amount'),
                'counter_account_no' => trim((string) $this->request->getPost('counter_account_no')),
                'reference_no' => trim((string) $this->request->getPost('reference_no')),
                'description' => trim((string) $this->request->getPost('description')),
            ];
            $statementLineId = (int) $this->request->getPost('statement_line_id');
            $entryId = $type === 'bank' && $statementLineId > 0
                ? (new BankStatementImportService())->postAdjustmentEntry($statementLineId, $entryData, auth()->id())
                : (new CashBankService())->post($entryData, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries') . '/' . $entryId)->with('message', 'Cash/Bank entry posted.');
    }

    public function showEntry(string $type, int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new CashBankEntryModel();
        $this->scope($model, $tenant);
        $entry = $model->find($id);
        if ($entry === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/cash_bank/entries/show', [
            'title' => 'Cash/Bank Entry Detail',
            'type' => $type,
            'entry' => $entry,
        ]);
    }

    public function reconciliations(): string
    {
        $tenant = new TenantContext(session());
        $model = new BankReconciliationModel();
        $this->scope($model, $tenant);

        return view('finance/cash_bank/reconciliations/index', [
            'title' => 'Bank Reconcile',
            'reconciliations' => $model->orderBy('statement_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function statementImports(): string
    {
        $tenant = new TenantContext(session());
        $model = new BankStatementImportModel();
        $this->scope($model, $tenant);

        return view('finance/cash_bank/statements/index', [
            'title' => 'Bank Statement Imports',
            'imports' => $model->orderBy('statement_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function statementTemplate()
    {
        return $this->xlsxResponse('bank-statement-import-template.xlsx', [
            ['statement_date', 'value_date', 'reference_no', 'description', 'debit', 'credit', 'balance', 'currency'],
            [date('Y-m-d'), date('Y-m-d'), 'TRX-001', 'Customer transfer example', '0', '1500000', '1500000', 'IDR'],
            [date('Y-m-d'), date('Y-m-d'), 'ADM-001', 'Bank admin fee example', '6500', '0', '1493500', 'IDR'],
        ], 'Bank Statement');
    }

    public function statementImportForm(): string
    {
        return view('finance/cash_bank/statements/import', [
            'title' => 'Import Bank Statement',
            'accounts' => $this->cashBankAccounts('bank'),
        ]);
    }

    public function importStatement()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'cash_bank_code' => 'required|max_length[60]',
            'statement_date' => 'required|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $file = $this->request->getFile('statement_file');
        $uploadError = $this->validateXlsxUpload($file);
        if ($uploadError !== null) {
            return redirect()->back()->withInput()->with('error', $uploadError);
        }

        try {
            $importId = (new BankStatementImportService())->importXlsx($file->getTempName(), $file->getClientName(), [
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'statement_date' => (string) $this->request->getPost('statement_date'),
                'statement_ref' => trim((string) $this->request->getPost('statement_ref')),
                'opening_balance' => (float) $this->request->getPost('opening_balance'),
                'closing_balance' => (float) $this->request->getPost('closing_balance'),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/statements/' . $importId)->with('message', 'Bank statement Excel imported.');
    }

    public function showStatementImport(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new BankStatementImportModel();
        $this->scope($model, $tenant);
        $import = $model->find($id);
        if ($import === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/cash_bank/statements/show', [
            'title' => 'Bank Statement Import Detail',
            'import' => $import,
            'lines' => (new BankStatementLineModel())->where('bank_statement_import_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    public function matchStatementImport(int $id)
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->with('error', 'Active company is required.');
        }

        try {
            (new BankStatementImportService())->matchImport($id, $companyId, $tenant->activeSiteId(), auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/statements/' . $id)->with('message', 'Bank statement matching completed.');
    }

    public function newReconciliation(): string
    {
        return view('finance/cash_bank/reconciliations/form', [
            'title' => 'Create Bank Reconciliation',
            'accounts' => $this->cashBankAccounts('bank'),
        ]);
    }

    public function storeReconciliation()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'cash_bank_code' => 'required|max_length[60]',
            'statement_date' => 'required|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $reconciliationId = (new BankReconciliationService())->create([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'statement_date' => (string) $this->request->getPost('statement_date'),
                'statement_ref' => trim((string) $this->request->getPost('statement_ref')),
                'opening_balance' => (float) $this->request->getPost('opening_balance'),
                'closing_balance' => (float) $this->request->getPost('closing_balance'),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/reconciliations/' . $reconciliationId)->with('message', 'Bank reconciliation draft created.');
    }

    public function showReconciliation(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new BankReconciliationModel();
        $this->scope($model, $tenant);
        $reconciliation = $model->find($id);
        if ($reconciliation === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/cash_bank/reconciliations/show', [
            'title' => 'Bank Reconciliation Detail',
            'reconciliation' => $reconciliation,
            'lines' => (new BankStatementLineModel())->where('bank_reconciliation_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
    }

    private function cashBankAccounts(string $type): array
    {
        $tenant = new TenantContext(session());
        $model = new CashBankAccountModel();
        $this->scope($model, $tenant);
        return $model->where('account_type', $type)->where('is_active', 1)->orderBy('cash_bank_code', 'ASC')->findAll();
    }

    private function chartAccounts(): array
    {
        $tenant = new TenantContext(session());
        $model = new ChartAccountModel();
        $this->scope($model, $tenant);
        return $model->where('is_active', 1)->orderBy('account_no', 'ASC')->findAll(500);
    }

    private function entryDefaultsFromStatementLine(TenantContext $tenant): array
    {
        $lineId = (int) $this->request->getGet('statement_line_id');
        if ($lineId < 1) {
            return [];
        }
        $line = (new BankStatementLineModel())->find($lineId);
        if ($line === null || (string) ($line['match_status'] ?? '') === 'matched') {
            return [];
        }
        $import = (new BankStatementImportModel())->find((int) ($line['bank_statement_import_id'] ?? 0));
        if ($import === null) {
            return [];
        }
        if ($tenant->activeCompanyId() !== null && (int) ($import['company_id'] ?? 0) !== $tenant->activeCompanyId()) {
            return [];
        }
        if ($tenant->activeSiteId() !== null && ! empty($import['site_id']) && (int) $import['site_id'] !== $tenant->activeSiteId()) {
            return [];
        }

        $debit = (float) ($line['debit'] ?? 0);
        $credit = (float) ($line['credit'] ?? 0);
        $amount = $credit > 0 ? $credit : $debit;

        return [
            'statement_line_id' => $lineId,
            'entry_no' => 'BANK-' . date('Ymd-His') . '-' . $lineId,
            'entry_date' => $line['value_date'] ?? $line['statement_date'] ?? date('Y-m-d'),
            'direction' => $credit > 0 ? 'in' : 'out',
            'currency_code' => $line['currency_code'] ?? $import['currency_code'] ?? 'IDR',
            'cash_bank_code' => $import['cash_bank_code'] ?? '',
            'amount' => number_format($amount, 2, '.', ''),
            'reference_no' => $line['reference_no'] ?? '',
            'description' => $line['description'] ?? '',
        ];
    }
}
