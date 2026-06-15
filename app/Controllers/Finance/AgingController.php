<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use DateTimeImmutable;

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
}
