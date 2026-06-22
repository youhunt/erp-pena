<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\BankStatementImportModel;
use App\Models\BankStatementLineModel;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class CashBankAuditExportController extends BaseController
{
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
            'Summary' => $this->summaryRows($import, $lines),
            'Statement Lines' => $this->lineRows($lines),
        ]);
    }

    private function summaryRows(array $import, array $lines): array
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

    private function lineRows(array $lines): array
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
