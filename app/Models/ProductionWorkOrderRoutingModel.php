<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkOrderRoutingModel extends Model
{
    protected $table = 'production_work_order_routings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_work_order_id', 'line_no', 'routing_name', 'work_center_code',
        'work_center_name', 'hour_qty', 'uom_code',
    ];
    protected $beforeInsert = ['syncFromWorkOrderForm'];

    protected function syncFromWorkOrderForm(array $event): array
    {
        if (! isset($event['data']) || ! is_array($event['data'])) {
            return $event;
        }

        $request = service('request');
        if (! str_contains((string) $request->getPath(), 'production/work-orders')) {
            return $event;
        }

        $lineNo = (string) ($event['data']['line_no'] ?? '');
        $nos = (array) $request->getPost('routing_line_no');
        if ($lineNo === '' || $nos === []) {
            return $event;
        }

        $names = (array) $request->getPost('wo_routing_name');
        $workCenters = (array) $request->getPost('wo_work_center_code');
        $workCenterNames = (array) $request->getPost('wo_work_center_name');
        $hours = (array) $request->getPost('wo_hour_qty');
        $uoms = (array) $request->getPost('wo_route_uom');

        foreach ($nos as $index => $postedNo) {
            if ((string) $postedNo !== $lineNo) {
                continue;
            }
            $workCenter = trim((string) ($workCenters[$index] ?? ''));
            if ($workCenter === '') {
                break;
            }
            $event['data']['routing_name'] = trim((string) ($names[$index] ?? ''));
            $event['data']['work_center_code'] = $workCenter;
            $event['data']['work_center_name'] = trim((string) ($workCenterNames[$index] ?? $workCenter));
            $event['data']['hour_qty'] = (float) ($hours[$index] ?? 0);
            $event['data']['uom_code'] = trim((string) ($uoms[$index] ?? 'Hour'));
            break;
        }

        return $event;
    }
}
