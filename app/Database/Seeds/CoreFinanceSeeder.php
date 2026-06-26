<?php

namespace App\Database\Seeds;

use App\Services\Finance\PostingProfileService;
use CodeIgniter\Database\Seeder;

class CoreFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTransactionCodes();
        $this->seedCurrencies();
        $this->seedUoms();
        $this->seedCostTypes();
        $this->seedPostingProfiles();
    }

    private function seedTransactionCodes(): void
    {
        if (! $this->db->tableExists('transaction_codes')) {
            return;
        }

        $rows = [
            ['code' => 'PO', 'name' => 'Purchase Order', 'description' => 'Purchase order document numbering'],
            ['code' => 'PR', 'name' => 'Purchase Receipt', 'description' => 'Purchase receipt document numbering'],
            ['code' => 'SO', 'name' => 'Sales Order', 'description' => 'Sales order document numbering'],
            ['code' => 'SD', 'name' => 'Sales Delivery', 'description' => 'Sales delivery document numbering'],
            ['code' => 'SI', 'name' => 'Sales Invoice', 'description' => 'Sales invoice document numbering'],
            ['code' => 'PI', 'name' => 'Purchase Invoice', 'description' => 'Purchase invoice document numbering'],
            ['code' => 'JV', 'name' => 'Journal Voucher', 'description' => 'General ledger journal voucher numbering'],
        ];

        foreach ($rows as $row) {
            $existing = $this->db->table('transaction_codes')->where('code', $row['code'])->get(1)->getRowArray();
            $payload = $row + ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
            if ($existing) {
                $this->db->table('transaction_codes')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('transaction_codes')->insert($payload);
            }
        }
    }

    private function seedCurrencies(): void
    {
        if (! $this->db->tableExists('currencies')) {
            return;
        }

        $rows = [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'rounding' => 0],
            ['code' => 'USD', 'name' => 'US Dollar', 'rounding' => 0.01],
        ];

        foreach ($rows as $row) {
            $existing = $this->db->table('currencies')->where('code', $row['code'])->get(1)->getRowArray();
            $payload = $this->filterExistingColumns('currencies', $row + ['company_id' => null, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            if ($existing) {
                $this->db->table('currencies')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('currencies')->insert($this->filterExistingColumns('currencies', $payload));
            }
        }
    }

    private function seedUoms(): void
    {
        if (! $this->db->tableExists('uoms')) {
            return;
        }

        $companyIds = $this->companyIds();
        if ($companyIds === []) {
            return;
        }

        $rows = [
            ['code' => 'PCS', 'name' => 'Pieces', 'rate' => 1, 'description' => 'Default stock unit'],
            ['code' => 'KG', 'name' => 'Kilogram', 'rate' => 1, 'description' => 'Weight unit'],
            ['code' => 'GR', 'name' => 'Gram', 'rate' => 1, 'description' => 'Weight unit'],
            ['code' => 'MTR', 'name' => 'Meter', 'rate' => 1, 'description' => 'Length unit'],
            ['code' => 'LTR', 'name' => 'Liter', 'rate' => 1, 'description' => 'Volume unit'],
            ['code' => 'BOX', 'name' => 'Box', 'rate' => 1, 'description' => 'Packing unit'],
            ['code' => 'SET', 'name' => 'Set', 'rate' => 1, 'description' => 'Set unit'],
        ];

        foreach ($companyIds as $companyId) {
            foreach ($rows as $row) {
                $existing = $this->db->table('uoms')
                    ->where('company_id', $companyId)
                    ->where('code', $row['code'])
                    ->get(1)
                    ->getRowArray();

                $payload = $this->filterExistingColumns('uoms', $row + [
                    'company_id' => $companyId,
                    'is_active' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if ($existing) {
                    $this->db->table('uoms')->where('id', (int) $existing['id'])->update($payload);
                    continue;
                }

                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('uoms')->insert($this->filterExistingColumns('uoms', $payload));
            }
        }
    }

    private function seedCostTypes(): void
    {
        if (! $this->db->tableExists('costing_cost_types')) {
            return;
        }

        $rows = [
            ['type' => 'TK', 'description' => 'Tenaga Kerja', 'cost_group' => 'Labor'],
            ['type' => 'Listrik', 'description' => 'Biaya listrik', 'cost_group' => 'Overhead'],
            ['type' => 'Pisau', 'description' => 'Biaya pisau/cutting tool', 'cost_group' => 'Overhead'],
            ['type' => 'Bensin', 'description' => 'Biaya bensin', 'cost_group' => 'Overhead'],
        ];

        foreach ($rows as $row) {
            $existing = $this->db->table('costing_cost_types')->where('company_id', null)->where('type', $row['type'])->get(1)->getRowArray();
            $payload = $this->filterExistingColumns('costing_cost_types', $row + ['company_id' => null, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            if ($existing) {
                $this->db->table('costing_cost_types')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('costing_cost_types')->insert($this->filterExistingColumns('costing_cost_types', $payload));
            }
        }
    }

    private function seedPostingProfiles(): void
    {
        if (! $this->db->tableExists('gl_posting_profiles')) {
            return;
        }

        $companyIds = $this->companyIds();
        if ($companyIds === []) {
            $companyIds = [1];
        }

        foreach ($companyIds as $companyId) {
            foreach (PostingProfileService::defaults() as $moduleCode => $keys) {
                foreach ($keys as $postingKey => $accountNo) {
                    $existing = $this->db->table('gl_posting_profiles')
                        ->where('company_id', $companyId)
                        ->where('module_code', $moduleCode)
                        ->where('posting_key', $postingKey)
                        ->get(1)
                        ->getRowArray();

                    $payload = $this->filterExistingColumns('gl_posting_profiles', [
                        'company_id' => $companyId,
                        'module_code' => $moduleCode,
                        'posting_key' => $postingKey,
                        'account_no' => $accountNo,
                        'description' => PostingProfileService::label($moduleCode, $postingKey),
                        'is_active' => 1,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    if ($existing) {
                        if (trim((string) ($existing['account_no'] ?? '')) === '') {
                            $this->db->table('gl_posting_profiles')->where('id', (int) $existing['id'])->update($payload);
                        }
                        continue;
                    }

                    $payload['created_at'] = date('Y-m-d H:i:s');
                    $this->db->table('gl_posting_profiles')->insert($this->filterExistingColumns('gl_posting_profiles', $payload));
                }
            }
        }
    }

    /** @return list<int> */
    private function companyIds(): array
    {
        if (! $this->db->tableExists('companies')) {
            return [];
        }

        $rows = $this->db->table('companies')->select('id')->get()->getResultArray();
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterExistingColumns(string $table, array $payload): array
    {
        foreach (array_keys($payload) as $column) {
            if (! $this->db->fieldExists($column, $table)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }
}
