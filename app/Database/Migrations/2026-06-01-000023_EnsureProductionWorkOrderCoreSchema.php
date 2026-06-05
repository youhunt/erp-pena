<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureProductionWorkOrderCoreSchema extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('production_work_orders')) {
            return;
        }

        $this->addMissingColumns('production_work_orders', [
            'wo_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'WO'],
            'site_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'department_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'work_center_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'parent_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'parent_item_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'parent_item_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'bom_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'routing_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'batch_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 1],
            'wo_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 1],
            'std_qty_finished' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
            'act_qty_finished' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);

        if ($this->db->tableExists('production_work_order_components')) {
            $this->addMissingColumns('production_work_order_components', [
                'allocated_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'issued_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
            ]);
        }
    }

    public function down(): void
    {
        // Schema ensure migration intentionally does not drop columns.
    }

    /**
     * @param array<string, array<string, mixed>> $columns
     */
    private function addMissingColumns(string $table, array $columns): void
    {
        $missing = [];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, $table)) {
                $missing[$name] = $definition;
            }
        }

        if ($missing !== []) {
            $this->forge->addColumn($table, $missing);
        }
    }
}
