<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\ProductionWorkCenterModel;
use App\Models\ProductionWorkOrderComponentModel;
use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderRoutingModel;
use App\Services\Production\WorkOrderService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class WorkOrderController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new ProductionWorkOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        return view('production/work_orders/index', [
            'title' => 'Work Order',
            'rows' => $model->orderBy('wo_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function create(): string
    {
        return view('production/work_orders/form', [
            'title' => 'Create Work Order',
            'sites' => $this->masterRows('sites'),
            'departments' => $this->masterRows('departments'),
            'warehouses' => $this->masterRows('warehouses'),
            'items' => $this->masterRows('items'),
            'workCenters' => $this->workCenters(),
        ]);
    }

    public function store()
    {
        if (! $this->validate([
            'wo_no' => 'required|max_length[60]',
            'wo_date' => 'required|valid_date[Y-m-d]',
            'site_code' => 'required|max_length[12]',
            'department_code' => 'required|max_length[12]',
            'parent_item_code' => 'required|max_length[50]',
            'wo_qty' => 'required|decimal',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $item = $this->itemByCode(trim((string) $this->request->getPost('parent_item_code')));
        try {
            $woId = (new WorkOrderService())->create([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'wo_code' => trim((string) ($this->request->getPost('wo_code') ?: 'WO')),
                'wo_no' => trim((string) $this->request->getPost('wo_no')),
                'wo_date' => (string) $this->request->getPost('wo_date'),
                'site_code' => trim((string) $this->request->getPost('site_code')),
                'department_code' => trim((string) $this->request->getPost('department_code')),
                'warehouse_code' => trim((string) $this->request->getPost('warehouse_code')),
                'work_center_code' => trim((string) $this->request->getPost('work_center_code')),
                'parent_item_id' => $item['id'] ?? null,
                'parent_item_code' => trim((string) $this->request->getPost('parent_item_code')),
                'parent_item_name' => $item['item_name'] ?? $item['name'] ?? trim((string) $this->request->getPost('parent_item_code')),
                'wo_qty' => (float) $this->request->getPost('wo_qty'),
                'description' => trim((string) $this->request->getPost('description')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/work-orders/' . $woId)->with('message', 'Work order created.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new ProductionWorkOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        $workOrder = $model->find($id);
        if ($workOrder === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('production/work_orders/show', [
            'title' => 'Work Order Detail',
            'workOrder' => $workOrder,
            'components' => (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'routings' => (new ProductionWorkOrderRoutingModel())->where('production_work_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    public function allocate(int $id)
    {
        try {
            (new WorkOrderService())->allocate($id, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/work-orders/' . $id)->with('message', 'Work order material allocated.');
    }

    public function issueMaterials(int $id)
    {
        try {
            (new WorkOrderService())->issueMaterials($id, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/work-orders/' . $id)->with('message', 'Work order material issued.');
    }

    public function receiveFinished(int $id)
    {
        if (! $this->validate([
            'receive_qty' => 'permit_empty|decimal',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $qty = $this->request->getPost('receive_qty');
        try {
            (new WorkOrderService())->receiveFinishedGoods($id, $qty === null || $qty === '' ? null : (float) $qty, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/work-orders/' . $id)->with('message', 'Work order finished good received.');
    }

    private function masterRows(string $table): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
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

    private function workCenters(): array
    {
        $tenant = new TenantContext(session());
        $model = new ProductionWorkCenterModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        return $model->orderBy('work_center_code', 'ASC')->findAll(200);
    }

    private function itemByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        $builder = $db->table('items');
        $db->fieldExists('item_code', 'items') ? $builder->where('item_code', $code) : $builder->where('code', $code);

        return $builder->get()->getRowArray();
    }
}
