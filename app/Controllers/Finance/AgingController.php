<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class AgingController extends BaseController
{
    public function ap(): string
    {
        return $this->report('ap');
    }

    public function ar(): string
    {
        return $this->report('ar');
    }

    public function apExport()
    {
        return $this->export('ap');
    }

    public function arExport()
    {
        return $this->export('ar');
    }

    private function report(string $type): string
    {
        $asOf = $this->asOfDate();
        $tenant = new TenantContext(session());
        $config = $this->config($type);
        $rows = $this->openRows($config, $tenant);
        $summary = $this->summary($rows, $asOf, $config);

        return view('finance/aging/index', [
            'title' => $config['title'],
            'type' => $type,
            'asOf' => $asOf->format('Y-m-d'),
            'config' => $config,
            'summary' => $summary,
            'rows' => $this->decorateRows($rows, $asOf, $config),
            'totals' => $this->totals($summary),
        ]);
    }

    private function export(string $type)
    {
        $asOf = $this->asOfDate();
        $tenant = new TenantContext(session());
        $config = $this->config($type);
        $rows = $this->decorateRows($this->openRows($config, $tenant), $asOf, $config);
        $summary = $this->summary($rows, $asOf, $config);
        $totals = $this->totals($summary);
        $prefix = strtoupper($type);

        return $this->xlsxWorkbookResponse(
            strtolower($type) . '-aging-' . $asOf->format('Y-m-d') . '.xlsx',
            [
                $prefix . ' Aging Summary' => $this->agingSummaryRows($summary, $totals, $config, $asOf),
                $prefix . ' Aging Detail' => $this->agingDetailRows($rows, $config),
            ]
        );
    }

    private function asOfDate(): DateTimeImmutable
    {
        $value = trim((string) $this->request->getGet('as_of'));
        if ($value === '') {
            return new DateTimeImmutable(date('Y-m-d'));
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date ?: new DateTimeImmutable(date('Y-m-d'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openRows(array $config, TenantContext $tenant): array
    {
        $db = Database::connect();
        if (! $db->tableExists($config['table'])) {
            return [];
        }

        $builder = $db->table($config['table'])
            ->where('outstanding_amount >', 0)
            ->whereIn('status', ['open', 'partial']);

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        return $builder
            ->orderBy($config['partnerNameField'], 'ASC')
            ->orderBy('due_date', 'ASC')
            ->orderBy('invoice_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function summary(array $rows, DateTimeImmutable $asOf, array $config): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $partnerKey = (string) ($row[$config['partnerCodeField']] ?? '') ?: (string) ($row[$config['partnerNameField']] ?? '-');
            if (! isset($summary[$partnerKey])) {
                $summary[$partnerKey] = [
                    'partner_code' => $row[$config['partnerCodeField']] ?? '-',
                    'partner_name' => $row[$config['partnerNameField']] ?? '-',
                    'current' => 0.0,
                    'days_1_30' => 0.0,
                    'days_31_60' => 0.0,
                    'days_61_90' => 0.0,
                    'days_over_90' => 0.0,
                    'total' => 0.0,
                ];
            }

            $amount = (float) ($row['outstanding_amount'] ?? 0);
            $bucket = $this->bucket($this->ageDays($row, $asOf));
            $summary[$partnerKey][$bucket] += $amount;
            $summary[$partnerKey]['total'] += $amount;
        }

        usort($summary, static fn (array $a, array $b): int => strcasecmp((string) $a['partner_name'], (string) $b['partner_name']));

        return array_values($summary);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function decorateRows(array $rows, DateTimeImmutable $asOf, array $config): array
    {
        foreach ($rows as &$row) {
            $ageDays = $this->ageDays($row, $asOf);
            $row['age_days'] = $ageDays;
            $row['bucket'] = $this->bucketLabel($this->bucket($ageDays));
            $row['document_url'] = site_url($config['invoiceRoute'] . '/' . ($row[$config['invoiceIdField']] ?? 0));
        }
        unset($row);

        return $rows;
    }

    private function ageDays(array $row, DateTimeImmutable $asOf): int
    {
        $dateValue = (string) ($row['due_date'] ?? $row['invoice_date'] ?? '');
        $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateValue) ?: $asOf;
        $days = (int) $dueDate->diff($asOf)->format('%r%a');

        return max(0, $days);
    }

    private function bucket(int $ageDays): string
    {
        return match (true) {
            $ageDays <= 0 => 'current',
            $ageDays <= 30 => 'days_1_30',
            $ageDays <= 60 => 'days_31_60',
            $ageDays <= 90 => 'days_61_90',
            default => 'days_over_90',
        };
    }

    private function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'current' => 'Current',
            'days_1_30' => '1-30',
            'days_31_60' => '31-60',
            'days_61_90' => '61-90',
            default => '> 90',
        };
    }

    /**
     * @param list<array<string, mixed>> $summary
     *
     * @return array<string, float>
     */
    private function totals(array $summary): array
    {
        $totals = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
            'total' => 0.0,
        ];

        foreach ($summary as $row) {
            foreach ($totals as $field => $amount) {
                $totals[$field] = $amount + (float) ($row[$field] ?? 0);
            }
        }

        return $totals;
    }

    private function agingSummaryRows(array $summary, array $totals, array $config, DateTimeImmutable $asOf): array
    {
        $rows = [
            ['Report', $config['title']],
            ['As Of', $asOf->format('Y-m-d')],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            [$config['partnerLabel'] . ' Code', $config['partnerLabel'] . ' Name', 'Current', '1-30', '31-60', '61-90', '> 90', 'Total'],
        ];

        foreach ($summary as $row) {
            $rows[] = [
                $row['partner_code'] ?? '-',
                $row['partner_name'] ?? '-',
                (float) ($row['current'] ?? 0),
                (float) ($row['days_1_30'] ?? 0),
                (float) ($row['days_31_60'] ?? 0),
                (float) ($row['days_61_90'] ?? 0),
                (float) ($row['days_over_90'] ?? 0),
                (float) ($row['total'] ?? 0),
            ];
        }

        $rows[] = [
            'TOTAL',
            '',
            (float) ($totals['current'] ?? 0),
            (float) ($totals['days_1_30'] ?? 0),
            (float) ($totals['days_31_60'] ?? 0),
            (float) ($totals['days_61_90'] ?? 0),
            (float) ($totals['days_over_90'] ?? 0),
            (float) ($totals['total'] ?? 0),
        ];

        return $rows;
    }

    private function agingDetailRows(array $rows, array $config): array
    {
        $exportRows = [[
            'Invoice No',
            'Invoice Date',
            'Due Date',
            $config['partnerLabel'] . ' Code',
            $config['partnerLabel'] . ' Name',
            'Bucket',
            'Age Days',
            'Outstanding Amount',
            'Status',
        ]];

        foreach ($rows as $row) {
            $exportRows[] = [
                $row['invoice_no'] ?? '',
                $row['invoice_date'] ?? '',
                $row['due_date'] ?? '',
                $row[$config['partnerCodeField']] ?? '',
                $row[$config['partnerNameField']] ?? '',
                $row['bucket'] ?? '',
                (int) ($row['age_days'] ?? 0),
                (float) ($row['outstanding_amount'] ?? 0),
                $row['status'] ?? '',
            ];
        }

        return $exportRows;
    }

    /**
     * @return array<string, string>
     */
    private function config(string $type): array
    {
        if ($type === 'ap') {
            return [
                'title' => 'A/P Aging',
                'table' => 'ap_payables',
                'partnerLabel' => 'Supplier',
                'partnerCodeField' => 'supplier_code',
                'partnerNameField' => 'supplier_name',
                'invoiceRoute' => 'ap/purchase-invoices',
                'invoiceIdField' => 'purchase_invoice_id',
            ];
        }

        return [
            'title' => 'A/R Aging',
            'table' => 'ar_receivables',
            'partnerLabel' => 'Customer',
            'partnerCodeField' => 'customer_code',
            'partnerNameField' => 'customer_name',
            'invoiceRoute' => 'ar/sales-invoices',
            'invoiceIdField' => 'sales_invoice_id',
        ];
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
            $headerRow = str_contains((string) $sheetTitle, 'Summary') ? 5 : 1;
            $headerRow = min($headerRow, max(1, $highestRow));
            $sheet->getStyle('A' . $headerRow . ':' . $highestColumn . $headerRow)->getFont()->setBold(true);
            if ($highestRow >= $headerRow) {
                $sheet->setAutoFilter('A' . $headerRow . ':' . $highestColumn . max($headerRow, $highestRow));
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
