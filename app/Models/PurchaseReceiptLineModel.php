<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

class PurchaseReceiptLineModel extends Model
{
    protected $table = 'purchase_receipt_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'purchase_receipt_id', 'purchase_order_id', 'purchase_order_line_id',
        'stock_movement_id', 'reversal_movement_id', 'line_no',
        'item_id', 'item_code', 'batch_no', 'item_name',
        'qty_received', 'reversed_qty', 'uom_code', 'unit_cost',
        'unit_price', 'freight_amount', 'special_price',
        'warehouse_id', 'location_id',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected $beforeInsert = ['syncEditablePriceFields'];

    protected function syncEditablePriceFields(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        $db = Database::connect();
        $priceColumns = ['unit_price', 'freight_amount', 'special_price'];
        foreach ($priceColumns as $column) {
            if (! $db->fieldExists($column, $this->table)) {
                unset($data['data'][$column]);
            }
        }

        $purchaseOrderLineId = (int) ($data['data']['purchase_order_line_id'] ?? 0);
        if ($purchaseOrderLineId < 1) {
            return $data;
        }

        $postedLineIds = (array) request()->getPost('purchase_order_line_id');
        $index = null;
        foreach ($postedLineIds as $i => $lineId) {
            if ((int) $lineId === $purchaseOrderLineId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return $data;
        }

        $unitPrices = (array) request()->getPost('unit_price');
        $freights = (array) request()->getPost('freight_amount');
        $specialPrices = (array) request()->getPost('special_price');

        if ($db->fieldExists('unit_price', $this->table)) {
            $data['data']['unit_price'] = $this->toNumber($unitPrices[$index] ?? ($data['data']['unit_price'] ?? 0));
        }
        if ($db->fieldExists('freight_amount', $this->table)) {
            $data['data']['freight_amount'] = $this->toNumber($freights[$index] ?? ($data['data']['freight_amount'] ?? 0));
        }
        if ($db->fieldExists('special_price', $this->table)) {
            $data['data']['special_price'] = $this->toNumber($specialPrices[$index] ?? ($data['data']['special_price'] ?? 0));
        }

        return $data;
    }

    private function toNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        return (float) $value;
    }
}
