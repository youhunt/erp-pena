<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkOrderComponentModel extends Model
{
    protected $table = 'production_work_order_components';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_work_order_id', 'line_no', 'component_item_id',
        'component_item_code', 'component_item_name', 'qty_used', 'uom_code',
        'warehouse_code', 'location_code', 'batch_no', 'booking_qty',
        'allocated_qty', 'issued_qty', 'line_status',
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
        $codes = (array) $request->getPost('component_item_code');
        if ($lineNo === '' || $codes === []) {
            return $event;
        }

        $nos = (array) $request->getPost('component_line_no');
        $names = (array) $request->getPost('component_item_name');
        $qtys = (array) $request->getPost('component_qty_used');
        $uoms = (array) $request->getPost('component_uom_code');
        $warehouses = (array) $request->getPost('component_warehouse_code');
        $locations = (array) $request->getPost('component_location_code');
        $batches = (array) $request->getPost('component_batch_no');
        $bookings = (array) $request->getPost('component_booking_qty');

        foreach ($nos as $index => $postedNo) {
            if ((string) $postedNo !== $lineNo) {
                continue;
            }
            $code = trim((string) ($codes[$index] ?? ''));
            if ($code === '') {
                break;
            }
            $event['data']['component_item_code'] = $code;
            $event['data']['component_item_name'] = trim((string) ($names[$index] ?? ''));
            $event['data']['qty_used'] = (float) ($qtys[$index] ?? 0);
            $event['data']['uom_code'] = trim((string) ($uoms[$index] ?? ''));
            $event['data']['warehouse_code'] = trim((string) ($warehouses[$index] ?? ''));
            $event['data']['location_code'] = trim((string) ($locations[$index] ?? ''));
            $event['data']['batch_no'] = trim((string) ($batches[$index] ?? ''));
            $event['data']['booking_qty'] = (float) ($bookings[$index] ?? $event['data']['qty_used'] ?? 0);
            break;
        }

        return $event;
    }
}
