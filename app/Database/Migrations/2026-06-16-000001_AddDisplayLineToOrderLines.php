<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDisplayLineToOrderLines extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sales_order_lines') && ! $this->db->fieldExists('so_line', 'sales_order_lines')) {
            $this->forge->addColumn('sales_order_lines', [
                'so_line' => ['type' => 'INT', 'constraint' => 10, 'null' => true, 'after' => 'line_no'],
            ]);
        }
        if ($this->db->tableExists('sales_order_lines') && $this->db->fieldExists('so_line', 'sales_order_lines')) {
            $this->backfillDisplayLine('sales_order_lines', 'sales_order_id', 'so_line');
            $this->addIndexIfMissing('sales_order_lines', 'idx_sales_order_lines_so_line', ['sales_order_id', 'so_line']);
        }

        if ($this->db->tableExists('purchase_order_lines') && ! $this->db->fieldExists('po_line', 'purchase_order_lines')) {
            $this->forge->addColumn('purchase_order_lines', [
                'po_line' => ['type' => 'INT', 'constraint' => 10, 'null' => true, 'after' => 'line_no'],
            ]);
        }
        if ($this->db->tableExists('purchase_order_lines') && $this->db->fieldExists('po_line', 'purchase_order_lines')) {
            $this->backfillDisplayLine('purchase_order_lines', 'purchase_order_id', 'po_line');
            $this->addIndexIfMissing('purchase_order_lines', 'idx_purchase_order_lines_po_line', ['purchase_order_id', 'po_line']);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('purchase_order_lines') && $this->db->fieldExists('po_line', 'purchase_order_lines')) {
            $this->dropIndexIfExists('purchase_order_lines', 'idx_purchase_order_lines_po_line');
            $this->forge->dropColumn('purchase_order_lines', 'po_line');
        }

        if ($this->db->tableExists('sales_order_lines') && $this->db->fieldExists('so_line', 'sales_order_lines')) {
            $this->dropIndexIfExists('sales_order_lines', 'idx_sales_order_lines_so_line');
            $this->forge->dropColumn('sales_order_lines', 'so_line');
        }
    }

    private function backfillDisplayLine(string $table, string $foreignKey, string $displayField): void
    {
        $rows = $this->db->table($table)
            ->select('id, ' . $foreignKey)
            ->orderBy($foreignKey, 'ASC')
            ->orderBy('line_no', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $currentParent = null;
        $line = 0;
        foreach ($rows as $row) {
            if ((string) $currentParent !== (string) $row[$foreignKey]) {
                $currentParent = $row[$foreignKey];
                $line = 1;
            } else {
                $line++;
            }

            $this->db->table($table)
                ->where('id', (int) $row['id'])
                ->update([$displayField => $line, 'line_no' => $line]);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        )->getRowArray();

        if ((int) ($exists['total'] ?? 0) > 0) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $index . '`');
        }
    }

    /**
     * @param list<string> $columns
     */
    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        $exists = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        )->getRowArray();

        if ((int) ($exists['total'] ?? 0) > 0) {
            return;
        }

        $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $columns);
        $this->db->query('ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` (' . implode(', ', $quotedColumns) . ')');
    }
}
