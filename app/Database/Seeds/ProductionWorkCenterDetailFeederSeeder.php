<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;

class ProductionWorkCenterDetailFeederSeeder extends Seeder
{
    public function run(): void
    {
        $db = Database::connect();
        if (! $db->tableExists('production_work_centers')) {
            return;
        }

        $rows = $db->table('production_work_centers')
            ->whereIn('work_center_code', ['WC001', 'WC002', 'WC003', 'WC004', 'WC005'])
            ->orderBy('work_center_code', 'ASC')
            ->get(100)
            ->getResultArray();

        foreach ($rows as $wc) {
            $id = (int) ($wc['id'] ?? 0);
            $code = (string) ($wc['work_center_code'] ?? '');
            if ($id < 1 || $code === '') {
                continue;
            }

            if ($db->tableExists('work_center_machine')) {
                $payload = $this->filter($db, 'work_center_machine', [
                    'company_id' => $wc['company_id'] ?? null,
                    'site_id' => $wc['site_id'] ?? null,
                    'work_center_id' => $id,
                    'site' => $wc['site_code'] ?? '',
                    'dept' => $wc['department_code'] ?? '',
                    'warehouse' => $wc['warehouse_code'] ?? '',
                    'work_center' => $code,
                    'no' => 1,
                    'machine' => $wc['machine_code'] ?? ($code . '-MCH'),
                    'notes1' => $wc['notes'] ?? 'Production feeder machine detail.',
                    'speed' => $wc['speed'] ?? 1,
                    'capacity' => $wc['capacity_percent'] ?? 100,
                    'length' => $wc['max_length'] ?? 0,
                    'luom' => $wc['length_uom'] ?? '',
                    'width' => $wc['max_width'] ?? 0,
                    'wuom' => $wc['width_uom'] ?? '',
                    'height' => $wc['max_height'] ?? 0,
                    'huom' => $wc['height_uom'] ?? '',
                    'volume' => $wc['max_volume'] ?? 0,
                    'vuom' => $wc['volume_uom'] ?? '',
                    'qtylabor' => $wc['qty_labor'] ?? 1,
                    'workhour' => $wc['working_hour'] ?? 8,
                    'active' => 1,
                    'created_by' => 'feeder',
                    'updated_by' => 'feeder',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $machine = $db->table('work_center_machine')->where('work_center_id', $id)->where('no', 1)->get(1)->getRowArray();
                if ($machine) {
                    $db->table('work_center_machine')->where('id', (int) $machine['id'])->update($payload);
                } else {
                    $db->table('work_center_machine')->insert($payload);
                }
            }

            if ($db->tableExists('work_center_cost')) {
                $payload = $this->filter($db, 'work_center_cost', [
                    'company_id' => $wc['company_id'] ?? null,
                    'site_id' => $wc['site_id'] ?? null,
                    'work_center_id' => $id,
                    'work_center' => $code,
                    'costtype' => $wc['cost_type'] ?? 'Labor',
                    'costamount' => $wc['cost_amount'] ?? 0,
                    'costuom' => $wc['cost_uom'] ?? 'Hour',
                    'notes2' => 'Production feeder cost detail.',
                    'active' => 1,
                    'created_by' => 'feeder',
                    'updated_by' => 'feeder',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $cost = $db->table('work_center_cost')->where('work_center_id', $id)->where('costtype', $payload['costtype'] ?? 'Labor')->get(1)->getRowArray();
                if ($cost) {
                    $db->table('work_center_cost')->where('id', (int) $cost['id'])->update($payload);
                } else {
                    $db->table('work_center_cost')->insert($payload);
                }
            }
        }
    }

    private function filter($db, string $table, array $payload): array
    {
        return array_intersect_key($payload, array_flip($db->getFieldNames($table)));
    }
}
