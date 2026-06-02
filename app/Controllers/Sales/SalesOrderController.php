<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\Sales\SalesOrderService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class SalesOrderController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $orders = new SalesOrderModel();

        if ($tenant->activeCompanyId() !== null) {
            $orders->where('company_id', $tenant->activeCompanyId());
        }

        if ($tenant->activeSiteId() !== null) {
            $orders->where('site_id', $tenant->activeSiteId());
        }

        return view('sales/orders/index', [
            'title' => 'Sales Orders',
            'orders' => $orders->orderBy('so_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function create(): string
    {
        return view('sales/orders/form', [
            'title' => 'Create Sales Order',
            'customers' => $this->masterRows('customers'),
            'items' => $this->masterRows('items'),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate(['so_no' => 'required|max_length[60]', 'so_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line item is required.');
        }

        $customerId = (int) ($this->request->getPost('customer_id') ?: 0);
        $customer = $customerId > 0 ? Database::connect()->table('customers')->where('id', $customerId)->get()->getRowArray() : null;

        try {
            $soId = (new SalesOrderService())->create([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'so_no' => trim((string) $this->request->getPost('so_no')),
                'so_date' => (string) $this->request->getPost('so_date'),
                'customer_id' => $customerId > 0 ? $customerId : null,
                'customer_name' => $customer['name'] ?? trim((string) $this->request->getPost('customer_name')),
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
                'status' => 'draft',
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $lines, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/sales/orders/' . $soId)->with('message', 'Sales order created.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $order = $this->scopedOrder($tenant, $id);

        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('sales/orders/show', [
            'title' => 'Sales Order Detail',
            'order' => $order,
            'lines' => (new SalesOrderLineModel())->where('sales_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    private function scopedOrder(TenantContext $tenant, int $id): ?array
    {
        $orders = new SalesOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $orders->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $orders->where('site_id', $tenant->activeSiteId());
        }

        return $orders->find($id);
    }

    private function postedLines(): array
    {
        $itemCodes = (array) $this->request->getPost('item_code');
        $itemNames = (array) $this->request->getPost('item_name');
        $qtys = (array) $this->request->getPost('qty');
        $uoms = (array) $this->request->getPost('uom_code');
        $prices = (array) $this->request->getPost('unit_price');
        $discounts = (array) $this->request->getPost('discount_amount');
        $taxes = (array) $this->request->getPost('tax_amount');
        $lines = [];

        foreach ($itemCodes as $index => $code) {
            $qty = (float) ($qtys[$index] ?? 0);
            $price = (float) ($prices[$index] ?? 0);
            $name = trim((string) ($itemNames[$index] ?? ''));
            $code = trim((string) $code);

            if ($code === '' && $name === '' && $qty <= 0) {
                continue;
            }

            if ($qty <= 0) {
                continue;
            }

            $lines[] = [
                'item_code' => $code !== '' ? $code : null,
                'item_name' => $name !== '' ? $name : $code,
                'qty' => $qty,
                'uom_code' => trim((string) ($uoms[$index] ?? 'PCS')),
                'unit_price' => $price,
                'discount_amount' => (float) ($discounts[$index] ?? 0),
                'tax_amount' => (float) ($taxes[$index] ?? 0),
            ];
        }

        return $lines;
    }

    private function masterRows(string $table): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table($table)->where('deleted_at', null)->where('is_active', 1);

        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }

        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        return $builder->orderBy('code', 'ASC')->get()->getResultArray();
    }
}
