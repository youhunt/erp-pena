<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\ProductionWorkOrderModel;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class ProductionAuditExportController extends BaseController
{
    public function workOrders()
    {
        $tenant = new TenantContext(session());
        $model = new ProductionWorkOrderModel();
        $this->scope($model, $tenant);
        $workOrders = $model->orderBy('wo_date', 'DESC')->orderBy('id', 'DESC')->findAll(10000);

        return $this->xlsxWorkbookResponse('production-work-orders-' . date('Y-m-d') . '.xlsx', [
            'Work Orders' => $this->workOrderHeaderRows($workOrders),
        ]);
    }

    public function workOrder(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new ProductionWorkOrderModel();
        $this->scope($model, $tenant);
        $workOrder = $model->find($id);
        if ($workOrder === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $filename = 'work-order-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($workOrder['wo_no'] ?? $id)) . '.xlsx';

        return $this->xlsxWorkbookResponse($filename, [
            'Work Orders' => $this->workOrderHeaderRows([$workOrder]),
        ]);
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
    }

    private function workOrderHeaderRows(array $workOrders): array
    {
        $rows = [[
            'wo_code',
            'wo_no',
            'wo_date',
            'site_code',
            'department_code',
            'warehouse_code',
            'work_center_code',
            'parent_item_code',
            'parent_item_name',
            'batch_qty',
            'wo_qty',
            'uom_code',
            'std_qty_finished',
            'act_qty_finished',
            'description',
            'status',
        ]];

        foreach ($workOrders as $workOrder) {
            $rows[] = [
                $workOrder['wo_code'] ?? '',
                $workOrder['wo_no'] ?? '',
                $workOrder['wo_date'] ?? '',
                $workOrder['site_code'] ?? $workOrder['site'] ?? '',
                $workOrder['department_code'] ?? '',
                $workOrder['warehouse_code'] ?? '',
                $workOrder['work_center_code'] ?? '',
                $workOrder['parent_item_code'] ?? '',
                $workOrder['parent_item_name'] ?? '',
                (float) ($workOrder['batch_qty'] ?? 0),
                (float) ($workOrder['wo_qty'] ?? 0),
                $workOrder['uom_code'] ?? '',
                (float) ($workOrder['std_qty_finished'] ?? 0),
                (float) ($workOrder['act_qty_finished'] ?? 0),
                $workOrder['description'] ?? '',
                $workOrder['status'] ?? '',
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
