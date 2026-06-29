<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\ProductionBomLineModel;
use App\Models\ProductionBomModel;
use App\Models\ProductionRoutingLineModel;
use App\Models\ProductionRoutingModel;
use App\Models\ProductionWorkCenterModel;
use App\Services\TenantContext;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class WorkOrderBomPreviewController extends BaseController
{
    public function index(): ResponseInterface
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Active company is required.']);
        }

        $header = [
            'company_id' => $companyId,
            'site_id' => $tenant->activeSiteId(),
            'site_code' => trim((string) $this->request->getGet('site_code')),
            'department_code' => trim((string) $this->request->getGet('department_code')),
            'warehouse_code' => trim((string) $this->request->getGet('warehouse_code')),
            'parent_item_code' => trim((string) $this->request->getGet('parent_item_code')),
            'wo_qty' => (float) ($this->request->getGet('wo_qty') ?: 1),
        ];

        if ($header['site_code'] === '' || $header['parent_item_code'] === '') {
            return $this->response->setJSON(['bom' => null, 'components' => [], 'routing' => null, 'routings' => []]);
        }

        try {
            return $this->response->setJSON($this->preview($header));
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => $e->getMessage()]);
        }
    }

    private function preview(array $header): array
    {
        $bom = $this->findBom($header);
        if ($bom === null) {
            throw new RuntimeException('BOM not found for parent item ' . $header['parent_item_code'] . '.');
        }

        $woQty = (float) ($header['wo_qty'] ?? 1);
        $batchQty = max(1.0, (float) ($bom['qty_batch'] ?? 1));
        $scale = $woQty / $batchQty;
        $warehouse = trim((string) ($header['warehouse_code'] ?? ''));
        if ($warehouse === '') {
            $warehouse = (string) ($bom['warehouse_code'] ?? '');
        }

        $components = [];
        foreach ((new ProductionBomLineModel())->where('production_bom_id', (int) $bom['id'])->orderBy('child_no', 'ASC')->findAll() as $line) {
            $qty = round((float) ($line['qty_used'] ?? 0) * $scale, 12);
            $components[] = [
                'line_no' => (int) ($line['child_no'] ?? 0),
                'component_item_code' => (string) ($line['child_item_code'] ?? ''),
                'component_item_name' => (string) ($line['child_item_name'] ?? ''),
                'qty_used' => $qty,
                'uom_code' => (string) ($line['uom_code'] ?? ''),
                'warehouse_code' => $warehouse,
                'location_code' => '',
                'batch_no' => '',
                'booking_qty' => $qty,
            ];
        }

        $routing = $this->findRouting($header, $bom);
        $routings = [];
        if ($routing !== null) {
            foreach ((new ProductionRoutingLineModel())->where('production_routing_id', (int) $routing['id'])->orderBy('route_no', 'ASC')->findAll() as $line) {
                $workCenter = (new ProductionWorkCenterModel())
                    ->where('company_id', $header['company_id'])
                    ->where('work_center_code', $line['work_center_code'])
                    ->first();
                $routings[] = [
                    'line_no' => (int) ($line['route_no'] ?? 0),
                    'routing_name' => (string) ($line['routing_name'] ?? ''),
                    'work_center_code' => (string) ($line['work_center_code'] ?? ''),
                    'work_center_name' => (string) ($workCenter['description'] ?? $line['work_center_code'] ?? ''),
                    'hour_qty' => round((float) ($line['hour_qty'] ?? 0) * $scale, 8),
                    'uom_code' => (string) ($line['hour_uom'] ?? 'Hour'),
                ];
            }
        }

        return [
            'bom' => [
                'id' => (int) $bom['id'],
                'batch_qty' => $batchQty,
                'uom_code' => (string) ($bom['uom_code'] ?? 'PCS'),
                'description' => (string) ($bom['description'] ?? ''),
                'warehouse_code' => (string) ($bom['warehouse_code'] ?? ''),
            ],
            'routing' => $routing === null ? null : [
                'id' => (int) $routing['id'],
                'work_center_code' => (string) ($routings[0]['work_center_code'] ?? ''),
            ],
            'components' => $components,
            'routings' => $routings,
        ];
    }

    private function findBom(array $header): ?array
    {
        $model = new ProductionBomModel();
        $model->where('company_id', $header['company_id'])
            ->where('site_code', $header['site_code'])
            ->where('parent_item_code', $header['parent_item_code']);
        if ($header['department_code'] !== '') {
            $model->where('department_code', $header['department_code']);
        }
        if ($header['warehouse_code'] !== '') {
            $model->groupStart()->where('warehouse_code', $header['warehouse_code'])->orWhere('warehouse_code', '')->orWhere('warehouse_code', null)->groupEnd();
        }

        $row = $model->orderBy('warehouse_code', 'DESC')->orderBy('id', 'DESC')->first();
        if ($row !== null) {
            return $row;
        }

        return (new ProductionBomModel())
            ->where('company_id', $header['company_id'])
            ->where('site_code', $header['site_code'])
            ->where('parent_item_code', $header['parent_item_code'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function findRouting(array $header, array $bom): ?array
    {
        if (! empty($bom['routing_id'])) {
            $linked = (new ProductionRoutingModel())->where('company_id', $header['company_id'])->find((int) $bom['routing_id']);
            if ($linked !== null) {
                return $linked;
            }
        }

        $model = new ProductionRoutingModel();
        $model->where('company_id', $header['company_id'])
            ->where('site_code', $header['site_code'])
            ->where('item_code', $header['parent_item_code']);
        if ($header['department_code'] !== '') {
            $model->where('department_code', $header['department_code']);
        }
        if ($header['warehouse_code'] !== '') {
            $model->groupStart()->where('warehouse_code', $header['warehouse_code'])->orWhere('warehouse_code', '')->orWhere('warehouse_code', null)->groupEnd();
        }

        $row = $model->orderBy('warehouse_code', 'DESC')->orderBy('id', 'DESC')->first();
        if ($row !== null) {
            return $row;
        }

        return (new ProductionRoutingModel())
            ->where('company_id', $header['company_id'])
            ->where('site_code', $header['site_code'])
            ->where('item_code', $header['parent_item_code'])
            ->orderBy('id', 'DESC')
            ->first();
    }
}
