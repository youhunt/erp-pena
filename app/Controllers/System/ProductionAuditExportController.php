<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\ProductionWorkOrderComponentModel;
use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderRoutingModel;
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
        $rows = $model->orderBy('wo_date', 'DESC')->orderBy('id', 'DESC')->findAll(10000);

        return $this->xlsxWorkbookResponse('production-work-orders-' . date('Y-m-d') . '.xlsx', [
            'Summary' => $this->workOrderSummaryRows($rows),
            'Work Orders' => $this->workOrderRows($rows),
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

        $components = (new ProductionWorkOrderComponentModel())
            ->where('production_work_order_id', $id)
            ->orderBy('line_no', 'ASC')
            ->findAll(10000);
        $routings = (new ProductionWorkOrderRoutingModel())
            ->where('production_work_order_id', $id)
            ->orderBy('line_no', 'ASC')
            ->findAll(10000);

        $filename = 'work-order-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($workOrder['wo_no'] ?? $id)) . '.xlsx';

        return $this->xlsxWorkbookResponse($filename, [
            'Summary' => $this->singleWorkOrderSummaryRows($workOrder, $components, $routings),
            'Components' => $this->componentRows($components),
            'Routings' => $this->routingRows($routings),
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

    private function workOrderSummaryRows(array $rows): array
    {
        $qty = 0.0;
        $finished = 0.0;
        $statusCounts = [];
        foreach ($rows as $row) {
            $qty += (float) ($row['wo_qty'] ?? 0);
            $finished += (float) ($row['act_qty_finished'] ?? 0);
            $status = (string) ($row['status'] ?? 'unknown');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        $summary = [
            ['Metric', 'Value'],
            ['Report', 'Production Work Orders'],
            ['Rows', count($rows)],
            ['Total WO Qty', $qty],
            ['Total Finished Qty', $finished],
            ['Generated At', date('Y-m-d H:i:s')],
            ['', ''],
            ['Status', 'Count'],
        ];
        foreach ($statusCounts as $status => $count) {
            $summary[] = [$status, $count];
        }

        return $summary;
    }

    private function workOrderRows(array $rows): array
    {
        $exportRows = [[
            'WO Code',
            'WO No',
            'WO Date',
            'Site',
            'Department',
            'Warehouse',
            'Work Center',
            'Parent Item Code',
            'Parent Item Name',
            'Batch Qty',
            'WO Qty',
            'Std Finished Qty',
            'Actual Finished Qty',
            'Status',
            'Description',
            'Created At',
            'Updated At',
        ]];

        foreach ($rows as $row) {
            $exportRows[] = [
                $row['wo_code'] ?? '',
                $row['wo_no'] ?? '',
                $row['wo_date'] ?? '',
                $row['site_code'] ?? '',
                $row['department_code'] ?? '',
                $row['warehouse_code'] ?? '',
                $row['work_center_code'] ?? '',
                $row['parent_item_code'] ?? '',
                $row['parent_item_name'] ?? '',
                (float) ($row['batch_qty'] ?? 0),
                (float) ($row['wo_qty'] ?? 0),
                (float) ($row['std_qty_finished'] ?? 0),
                (float) ($row['act_qty_finished'] ?? 0),
                $row['status'] ?? '',
                $row['description'] ?? '',
                $row['created_at'] ?? '',
                $row['updated_at'] ?? '',
            ];
        }

        return $exportRows;
    }

    private function singleWorkOrderSummaryRows(array $workOrder, array $components, array $routings): array
    {
        return [
            ['Metric', 'Value'],
            ['Report', 'Production Work Order Detail'],
            ['WO No', (string) ($workOrder['wo_no'] ?? '-')],
            ['WO Date', (string) ($workOrder['wo_date'] ?? '-')],
            ['Status', (string) ($workOrder['status'] ?? '-')],
            ['Site', (string) ($workOrder['site_code'] ?? '-')],
            ['Department', (string) ($workOrder['department_code'] ?? '-')],
            ['Warehouse', (string) ($workOrder['warehouse_code'] ?? '-')],
            ['Work Center', (string) ($workOrder['work_center_code'] ?? '-')],
            ['Parent Item', (string) ($workOrder['parent_item_code'] ?? '-')],
            ['Parent Item Name', (string) ($workOrder['parent_item_name'] ?? '-')],
            ['WO Qty', (float) ($workOrder['wo_qty'] ?? 0)],
            ['Std Finished Qty', (float) ($workOrder['std_qty_finished'] ?? 0)],
            ['Actual Finished Qty', (float) ($workOrder['act_qty_finished'] ?? 0)],
            ['Component Lines', count($components)],
            ['Routing Lines', count($routings)],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function componentRows(array $components): array
    {
        $rows = [[
            'Line No',
            'Component Item Code',
            'Component Item Name',
            'Qty Used',
            'UoM',
            'Warehouse',
            'Location',
            'Batch No',
            'Booking Qty',
            'Allocated Qty',
            'Issued Qty',
            'Line Status',
        ]];

        foreach ($components as $line) {
            $rows[] = [
                (int) ($line['line_no'] ?? 0),
                $line['component_item_code'] ?? '',
                $line['component_item_name'] ?? '',
                (float) ($line['qty_used'] ?? 0),
                $line['uom_code'] ?? '',
                $line['warehouse_code'] ?? '',
                $line['location_code'] ?? '',
                $line['batch_no'] ?? '',
                (float) ($line['booking_qty'] ?? 0),
                (float) ($line['allocated_qty'] ?? 0),
                (float) ($line['issued_qty'] ?? 0),
                $line['line_status'] ?? '',
            ];
        }

        return $rows;
    }

    private function routingRows(array $routings): array
    {
        $rows = [[
            'Line No',
            'Routing Code',
            'Routing Name',
            'Work Center Code',
            'Work Center Name',
            'Hour Qty',
            'UoM',
        ]];

        foreach ($routings as $line) {
            $rows[] = [
                (int) ($line['line_no'] ?? 0),
                $line['routing_code'] ?? '',
                $line['routing_name'] ?? '',
                $line['work_center_code'] ?? '',
                $line['work_center_name'] ?? '',
                (float) ($line['hour_qty'] ?? 0),
                $line['uom_code'] ?? '',
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
