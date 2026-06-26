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
            $payload = $row + ['company_id' => null, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
            if ($existing) {
                $this->db->table('currencies')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('currencies')->insert($payload);
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
            $payload = $row + ['company_id' => null, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
            if ($existing) {
                $this->db->table('costing_cost_types')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('costing_cost_types')->insert($payload);
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

                    $payload = [
                        'company_id' => $companyId,
                        'module_code' => $moduleCode,
                        'posting_key' => $postingKey,
                        'account_no' => $accountNo,
                        'description' => PostingProfileService::label($moduleCode, $postingKey),
                        'is_active' => 1,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];

                    if ($existing) {
                        if (trim((string) ($existing['account_no'] ?? '')) === '') {
                            $this->db->table('gl_posting_profiles')->where('id', (int) $existing['id'])->update($payload);
                        }
                        continue;
                    }

                    $payload['created_at'] = date('Y-m-d H:i:s');
                    $this->db->table('gl_posting_profiles')->insert($payload);
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
}
