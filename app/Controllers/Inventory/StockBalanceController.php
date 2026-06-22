<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class StockBalanceController extends BaseController
{
    public function index()
    {
        $tenant = new TenantContext(session());
        $keyword = trim((string) $this->request->getGet('q'));
        $balances = $this->balanceRows($tenant, $keyword, $this->request->getGet('export') === 'xlsx' ? 10000 : 200);
        $summary = $this->summary($balances);

        if ($this->request->getGet('export') === 'xlsx') {
            return $this->xlsxWorkbookResponse('stock-balance-' . date('Y-m-d') . '.xlsx', [
                'Summary' => $this->summaryRows($summary, $keyword, count($balances)),
                'Stock Balance Detail' => $this->detailRows($balances),
            ]);
        }

        return view('inventory/stock_balances/index', [
            'title' => 'Stock Balance',
            'balances' => $balances,
            'keyword' => $keyword,
            'summary' => $summary,
        ]);
    }

    private function balanceRows(TenantContext $tenant, string $keyword, int $limit): array
    {
        $db = Database::connect();
        $builder = $db->table('inventory_stock_balances b')
            ->select('b.*, w.code AS warehouse_code, w.name AS warehouse_name, l.code AS location_code, l.name AS location_name')
            ->join('warehouses w', 'w.id = b.warehouse_id', 'left')
            ->join('locations l', 'l.id = b.location_id', 'left');

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('b.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('b.site_id', $tenant->activeSiteId());
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('b.item_code', $keyword)
                ->orLike('b.batch_no', $keyword)
                ->orLike('w.code', $keyword)
                ->orLike('w.name', $keyword)
                ->orLike('l.code', $keyword)
                ->orLike('l.name', $keyword)
                ->groupEnd();
        }

        return $builder
            ->orderBy('b.item_code', 'ASC')
            ->orderBy('w.code', 'ASC')
            ->orderBy('l.code', 'ASC')
            ->get($limit)
            ->getResultArray();
    }

    private function summary(array $balances): array
    {
        $items = [];
        $onHand = 0.0;
        $reserved = 0.0;
        $available = 0.0;
        $value = 0.0;

        foreach ($balances as $row) {
            $items[$row['item_code']] = true;
            $onHand += (float) ($row['qty_on_hand'] ?? 0);
            $reserved += (float) ($row['qty_reserved'] ?? 0);
            $available += (float) ($row['qty_available'] ?? 0);
            $value += (float) ($row['stock_value'] ?? 0);
        }

        return [
            'item_count' => count($items),
            'qty_on_hand' => $onHand,
            'qty_reserved' => $reserved,
            'qty_available' => $available,
            'stock_value' => $value,
        ];
    }

    private function summaryRows(array $summary, string $keyword, int $rowCount): array
    {
        return [
            ['Metric', 'Value'],
            ['Report', 'Stock Balance'],
            ['Keyword', $keyword !== '' ? $keyword : 'ALL'],
            ['Rows', $rowCount],
            ['Items', (int) ($summary['item_count'] ?? 0)],
            ['Qty On Hand', (float) ($summary['qty_on_hand'] ?? 0)],
            ['Qty Reserved', (float) ($summary['qty_reserved'] ?? 0)],
            ['Qty Available', (float) ($summary['qty_available'] ?? 0)],
            ['Stock Value', (float) ($summary['stock_value'] ?? 0)],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function detailRows(array $balances): array
    {
        $rows = [[
            'Item Code',
            'Batch No',
            'Warehouse Code',
            'Warehouse Name',
            'Location Code',
            'Location Name',
            'UoM',
            'Qty On Hand',
            'Qty Reserved',
            'Qty Available',
            'Average Cost',
            'Stock Value',
        ]];

        foreach ($balances as $balance) {
            $rows[] = [
                $balance['item_code'] ?? '',
                $balance['batch_no'] ?? '',
                $balance['warehouse_code'] ?? '',
                $balance['warehouse_name'] ?? '',
                $balance['location_code'] ?? '',
                $balance['location_name'] ?? '',
                $balance['uom_code'] ?? '',
                (float) ($balance['qty_on_hand'] ?? 0),
                (float) ($balance['qty_reserved'] ?? 0),
                (float) ($balance['qty_available'] ?? 0),
                (float) ($balance['avg_cost'] ?? 0),
                (float) ($balance['stock_value'] ?? 0),
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
