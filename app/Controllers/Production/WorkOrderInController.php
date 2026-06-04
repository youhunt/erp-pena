<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderOutputModel;
use App\Services\Production\WorkOrderInService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class WorkOrderInController extends BaseController
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
        return view('production/work_order_in/index', [
            'title' => 'Work Order In',
            'orders' => $model->where('production_type', 'work_order_in')->orderBy('wo_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function create(): string
    {
        return view('production/work_order_in/form', [
            'title' => 'Post Work Order In',
            'items' => $this->masterRows('items'),
            'warehouses' => $this->masterRows('warehouses'),
            'locations' => $this->masterRows('locations'),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }
        if (! $this->validate(['wo_no' => 'required|max_length[60]', 'wo_date' => 'required|valid_date[Y-m-d]', 'qty_good' => 'required|decimal'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $itemId = (int) ($this->request->getPost('finished_item_id') ?: 0);
        $item = $itemId > 0 ? Database::connect()->table('items')->where('id', $itemId)->get()->getRowArray() : null;
        $itemCode = trim((string) ($this->request->getPost('finished_item_code') ?: ($item['item_code'] ?? $item['code'] ?? '')));
        if ($itemCode === '') {
            return redirect()->back()->withInput()->with('error', 'Finished good item is required.');
        }

        try {
            $woId = (new WorkOrderInService())->post([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'company' => session('active_company_code'),
                'site' => session('active_site_code'),
                'wo_no' => trim((string) $this->request->getPost('wo_no')),
                'wo_date' => (string) $this->request->getPost('wo_date'),
                'finished_item_id' => $itemId > 0 ? $itemId : null,
                'finished_item_code' => $itemCode,
                'finished_item_name' => trim((string) ($this->request->getPost('finished_item_name') ?: ($item['item_name'] ?? $item['name'] ?? $itemCode))),
                'uom_code' => trim((string) ($this->request->getPost('uom_code') ?: ($item['stockuom'] ?? 'PCS'))),
                'qty_plan' => (float) ($this->request->getPost('qty_plan') ?: 0),
                'qty_good' => (float) ($this->request->getPost('qty_good') ?: 0),
                'qty_reject' => (float) ($this->request->getPost('qty_reject') ?: 0),
                'unit_cost' => (float) ($this->request->getPost('unit_cost') ?: ($item['item_price'] ?? 0)),
                'warehouse_id' => $this->nullableInt($this->request->getPost('warehouse_id')),
                'location_id' => $this->nullableInt($this->request->getPost('location_id')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/work-order-in/' . $woId)->with('message', 'Work Order In posted.');
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
        $order = $model->find($id);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('production/work_order_in/show', [
            'title' => 'Work Order In Detail',
            'order' => $order,
            'outputs' => (new ProductionWorkOrderOutputModel())->where('production_work_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
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

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
