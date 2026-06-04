<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureOrderLineNumberColumns extends Migration
{
    public function up(): void
    {
        $this->ensureLineColumns('sales_order_lines', 'sales_order_id', [
            'line_no' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_reserved' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_delivered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
        ]);

        $this->ensureLineColumns('purchase_order_lines', 'purchase_order_id', [
            'line_no' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_received' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
        ]);
    }

    public function down(): void
    {
        // No-op: repair migration for older local schemas.
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function ensureLineColumns(string $table, string $parentField, array $definitions): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        foreach ($definitions as $field => $definition) {
            if (! $this->db->fieldExists($field, $table)) {
                $fields[$field] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }

        $this->backfillLineNumbers($table, $parentField);
        $this->backfillQuantities($table);
    }

    private function backfillLineNumbers(string $table, string $parentField): void
    {
        if (! $this->db->fieldExists('line_no', $table)) {
            return;
        }

        $rows = $this->db->table($table)
            ->select('id, ' . $parentField . ', line_no')
            ->orderBy($parentField, 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $currentParent = null;
        $lineNo = 10;

        foreach ($rows as $row) {
            $parentId = (int) ($row[$parentField] ?? 0);
            if ($currentParent !== $parentId) {
                $currentParent = $parentId;
                $lineNo = 10;
            }

            if (empty($row['line_no'])) {
                $this->db->table($table)->where('id', $row['id'])->update(['line_no' => $lineNo]);
            }

            $lineNo += 10;
        }
    }

    private function backfillQuantities(string $table): void
    {
        if (! $this->db->fieldExists('qty', $table) || ! $this->db->fieldExists('qty_ordered', $table)) {
            return;
        }

        foreach ($this->db->table($table)->get()->getResultArray() as $row) {
            $qty = (float) ($row['qty'] ?? 0);
            $ordered = (float) ($row['qty_ordered'] ?? 0);
            if ($ordered <= 0) {
                $ordered = $qty;
            }

            $data = ['qty_ordered' => $ordered];

            if ($this->db->fieldExists('qty_reserved', $table)) {
                $reserved = (float) ($row['qty_reserved'] ?? 0);
                $delivered = (float) ($row['qty_delivered'] ?? 0);
                $data['qty_reserved'] = $reserved;
                $data['qty_delivered'] = $delivered;
                $data['qty_outstanding'] = max(0, $ordered - $delivered);
                $data['line_status'] = $delivered >= $ordered && $ordered > 0 ? 'delivered' : ($reserved > 0 ? 'reserved' : 'open');
            } elseif ($this->db->fieldExists('qty_received', $table)) {
                $received = (float) ($row['qty_received'] ?? 0);
                $data['qty_received'] = $received;
                $data['qty_outstanding'] = max(0, $ordered - $received);
                $data['line_status'] = $received >= $ordered && $ordered > 0 ? 'received' : ($received > 0 ? 'partial_received' : 'open');
            }

            $this->db->table($table)->where('id', $row['id'])->update($data);
        }
    }
}
