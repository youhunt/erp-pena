<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsurePurchaseOrderLineCoreColumns extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_order_lines')) {
            return;
        }

        $fields = [];
        foreach ($this->fields() as $field => $definition) {
            if (! $this->db->fieldExists($field, 'purchase_order_lines')) {
                $fields[$field] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_order_lines', $fields);
        }

        $this->backfillLineNumbers();
        $this->backfillReceivingQuantities();
    }

    public function down(): void
    {
        // No-op: safe repair migration for existing local schemas.
    }

    private function fields(): array
    {
        return [
            'line_no' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_received' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    private function backfillLineNumbers(): void
    {
        if (! $this->db->fieldExists('line_no', 'purchase_order_lines')) {
            return;
        }

        $rows = $this->db->table('purchase_order_lines')
            ->select('id, purchase_order_id, line_no')
            ->orderBy('purchase_order_id', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $currentPo = null;
        $lineNo = 10;

        foreach ($rows as $row) {
            if ($currentPo !== (int) $row['purchase_order_id']) {
                $currentPo = (int) $row['purchase_order_id'];
                $lineNo = 10;
            }

            if (empty($row['line_no'])) {
                $this->db->table('purchase_order_lines')->where('id', $row['id'])->update(['line_no' => $lineNo]);
            }

            $lineNo += 10;
        }
    }

    private function backfillReceivingQuantities(): void
    {
        if (! $this->db->fieldExists('qty', 'purchase_order_lines')) {
            return;
        }

        $updates = [];
        foreach ($this->db->table('purchase_order_lines')->get()->getResultArray() as $row) {
            $qty = (float) ($row['qty'] ?? 0);
            $received = (float) ($row['qty_received'] ?? 0);
            $ordered = (float) ($row['qty_ordered'] ?? 0);
            if ($ordered <= 0) {
                $ordered = $qty;
            }
            $updates[] = [
                'id' => $row['id'],
                'qty_ordered' => $ordered,
                'qty_received' => $received,
                'qty_outstanding' => max(0, $ordered - $received),
                'line_status' => $received >= $ordered && $ordered > 0 ? 'received' : ($received > 0 ? 'partial_received' : 'open'),
            ];
        }

        foreach ($updates as $data) {
            $id = $data['id'];
            unset($data['id']);
            $this->db->table('purchase_order_lines')->where('id', $id)->update($data);
        }
    }
}
