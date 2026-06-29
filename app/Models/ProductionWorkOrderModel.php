<?php

namespace App\Models;

use App\Services\Support\DocumentNumberService;
use CodeIgniter\Model;
use DateTimeImmutable;
use Throwable;

class ProductionWorkOrderModel extends Model
{
    protected $table = 'production_work_orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'wo_code', 'wo_no', 'wo_date', 'site_code',
        'department_code', 'warehouse_code', 'work_center_code', 'parent_item_id',
        'parent_item_code', 'parent_item_name', 'bom_id', 'routing_id',
        'batch_qty', 'wo_qty', 'std_qty_finished', 'act_qty_finished',
        'production_type', 'finished_item_id', 'finished_item_code', 'finished_item_name', 'uom_code',
        'qty_plan', 'qty_good', 'qty_reject', 'unit_cost', 'warehouse_id', 'location_id',
        'description', 'status', 'notes', 'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];
    protected $beforeInsert = ['syncHeaderFromWorkOrderForm'];
    protected $beforeUpdate = ['syncHeaderFromWorkOrderForm'];

    protected function syncHeaderFromWorkOrderForm(array $event): array
    {
        if (! isset($event['data']) || ! is_array($event['data'])) {
            return $event;
        }

        $request = service('request');
        if (! str_contains((string) $request->getPath(), 'production/work-orders')) {
            return $event;
        }

        foreach ([
            'bom_id' => 'int',
            'routing_id' => 'int',
            'batch_qty' => 'float',
            'std_qty_finished' => 'float',
            'act_qty_finished' => 'float',
            'uom_code' => 'string',
        ] as $field => $type) {
            $value = $request->getPost($field);
            if ($value === null || $value === '') {
                continue;
            }
            $event['data'][$field] = match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                default => trim((string) $value),
            };
        }

        if (($event['data']['wo_no'] ?? '') === '' || (string) $request->getPost('wo_no_auto') === '1') {
            $event['data']['wo_no'] = $this->nextWorkOrderNo($event['data']);
        }

        return $event;
    }

    private function nextWorkOrderNo(array $data): string
    {
        $code = strtoupper(trim((string) ($data['wo_code'] ?? 'WO')));
        if ($code === '') {
            $code = 'WO';
        }
        $date = (string) ($data['wo_date'] ?? date('Y-m-d'));
        $fallback = $code . '-' . date('Ymd-His');

        try {
            return (new DocumentNumberService())->next($code, new DateTimeImmutable($date), [
                'company_id' => $data['company_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'prefix' => $code,
                'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
                'reset_period' => 'monthly',
                'padding' => 4,
            ]);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
