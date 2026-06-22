<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        if (in_array((string) $this->request->getGet('export'), ['xlsx', 'excel'], true)) {
            return $this->xlsxResponse($rows, $dateFrom, $dateTo, $status);
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

    private function xlsxResponse(array $rows, string $dateFrom, string $dateTo, string $status): ResponseInterface
    {
        $summary = $this->summary($rows);
        $spreadsheet = new Spreadsheet();

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->setCellValue('A1', 'Sales Margin Summary');
        $summarySheet->setCellValue('A2', 'Period');
        $summarySheet->setCellValue('B2', $dateFrom . ' to ' . $dateTo);
        $summarySheet->setCellValue('A3', 'Filter');
        $summarySheet->setCellValue('B3', $status !== '' ? $status : 'All');
        $summarySheet->setCellValue('A5', 'Metric');
        $summarySheet->setCellValue('B5', 'Value');
        $summarySheet->setCellValue('A6', 'Invoice Count');
        $summarySheet->setCellValue('B6', (float) ($summary['invoice_count'] ?? 0));
        $summarySheet->setCellValue('A7', 'Total Invoice Amount');
        $summarySheet->setCellValue('B7', (float) ($summary['invoice_amount'] ?? 0));
        $summarySheet->setCellValue('A8', 'Total COGS Amount');
        $summarySheet->setCellValue('B8', (float) ($summary['cogs_amount'] ?? 0));
        $summarySheet->setCellValue('A9', 'Gross Profit/Loss');
        $summarySheet->setCellValue('B9', (float) ($summary['gross_profit_loss'] ?? 0));
        $summarySheet->setCellValue('A10', 'Gross Margin %');
        $summarySheet->setCellValue('B10', $summary['gross_margin_pct'] !== null ? ((float) $summary['gross_margin_pct']) / 100 : null);
        $summarySheet->setCellValue('A12', 'Margin Status');
        $summarySheet->setCellValue('B12', 'Count');
        $summarySheet->setCellValue('C12', 'Invoice Amount');
        $summarySheet->setCellValue('D12', 'COGS Amount');
        $summarySheet->setCellValue('E12', 'Gross Profit/Loss');
        $summarySheet->setCellValue('F12', 'Gross Margin %');

        $summaryRow = 13;
        foreach ($summary['by_status'] ?? [] as $marginStatus => $data) {
            $invoiceAmount = (float) ($data['invoice_amount'] ?? 0);
            $grossProfit = (float) ($data['gross_profit_loss'] ?? 0);
            $summarySheet->setCellValue('A' . $summaryRow, (string) $marginStatus);
            $summarySheet->setCellValue('B' . $summaryRow, (float) ($data['count'] ?? 0));
            $summarySheet->setCellValue('C' . $summaryRow, $invoiceAmount);
            $summarySheet->setCellValue('D' . $summaryRow, (float) ($data['cogs_amount'] ?? 0));
            $summarySheet->setCellValue('E' . $summaryRow, $grossProfit);
            $summarySheet->setCellValue('F' . $summaryRow, $invoiceAmount > 0 ? $grossProfit / $invoiceAmount : null);
            $summaryRow++;
        }

        $summaryLastRow = max(12, $summaryRow - 1);
        $summarySheet->mergeCells('A1:F1');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summarySheet->getStyle('A5:B5')->getFont()->setBold(true);
        $summarySheet->getStyle('A12:F12')->getFont()->setBold(true);
        $summarySheet->getStyle('A5:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $summarySheet->getStyle('A12:F' . $summaryLastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $summarySheet->getStyle('A5:B5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
        $summarySheet->getStyle('A12:F12')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
        $summarySheet->getStyle('B7:B9')->getNumberFormat()->setFormatCode('#,##0.00');
        $summarySheet->getStyle('B10')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $summarySheet->getStyle('C13:E' . $summaryLastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $summarySheet->getStyle('F13:F' . $summaryLastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $summarySheet->getStyle('B6:F' . $summaryLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (range('A', 'F') as $column) {
            $summarySheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sales Margin Detail');

        $headers = [
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
        ];

        $sheet->setCellValue('A1', 'Sales Margin Report');
        $sheet->setCellValue('A2', 'Period');
        $sheet->setCellValue('B2', $dateFrom . ' to ' . $dateTo);
        $sheet->setCellValue('A3', 'Filter');
        $sheet->setCellValue('B3', $status !== '' ? $status : 'All');

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '5', $header);
        }

        $rowNumber = 6;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $rowNumber, $row['invoice_date'] ?? '');
            $sheet->setCellValue('B' . $rowNumber, $row['invoice_no'] ?? '');
            $sheet->setCellValue('C' . $rowNumber, $row['invoice_status'] ?? '');
            $sheet->setCellValue('D' . $rowNumber, $row['customer_code'] ?? '');
            $sheet->setCellValue('E' . $rowNumber, $row['customer_name'] ?? '');
            $sheet->setCellValue('F' . $rowNumber, $row['so_no'] ?? '');
            $sheet->setCellValue('G' . $rowNumber, $row['linked_delivery_no'] ?? $row['delivery_no'] ?? '');
            $sheet->setCellValue('H' . $rowNumber, $row['delivery_status'] ?? '');
            $sheet->setCellValue('I' . $rowNumber, (float) ($row['invoice_amount'] ?? 0));
            $sheet->setCellValue('J' . $rowNumber, (float) ($row['cogs_amount'] ?? 0));
            $sheet->setCellValue('K' . $rowNumber, (float) ($row['gross_profit_loss'] ?? 0));
            $sheet->setCellValue('L' . $rowNumber, $row['gross_margin_pct'] !== null ? ((float) $row['gross_margin_pct']) / 100 : null);
            $sheet->setCellValue('M' . $rowNumber, $row['margin_status'] ?? '');
            $sheet->setCellValue('N' . $rowNumber, $row['invoice_gl_entry_id'] ?? '');
            $sheet->setCellValue('O' . $rowNumber, $row['cogs_gl_entry_id'] ?? '');
            $rowNumber++;
        }

        $lastRow = max(5, $rowNumber - 1);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->mergeCells('A1:O1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5:' . $lastColumn . '5')->getFont()->setBold(true);
        $sheet->getStyle('A5:' . $lastColumn . '5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
        $sheet->getStyle('A5:' . $lastColumn . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $sheet->getStyle('I6:K' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('L6:L' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('I6:L' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane('A6');
        $sheet->setAutoFilter('A5:' . $lastColumn . $lastRow);

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $safeStatus = $status !== '' ? '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $status) : '';
        $filename = 'sales_margin_report_' . $dateFrom . '_to_' . $dateTo . $safeStatus . '.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'sales_margin_');

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $body = file_get_contents($path) ?: '';
        @unlink($path);
        $spreadsheet->disconnectWorksheets();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($body);
    }
}
