<?php

namespace App\Services\Production;

use App\Services\Purchase\PurchaseOrderService;
use App\Services\Support\DocumentNumberService;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class MrpPlannedOrderConverter
{
    public function convertToWorkOrder(int $plannedOrderId, ?int $userId = null): int
    {
        $db = Database::connect();
        $planned = $this->plannedOrder($db, $plannedOrderId);
        if (($planned['plan_type'] ?? '') !== 'planned_work_order') {
            throw new RuntimeException('Only planned_work_order can be converted to work order.');
        }
        $this->assertNotConverted($planned);

        $companyId = (int) ($planned['company_id'] ?? 0);
        $siteId = isset($planned['site_id']) ? (int) $planned['site_id'] : null;
        if ($companyId < 1) {
            throw new RuntimeException('Company is required for planned order conversion.');
        }

        $site = $siteId !== null && $siteId > 0 ? $db->table('sites')->where('id', $siteId)->get()->getRowArray() : null;
        $department = $this->firstMaster($db, 'departments', $companyId, $siteId);
        $warehouse = $this->firstMaster($db, 'warehouses', $companyId, $siteId);
        if ($site === null) {
            throw new RuntimeException('Site not found. Set active site or fill site_id on planned order.');
        }
        if ($department === null) {
            throw new RuntimeException('Default department not found. Create department master first.');
        }

        $itemCode = (string) ($planned['item_code'] ?? '');
        $item = $this->itemByCode($db, $companyId, $siteId, $itemCode);
        $woDate = date('Y-m-d');
        $woNo = $this->nextNo('WO', $woDate, $companyId, $siteId);
        $woId = (new WorkOrderService())->create([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'wo_code' => 'WO',
            'wo_no' => $woNo,
            'wo_date' => $woDate,
            'site_code' => (string) ($site['code'] ?? $site['site_code'] ?? ''),
            'department_code' => (string) ($department['code'] ?? $department['department_code'] ?? ''),
            'warehouse_code' => (string) ($warehouse['code'] ?? $warehouse['warehouse_code'] ?? ''),
            'work_center_code' => '',
            'parent_item_id' => $item['id'] ?? null,
            'parent_item_code' => $itemCode,
            'parent_item_name' => $planned['item_name'] ?? $itemCode,
            'wo_qty' => (float) ($planned['qty'] ?? 0),
            'description' => 'Converted from MRP planned order ' . ($planned['plan_no'] ?? $plannedOrderId),
        ], $userId);

        $this->markConverted($db, $planned, $plannedOrderId, 'work_order', $woNo, $userId);
        return $woId;
    }

    public function convertToPurchaseOrder(int $plannedOrderId, ?int $userId = null): int
    {
        $db = Database::connect();
        $planned = $this->plannedOrder($db, $plannedOrderId);
        if (! in_array(($planned['plan_type'] ?? ''), ['planned_purchase_requisition', 'planned_purchase_order'], true)) {
            throw new RuntimeException('Only planned purchase requirement can be converted to purchase order.');
        }
        $this->assertNotConverted($planned);

        $companyId = (int) ($planned['company_id'] ?? 0);
        $siteId = isset($planned['site_id']) ? (int) $planned['site_id'] : null;
        if ($companyId < 1) {
            throw new RuntimeException('Company is required for purchase conversion.');
        }

        $supplier = $this->firstMaster($db, 'suppliers', $companyId, $siteId);
        if ($supplier === null) {
            throw new RuntimeException('Default supplier not found. Create supplier master first or convert manually.');
        }

        $itemCode = (string) ($planned['item_code'] ?? '');
        $item = $this->itemByCode($db, $companyId, $siteId, $itemCode);
        $poDate = date('Y-m-d');
        $poNo = $this->nextNo('PO', $poDate, $companyId, $siteId);
        $supplierCode = (string) ($supplier['supplier'] ?? $supplier['code'] ?? $supplier['supplier_code'] ?? '');
        $supplierName = (string) ($supplier['supplierna'] ?? $supplier['name'] ?? $supplierCode);

        $poId = (new PurchaseOrderService())->create([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'company' => null,
            'site' => null,
            'po_no' => $poNo,
            'po_date' => $poDate,
            'delivery_date' => null,
            'arrive_date' => null,
            'supplier_id' => $supplier['id'] ?? null,
            'supplier' => $supplierCode,
            'supplier_code' => $supplierCode,
            'supplier_name' => $supplierName,
            'terms_code' => (string) ($supplier['terms_code'] ?? $supplier['terms'] ?? ''),
            'currency_code' => 'IDR',
            'discount_percent' => 0,
            'discount_amount' => 0,
            'freight_amount' => 0,
            'other_amount' => 0,
            'special_charge_amount' => 0,
            'vat_code' => '',
            'wht_code' => '',
            'vat_amount' => 0,
            'wht_amount' => 0,
            'status' => 'draft',
            'document_status' => 'draft',
            'notes' => 'Converted from MRP planned order ' . ($planned['plan_no'] ?? $plannedOrderId),
            'remarks' => '',
        ], [[
            'po_line' => 1,
            'item_id' => $item['id'] ?? null,
            'item_code' => $itemCode,
            'item_name' => (string) ($planned['item_name'] ?? $item['item_name'] ?? $itemCode),
            'description' => 'MRP planned purchase ' . ($planned['plan_no'] ?? $plannedOrderId),
            'qty' => (float) ($planned['qty'] ?? 0),
            'uom_code' => (string) ($planned['uom_code'] ?? $item['stockuom'] ?? 'PCS'),
            'unit_price' => 0,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'vat_amount' => 0,
            'wht_amount' => 0,
        ]], $userId);

        $this->markConverted($db, $planned, $plannedOrderId, 'purchase_order', $poNo, $userId);
        return $poId;
    }

    private function plannedOrder($db, int $plannedOrderId): array
    {
        $planned = $db->table('production_mrp_planned_orders')->where('id', $plannedOrderId)->get()->getRowArray();
        if ($planned === null) {
            throw new RuntimeException('Planned order not found.');
        }
        return $planned;
    }

    private function assertNotConverted(array $planned): void
    {
        if (($planned['status'] ?? '') === 'converted') {
            throw new RuntimeException('Planned order already converted.');
        }
    }

    private function markConverted($db, array $planned, int $plannedOrderId, string $docType, string $docNo, ?int $userId): void
    {
        $db->table('production_mrp_planned_orders')->where('id', $plannedOrderId)->update([
            'status' => 'converted',
            'target_doc_type' => $docType,
            'target_doc_no' => $docNo,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ((int) ($planned['mrp_line_id'] ?? 0) > 0 && $db->fieldExists('action_status', 'production_mrp_lines')) {
            $db->table('production_mrp_lines')->where('id', (int) $planned['mrp_line_id'])->update([
                'action_status' => 'converted',
                'planned_doc_type' => $docType,
                'planned_doc_no' => $docNo,
                'action_updated_by' => $userId,
                'action_updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function itemByCode($db, int $companyId, ?int $siteId, string $itemCode): ?array
    {
        if ($itemCode === '' || ! $db->tableExists('items')) {
            return null;
        }
        $builder = $db->table('items')->where('company_id', $companyId)->where('item_code', $itemCode);
        if ($siteId !== null && $db->fieldExists('site_id', 'items')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', 'items')) {
            $builder->where('deleted_at', null);
        }
        return $builder->get(1)->getRowArray() ?: null;
    }

    private function firstMaster($db, string $table, int $companyId, ?int $siteId): ?array
    {
        if (! $db->tableExists($table)) {
            return null;
        }
        $builder = $db->table($table);
        if ($db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', $table)) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', $table)) {
            $builder->where('is_active', 1);
        }
        return $builder->orderBy('id', 'ASC')->get(1)->getRowArray() ?: null;
    }

    private function nextNo(string $code, string $date, int $companyId, ?int $siteId): string
    {
        try {
            return (new DocumentNumberService())->next($code, new DateTimeImmutable($date), [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'prefix' => $code,
                'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
                'reset_period' => 'monthly',
                'padding' => 5,
            ]);
        } catch (\Throwable) {
            return $code . '-' . date('YmdHis');
        }
    }
}
