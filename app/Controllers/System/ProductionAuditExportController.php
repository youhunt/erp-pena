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
        $workOrders = $model->orderBy('wo_date', 'DESC')->orderBy('id', 'DESC')->findAll(10000);

        return $this->xlsxWorkbookResponse('production-work-orders-' . date('Y-m-d') . '.xlsx', [
            'Work Orders' => $this->workOrderTemplateRows($workOrders),
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
            'Work Orders' => $this->workOrderTemplateRows([$workOrder]),
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

    private function workOrderTemplateHeaders(): array
    {
        return [
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
            'component_line_no',
            'component_item_code',
            'component_item_name',
            'qty_used',
            'component_uom_code',
            'component_whs',
            'component_loc',
            'component_batch_no',
            'booking_qty',
            'routing_line_no',
            'routing_name',
            'route_work_center_code',
            'work_center_name',
            'hour_qty',
            'route_uom',
        ];
    }

    private function workOrderTemplateRows(array $workOrders): array
    {
        $rows = [$this->workOrderTemplateHeaders()];
        $componentModel = new ProductionWorkOrderComponentModel();
        $routingModel = new ProductionWorkOrderRoutingModel();

        foreach ($workOrders as $workOrder) {
            $workOrderId = (int) ($workOrder['id'] ?? 0);
            $components = $workOrderId > 0
                ? $componentModel->where('production_work_order_id', $workOrderId)->orderBy('line_no', 'ASC')->findAll(10000)
                : [];
            $routings = $workOrderId > 0
                ? $routingModel->where('production_work_order_id', $workOrderId)->orderBy('line_no', 'ASC')->findAll(10000)
                : [];

            $detailCount = max(1, count($components), count($routings));
            for ($index = 0; $index < $detailCount; $index++) {
                $component = $components[$index] ?? [];
                $routing = $routings[$index] ?? [];
                $rows[] = $this->workOrderTemplateRow($workOrder, $component, $routing);
            }
        }

        return $rows;
    }

    private function workOrderTemplateRow(array $workOrder, array $component, array $routing): array
    {
        return [
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
            $component === [] ? '' : (int) ($component['line_no'] ?? 0),
            $component['component_item_code'] ?? '',
            $component['component_item_name'] ?? '',
            $component === [] ? '' : (float) ($component['qty_used'] ?? 0),
            $component['uom_code'] ?? '',
            $component['warehouse_code'] ?? '',
            $component['location_code'] ?? '',
            $component['batch_no'] ?? '',
            $component === [] ? '' : (float) ($component['booking_qty'] ?? 0),
            $routing === [] ? '' : (int) ($routing['line_no'] ?? 0),
            $routing['routing_name'] ?? '',
            $routing['work_center_code'] ?? '',
            $routing['work_center_name'] ?? '',
            $routing === [] ? '' : (float) ($routing['hour_qty'] ?? 0),
            $routing['uom_code'] ?? '',
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
