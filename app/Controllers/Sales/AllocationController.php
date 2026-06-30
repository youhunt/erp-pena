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
}
