<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class StockAlertController extends BaseController
{
    public function index()
    {
        $tenant = new TenantContext(session());
        $status = trim((string) $this->request->getGet('status'));
        $keyword = trim((string) $this->request->getGet('q'));
        $rows = $this->rows($tenant, $status, $keyword, $this->request->getGet('export') === 'xlsx' ? 10000 : 500);
        $summary = $this->summary($rows);

        if ($this->request->getGet('export') === 'xlsx') {
            return $this->xlsxWorkbookResponse('stock-alerts-' . date('Y-m-d') . '.xlsx', [
                'Summary' => $this->summaryRows($summary, $status, $keyword, count($rows)),
                'Stock Alerts Detail' => $this->detailRows($rows),
            ]);
        }

        return view('inventory/stock_alerts/index', [
            'title' => 'Stock Alerts',
            'rows' => $rows,
            'summary' => $summary,
            'filters' => ['status' => $status, 'q' => $keyword],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(TenantContext $tenant, string $status, string $keyword, int $limit): array
    {
        $db = Database::connect();
        if (! $db->tableExists('item_locations')) {
            return [];
        }

        $stockSelect = null;
        if ($db->tableExists('inventory_stock_balances')) {
            $stockSelect = $db->table('inventory_stock_balances')
                ->select(
                    'company_id, site_id, warehouse_id, location_id, item_id, ' .
                    'SUM(qty_on_hand) AS qty_on_hand, SUM(qty_reserved) AS qty_reserved, SUM(qty_available) AS qty_available'
                )
                ->groupBy('company_id, site_id, warehouse_id, location_id, item_id')
                ->getCompiledSelect();
        }

        $builder = $db->table('item_locations il')
            ->select(
                'il.*, i.name AS item_name, w.code AS warehouse_code, w.name AS warehouse_name, ' .
                'l.code AS location_code, l.name AS location_name'
            )
            ->join('items i', 'i.id = il.item_id', 'left')
            ->join('warehouses w', 'w.id = il.warehouse_id', 'left')
            ->join('locations l', 'l.id = il.location_id', 'left')
            ->where('il.deleted_at', null)
            ->where('il.is_active', 1);

        if ($stockSelect !== null) {
            $builder
                ->select('COALESCE(b.qty_on_hand, 0) AS qty_on_hand, COALESCE(b.qty_reserved, 0) AS qty_reserved, COALESCE(b.qty_available, 0) AS qty_available')
                ->join(
                    '(' . $stockSelect . ') b',
                    'b.company_id = il.company_id AND ' .
                    '(b.site_id <=> il.site_id) AND ' .
                    '(b.warehouse_id <=> il.warehouse_id) AND ' .
                    'b.location_id = il.location_id AND b.item_id = il.item_id',
                    'left',
                    false
                );
        } else {
            $builder->select('0 AS qty_on_hand, 0 AS qty_reserved, 0 AS qty_available', false);
        }

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('il.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('il.site_id', $tenant->activeSiteId());
        }
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('il.item_code', $keyword)
                ->orLike('i.name', $keyword)
                ->orLike('w.code', $keyword)
                ->orLike('w.name', $keyword)
                ->orLike('l.code', $keyword)
                ->orLike('l.name', $keyword)
                ->groupEnd();
        }

        $rows = $builder
            ->orderBy('w.code', 'ASC')
            ->orderBy('l.code', 'ASC')
            ->orderBy('il.item_code', 'ASC')
            ->get($limit)
            ->getResultArray();

        $rows = array_map([$this, 'decorate'], $rows);

        if ($status !== '') {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => ($row['alert_status'] ?? '') === $status));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function decorate(array $row): array
    {
        $available = (float) ($row['qty_available'] ?? 0);
        $min = (float) ($row['min_qty'] ?? 0);
        $max = (float) ($row['max_qty'] ?? 0);
        $reorder = (float) ($row['reorder_qty'] ?? 0);

        $status = 'ok';
        if ($min > 0 && $available < $min) {
            $status = 'below_min';
        } elseif ($reorder > 0 && $available <= $reorder) {
            $status = 'reorder';
        } elseif ($max > 0 && $available > $max) {
            $status = 'over_max';
        }

        $targetQty = $max > 0 ? $max : ($reorder > 0 ? $reorder : $min);
        $suggestedQty = max(0, $targetQty - $available);

        $row['alert_status'] = $status;
        $row['alert_label'] = $this->statusOptions()[$status] ?? 'OK';
        $row['alert_badge'] = match ($status) {
            'below_min' => 'danger',
            'reorder' => 'warning',
            'over_max' => 'info',
            default => 'success',
        };
        $row['suggested_qty'] = $suggestedQty;

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int>
     */
    private function summary(array $rows): array
    {
        $summary = ['total' => count($rows), 'below_min' => 0, 'reorder' => 0, 'over_max' => 0, 'ok' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['alert_status'] ?? 'ok');
            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }

        return $summary;
    }

    private function summaryRows(array $summary, string $status, string $keyword, int $rowCount): array
    {
        return [
            ['Metric', 'Value'],
            ['Report', 'Stock Alerts'],
            ['Keyword', $keyword !== '' ? $keyword : 'ALL'],
            ['Status Filter', $status !== '' ? ($this->statusOptions()[$status] ?? $status) : 'ALL'],
            ['Rows', $rowCount],
            ['Below Min', (int) ($summary['below_min'] ?? 0)],
            ['Reorder', (int) ($summary['reorder'] ?? 0)],
            ['Over Max', (int) ($summary['over_max'] ?? 0)],
            ['OK', (int) ($summary['ok'] ?? 0)],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function detailRows(array $rows): array
    {
        $exportRows = [[
            'Item Code',
            'Item Name',
            'Warehouse Code',
            'Warehouse Name',
            'Location Code',
            'Location Name',
            'Qty Available',
            'Min Qty',
            'Reorder Qty',
            'Max Qty',
            'Suggested Qty',
            'Alert Status',
        ]];

        foreach ($rows as $row) {
            $exportRows[] = [
                $row['item_code'] ?? '',
                $row['item_name'] ?? '',
                $row['warehouse_code'] ?? '',
                $row['warehouse_name'] ?? '',
                $row['location_code'] ?? '',
                $row['location_name'] ?? '',
                (float) ($row['qty_available'] ?? 0),
                (float) ($row['min_qty'] ?? 0),
                (float) ($row['reorder_qty'] ?? 0),
                (float) ($row['max_qty'] ?? 0),
                (float) ($row['suggested_qty'] ?? 0),
                $row['alert_label'] ?? '',
            ];
        }

        return $exportRows;
    }

    private function statusOptions(): array
    {
        return [
            'below_min' => 'Below Min',
            'reorder' => 'Reorder',
            'over_max' => 'Over Max',
            'ok' => 'OK',
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
