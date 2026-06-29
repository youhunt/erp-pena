<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\ProductionBomLineModel;
use App\Models\ProductionBomModel;
use App\Models\ProductionRoutingLineModel;
use App\Models\ProductionRoutingModel;
use App\Models\ProductionWorkCenterModel;
use App\Models\WorkCenterCostModel;
use App\Models\WorkCenterMachineModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;
use Throwable;

class ProductionMasterController extends BaseController
{
    public function boms(): string
    {
        $tenant = new TenantContext(session());
        $model = $this->scopeModel(new ProductionBomModel(), $tenant);

        return view('production/boms/index', [
            'title' => 'BOM',
            'rows' => $model->orderBy('parent_item_code', 'ASC')->findAll(100),
        ]);
    }

    public function newBom(): string
    {
        return view('production/boms/form', $this->formLookups() + [
            'title' => 'Create BOM',
            'bom' => null,
            'lines' => [],
        ]);
    }

    public function storeBom()
    {
        if (! $this->validate([
            'site_code' => 'required|max_length[12]',
            'department_code' => 'required|max_length[12]',
            'parent_item_code' => 'required|max_length[50]',
            'qty_batch' => 'required|decimal',
            'uom_code' => 'required|max_length[12]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $id = $this->saveBom();
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/boms/' . $id)->with('message', 'BOM saved.');
    }

    public function showBom(int $id): string
    {
        $tenant = new TenantContext(session());
        $bom = $this->scopeModel(new ProductionBomModel(), $tenant)->find($id);
        if ($bom === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('production/boms/show', [
            'title' => 'BOM Detail',
            'bom' => $bom,
            'lines' => (new ProductionBomLineModel())->where('production_bom_id', $id)->orderBy('child_no', 'ASC')->findAll(),
        ]);
    }

    public function workCenters(): string
    {
        $tenant = new TenantContext(session());
        $model = $this->scopeModel(new ProductionWorkCenterModel(), $tenant);

        return view('production/work_centers/index', [
            'title' => 'Work Center',
            'rows' => $model->orderBy('work_center_code', 'ASC')->findAll(100),
        ]);
    }

    public function newWorkCenter(): string
    {
        return view('production/work_centers/form', $this->formLookups() + [
            'title' => 'Create Work Center',
            'workCenter' => [],
            'machines' => [],
            'costs' => [],
            'isEdit' => false,
            'action' => site_url('production/work-centers'),
        ]);
    }

    public function showWorkCenter(int $id): string
    {
        $tenant = new TenantContext(session());
        $workCenter = $this->scopeModel(new ProductionWorkCenterModel(), $tenant)->find($id);
        if ($workCenter === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('production/work_centers/show', [
            'title' => 'Work Center Detail',
            'workCenter' => $workCenter,
            'machines' => (new WorkCenterMachineModel())->where('work_center_id', $id)->orderBy('no', 'ASC')->findAll(),
            'costs' => (new WorkCenterCostModel())->where('work_center_id', $id)->orderBy('costtype', 'ASC')->findAll(),
        ]);
    }

    public function storeWorkCenter()
    {
        if (! $this->validate([
            'site_code' => 'required|max_length[12]',
            'department_code' => 'required|max_length[12]',
            'warehouse_code' => 'required|max_length[12]',
            'work_center_code' => 'required|max_length[12]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $machineRows = $this->postedWorkCenterMachines();
        if ($machineRows === []) {
            return redirect()->back()->withInput()->with('error', 'Minimal isi 1 Machine Detail.');
        }
        $costRows = $this->postedWorkCenterCosts();
        $primaryMachine = $machineRows[0];
        $primaryCost = $costRows[0] ?? ['costtype' => '', 'costamount' => 0, 'costuom' => ''];

        $model = new ProductionWorkCenterModel();
        $where = [
            'company_id' => $companyId,
            'site_code' => trim((string) $this->request->getPost('site_code')),
            'department_code' => trim((string) $this->request->getPost('department_code')),
            'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
            'work_center_code' => trim((string) $this->request->getPost('work_center_code')),
        ];
        if ($model->where($where)->first() !== null) {
            return redirect()->back()->withInput()->with('error', 'Data already exist.');
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $id = (int) $model->insert($where + [
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
                'active_date' => $this->nullableDate($this->request->getPost('active_date')),
                'inactive_date' => $this->nullableDate($this->request->getPost('inactive_date')),
                'is_active' => 1,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ], true);

            $this->saveWorkCenterChildren($id, $companyId, $tenant, $where, $machineRows, $costRows);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to save Work Center.');
            }
            $db->transCommit();
            $this->audit('production.work_center', 'work_center.create', 'production_work_centers', $id, $where['work_center_code']);

            return redirect()->to('/production/work-centers/' . $id)->with('message', 'Work center saved.');
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function routings(): string
    {
        $tenant = new TenantContext(session());
        $model = $this->scopeModel(new ProductionRoutingModel(), $tenant);

        return view('production/routings/index', [
            'title' => 'Routing',
            'rows' => $model->orderBy('item_code', 'ASC')->findAll(100),
        ]);
    }

    public function newRouting(): string
    {
        return view('production/routings/form', $this->formLookups() + [
            'title' => 'Create Routing',
            'workCenters' => $this->scopeModel(new ProductionWorkCenterModel(), new TenantContext(session()))->orderBy('work_center_code', 'ASC')->findAll(200),
        ]);
    }

    public function storeRouting()
    {
        if (! $this->validate([
            'site_code' => 'required|max_length[12]',
            'department_code' => 'required|max_length[12]',
            'item_code' => 'required|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $id = $this->saveRouting();
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/routings/' . $id)->with('message', 'Routing saved.');
    }

    public function showRouting(int $id): string
    {
        $tenant = new TenantContext(session());
        $routing = $this->scopeModel(new ProductionRoutingModel(), $tenant)->find($id);
        if ($routing === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('production/routings/show', [
            'title' => 'Routing Detail',
            'routing' => $routing,
            'lines' => (new ProductionRoutingLineModel())->where('production_routing_id', $id)->orderBy('route_no', 'ASC')->findAll(),
        ]);
    }

    private function saveBom(): int
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required.');
        }

        $item = $this->itemByCode(trim((string) $this->request->getPost('parent_item_code')));
        $model = new ProductionBomModel();
        $where = [
            'company_id' => $companyId,
            'site_code' => trim((string) $this->request->getPost('site_code')),
            'department_code' => trim((string) $this->request->getPost('department_code')),
            'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
            'parent_item_code' => trim((string) $this->request->getPost('parent_item_code')),
        ];
        if ($model->where($where)->first() !== null) {
            throw new RuntimeException('Data already exist.');
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $bomId = (int) $model->insert($where + [
                'site_id' => $tenant->activeSiteId(),
                'parent_item_id' => $item['id'] ?? null,
                'parent_item_name' => $this->itemName($item, $where['parent_item_code']),
                'bom_type' => trim((string) ($this->request->getPost('bom_type') ?: 'standard')),
                'qty_batch' => (float) $this->request->getPost('qty_batch'),
                'uom_code' => trim((string) $this->request->getPost('uom_code')),
                'ratio_percent' => (float) ($this->request->getPost('ratio_percent') ?: 100),
                'description' => trim((string) $this->request->getPost('description')),
                'active_date' => $this->nullableDateTime($this->request->getPost('active_date')),
                'inactive_date' => $this->nullableDateTime($this->request->getPost('inactive_date')),
                'is_active' => 1,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ], true);

            $lineModel = new ProductionBomLineModel();
            foreach ($this->postedBomLines() as $line) {
                $lineModel->insert($line + ['production_bom_id' => $bomId]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to save BOM.');
            }
            $db->transCommit();
            $this->audit('production.bom', 'bom.create', 'production_boms', $bomId, $where['parent_item_code']);

            return $bomId;
        } catch (RuntimeException $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function saveRouting(): int
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required.');
        }

        $item = $this->itemByCode(trim((string) $this->request->getPost('item_code')));
        $model = new ProductionRoutingModel();
        $where = [
            'company_id' => $companyId,
            'site_code' => trim((string) $this->request->getPost('site_code')),
            'item_code' => trim((string) $this->request->getPost('item_code')),
        ];
        if ($model->where($where)->first() !== null) {
            throw new RuntimeException('Data already exist.');
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $routingId = (int) $model->insert($where + [
                'site_id' => $tenant->activeSiteId(),
                'department_code' => trim((string) $this->request->getPost('department_code')),
                'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
                'item_id' => $item['id'] ?? null,
                'description' => trim((string) $this->request->getPost('description')),
                'is_active' => 1,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ], true);

            $lineModel = new ProductionRoutingLineModel();
            foreach ($this->postedRoutingLines() as $line) {
                $lineModel->insert($line + ['production_routing_id' => $routingId]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to save routing.');
            }
            $db->transCommit();
            $this->audit('production.routing', 'routing.create', 'production_routings', $routingId, $where['item_code']);

            return $routingId;
        } catch (RuntimeException $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function saveWorkCenterChildren(int $workCenterId, int $companyId, TenantContext $tenant, array $where, array $machineRows, array $costRows): void
    {
        $user = (string) (auth()->user()?->username ?? auth()->user()?->email ?? auth()->id() ?? 'system');

        $machineModel = new WorkCenterMachineModel();
        foreach ($machineRows as $row) {
            if (($row['machine'] ?? '') === '') {
                continue;
            }
            $machineModel->insert($row + [
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'work_center_id' => $workCenterId,
                'site' => $where['site_code'],
                'dept' => $where['department_code'],
                'warehouse' => $where['warehouse_code'],
                'work_center' => $where['work_center_code'],
                'created_by' => $user,
                'updated_by' => $user,
                'active' => 1,
            ]);
        }

        $costModel = new WorkCenterCostModel();
        foreach ($costRows as $row) {
            if (($row['costtype'] ?? '') === '') {
                continue;
            }
            $costModel->insert($row + [
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'work_center_id' => $workCenterId,
                'work_center' => $where['work_center_code'],
                'created_by' => $user,
                'updated_by' => $user,
                'active' => 1,
            ]);
        }
    }

    private function postedWorkCenterMachines(): array
    {
        $machines = (array) $this->request->getPost('machine');
        $rows = [];
        foreach ($machines as $index => $machine) {
            $machine = trim((string) $machine);
            if ($machine === '') {
                continue;
            }
            $rows[] = [
                'no' => (int) (((array) $this->request->getPost('machine_no'))[$index] ?? (($index + 1) * 10)),
                'machine' => $machine,
                'notes1' => trim((string) (((array) $this->request->getPost('machine_notes'))[$index] ?? '')),
                'speed' => $this->toNumber(((array) $this->request->getPost('machine_speed'))[$index] ?? 0),
                'capacity' => $this->toNumber(((array) $this->request->getPost('machine_capacity'))[$index] ?? 100),
                'length' => $this->toNumber(((array) $this->request->getPost('machine_length'))[$index] ?? 0),
                'luom' => trim((string) (((array) $this->request->getPost('machine_luom'))[$index] ?? '')),
                'width' => $this->toNumber(((array) $this->request->getPost('machine_width'))[$index] ?? 0),
                'wuom' => trim((string) (((array) $this->request->getPost('machine_wuom'))[$index] ?? '')),
                'height' => $this->toNumber(((array) $this->request->getPost('machine_height'))[$index] ?? 0),
                'huom' => trim((string) (((array) $this->request->getPost('machine_huom'))[$index] ?? '')),
                'volume' => $this->toNumber(((array) $this->request->getPost('machine_volume'))[$index] ?? 0),
                'vuom' => trim((string) (((array) $this->request->getPost('machine_vuom'))[$index] ?? '')),
                'qtylabor' => $this->toNumber(((array) $this->request->getPost('machine_qtylabor'))[$index] ?? 0),
                'workhour' => $this->toNumber(((array) $this->request->getPost('machine_workhour'))[$index] ?? 0),
            ];
        }

        return $rows;
    }

    private function postedWorkCenterCosts(): array
    {
        $types = (array) $this->request->getPost('costtype');
        $rows = [];
        foreach ($types as $index => $type) {
            $type = trim((string) $type);
            if ($type === '') {
                continue;
            }
            $rows[] = [
                'costtype' => $type,
                'costamount' => $this->toNumber(((array) $this->request->getPost('costamount'))[$index] ?? 0),
                'costuom' => trim((string) (((array) $this->request->getPost('costuom'))[$index] ?? '')),
                'notes2' => trim((string) (((array) $this->request->getPost('cost_notes'))[$index] ?? '')),
            ];
        }

        return $rows;
    }

    private function postedBomLines(): array
    {
        $childNos = (array) $this->request->getPost('child_no');
        $codes = (array) $this->request->getPost('child_item_code');
        $types = (array) $this->request->getPost('component_type');
        $qtys = (array) $this->request->getPost('qty_used');
        $uoms = (array) $this->request->getPost('line_uom_code');
        $factors = (array) $this->request->getPost('factor');
        $descriptions = (array) $this->request->getPost('line_description');
        $lines = [];

        foreach ($codes as $index => $code) {
            $code = trim((string) $code);
            $qty = (float) ($qtys[$index] ?? 0);
            if ($code === '' || $qty <= 0) {
                continue;
            }
            $item = $this->itemByCode($code);
            $lines[] = [
                'child_no' => (int) ($childNos[$index] ?? (($index + 1) * 10)),
                'child_item_id' => $item['id'] ?? null,
                'child_item_code' => $code,
                'child_item_name' => $this->itemName($item, $code),
                'component_type' => trim((string) ($types[$index] ?? 'material')),
                'qty_used' => $qty,
                'uom_code' => trim((string) ($uoms[$index] ?? 'PCS')),
                'factor' => (float) ($factors[$index] ?? 1),
                'description' => trim((string) ($descriptions[$index] ?? '')),
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('At least one BOM child item is required.');
        }

        return $lines;
    }

    private function postedRoutingLines(): array
    {
        $routeNos = (array) $this->request->getPost('route_no');
        $workCenters = (array) $this->request->getPost('work_center_code');
        $names = (array) $this->request->getPost('routing_name');
        $types = (array) $this->request->getPost('operation_type');
        $hours = (array) $this->request->getPost('hour_qty');
        $hourUoms = (array) $this->request->getPost('hour_uom');
        $speeds = (array) $this->request->getPost('std_speed');
        $speedUoms = (array) $this->request->getPost('speed_uom');
        $notes = (array) $this->request->getPost('route_notes');
        $lines = [];

        foreach ($routeNos as $index => $routeNo) {
            $routeNo = trim((string) $routeNo);
            $workCenter = trim((string) ($workCenters[$index] ?? ''));
            if ($routeNo === '' || $workCenter === '') {
                continue;
            }
            $lines[] = [
                'route_no' => $routeNo,
                'routing_name' => trim((string) ($names[$index] ?? '')),
                'work_center_code' => $workCenter,
                'operation_type' => trim((string) ($types[$index] ?? 'process')),
                'hour_qty' => (float) ($hours[$index] ?? 0),
                'hour_uom' => trim((string) ($hourUoms[$index] ?? 'Hour')),
                'std_speed' => (float) ($speeds[$index] ?? 0),
                'speed_uom' => trim((string) ($speedUoms[$index] ?? 'Unit/Hour')),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('At least one routing line is required.');
        }

        return $lines;
    }

    private function formLookups(): array
    {
        return [
            'sites' => $this->masterRows('sites'),
            'departments' => $this->masterRows('departments'),
            'warehouses' => $this->masterRows('warehouses'),
            'items' => $this->masterRows('items'),
            'uoms' => $this->masterRows('uoms'),
        ];
    }

    private function masterRows(string $table): array
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

    private function itemByCode(string $code): ?array
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
        if ($item === null) {
            return $fallback;
        }

        return (string) ($item['item_name'] ?? $item['name'] ?? $item['code'] ?? $fallback);
    }

    private function scopeModel($model, TenantContext $tenant)
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        return $model;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? str_replace('T', ' ', $value) : null;
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
            'description' => 'Production master data saved.',
        ]);
    }
}
