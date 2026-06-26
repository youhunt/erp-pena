<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CoreProductionCostingFoundation extends Migration
{
    public function up(): void
    {
        $this->ensureProductionForecastsTable();
        $this->ensureProductionMrpRunsTable();
        $this->ensureProductionMrpLinesTable();
        $this->ensureProductionMrpPlannedOrdersTable();
        $this->ensureCostingCostTypesTable();
        $this->ensureCostingItemCostsTable();
        $this->ensureCostingItemCostLinesTable();
    }

    public function down(): void
    {
        // Core ERP migrations are intentionally non-destructive.
        // Production/MRP and costing tables may already contain transactional/planning data.
    }

    private function ensureProductionForecastsTable(): void
    {
        if ($this->db->tableExists('production_forecasts')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT'],
            'site_id' => ['type' => 'INT', 'null' => true],
            'forecast_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'forecast_date' => ['type' => 'DATE'],
            'period_start' => ['type' => 'DATE'],
            'period_end' => ['type' => 'DATE'],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qty_forecast' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'site_id', 'item_code', 'period_start', 'period_end'], false, false, 'idx_production_forecasts_item_period');
        $this->forge->createTable('production_forecasts', true);
    }

    private function ensureProductionMrpRunsTable(): void
    {
        if ($this->db->tableExists('production_mrp_runs')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT'],
            'site_id' => ['type' => 'INT', 'null' => true],
            'run_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'run_date' => ['type' => 'DATE'],
            'period_start' => ['type' => 'DATE', 'null' => true],
            'period_end' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status', false, false, 'idx_production_mrp_runs_status');
        $this->forge->createTable('production_mrp_runs', true);
    }

    private function ensureProductionMrpLinesTable(): void
    {
        if ($this->db->tableExists('production_mrp_lines')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'production_mrp_run_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'company_id' => ['type' => 'INT'],
            'site_id' => ['type' => 'INT', 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'demand_qty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'onhand_qty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'supply_qty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'planned_qty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'action_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'action_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'planned'],
            'required_date' => ['type' => 'DATE', 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('production_mrp_run_id', false, false, 'idx_production_mrp_lines_run');
        $this->forge->addKey(['company_id', 'site_id', 'item_code'], false, false, 'idx_production_mrp_lines_item');
        $this->forge->createTable('production_mrp_lines', true);
    }

    private function ensureProductionMrpPlannedOrdersTable(): void
    {
        if ($this->db->tableExists('production_mrp_planned_orders')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT'],
            'site_id' => ['type' => 'INT', 'null' => true],
            'production_mrp_run_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'production_mrp_line_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'planned_order_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'planned_order_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qty_planned' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'required_date' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'planned'],
            'source_document_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status', false, false, 'idx_mrp_planned_orders_status');
        $this->forge->addKey(['company_id', 'site_id', 'item_code'], false, false, 'idx_mrp_planned_orders_item');
        $this->forge->createTable('production_mrp_planned_orders', true);
    }

    private function ensureCostingCostTypesTable(): void
    {
        if ($this->db->tableExists('costing_cost_types')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'description' => ['type' => 'VARCHAR', 'constraint' => 300],
            'cost_group' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Material'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('cost_group', false, false, 'idx_costing_cost_types_group');
        $this->forge->createTable('costing_cost_types', true);
    }

    private function ensureCostingItemCostsTable(): void
    {
        if ($this->db->tableExists('costing_item_costs')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'null' => true],
            'site_id' => ['type' => 'INT', 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'department_code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'this_item_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'total_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'calculated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'site_id', 'item_code'], false, false, 'idx_costing_item_costs_item');
        $this->forge->createTable('costing_item_costs', true);
    }

    private function ensureCostingItemCostLinesTable(): void
    {
        if ($this->db->tableExists('costing_item_cost_lines')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'costing_item_cost_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'bom_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'child_item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'child_item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'bom_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'work_center_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'total_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('costing_item_cost_id', false, false, 'idx_costing_item_cost_lines_parent');
        $this->forge->createTable('costing_item_cost_lines', true);
    }
}
