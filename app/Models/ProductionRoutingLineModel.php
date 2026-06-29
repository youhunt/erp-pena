<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionRoutingLineModel extends Model
{
    protected $table = 'production_routing_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_routing_id', 'route_no', 'routing_name', 'work_center_code',
        'operation_type', 'hour_qty', 'hour_uom', 'std_speed', 'speed_uom', 'notes',
        'active_date', 'inactive_date',
    ];
    protected $beforeInsert = ['syncLineDatesFromRequest'];

    protected function syncLineDatesFromRequest(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        $request = service('request');
        $routeNos = (array) $request->getPost('route_no');
        $workCenters = (array) $request->getPost('work_center_code');
        $activeDates = (array) $request->getPost('active_date');
        $inactiveDates = (array) $request->getPost('inactive_date');

        $routeNo = (string) ($data['data']['route_no'] ?? '');
        $workCenter = (string) ($data['data']['work_center_code'] ?? '');

        foreach ($routeNos as $index => $postedRouteNo) {
            if ((string) $postedRouteNo !== $routeNo) {
                continue;
            }
            if ((string) ($workCenters[$index] ?? '') !== $workCenter) {
                continue;
            }

            $data['data']['active_date'] = $this->normalizeDate($activeDates[$index] ?? null);
            $data['data']['inactive_date'] = $this->normalizeDate($inactiveDates[$index] ?? null, '9999-12-31');
            break;
        }

        return $data;
    }

    private function normalizeDate(mixed $value, ?string $default = null): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }
        if ($value === '99/99/9999' || $value === '9999-99-99') {
            return '9999-12-31';
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        return substr($value, 0, 10);
    }
}
