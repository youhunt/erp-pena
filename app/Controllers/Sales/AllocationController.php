<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Models\AllocationLineModel;
use App\Models\AllocationOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\Sales\AllocationService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class AllocationController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = $this->scopeModel(new AllocationOrderModel(), $tenant);

        return view('sales/allocations/index', [
            'title' => 'Allocation Order',
            'allocations' => $model->orderBy('allocdate', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function createFromSo(int $salesOrderId): string
    {
        $tenant = new TenantContext(session());
        $order = $this->scopeModel(new SalesOrderModel(), $tenant)->find($salesOrderId);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $lines = (new SalesOrderLineModel())->where('sales_order_id', $salesOrderId)->orderBy('line_no', 'ASC')->findAll();

        return view('sales/allocations/form', [
            'title' => 'Create Allocation Order',
            'order' => $order,
            'lines' => $lines,
            'previewRows' => (new AllocationService())->allocationPreviewRows($order, $lines),
        ]);
    }

    public function storeFromSo(int $salesOrderId)
    {
        if (! $this->validate([
            'allocnumb' => 'permit_empty|max_length[60]',
            'allocdate' => 'required|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $allocationId = (new AllocationService())->allocateFromSalesOrder($salesOrderId, [
                'allocnumb' => trim((string) $this->request->getPost('allocnumb')),
                'allocdate' => (string) $this->request->getPost('allocdate'),
                'shipdate' => $this->request->getPost('shipdate') ?: null,
                'shipto' => trim((string) $this->request->getPost('shipto')),
                'dept' => trim((string) $this->request->getPost('dept')),
                'whs' => trim((string) $this->request->getPost('whs')),
                'loc' => trim((string) $this->request->getPost('loc')),
                'batchno' => trim((string) $this->request->getPost('batchno')),
                'remarks' => trim((string) $this->request->getPost('remarks')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/sales/allocations/' . $allocationId)->with('message', 'Allocation order posted.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $allocation = $this->scopeModel(new AllocationOrderModel(), $tenant)->find($id);
        if ($allocation === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('sales/allocations/show', [
            'title' => 'Allocation Order Detail',
            'allocation' => $allocation,
            'lines' => (new AllocationLineModel())->where('allocationorder_id', $id)->orderBy('line', 'ASC')->findAll(),
        ]);
    }

    public function edit(int $id): string
    {
        $tenant = new TenantContext(session());
        $allocation = $this->scopeModel(new AllocationOrderModel(), $tenant)->find($id);
        if ($allocation === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('sales/allocations/edit', [
            'title' => 'Edit Allocation Header',
            'allocation' => $allocation,
            'lines' => (new AllocationLineModel())->where('allocationorder_id', $id)->orderBy('line', 'ASC')->findAll(),
            'departments' => $this->masterRows('departments', $tenant),
            'warehouses' => $this->masterRows('warehouses', $tenant),
        ]);
    }

    public function update(int $id)
    {
        $tenant = new TenantContext(session());
        $model = $this->scopeModel(new AllocationOrderModel(), $tenant);
        $allocation = $model->find($id);
        if ($allocation === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate([
            'dept' => 'permit_empty|max_length[12]',
            'whs' => 'permit_empty|max_length[12]',
            'shipdate' => 'permit_empty|valid_date[Y-m-d]',
            'shipto' => 'permit_empty|max_length[12]',
            'remarks' => 'permit_empty|max_length[500]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model->update($id, [
            'dept' => trim((string) $this->request->getPost('dept')),
            'whs' => trim((string) $this->request->getPost('whs')),
            'shipdate' => $this->request->getPost('shipdate') ?: null,
            'shipto' => trim((string) $this->request->getPost('shipto')),
            'remarks' => trim((string) $this->request->getPost('remarks')),
            'updated_by' => (string) (auth()->id() ?? 'system'),
        ]);

        return redirect()->to('/sales/allocations/' . $id)->with('message', 'Allocation header updated. Stock reservation lines are not changed.');
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

    private function masterRows(string $table, TenantContext $tenant): array
    {
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
            $builder->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }

        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get()->getResultArray();
    }
}
