<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class CoreAuditExportController extends BaseController
{
    public function stockCard()
    {
        $tenant = new TenantContext(session());
        $filters = $this->stockCardFilters();
        $opening = $this->stockOpeningBalance($tenant, $filters);
        $movements = $this->stockMovements($tenant, $filters);
        $rows = $this->stockCardRows($this->withStockRunningBalance($movements, $opening), $opening, $filters);

        return $this->xlsxResponse(
            'stock-card-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.xlsx',
            $rows,
            'Stock Card'
        );
    }

    public function glEntries()
    {
        $tenant = new TenantContext(session());
        $filters = $this->glFilters();
        $rows = $this->glEntryRows($tenant, $filters);

        return $this->xlsxResponse(
            'gl-entries-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.xlsx',
            $rows,
            'GL Entries'
        );
    }

    /**
     * @return array{item_code: string, batch_no: string, warehouse_id: ?int, location_id: ?int, date_from: string, date_to: string}
     */
    private function stockCardFilters(): array
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
            'item_code' => trim((string) $this->request->getGet('item_code')),
            'batch_no' => trim((string) $this->request->getGet('batch_no')),
            'warehouse_id' => $this->nullableInt($this->request->getGet('warehouse_id')),
            'location_id' => $this->nullableInt($this->request->getGet('location_id')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function stockOpeningBalance(TenantContext $tenant, array $filters): array
    {
        $builder = $this->stockMovementBuilder($tenant, $filters, false)
            ->select("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) qty", false)
            ->select("COALESCE(SUM(CASE WHEN direction = 'in' THEN stock_value ELSE -stock_value END), 0) stock_value", false)
            ->where('movement_date <', $filters['date_from'] . ' 00:00:00');

        $row = $builder->get()->getRowArray() ?? [];

        return [
            'qty' => (float) ($row['qty'] ?? 0),
            'stock_value' => (float) ($row['stock_value'] ?? 0),
        ];
    }

    private function stockMovements(TenantContext $tenant, array $filters): array
    {
        return $this->stockMovementBuilder($tenant, $filters)
            ->select('m.*, w.code warehouse_code, l.code location_code')
            ->join('warehouses w', 'w.id = m.warehouse_id', 'left')
            ->join('locations l', 'l.id = m.location_id', 'left')
            ->where('m.movement_date >=', $filters['date_from'] . ' 00:00:00')
            ->where('m.movement_date <=', $filters['date_to'] . ' 23:59:59')
            ->orderBy('m.movement_date', 'ASC')
            ->orderBy('m.id', 'ASC')
            ->get(5000)
            ->getResultArray();
    }

    private function stockMovementBuilder(TenantContext $tenant, array $filters, bool $aliased = true)
    {
        $db = Database::connect();
        $table = $aliased ? 'inventory_stock_movements m' : 'inventory_stock_movements';
        $prefix = $aliased ? 'm.' : '';
        $builder = $db->table($table);

        if ($tenant->activeCompanyId() !== null) {
            $builder->where($prefix . 'company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where($prefix . 'site_id', $tenant->activeSiteId());
        }
        if ($filters['item_code'] !== '') {
            $builder->where($prefix . 'item_code', $filters['item_code']);
        }
        if ($filters['batch_no'] !== '') {
            $builder->where($prefix . 'batch_no', $filters['batch_no']);
        }
        if ($filters['warehouse_id'] !== null) {
            $builder->where($prefix . 'warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['location_id'] !== null) {
            $builder->where($prefix . 'location_id', $filters['location_id']);
        }

        return $builder;
    }

    private function withStockRunningBalance(array $movements, array $opening): array
    {
        $qtyBalance = (float) $opening['qty'];
        $valueBalance = (float) $opening['stock_value'];

        foreach ($movements as &$movement) {
            $qty = (float) ($movement['qty'] ?? 0);
            $value = (float) ($movement['stock_value'] ?? 0);
            $isIn = (string) ($movement['direction'] ?? '') === 'in';
            $movement['qty_in'] = $isIn ? $qty : 0;
            $movement['qty_out'] = $isIn ? 0 : $qty;
            $movement['value_in'] = $isIn ? $value : 0;
            $movement['value_out'] = $isIn ? 0 : $value;
            $qtyBalance += $isIn ? $qty : -$qty;
            $valueBalance += $isIn ? $value : -$value;
            $movement['running_qty'] = $qtyBalance;
            $movement['running_value'] = $valueBalance;
        }

        return $movements;
    }

    private function stockCardRows(array $movements, array $opening, array $filters): array
    {
        $rows = [[
            'Row Type',
            'Date',
            'Item Code',
            'Item Name',
            'Batch No',
            'Warehouse',
            'Location',
            'Movement Type',
            'Reference Type',
            'Reference No',
            'Qty In',
            'Qty Out',
            'Balance Qty',
            'Value In',
            'Value Out',
            'Balance Value',
            'Notes',
        ]];

        $rows[] = [
            'Opening',
            $filters['date_from'],
            $filters['item_code'] !== '' ? $filters['item_code'] : 'ALL',
            '',
            $filters['batch_no'] !== '' ? $filters['batch_no'] : 'ALL',
            $filters['warehouse_id'] !== null ? (string) $filters['warehouse_id'] : 'ALL',
            $filters['location_id'] !== null ? (string) $filters['location_id'] : 'ALL',
            '',
            '',
            '',
            0,
            0,
            (float) $opening['qty'],
            0,
            0,
            (float) $opening['stock_value'],
            'Opening balance before selected period',
        ];

        foreach ($movements as $movement) {
            $rows[] = [
                'Movement',
                substr((string) ($movement['movement_date'] ?? ''), 0, 10),
                $movement['item_code'] ?? '',
                $movement['item_name'] ?? '',
                $movement['batch_no'] ?? '',
                $movement['warehouse_code'] ?? '',
                $movement['location_code'] ?? '',
                $movement['movement_type'] ?? '',
                $movement['reference_type'] ?? '',
                $movement['reference_no'] ?? '',
                (float) ($movement['qty_in'] ?? 0),
                (float) ($movement['qty_out'] ?? 0),
                (float) ($movement['running_qty'] ?? 0),
                (float) ($movement['value_in'] ?? 0),
                (float) ($movement['value_out'] ?? 0),
                (float) ($movement['running_value'] ?? 0),
                $movement['notes'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return array{date_from: string, date_to: string, source_module: string}
     */
    private function glFilters(): array
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

    private function glEntryRows(TenantContext $tenant, array $filters): array
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
            'Line No',
            'Account No',
            'Account Name',
            'Line Description',
            'Debit',
            'Credit',
            'Entry Difference',
        ]];

        $entries = $this->glEntryBuilder($tenant, $filters)
            ->select('ge.id, ge.journal_date, ge.period, ge.journal_no, ge.source_module, ge.source_type, ge.source_no, ge.status, ge.description')
            ->select('ge.total_debit, ge.total_credit')
            ->select('gel.line_no, gel.account_no, gel.account_name, gel.description line_description, gel.debit, gel.credit')
            ->orderBy('ge.journal_date', 'ASC')
            ->orderBy('ge.id', 'ASC')
            ->orderBy('gel.line_no', 'ASC')
            ->get(10000)
            ->getResultArray();

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
                $entry['line_no'] ?? '',
                $entry['account_no'] ?? '',
                $entry['account_name'] ?? '',
                $entry['line_description'] ?? '',
                (float) ($entry['debit'] ?? 0),
                (float) ($entry['credit'] ?? 0),
                round((float) ($entry['total_debit'] ?? 0) - (float) ($entry['total_credit'] ?? 0), 2),
            ];
        }

        return $rows;
    }

    private function glEntryBuilder(TenantContext $tenant, array $filters)
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

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function xlsxResponse(string $filename, array $rows, string $sheetTitle)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new RuntimeException('PhpSpreadsheet is required to generate Excel files. Run composer install.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));
        $sheet->fromArray($rows, null, 'A1');
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
        $sheet->setAutoFilter('A1:' . $highestColumn . max(1, $highestRow));
        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

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
