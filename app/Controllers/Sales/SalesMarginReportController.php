<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class SalesMarginReportController extends BaseController
{
    public function index(): string|ResponseInterface
    {
        $dateFrom = trim((string) $this->request->getGet('date_from')) ?: date('Y-m-01');
        $dateTo = trim((string) $this->request->getGet('date_to')) ?: date('Y-m-t');
        $status = trim((string) $this->request->getGet('margin_status'));
        $tenant = new TenantContext(session());
        $rows = $this->rows($dateFrom, $dateTo, $tenant);

        if ($status !== '') {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => ($row['margin_status'] ?? '') === $status));
        }

        if ((string) $this->request->getGet('export') === 'csv') {
            return $this->csvResponse($rows, $dateFrom, $dateTo, $status);
        }

        return view('sales/reports/margins', [
            'title' => 'Sales Margin Report',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedStatus' => $status,
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'statusOptions' => ['PROFIT_OK', 'LOSS_REVIEW_COST_OR_PRICE', 'MISSING_COGS_GL', 'MISSING_DELIVERY'],
        ]);
    }

    private function rows(string $dateFrom, string $dateTo, TenantContext $tenant): array
    {
        $db = Database::connect();
        if (! $db->tableExists('sales_invoices')) {
            return [];
        }

        $amountExpr = $this->amountExpression('i');
        $builder = $db->table('sales_invoices i')
            ->select("i.id AS invoice_id, i.invoice_date, i.invoice_no, i.status AS invoice_status", false)
            ->select("i.customer_code, i.customer_name, i.sales_delivery_id, i.so_no, i.delivery_no", false)
            ->select("d.id AS delivery_id, d.delivery_no AS linked_delivery_no, d.status AS delivery_status, d.gl_entry_id AS cogs_gl_entry_id", false)
            ->select("i.gl_entry_id AS invoice_gl_entry_id", false)
            ->select("{$amountExpr} AS invoice_amount", false)
            ->select("COALESCE(cogs.total_debit, 0) AS cogs_amount", false)
            ->select("ROUND({$amountExpr} - COALESCE(cogs.total_debit, 0), 2) AS gross_profit_loss", false)
            ->select("CASE WHEN {$amountExpr} = 0 THEN NULL ELSE ROUND((({$amountExpr} - COALESCE(cogs.total_debit, 0)) / {$amountExpr}) * 100, 2) END AS gross_margin_pct", false)
            ->select("CASE WHEN d.id IS NULL THEN 'MISSING_DELIVERY' WHEN d.gl_entry_id IS NULL THEN 'MISSING_COGS_GL' WHEN {$amountExpr} - COALESCE(cogs.total_debit, 0) < 0 THEN 'LOSS_REVIEW_COST_OR_PRICE' ELSE 'PROFIT_OK' END AS margin_status", false)
            ->join('sales_deliveries d', 'd.id = i.sales_delivery_id OR d.delivery_no = i.delivery_no', 'left')
            ->join('gl_entries cogs', 'cogs.id = d.gl_entry_id', 'left')
            ->where('i.invoice_date >=', $dateFrom)
            ->where('i.invoice_date <=', $dateTo)
            ->where("COALESCE(i.status, '') !=", 'cancelled');

        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'sales_invoices')) {
            $builder->where('i.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', 'sales_invoices')) {
            $builder->where('i.site_id', $tenant->activeSiteId());
        }

        return $builder->orderBy('i.invoice_date', 'DESC')->orderBy('i.id', 'DESC')->get()->getResultArray();
    }

    private function amountExpression(string $alias): string
    {
        $db = Database::connect();
        $fields = [];
        foreach (['total_amount', 'grand_total', 'subtotal_amount'] as $field) {
            if ($db->fieldExists($field, 'sales_invoices')) {
                $fields[] = $alias . '.' . $field;
            }
        }
        $fields[] = '0';
        return 'COALESCE(' . implode(', ', $fields) . ')';
    }

    private function summary(array $rows): array
    {
        $summary = [
            'invoice_count' => count($rows),
            'invoice_amount' => 0.0,
            'cogs_amount' => 0.0,
            'gross_profit_loss' => 0.0,
            'gross_margin_pct' => null,
            'by_status' => [],
        ];

        foreach ($rows as $row) {
            $invoice = (float) ($row['invoice_amount'] ?? 0);
            $cogs = (float) ($row['cogs_amount'] ?? 0);
            $profit = (float) ($row['gross_profit_loss'] ?? 0);
            $status = (string) ($row['margin_status'] ?? 'UNKNOWN');
            $summary['invoice_amount'] += $invoice;
            $summary['cogs_amount'] += $cogs;
            $summary['gross_profit_loss'] += $profit;
            if (! isset($summary['by_status'][$status])) {
                $summary['by_status'][$status] = ['count' => 0, 'invoice_amount' => 0.0, 'cogs_amount' => 0.0, 'gross_profit_loss' => 0.0];
            }
            $summary['by_status'][$status]['count']++;
            $summary['by_status'][$status]['invoice_amount'] += $invoice;
            $summary['by_status'][$status]['cogs_amount'] += $cogs;
            $summary['by_status'][$status]['gross_profit_loss'] += $profit;
        }

        if ($summary['invoice_amount'] > 0) {
            $summary['gross_margin_pct'] = ($summary['gross_profit_loss'] / $summary['invoice_amount']) * 100;
        }

        ksort($summary['by_status']);
        return $summary;
    }

    private function csvResponse(array $rows, string $dateFrom, string $dateTo, string $status): ResponseInterface
    {
        $handle = fopen('php://temp', 'rb+');
        fputcsv($handle, [
            'Invoice Date',
            'Invoice No',
            'Invoice Status',
            'Customer Code',
            'Customer Name',
            'SO No',
            'Delivery No',
            'Delivery Status',
            'Invoice Amount',
            'COGS Amount',
            'Gross Profit/Loss',
            'Gross Margin %',
            'Margin Status',
            'Invoice GL Entry ID',
            'COGS GL Entry ID',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['invoice_date'] ?? '',
                $row['invoice_no'] ?? '',
                $row['invoice_status'] ?? '',
                $row['customer_code'] ?? '',
                $row['customer_name'] ?? '',
                $row['so_no'] ?? '',
                $row['linked_delivery_no'] ?? $row['delivery_no'] ?? '',
                $row['delivery_status'] ?? '',
                number_format((float) ($row['invoice_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($row['cogs_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($row['gross_profit_loss'] ?? 0), 2, '.', ''),
                $row['gross_margin_pct'] !== null ? number_format((float) $row['gross_margin_pct'], 2, '.', '') : '',
                $row['margin_status'] ?? '',
                $row['invoice_gl_entry_id'] ?? '',
                $row['cogs_gl_entry_id'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        $safeStatus = $status !== '' ? '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $status) : '';
        $filename = 'sales_margin_report_' . $dateFrom . '_to_' . $dateTo . $safeStatus . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }
}
