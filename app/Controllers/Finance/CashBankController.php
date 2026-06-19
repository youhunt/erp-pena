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
                'amount' => (float) $this->request->getPost('amount'),
                'counter_account_no' => trim((string) $this->request->getPost('counter_account_no')),
                'reference_no' => trim((string) $this->request->getPost('reference_no')),
                'description' => trim((string) $this->request->getPost('description')),
            ];
            $statementLineId = (int) $this->request->getPost('statement_line_id');
            $statementImportId = $type === 'bank' && $statementLineId > 0
                ? $this->validateStatementLineEntrySource($statementLineId, $entryData)
                : null;

            $entryId = (new CashBankService())->post($entryData, auth()->id());
            if ($statementImportId !== null) {
                (new BankStatementLineModel())->update($statementLineId, [
                    'match_status' => 'matched',
                    'cash_bank_entry_id' => $entryId,
                ]);
                $this->refreshStatementImportStatus($statementImportId);
            }
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
            'lines' => (new BankStatementLineModel())->where('bank_statement_import_id', $id)->orderBy('line_no', 'ASC')->findAll(500),
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
            $result = (new BankStatementImportService())->autoMatch($id, $companyId, $tenant->activeSiteId(), auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/statements/' . $id)
            ->with('message', "Auto match finished. {$result['matched']} matched, {$result['skipped']} skipped.");
    }

    public function newReconciliation(): string
    {
        $tenant = new TenantContext(session());
        $selectedCode = trim((string) ($this->request->getGet('cash_bank_code') ?: ''));
        $accounts = $this->cashBankAccounts('bank');
        $defaults = [
            'bank_statement_import_id' => null,
            'reconcile_no' => 'BR-' . date('Ymd-His'),
            'statement_date' => date('Y-m-d'),
            'statement_balance' => '0.00',
            'statement_ref' => '',
            'notes' => '',
        ];
        $selectedEntryIds = [];

        $statementContext = $this->statementReconciliationContext($tenant);
        if ($statementContext !== null) {
            $selectedCode = (string) $statementContext['cash_bank_code'];
            $defaults = array_merge($defaults, $statementContext['defaults']);
            $selectedEntryIds = $statementContext['entry_ids'];
        }

        if ($selectedCode === '' && $accounts !== []) {
            $selectedCode = (string) $accounts[0]['cash_bank_code'];
        }

        return view('finance/cash_bank/reconciliations/form', [
            'title' => 'Create Bank Reconcile',
            'accounts' => $accounts,
            'selectedCode' => $selectedCode,
            'defaults' => $defaults,
            'selectedEntryIds' => $selectedEntryIds,
            'entries' => $selectedCode !== '' ? $this->unreconciledBankEntries($tenant, $selectedCode) : [],
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
            'reconcile_no' => 'required|max_length[60]',
            'statement_date' => 'required|valid_date[Y-m-d]',
            'cash_bank_code' => 'required|max_length[60]',
            'statement_balance' => 'required|numeric',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $reconciliationId = (new BankReconciliationService())->post([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'reconcile_no' => trim((string) $this->request->getPost('reconcile_no')),
                'statement_date' => (string) $this->request->getPost('statement_date'),
                'statement_ref' => trim((string) $this->request->getPost('statement_ref')),
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'bank_statement_import_id' => (int) $this->request->getPost('bank_statement_import_id'),
                'statement_balance' => (float) $this->request->getPost('statement_balance'),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], (array) $this->request->getPost('entry_ids'), auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/reconciliations/' . $reconciliationId)->with('message', 'Bank reconciliation posted.');
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
            'title' => 'Bank Reconcile Detail',
            'reconciliation' => $reconciliation,
            'entries' => (new CashBankEntryModel())->where('bank_reconciliation_id', $id)->orderBy('entry_date', 'ASC')->findAll(),
        ]);
    }

    private function cashBankAccounts(string $type): array
    {
        $tenant = new TenantContext(session());
        $model = new CashBankAccountModel();
        $this->scope($model, $tenant);

        return $model->where('account_type', $type)->where('is_active', 1)->orderBy('cash_bank_code', 'ASC')->findAll(100);
    }

    private function chartAccounts(): array
    {
        $tenant = new TenantContext(session());
        $model = new ChartAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        return $model->where('is_active', 1)->where('is_postable', 1)->orderBy('account_no', 'ASC')->findAll(300);
    }

    private function unreconciledBankEntries(TenantContext $tenant, string $cashBankCode): array
    {
        $model = new CashBankEntryModel();
        $this->scope($model, $tenant);

        return $model
            ->where('cash_bank_code', $cashBankCode)
            ->whereIn('entry_type', ['bank_in', 'bank_out'])
            ->where('bank_reconciliation_id', null)
            ->orderBy('entry_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll(200);
    }

    private function entryDefaultsFromStatementLine(TenantContext $tenant): array
    {
        $statementLineId = (int) ($this->request->getGet('statement_line_id') ?? 0);
        if ($statementLineId < 1 || $tenant->activeCompanyId() === null) {
            return [];
        }

        $lineModel = new BankStatementLineModel();
        $this->scope($lineModel, $tenant);
        $line = $lineModel
            ->where('id', $statementLineId)
            ->where('match_status !=', 'matched')
            ->first();
        if ($line === null) {
            return [];
        }

        $signed = (float) ($line['signed_amount'] ?? 0);
        return [
            'statement_line_id' => (int) $line['id'],
            'entry_no' => 'BANK-' . date('Ymd-His'),
            'entry_date' => (string) ($line['statement_date'] ?? date('Y-m-d')),
            'direction' => $signed >= 0 ? 'in' : 'out',
            'currency_code' => (string) ($line['currency_code'] ?? 'IDR'),
            'cash_bank_code' => (string) ($line['cash_bank_code'] ?? ''),
            'amount' => number_format(abs($signed), 2, '.', ''),
            'reference_no' => (string) ($line['reference_no'] ?? ''),
            'description' => (string) ($line['description'] ?? ''),
        ];
    }

    private function validateStatementLineEntrySource(int $statementLineId, array $entryData): int
    {
        $line = (new BankStatementLineModel())
            ->where('id', $statementLineId)
            ->where('company_id', (int) $entryData['company_id'])
            ->where('match_status !=', 'matched')
            ->first();
        if ($line === null) {
            throw new RuntimeException('Bank statement line is not available for adjustment entry.');
        }

        $signed = round((float) ($line['signed_amount'] ?? 0), 2);
        $expectedDirection = $signed >= 0 ? 'in' : 'out';
        $actualDirection = str_ends_with((string) $entryData['entry_type'], '_in') ? 'in' : 'out';

        if ((string) $line['cash_bank_code'] !== (string) $entryData['cash_bank_code']
            || (string) $line['statement_date'] !== (string) $entryData['entry_date']
            || $expectedDirection !== $actualDirection
            || round(abs($signed), 2) !== round((float) $entryData['amount'], 2)) {
            throw new RuntimeException('Bank entry must keep the same bank, date, direction, and amount as the source statement line.');
        }

        return (int) $line['bank_statement_import_id'];
    }

    private function refreshStatementImportStatus(int $statementImportId): void
    {
        $lineModel = new BankStatementLineModel();
        $total = $lineModel->where('bank_statement_import_id', $statementImportId)->countAllResults();
        $matched = $lineModel->where('bank_statement_import_id', $statementImportId)->where('match_status', 'matched')->countAllResults();
        $status = $matched < 1 ? 'imported' : ($matched >= $total ? 'matched' : 'partial_matched');

        (new BankStatementImportModel())->update($statementImportId, [
            'matched_count' => $matched,
            'status' => $status,
        ]);
    }

    private function statementReconciliationContext(TenantContext $tenant): ?array
    {
        $statementImportId = (int) ($this->request->getGet('statement_import_id') ?? 0);
        if ($statementImportId < 1 || $tenant->activeCompanyId() === null) {
            return null;
        }

        $model = new BankStatementImportModel();
        $this->scope($model, $tenant);
        $import = $model->find($statementImportId);
        if ($import === null || ($import['status'] ?? '') === 'reconciled') {
            return null;
        }

        $lines = (new BankStatementLineModel())
            ->where('bank_statement_import_id', $statementImportId)
            ->where('match_status', 'matched')
            ->where('cash_bank_entry_id IS NOT NULL', null, false)
            ->findAll(500);

        $entryIds = array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line['cash_bank_entry_id'],
            $lines
        )));

        return [
            'cash_bank_code' => (string) $import['cash_bank_code'],
            'entry_ids' => $entryIds,
            'defaults' => [
                'bank_statement_import_id' => (int) $import['id'],
                'reconcile_no' => 'BR-' . date('Ymd-His'),
                'statement_date' => (string) ($import['statement_date'] ?? date('Y-m-d')),
                'statement_balance' => number_format((float) ($import['closing_balance'] ?? 0), 2, '.', ''),
                'statement_ref' => (string) ($import['statement_ref'] ?? ''),
                'notes' => 'From bank statement import #' . $import['id'] . ' - ' . ($import['source_filename'] ?? ''),
            ],
        ];
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }
    }

    private function validateXlsxUpload($file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Please upload a valid Excel file.';
        }

        if ($file->getSize() < 1) {
            return 'Uploaded Excel file is empty.';
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return 'Excel file is too large. Maximum allowed size is 5 MB.';
        }

        if (strtolower($file->getClientExtension()) !== 'xlsx') {
            return 'Only .xlsx Excel files are supported for bank statement import.';
        }

        return null;
    }

    private function xlsxResponse(string $filename, array $rows, string $sheetTitle)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new RuntimeException('PhpSpreadsheet is required to generate Excel files. Run composer install.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));
        $sheet->fromArray($rows, null, 'A1');
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
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
