<?php

namespace App\Services\Production;

use App\Services\Support\DocumentNumberService;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class MrpPlannedOrderConverter
{
    public function convertToWorkOrder(int $plannedOrderId, ?int $userId = null): int
    {
        $db = Database::connect();
        $planned = $db->table('production_mrp_planned_orders')->where('id', $plannedOrderId)->get()->getRowArray();
        if ($planned === null) {
            throw new RuntimeException('Planned order not found.');
        }
        if (($planned['plan_type'] ?? '') !== 'planned_work_order') {
            throw new RuntimeException('Only planned_work_order can be converted to work order.');
        }
        if (($planned['status'] ?? '') === 'converted') {
            throw new RuntimeException('Planned order already converted.');
        }

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
        $item = $db->table('items')
            ->where('company_id', $companyId)
            ->where('item_code', $itemCode)
            ->groupStart()
                ->where('site_id', $siteId)
                ->orWhere('site_id', null)
                ->orWhere('site_id', 0)
            ->groupEnd()
            ->get(1)
            ->getRowArray();

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

        $db->table('production_mrp_planned_orders')->where('id', $plannedOrderId)->update([
            'status' => 'converted',
            'target_doc_type' => 'work_order',
            'target_doc_no' => $woNo,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ((int) ($planned['mrp_line_id'] ?? 0) > 0 && $db->fieldExists('action_status', 'production_mrp_lines')) {
            $db->table('production_mrp_lines')->where('id', (int) $planned['mrp_line_id'])->update([
                'action_status' => 'converted',
                'planned_doc_type' => 'work_order',
                'planned_doc_no' => $woNo,
                'action_updated_by' => $userId,
                'action_updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $woId;
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
