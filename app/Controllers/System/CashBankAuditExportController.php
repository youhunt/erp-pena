<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\BankReconciliationModel;
use App\Models\BankStatementImportModel;
use App\Models\BankStatementLineModel;
use App\Models\CashBankEntryModel;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class CashBankAuditExportController extends BaseController
{
    public function entries(string $type = 'cash')
    {
        $type = $type === 'bank' ? 'bank' : 'cash';
        $tenant = new TenantContext(session());
        $model = new CashBankEntryModel();
        $this->scope($model, $tenant);
        $type === 'cash'
            ? $model->whereIn('entry_type', ['cash_in', 'cash_out'])
            : $model->whereIn('entry_type', ['bank_in', 'bank_out']);

        $entries = $model
            ->orderBy('entry_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(10000);

        return $this->xlsxWorkbookResponse($type . '-entries-' . date('Y-m-d') . '.xlsx', [
            'Summary' => $this->entrySummaryRows($entries, $type),
            'Entry Detail' => $this->entryRows($entries),
        ]);
    }

    public function statement(int $id)
    {
        $tenant = new TenantContext(session());
        $importModel = new BankStatementImportModel();
        $this->scope($importModel, $tenant);
        $import = $importModel->find($id);
        if ($import === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $lines = (new BankStatementLineModel())
            ->where('bank_statement_import_id', $id)
            ->orderBy('line_no', 'ASC')
            ->findAll(10000);

        $filename = 'bank-statement-'
            . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($import['cash_bank_code'] ?? 'BANK'))
            . '-' . ((string) ($import['statement_date'] ?? date('Y-m-d')))
            . '.xlsx';

        return $this->xlsxWorkbookResponse($filename, [
            'Summary' => $this->statementSummaryRows($import, $lines),
            'Statement Lines' => $this->statementLineRows($lines),
        ]);
    }

    public function reconciliation(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new BankReconciliationModel();
        $this->scope($model, $tenant);
        $reconciliation = $model->find($id);
        if ($reconciliation === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $entries = (new CashBankEntryModel())
            ->where('bank_reconciliation_id', $id)
            ->orderBy('entry_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll(10000);

        $filename = 'bank-reconciliation-'
            . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($reconciliation['reconcile_no'] ?? $id))
            . '.xlsx';

        return $this->xlsxWorkbookResponse($filename, [
            'Summary' => $this->reconciliationSummaryRows($reconciliation, $entries),
            'Matched Entries' => $this->entryRows($entries),
        ]);
    }

    private function entrySummaryRows(array $entries, string $type): array
    {
        $cashIn = 0.0;
        $cashOut = 0.0;
        $postedGl = 0;
        $withoutGl = 0;

        foreach ($entries as $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            str_ends_with((string) ($entry['entry_type'] ?? ''), '_in') ? $cashIn += $amount : $cashOut += $amount;
            ! empty($entry['gl_entry_id']) ? $postedGl++ : $withoutGl++;
        }

        return [
            ['Metric', 'Value'],
            ['Report', ucfirst($type) . ' Entries'],
            ['Rows', count($entries)],
            ['Total In', $cashIn],
            ['Total Out', $cashOut],
            ['Net Movement', $cashIn - $cashOut],
            ['Posted GL Rows', $postedGl],
            ['Without GL Rows', $withoutGl],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function entryRows(array $entries): array
    {
        $rows = [[
            'Entry Date',
            'Entry No',
            'Entry Type',
            'Cash/Bank Code',
            'Currency',
            'Amount',
            'Counter Account No',
            'Reference No',
            'Description',
            'GL Entry ID',
            'Bank Reconciliation ID',
            'Created At',
        ]];

        foreach ($entries as $entry) {
            $rows[] = [
                $entry['entry_date'] ?? '',
                $entry['entry_no'] ?? '',
                $entry['entry_type'] ?? '',
                $entry['cash_bank_code'] ?? '',
                $entry['currency_code'] ?? '',
                (float) ($entry['amount'] ?? 0),
                $entry['counter_account_no'] ?? '',
                $entry['reference_no'] ?? '',
                $entry['description'] ?? '',
                $entry['gl_entry_id'] ?? '',
                $entry['bank_reconciliation_id'] ?? '',
                $entry['created_at'] ?? '',
            ];
        }

        return $rows;
    }

    private function statementSummaryRows(array $import, array $lines): array
    {
        $matched = 0;
        $unmatched = 0;
        $debit = 0.0;
        $credit = 0.0;
        $lastBalance = null;

        foreach ($lines as $line) {
            $status = (string) ($line['match_status'] ?? 'unmatched');
            $status === 'matched' ? $matched++ : $unmatched++;
            $debit += (float) ($line['debit_amount'] ?? 0);
            $credit += (float) ($line['credit_amount'] ?? 0);
            $lastBalance = (float) ($line['balance_amount'] ?? $lastBalance ?? 0);
        }

        return [
            ['Metric', 'Value'],
            ['Report', 'Bank Statement Import'],
            ['Cash/Bank Code', (string) ($import['cash_bank_code'] ?? '-')],
            ['Statement Date', (string) ($import['statement_date'] ?? '-')],
            ['Statement Ref', (string) ($import['statement_ref'] ?? '-')],
            ['Source File', (string) ($import['source_filename'] ?? '-')],
            ['Status', (string) ($import['status'] ?? '-')],
            ['Opening Balance', (float) ($import['opening_balance'] ?? 0)],
            ['Debit Total', $debit],
            ['Credit Total', $credit],
            ['Closing Balance', (float) ($import['closing_balance'] ?? $lastBalance ?? 0)],
            ['Calculated Last Balance', $lastBalance ?? 0],
            ['Line Count', count($lines)],
            ['Matched Lines', $matched],
            ['Unmatched Lines', $unmatched],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function statementLineRows(array $lines): array
    {
        $rows = [[
            'Line No',
            'Statement Date',
            'Value Date',
            'Reference No',
            'Description',
            'Debit',
            'Credit',
            'Signed Amount',
            'Balance',
            'Currency',
            'Match Status',
            'Cash/Bank Entry ID',
        ]];

        foreach ($lines as $line) {
            $rows[] = [
                (int) ($line['line_no'] ?? 0),
                $line['statement_date'] ?? '',
                $line['value_date'] ?? '',
                $line['reference_no'] ?? '',
                $line['description'] ?? '',
                (float) ($line['debit_amount'] ?? 0),
                (float) ($line['credit_amount'] ?? 0),
                (float) ($line['signed_amount'] ?? 0),
                (float) ($line['balance_amount'] ?? 0),
                $line['currency_code'] ?? '',
                $line['match_status'] ?? '',
                $line['cash_bank_entry_id'] ?? '',
            ];
        }

        return $rows;
    }

    private function reconciliationSummaryRows(array $reconciliation, array $entries): array
    {
        $entryAmount = 0.0;
        foreach ($entries as $entry) {
            $entryAmount += (float) ($entry['amount'] ?? 0);
        }

        return [
            ['Metric', 'Value'],
            ['Report', 'Bank Reconciliation'],
            ['Reconcile No', (string) ($reconciliation['reconcile_no'] ?? '-')],
            ['Cash/Bank Code', (string) ($reconciliation['cash_bank_code'] ?? '-')],
            ['Statement Date', (string) ($reconciliation['statement_date'] ?? '-')],
            ['Statement Ref', (string) ($reconciliation['statement_ref'] ?? '-')],
            ['Status', (string) ($reconciliation['status'] ?? '-')],
            ['Bank Statement Import ID', (string) ($reconciliation['bank_statement_import_id'] ?? '')],
            ['Book Balance', (float) ($reconciliation['book_balance'] ?? 0)],
            ['Statement Balance', (float) ($reconciliation['statement_balance'] ?? 0)],
            ['Difference Amount', (float) ($reconciliation['difference_amount'] ?? 0)],
            ['Reconciled Amount', (float) ($reconciliation['reconciled_amount'] ?? 0)],
            ['Matched Entry Count', count($entries)],
            ['Matched Entry Amount', $entryAmount],
            ['Posted At', (string) ($reconciliation['posted_at'] ?? '')],
            ['Generated At', date('Y-m-d H:i:s')],
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

    /**
     * @param array<string, array<int, array<int, mixed>>> $sheets
     */
    private function xlsxWorkbookResponse(string $filename, array $sheets)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new RuntimeException('PhpSpreadsheet is required to generate Excel files. Run composer install.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetIndex = 0;

        foreach ($sheets as $sheetTitle => $rows) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(substr((string) $sheetTitle, 0, 31));
            $sheet->fromArray($rows, null, 'A1');
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();
            if ($highestRow >= 1) {
                $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
                $sheet->setAutoFilter('A1:' . $highestColumn . max(1, $highestRow));
            }
            foreach (range('A', $highestColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);
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
