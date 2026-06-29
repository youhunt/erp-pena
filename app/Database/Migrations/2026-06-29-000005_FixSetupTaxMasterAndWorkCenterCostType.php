<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixSetupTaxMasterAndWorkCenterCostType extends Migration
{
    public function up(): void
    {
        foreach (['item_vat_rates', 'charge_vat_rates', 'wht_rates'] as $table) {
            $this->ensureAuditColumns($table);
        }

        $this->ensureCostingCostTypeCompatibility();
        $this->ensureMenuItems();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureCostingCostTypeCompatibility(): void
    {
        if (! $this->db->tableExists('costing_cost_types')) {
            return;
        }

        $this->ensureColumn('costing_cost_types', 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->ensureColumn('costing_cost_types', 'created_by', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn('costing_cost_types', 'updated_by', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn('costing_cost_types', 'created_at', ['type' => 'DATETIME', 'null' => true]);
        $this->ensureColumn('costing_cost_types', 'updated_at', ['type' => 'DATETIME', 'null' => true]);
        $this->ensureColumn('costing_cost_types', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
    }

    private function ensureAuditColumns(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $this->ensureColumn($table, 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->ensureColumn($table, 'created_by', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn($table, 'updated_by', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn($table, 'created_at', ['type' => 'DATETIME', 'null' => true]);
        $this->ensureColumn($table, 'updated_at', ['type' => 'DATETIME', 'null' => true]);
        $this->ensureColumn($table, 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
    }

    private function ensureColumn(string $table, string $column, array $definition): void
    {
        if ($this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->addColumn($table, [$column => $definition]);
    }

    private function ensureMenuItems(): void
    {
        if (! $this->db->tableExists('menu_items')) {
            return;
        }

        $setup = $this->db->table('menu_items')
            ->where('label', 'Setup')
            ->groupStart()
                ->where('parent_id', null)
                ->orWhere('parent_id', 0)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        $now = date('Y-m-d H:i:s');
        if ($setup === null) {
            $this->db->table('menu_items')->insert([
                'parent_id' => 0,
                'label' => 'Setup',
                'route' => '#',
                'icon' => 'bx-slider-alt',
                'permission' => 'setup.master.view',
                'sort_order' => 900,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $setupId = (int) $this->db->insertID();
        } else {
            $setupId = (int) $setup['id'];
        }

        foreach ([
            ['Item VAT Master', 'setup/item-vat', 'bx-receipt', 237],
            ['Other Charge VAT Master', 'setup/other-charge-vat', 'bx-plus-medical', 238],
            ['WHT Master', 'setup/wht', 'bx-cut', 239],
        ] as [$label, $route, $icon, $sort]) {
            $existing = $this->db->table('menu_items')->where('route', $route)->get(1)->getRowArray();
            $payload = [
                'parent_id' => $setupId,
                'label' => $label,
                'route' => $route,
                'icon' => $icon,
                'permission' => 'setup.master.view',
                'sort_order' => $sort,
                'is_active' => 1,
                'updated_at' => $now,
            ];

            if ($existing) {
                $this->db->table('menu_items')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }

            $payload['created_at'] = $now;
            $this->db->table('menu_items')->insert($payload);
        }
    }
}
