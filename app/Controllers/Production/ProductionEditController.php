<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\ProductionBomLineModel;
use App\Models\ProductionBomModel;
use App\Models\ProductionRoutingLineModel;
use App\Models\ProductionRoutingModel;
use App\Models\ProductionWorkCenterModel;
use App\Models\ProductionWorkOrderComponentModel;
use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderRoutingModel;
use App\Models\WorkCenterCostModel;
use App\Models\WorkCenterMachineModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;
use Throwable;

class ProductionEditController extends BaseController
{
    public function editBom(int $id): string
    {
        $bom = $this->findScoped(new ProductionBomModel(), $id);
        return view('production/boms/form', $this->lookups() + [
            'title' => 'Edit BOM',
            'isEdit' => true,
            'action' => site_url('production/boms/' . $id),
            'bom' => $bom,
            'lines' => (new ProductionBomLineModel())->where('production_bom_id', $id)->orderBy('child_no', 'ASC')->findAll(),
        ]);
    }

    public function updateBom(int $id)
    {
        try {
            $this->saveBom($id);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->to('/production/boms/' . $id)->with('message', 'BOM updated.');
    }

    public function editWorkCenter(int $id): string
    {
        $wc = $this->findScoped(new ProductionWorkCenterModel(), $id);
        return view('production/work_centers/form', $this->lookups() + [
            'title' => 'Edit Work Center',
            'isEdit' => true,
            'action' => site_url('production/work-centers/' . $id),
            'workCenter' => $wc,
            'machines' => (new WorkCenterMachineModel())->where('work_center_id', $id)->orderBy('no', 'ASC')->findAll(),
            'costs' => (new WorkCenterCostModel())->where('work_center_id', $id)->orderBy('costtype', 'ASC')->findAll(),
        ]);
    }

    public function updateWorkCenter(int $id)
    {
        try {
            $this->saveWorkCenter($id);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->to('/production/work-centers/' . $id)->with('message', 'Work Center updated.');
    }

    public function editRouting(int $id): string
    {
        $routing = $this->findScoped(new ProductionRoutingModel(), $id);
        return view('production/routings/form', $this->lookups() + [
            'title' => 'Edit Routing',
            'isEdit' => true,
            'action' => site_url('production/routings/' . $id),
            'routing' => $routing,
            'lines' => (new ProductionRoutingLineModel())->where('production_routing_id', $id)->orderBy('route_no', 'ASC')->findAll(),
            'workCenters' => (new ProductionWorkCenterModel())->orderBy('work_center_code', 'ASC')->findAll(200),
        ]);
    }

    public function updateRouting(int $id)
    {
        try {
            $this->saveRouting($id);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->to('/production/routings/' . $id)->with('message', 'Routing updated.');
    }

    public function editWorkOrder(int $id): string
    {
        $wo = $this->findScoped(new ProductionWorkOrderModel(), $id);
        if (($wo['status'] ?? 'draft') !== 'draft') {
            return view('errors/html/error_404', ['message' => 'Only draft Work Order can be edited.']);
        }
        return view('production/work_orders/form', $this->lookups() + [
            'title' => 'Edit Work Order',
            'isEdit' => true,
            'action' => site_url('production/work-orders/' . $id),
            'workOrder' => $wo,
            'components' => (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'routings' => (new ProductionWorkOrderRoutingModel())->where('production_work_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'workCenters' => (new ProductionWorkCenterModel())->orderBy('work_center_code', 'ASC')->findAll(200),
        ]);
    }

    public function updateWorkOrder(int $id)
    {
        try {
            $this->saveWorkOrder($id);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->to('/production/work-orders/' . $id)->with('message', 'Work Order updated.');
    }

    private function saveBom(int $id): void
    {
        $tenant = new TenantContext(session());
        $company = $tenant->activeCompanyId();
        if (! $company) {
            throw new RuntimeException('Active company is required.');
        }
        $this->findScoped(new ProductionBomModel(), $id);
        $parent = trim((string) $this->request->getPost('parent_item_code'));
        if ($parent === '') {
            throw new RuntimeException('Parent item is required.');
        }

        $where = [
            'company_id' => $company,
            'site_code' => trim((string) $this->request->getPost('site_code')),
            'department_code' => trim((string) $this->request->getPost('department_code')),
            'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
            'parent_item_code' => $parent,
        ];
        $dup = (new ProductionBomModel())->where($where)->where('id !=', $id)->first();
        if ($dup) {
            throw new RuntimeException('BOM with same Company/Site/Department/Warehouse/Parent Item already exists.');
        }

        $item = $this->item($parent);
        $db = Database::connect();
        $db->transBegin();
        try {
            (new ProductionBomModel())->update($id, $where + [
                'site_id' => $tenant->activeSiteId(),
                'parent_item_id' => $item['id'] ?? null,
                'parent_item_name' => $this->itemName($item, $parent),
                'bom_type' => trim((string) ($this->request->getPost('bom_type') ?: 'standard')),
                'qty_batch' => $this->toNumber($this->request->getPost('qty_batch')),
                'uom_code' => trim((string) $this->request->getPost('uom_code')),
                'ratio_percent' => $this->toNumber($this->request->getPost('ratio_percent') ?: 100),
                'description' => trim((string) $this->request->getPost('description')),
                'active_date' => $this->ndt($this->request->getPost('active_date')),
                'inactive_date' => $this->ndt($this->request->getPost('inactive_date')),
                'updated_by' => auth()->id(),
            ]);
            $lineModel = new ProductionBomLineModel();
            $lineModel->where('production_bom_id', $id)->delete();
            foreach ($this->bomLines() as $line) {
                $lineModel->insert($line + ['production_bom_id' => $id]);
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to update BOM.');
            }
            $db->transCommit();
            $this->audit('production.bom', 'bom.update', 'production_boms', $id, $parent);
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function saveWorkCenter(int $id): void
    {
        $tenant = new TenantContext(session());
        $company = $tenant->activeCompanyId();
        if (! $company) {
            throw new RuntimeException('Active company is required.');
        }
        $this->findScoped(new ProductionWorkCenterModel(), $id);

        $where = [
            'company_id' => $company,
            'site_code' => trim((string) $this->request->getPost('site_code')),
            'department_code' => trim((string) $this->request->getPost('department_code')),
            'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
            'work_center_code' => trim((string) $this->request->getPost('work_center_code')),
        ];
        if ($where['work_center_code'] === '') {
            throw new RuntimeException('Work Center code is required.');
        }
        $dup = (new ProductionWorkCenterModel())->where($where)->where('id !=', $id)->first();
        if ($dup) {
            throw new RuntimeException('Work Center with same key already exists.');
        }

        $machineRows = $this->wcMachines();
        if ($machineRows === []) {
            throw new RuntimeException('Minimal isi 1 Machine Detail.');
        }
        $costRows = $this->wcCosts();
        $primaryMachine = $machineRows[0];
        $primaryCost = $costRows[0] ?? ['costtype' => '', 'costamount' => 0, 'costuom' => ''];

        $db = Database::connect();
        $db->transBegin();
        try {
            (new ProductionWorkCenterModel())->update($id, $where + [
                'site_id' => $tenant->activeSiteId(),
                'description' => trim((string) $this->request->getPost('description')),
                'machine_code' => (string) ($primaryMachine['machine'] ?? ''),
                'notes' => trim((string) $this->request->getPost('notes')),
                'speed' => (float) ($primaryMachine['speed'] ?? 0),
                'capacity_percent' => (float) ($primaryMachine['capacity'] ?? 100),
                'max_length' => (float) ($primaryMachine['length'] ?? 0),
                'length_uom' => (string) ($primaryMachine['luom'] ?? ''),
                'max_width' => (float) ($primaryMachine['width'] ?? 0),
                'width_uom' => (string) ($primaryMachine['wuom'] ?? ''),
                'max_height' => (float) ($primaryMachine['height'] ?? 0),
                'height_uom' => (string) ($primaryMachine['huom'] ?? ''),
                'max_volume' => (float) ($primaryMachine['volume'] ?? 0),
                'volume_uom' => (string) ($primaryMachine['vuom'] ?? ''),
                'qty_labor' => (float) ($primaryMachine['qtylabor'] ?? 0),
                'working_hour' => (float) ($primaryMachine['workhour'] ?? 0),
                'cost_type' => (string) ($primaryCost['costtype'] ?? ''),
                'cost_amount' => (float) ($primaryCost['costamount'] ?? 0),
                'cost_uom' => (string) ($primaryCost['costuom'] ?? ''),
                'active_date' => $this->nd($this->request->getPost('active_date')),
                'inactive_date' => $this->nd($this->request->getPost('inactive_date')),
                'updated_by' => auth()->id(),
            ]);

            (new WorkCenterMachineModel())->where('work_center_id', $id)->delete();
            (new WorkCenterCostModel())->where('work_center_id', $id)->delete();
            $this->saveWcChildren($id, $company, $tenant, $where, $machineRows, $costRows);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to update Work Center.');
            }
            $db->transCommit();
            $this->audit('production.work_center', 'work_center.update', 'production_work_centers', $id, $where['work_center_code']);
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function saveRouting(int $id): void
    {
        $tenant = new TenantContext(session());
        $company = $tenant->activeCompanyId();
        if (! $company) {
            throw new RuntimeException('Active company is required.');
        }
        $this->findScoped(new ProductionRoutingModel(), $id);
        $itemCode = trim((string) $this->request->getPost('item_code'));
        if ($itemCode === '') {
            throw new RuntimeException('Item code is required.');
        }

        $where = ['company_id' => $company, 'site_code' => trim((string) $this->request->getPost('site_code')), 'item_code' => $itemCode];
        $dup = (new ProductionRoutingModel())->where($where)->where('id !=', $id)->first();
        if ($dup) {
            throw new RuntimeException('Routing with same Company/Site/Item already exists.');
        }
        $item = $this->item($itemCode);
        $db = Database::connect();
        $db->transBegin();
        try {
            (new ProductionRoutingModel())->update($id, $where + [
                'site_id' => $tenant->activeSiteId(),
                'department_code' => trim((string) $this->request->getPost('department_code')),
                'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
                'item_id' => $item['id'] ?? null,
                'description' => trim((string) $this->request->getPost('description')),
                'updated_by' => auth()->id(),
            ]);
            $lineModel = new ProductionRoutingLineModel();
            $lineModel->where('production_routing_id', $id)->delete();
            foreach ($this->routingLines() as $line) {
                $lineModel->insert($line + ['production_routing_id' => $id]);
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to update Routing.');
            }
            $db->transCommit();
            $this->audit('production.routing', 'routing.update', 'production_routings', $id, $itemCode);
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function saveWorkOrder(int $id): void
    {
        $tenant = new TenantContext(session());
        $company = $tenant->activeCompanyId();
        if (! $company) {
            throw new RuntimeException('Active company is required.');
        }
        $wo = $this->findScoped(new ProductionWorkOrderModel(), $id);
        if (($wo['status'] ?? 'draft') !== 'draft') {
            throw new RuntimeException('Only draft Work Order can be edited.');
        }
        $woNo = trim((string) $this->request->getPost('wo_no'));
        if ($woNo === '') {
            throw new RuntimeException('WO No is required.');
        }
        $dup = (new ProductionWorkOrderModel())->where(['company_id' => $company, 'wo_no' => $woNo])->where('id !=', $id)->first();
        if ($dup) {
            throw new RuntimeException('WO No already exists.');
        }
        $parent = trim((string) $this->request->getPost('parent_item_code'));
        $item = $this->item($parent);
        $db = Database::connect();
        $db->transBegin();
        try {
            (new ProductionWorkOrderModel())->update($id, [
                'site_id' => $tenant->activeSiteId(),
                'site_code' => trim((string) $this->request->getPost('site_code')),
                'site' => trim((string) $this->request->getPost('site_code')),
                'department_code' => trim((string) $this->request->getPost('department_code')),
                'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
                'work_center_code' => trim((string) $this->request->getPost('work_center_code')),
                'wo_code' => trim((string) ($this->request->getPost('wo_code') ?: 'WO')),
                'wo_no' => $woNo,
                'wo_date' => (string) $this->request->getPost('wo_date'),
                'parent_item_id' => $item['id'] ?? null,
                'parent_item_code' => $parent,
                'parent_item_name' => $this->itemName($item, $parent),
                'batch_qty' => $this->toNumber($this->request->getPost('batch_qty') ?: 1),
                'wo_qty' => $this->toNumber($this->request->getPost('wo_qty')),
                'std_qty_finished' => $this->toNumber($this->request->getPost('std_qty_finished') ?: $this->request->getPost('wo_qty')),
                'act_qty_finished' => $this->toNumber($this->request->getPost('act_qty_finished')),
                'uom_code' => trim((string) ($this->request->getPost('uom_code') ?: 'PCS')),
                'description' => trim((string) $this->request->getPost('description')),
                'updated_by' => auth()->id(),
            ]);
            (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $id)->delete();
            (new ProductionWorkOrderRoutingModel())->where('production_work_order_id', $id)->delete();
            foreach ($this->woComponents() as $line) {
                (new ProductionWorkOrderComponentModel())->insert($line + ['production_work_order_id' => $id]);
            }
            foreach ($this->woRoutings() as $line) {
                (new ProductionWorkOrderRoutingModel())->insert($line + ['production_work_order_id' => $id]);
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to update Work Order.');
            }
            $db->transCommit();
            $this->audit('production.wo', 'wo.update', 'production_work_orders', $id, $woNo);
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function findScoped($model, int $id): array
    {
        $tenant = new TenantContext(session());
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        $row = $model->find($id);
        if (! $row) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $row;
    }

    private function lookups(): array
    {
        return [
            'sites' => $this->master('sites'),
            'departments' => $this->master('departments'),
            'warehouses' => $this->master('warehouses'),
            'uoms' => $this->master('uoms'),
            'items' => $this->master('items'),
            'workCenters' => (new ProductionWorkCenterModel())->orderBy('work_center_code', 'ASC')->findAll(200),
        ];
    }

    private function master(string $table): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }
        $builder = $db->table($table);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', $table)) {
            $builder->where('is_active', 1);
        }
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get()->getResultArray();
    }

    private function item(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        if (! $db->tableExists('items')) {
            return null;
        }
        $builder = $db->table('items');
        $db->fieldExists('item_code', 'items') ? $builder->where('item_code', $code) : $builder->where('code', $code);
        return $builder->get()->getRowArray();
    }

    private function itemName(?array $item, string $fallback): string
    {
        return $item ? (string) ($item['item_name'] ?? $item['name'] ?? $item['code'] ?? $fallback) : $fallback;
    }

    private function nd($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function ndt($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? str_replace('T', ' ', $value) : null;
    }

    private function bomLines(): array
    {
        $nos = (array) $this->request->getPost('child_no');
        $codes = (array) $this->request->getPost('child_item_code');
        $types = (array) $this->request->getPost('component_type');
        $qtys = (array) $this->request->getPost('qty_used');
        $uoms = (array) $this->request->getPost('line_uom_code');
        $factors = (array) $this->request->getPost('factor');
        $descriptions = (array) $this->request->getPost('line_description');
        $out = [];
        foreach ($codes as $i => $code) {
            $code = trim((string) $code);
            $qty = $this->toNumber($qtys[$i] ?? 0);
            if ($code === '' || $qty <= 0) {
                continue;
            }
            $item = $this->item($code);
            $out[] = [
                'child_no' => (int) ($nos[$i] ?? (($i + 1) * 10)),
                'child_item_id' => $item['id'] ?? null,
                'child_item_code' => $code,
                'child_item_name' => $this->itemName($item, $code),
                'component_type' => trim((string) ($types[$i] ?? 'material')),
                'qty_used' => $qty,
                'uom_code' => trim((string) ($uoms[$i] ?? 'PCS')),
                'factor' => $this->toNumber($factors[$i] ?? 1),
                'description' => trim((string) ($descriptions[$i] ?? '')),
            ];
        }
        if ($out === []) {
            throw new RuntimeException('At least one BOM child item is required.');
        }
        return $out;
    }

    private function routingLines(): array
    {
        $nos = (array) $this->request->getPost('route_no');
        $workCenters = (array) $this->request->getPost('work_center_code');
        $names = (array) $this->request->getPost('routing_name');
        $types = (array) $this->request->getPost('operation_type');
        $hours = (array) $this->request->getPost('hour_qty');
        $hourUoms = (array) $this->request->getPost('hour_uom');
        $speeds = (array) $this->request->getPost('std_speed');
        $speedUoms = (array) $this->request->getPost('speed_uom');
        $notes = (array) $this->request->getPost('route_notes');
        $out = [];
        foreach ($nos as $i => $no) {
            $no = trim((string) $no);
            $wc = trim((string) ($workCenters[$i] ?? ''));
            if ($no === '' || $wc === '') {
                continue;
            }
            $out[] = [
                'route_no' => $no,
                'routing_name' => trim((string) ($names[$i] ?? '')),
                'work_center_code' => $wc,
                'operation_type' => trim((string) ($types[$i] ?? 'process')),
                'hour_qty' => $this->toNumber($hours[$i] ?? 0),
                'hour_uom' => trim((string) ($hourUoms[$i] ?? 'Hour')),
                'std_speed' => $this->toNumber($speeds[$i] ?? 0),
                'speed_uom' => trim((string) ($speedUoms[$i] ?? 'Unit/Hour')),
                'notes' => trim((string) ($notes[$i] ?? '')),
            ];
        }
        if ($out === []) {
            throw new RuntimeException('At least one routing line is required.');
        }
        return $out;
    }

    private function woComponents(): array
    {
        $nos = (array) $this->request->getPost('component_line_no');
        $codes = (array) $this->request->getPost('component_item_code');
        $names = (array) $this->request->getPost('component_item_name');
        $qtys = (array) $this->request->getPost('component_qty_used');
        $uoms = (array) $this->request->getPost('component_uom_code');
        $warehouses = (array) $this->request->getPost('component_warehouse_code');
        $locations = (array) $this->request->getPost('component_location_code');
        $batches = (array) $this->request->getPost('component_batch_no');
        $bookings = (array) $this->request->getPost('component_booking_qty');
        $out = [];
        foreach ($codes as $i => $code) {
            $code = trim((string) $code);
            $qty = $this->toNumber($qtys[$i] ?? 0);
            if ($code === '' || $qty <= 0) {
                continue;
            }
            $item = $this->item($code);
            $out[] = [
                'line_no' => (int) ($nos[$i] ?? (($i + 1) * 10)),
                'component_item_id' => $item['id'] ?? null,
                'component_item_code' => $code,
                'component_item_name' => trim((string) ($names[$i] ?? $this->itemName($item, $code))),
                'qty_used' => $qty,
                'uom_code' => trim((string) ($uoms[$i] ?? 'PCS')),
                'warehouse_code' => trim((string) ($warehouses[$i] ?? '')),
                'location_code' => trim((string) ($locations[$i] ?? '')),
                'batch_no' => trim((string) ($batches[$i] ?? '')),
                'booking_qty' => $this->toNumber($bookings[$i] ?? $qty),
                'allocated_qty' => 0,
                'issued_qty' => 0,
                'line_status' => 'open',
            ];
        }
        return $out;
    }

    private function woRoutings(): array
    {
        $nos = (array) $this->request->getPost('routing_line_no');
        $names = (array) $this->request->getPost('wo_routing_name');
        $workCenters = (array) $this->request->getPost('wo_work_center_code');
        $hours = (array) $this->request->getPost('wo_hour_qty');
        $uoms = (array) $this->request->getPost('wo_route_uom');
        $out = [];
        foreach ($workCenters as $i => $wc) {
            $wc = trim((string) $wc);
            if ($wc === '') {
                continue;
            }
            $out[] = [
                'line_no' => (int) ($nos[$i] ?? (($i + 1) * 10)),
                'routing_name' => trim((string) ($names[$i] ?? '')),
                'work_center_code' => $wc,
                'work_center_name' => $wc,
                'hour_qty' => $this->toNumber($hours[$i] ?? 0),
                'uom_code' => trim((string) ($uoms[$i] ?? 'Hour')),
            ];
        }
        return $out;
    }

    private function saveWcChildren(int $id, int $company, TenantContext $tenant, array $where, array $machines, array $costs): void
    {
        $user = (string) (auth()->user()?->username ?? auth()->user()?->email ?? auth()->id() ?? 'system');
        foreach ($machines as $row) {
            (new WorkCenterMachineModel())->insert($row + [
                'company_id' => $company,
                'site_id' => $tenant->activeSiteId(),
                'work_center_id' => $id,
                'site' => $where['site_code'],
                'dept' => $where['department_code'],
                'warehouse' => $where['warehouse_code'],
                'work_center' => $where['work_center_code'],
                'created_by' => $user,
                'updated_by' => $user,
                'active' => 1,
            ]);
        }
        foreach ($costs as $row) {
            (new WorkCenterCostModel())->insert($row + [
                'company_id' => $company,
                'site_id' => $tenant->activeSiteId(),
                'work_center_id' => $id,
                'work_center' => $where['work_center_code'],
                'created_by' => $user,
                'updated_by' => $user,
                'active' => 1,
            ]);
        }
    }

    private function wcMachines(): array
    {
        $machines = (array) $this->request->getPost('machine');
        $out = [];
        foreach ($machines as $i => $machine) {
            $machine = trim((string) $machine);
            if ($machine === '') {
                continue;
            }
            $out[] = [
                'no' => (int) (((array) $this->request->getPost('machine_no'))[$i] ?? (($i + 1) * 10)),
                'machine' => $machine,
                'notes1' => trim((string) (((array) $this->request->getPost('machine_notes'))[$i] ?? '')),
                'speed' => $this->toNumber(((array) $this->request->getPost('machine_speed'))[$i] ?? 0),
                'capacity' => $this->toNumber(((array) $this->request->getPost('machine_capacity'))[$i] ?? 100),
                'length' => $this->toNumber(((array) $this->request->getPost('machine_length'))[$i] ?? 0),
                'luom' => trim((string) (((array) $this->request->getPost('machine_luom'))[$i] ?? '')),
                'width' => $this->toNumber(((array) $this->request->getPost('machine_width'))[$i] ?? 0),
                'wuom' => trim((string) (((array) $this->request->getPost('machine_wuom'))[$i] ?? '')),
                'height' => $this->toNumber(((array) $this->request->getPost('machine_height'))[$i] ?? 0),
                'huom' => trim((string) (((array) $this->request->getPost('machine_huom'))[$i] ?? '')),
                'volume' => $this->toNumber(((array) $this->request->getPost('machine_volume'))[$i] ?? 0),
                'vuom' => trim((string) (((array) $this->request->getPost('machine_vuom'))[$i] ?? '')),
                'qtylabor' => $this->toNumber(((array) $this->request->getPost('machine_qtylabor'))[$i] ?? 0),
                'workhour' => $this->toNumber(((array) $this->request->getPost('machine_workhour'))[$i] ?? 0),
            ];
        }
        return $out;
    }

    private function wcCosts(): array
    {
        $types = (array) $this->request->getPost('costtype');
        $out = [];
        foreach ($types as $i => $type) {
            $type = trim((string) $type);
            if ($type === '') {
                continue;
            }
            $out[] = [
                'costtype' => $type,
                'costamount' => $this->toNumber(((array) $this->request->getPost('costamount'))[$i] ?? 0),
                'costuom' => trim((string) (((array) $this->request->getPost('costuom'))[$i] ?? '')),
                'notes2' => trim((string) (((array) $this->request->getPost('cost_notes'))[$i] ?? '')),
            ];
        }
        return $out;
    }

    private function toNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        return (float) $value;
    }

    private function audit(string $module, string $action, string $table, int $id, string $code): void
    {
        (new AuditLogService())->log($module, $action, [
            'company_id' => (new TenantContext(session()))->activeCompanyId(),
            'site_id' => (new TenantContext(session()))->activeSiteId(),
            'user_id' => auth()->id(),
            'table_name' => $table,
            'record_id' => $id,
            'record_code' => $code,
            'description' => $action . ' completed.',
        ]);
    }
}
