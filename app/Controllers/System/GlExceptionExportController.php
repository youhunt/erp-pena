<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class GlExceptionExportController extends BaseController
{
    public function unbalanced()
    {
        $tenant = new TenantContext(session());
        $filters = $this->filters();
        $entries = $this->unbalancedEntries($tenant, $filters);
        $lines = $this->unbalancedEntryLines($tenant, $filters);

        return $this->xlsxWorkbookResponse(
            'gl-unbalanced-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.xlsx',
            [
                'Summary' => $this->summaryRows($entries, $filters),
                'Unbalanced Entries' => $this->entryRows($entries),
                'Entry Lines' => $this->lineRows($lines),
            ]
        );
    }

    private function filters(): array
    {
        $dateFrom = trim((string) ($this->request->getGet('date_from') ?: date('Y-m-01')));
        $dateTo = trim((string) ($this->request->getGet('date_to') ?: date('Y-m-d')));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'source_module' => trim((string) $this->request->getGet('source_module')),
        ];
    }

    private function baseBuilder(TenantContext $tenant, array $filters)
    {
        $db = Database::connect();
        $builder = $db->table('gl_entries ge')
            ->join('gl_entry_lines gel', 'gel.gl_entry_id = ge.id', 'inner');

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('ge.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('ge.site_id', $tenant->activeSiteId());
        }
        if ($filters['date_from'] !== '') {
            $builder->where('ge.journal_date >=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $builder->where('ge.journal_date <=', $filters['date_to']);
        }
        if ($filters['source_module'] !== '') {
            $builder->where('ge.source_module', $filters['source_module']);
        }
        $builder->where('ge.status !=', 'cancelled');
        if ($db->fieldExists('deleted_at', 'gl_entries')) {
            $builder->where('ge.deleted_at', null);
        }

        return $builder;
    }

    private function unbalancedEntries(TenantContext $tenant, array $filters): array
    {
        $db = Database::connect();
        if (! $db->tableExists('gl_entries') || ! $db->tableExists('gl_entry_lines')) {
            return [];
        }

        return $this->baseBuilder($tenant, $filters)
            ->select('ge.id, ge.journal_date, ge.period, ge.journal_no, ge.source_module, ge.source_type, ge.source_no, ge.status, ge.description')
            ->select('COUNT(gel.id) line_count', false)
            ->select('COALESCE(SUM(gel.debit), 0) debit', false)
            ->select('COALESCE(SUM(gel.credit), 0) credit', false)
            ->select('COALESCE(SUM(gel.debit), 0) - COALESCE(SUM(gel.credit), 0) difference', false)
            ->groupBy('ge.id')
            ->having('ROUND(COALESCE(SUM(gel.debit),0) - COALESCE(SUM(gel.credit),0), 2) !=', 0)
            ->orderBy('ge.journal_date', 'ASC')
            ->orderBy('ge.id', 'ASC')
            ->get(5000)
            ->getResultArray();
    }

    private function unbalancedEntryLines(TenantContext $tenant, array $filters): array
    {
        $entries = $this->unbalancedEntries($tenant, $filters);
        $ids = array_map(static fn (array $entry): int => (int) $entry['id'], $entries);
        if ($ids === []) {
            return [];
        }

        return $this->baseBuilder($tenant, $filters)
            ->select('ge.journal_date, ge.journal_no, ge.source_module, ge.source_type, ge.source_no')
            ->select('gel.line_no, gel.account_no, gel.account_name, gel.description line_description, gel.debit, gel.credit')
            ->whereIn('ge.id', $ids)
            ->orderBy('ge.journal_date', 'ASC')
            ->orderBy('ge.id', 'ASC')
            ->orderBy('gel.line_no', 'ASC')
            ->get(10000)
            ->getResultArray();
    }

    private function summaryRows(array $entries, array $filters): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totalDifference = 0.0;
        foreach ($entries as $entry) {
            $totalDebit += (float) ($entry['debit'] ?? 0);
            $totalCredit += (float) ($entry['credit'] ?? 0);
            $totalDifference += (float) ($entry['difference'] ?? 0);
        }

        return [
            ['Metric', 'Value'],
            ['Report', 'GL Unbalanced Entries'],
            ['Period From', $filters['date_from']],
            ['Period To', $filters['date_to']],
            ['Source Module', $filters['source_module'] !== '' ? $filters['source_module'] : 'ALL'],
            ['Unbalanced Entries', count($entries)],
            ['Total Debit', $totalDebit],
            ['Total Credit', $totalCredit],
            ['Total Difference', round($totalDifference, 2)],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function entryRows(array $entries): array
    {
        $rows = [[
            'Journal Date',
            'Period',
            'Journal No',
            'Source Module',
            'Source Type',
            'Source No',
            'Status',
            'Description',
            'Line Count',
            'Debit',
            'Credit',
            'Difference',
        ]];

        foreach ($entries as $entry) {
            $rows[] = [
                $entry['journal_date'] ?? '',
                $entry['period'] ?? '',
                $entry['journal_no'] ?? '',
                $entry['source_module'] ?? '',
                $entry['source_type'] ?? '',
                $entry['source_no'] ?? '',
                $entry['status'] ?? '',
                $entry['description'] ?? '',
                (int) ($entry['line_count'] ?? 0),
                (float) ($entry['debit'] ?? 0),
                (float) ($entry['credit'] ?? 0),
                (float) ($entry['difference'] ?? 0),
            ];
        }

        return $rows;
    }

    private function lineRows(array $lines): array
    {
        $rows = [[
            'Journal Date',
            'Journal No',
            'Source Module',
            'Source Type',
            'Source No',
            'Line No',
            'Account No',
            'Account Name',
            'Line Description',
            'Debit',
            'Credit',
        ]];

        foreach ($lines as $line) {
            $rows[] = [
                $line['journal_date'] ?? '',
                $line['journal_no'] ?? '',
                $line['source_module'] ?? '',
                $line['source_type'] ?? '',
                $line['source_no'] ?? '',
                (int) ($line['line_no'] ?? 0),
                $line['account_no'] ?? '',
                $line['account_name'] ?? '',
                $line['line_description'] ?? '',
                (float) ($line['debit'] ?? 0),
                (float) ($line['credit'] ?? 0),
            ];
        }

        return $rows;
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
