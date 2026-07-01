<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class AgingController extends BaseController
{
    private array $partnerGroupCache = [];

    public function ap()
    {
        if ($this->request->getGet('export') === 'xlsx') {
            return $this->export('ap');
        }

        return $this->report('ap');
    }

    public function ar()
    {
        if ($this->request->getGet('export') === 'xlsx') {
            return $this->export('ar');
        }

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
        $filters = $this->filters();
        $rows = $this->decorateRows($this->openRows($config, $tenant), $asOf, $config);
        $rows = $this->filterRows($rows, $filters, $config);
        $summary = $this->summary($rows, $asOf, $config);

        return view('finance/aging/index', [
            'title' => $config['title'],
            'type' => $type,
            'asOf' => $asOf->format('Y-m-d'),
            'config' => $config,
            'filters' => $filters,
            'bucketOptions' => $this->bucketOptions(),
            'summary' => $summary,
            'rows' => $rows,
            'totals' => $this->totals($summary),
        ]);
    }

    private function export(string $type)
    {
        $asOf = $this->asOfDate();
        $tenant = new TenantContext(session());
        $config = $this->config($type);
        $filters = $this->filters();
        $rows = $this->decorateRows($this->openRows($config, $tenant), $asOf, $config);
        $rows = $this->filterRows($rows, $filters, $config);
        $summary = $this->summary($rows, $asOf, $config);
        $totals = $this->totals($summary);
        $prefix = strtoupper($type);

        return $this->xlsxWorkbookResponse(
            strtolower($type) . '-aging-' . $asOf->format('Y-m-d') . '.xlsx',
            [
                $prefix . ' Aging Summary' => $this->agingSummaryRows($summary, $totals, $config, $asOf, $filters),
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

    private function filters(): array
    {
        $bucket = trim((string) $this->request->getGet('aging_bucket'));
        if (! array_key_exists($bucket, $this->bucketOptions())) {
            $bucket = '';
        }

        return [
            'partner_code' => trim((string) $this->request->getGet('partner_code')),
            'partner_group' => trim((string) $this->request->getGet('partner_group')),
            'aging_bucket' => $bucket,
        ];
    }

    private function bucketOptions(): array
    {
        return [
            '' => 'All Aging',
            'current' => 'Current',
            'days_1_30' => '1-30',
            'days_31_60' => '31-60',
            'days_61_90' => '61-90',
            'days_over_90' => '> 90',
        ];
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
     * @return list<array<string, mixed>>
     */
    private function filterRows(array $rows, array $filters, array $config): array
    {
        $partnerCode = strtolower($filters['partner_code'] ?? '');
        $partnerGroup = strtolower($filters['partner_group'] ?? '');
        $bucket = (string) ($filters['aging_bucket'] ?? '');

        if ($partnerCode === '' && $partnerGroup === '' && $bucket === '') {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($partnerCode !== '') {
                $code = strtolower((string) ($row[$config['partnerCodeField']] ?? ''));
                $name = strtolower((string) ($row[$config['partnerNameField']] ?? ''));
                if (! str_contains($code, $partnerCode) && ! str_contains($name, $partnerCode)) {
                    continue;
                }
            }

            if ($partnerGroup !== '') {
                $group = strtolower((string) ($row['partner_group'] ?? ''));
                if (! str_contains($group, $partnerGroup)) {
                    continue;
                }
            }

            if ($bucket !== '' && (string) ($row['bucket_key'] ?? '') !== $bucket) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * @param list<array<string, mixed>> $rows
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
                    'partner_group' => $row['partner_group'] ?? '',
                    'current' => 0.0,
                    'days_1_30' => 0.0,
                    'days_31_60' => 0.0,
                    'days_61_90' => 0.0,
                    'days_over_90' => 0.0,
                    'total' => 0.0,
                ];
            }

            $amount = (float) ($row['outstanding_amount'] ?? 0);
            $bucket = (string) ($row['bucket_key'] ?? $this->bucket($this->ageDays($row, $asOf)));
            $summary[$partnerKey][$bucket] += $amount;
            $summary[$partnerKey]['total'] += $amount;
        }

        usort($summary, static fn (array $a, array $b): int => strcasecmp((string) $a['partner_name'], (string) $b['partner_name']));

        return array_values($summary);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorateRows(array $rows, DateTimeImmutable $asOf, array $config): array
    {
        foreach ($rows as &$row) {
            $ageDays = $this->ageDays($row, $asOf);
            $bucket = $this->bucket($ageDays);
            $row['age_days'] = $ageDays;
            $row['bucket_key'] = $bucket;
            $row['bucket'] = $this->bucketLabel($bucket);
            $row['partner_group'] = $this->partnerGroup($row, $config);
            $row['document_url'] = site_url($config['invoiceRoute'] . '/' . ($row[$config['invoiceIdField']] ?? 0));
        }
        unset($row);

        return $rows;
    }

    private function partnerGroup(array $row, array $config): string
    {
        foreach ($config['partnerGroupFields'] as $field) {
            if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                return trim((string) $row[$field]);
            }
        }

        $partnerCode = trim((string) ($row[$config['partnerCodeField']] ?? ''));
        if ($partnerCode === '' || empty($config['partnerMasterTable'])) {
            return '';
        }

        $cacheKey = $config['partnerMasterTable'] . ':' . $partnerCode;
        if (array_key_exists($cacheKey, $this->partnerGroupCache)) {
            return $this->partnerGroupCache[$cacheKey];
        }

        $db = Database::connect();
        if (! $db->tableExists($config['partnerMasterTable'])) {
            return $this->partnerGroupCache[$cacheKey] = '';
        }

        $builder = $db->table($config['partnerMasterTable']);
        $codeFields = array_unique(array_filter([$config['partnerMasterCodeField'] ?? null, 'code', $config['partnerCodeField']]));
        $usableCodeFields = array_values(array_filter($codeFields, static fn ($field) => is_string($field) && $field !== ''));

        $builder->groupStart();
        $first = true;
        foreach ($usableCodeFields as $field) {
            if (! $db->fieldExists($field, $config['partnerMasterTable'])) {
                continue;
            }
            $first ? $builder->where($field, $partnerCode) : $builder->orWhere($field, $partnerCode);
            $first = false;
        }
        $builder->groupEnd();
        if ($first) {
            return $this->partnerGroupCache[$cacheKey] = '';
        }

        if ($db->fieldExists('deleted_at', $config['partnerMasterTable'])) {
            $builder->where('deleted_at', null);
        }

        $master = $builder->get(1)->getRowArray() ?: [];
        foreach ($config['partnerGroupFields'] as $field) {
            if (isset($master[$field]) && trim((string) $master[$field]) !== '') {
                return $this->partnerGroupCache[$cacheKey] = trim((string) $master[$field]);
            }
        }

        return $this->partnerGroupCache[$cacheKey] = '';
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
            default => $bucket === 'days_61_90' ? '61-90' : '> 90',
        };
    }

    /**
     * @param list<array<string, mixed>> $summary
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

    private function agingSummaryRows(array $summary, array $totals, array $config, DateTimeImmutable $asOf, array $filters = []): array
    {
        $rows = [
            ['Report', $config['title']],
            ['As Of', $asOf->format('Y-m-d')],
            ['Filter ' . $config['partnerLabel'], $filters['partner_code'] ?? ''],
            ['Filter Group', $filters['partner_group'] ?? ''],
            ['Filter Aging', $this->bucketOptions()[$filters['aging_bucket'] ?? ''] ?? 'All Aging'],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            [$config['partnerLabel'] . ' Code', $config['partnerLabel'] . ' Name', 'Group', 'Current', '1-30', '31-60', '61-90', '> 90', 'Total'],
        ];

        foreach ($summary as $row) {
            $rows[] = [
                $row['partner_code'] ?? '-',
                $row['partner_name'] ?? '-',
                $row['partner_group'] ?? '',
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
            'Group',
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
                $row['partner_group'] ?? '',
                $row['bucket'] ?? '',
                (int) ($row['age_days'] ?? 0),
                (float) ($row['outstanding_amount'] ?? 0),
                $row['status'] ?? '',
            ];
        }

        return $exportRows;
    }

    /**
     * @return array<string, mixed>
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
                'partnerMasterTable' => 'suppliers',
                'partnerMasterCodeField' => 'supplier',
                'partnerGroupFields' => ['supplier_group', 'suppliergroup', 'suppliergrp', 'group_code', 'group_name', 'vendor_group', 'supplierref'],
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
            'partnerMasterTable' => 'customers',
            'partnerMasterCodeField' => 'customer',
            'partnerGroupFields' => ['customer_group', 'customergroup', 'customergrp', 'group_code', 'group_name', 'customerr'],
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
            $headerRow = str_contains((string) $sheetTitle, 'Summary') ? 8 : 1;
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
