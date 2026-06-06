<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CashBankSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $companies = $this->db->table('companies')->where('is_active', 1)->get()->getResultArray();

        foreach ($companies as $company) {
            $companyId = (int) $company['id'];
            foreach ([
                ['CASH-IDR', 'Petty Cash IDR', 'cash', 5000000],
                ['BANK-IDR', 'Main Bank IDR', 'bank', 25000000],
            ] as [$code, $name, $type, $balance]) {
                $this->upsert('cash_bank_accounts', ['company_id' => $companyId, 'cash_bank_code' => $code], [
                    'company_id' => $companyId,
                    'site_id' => null,
                    'cash_bank_code' => $code,
                    'cash_bank_name' => $name,
                    'account_type' => $type,
                    'currency_code' => $company['base_currency'] ?? 'IDR',
                    'gl_account_no' => '1100',
                    'opening_balance' => $balance,
                    'current_balance' => $balance,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function upsert(string $table, array $where, array $data): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $row = $this->db->table($table)->where($where)->get()->getRowArray();
        if ($row !== null) {
            unset($data['current_balance']);
            $this->db->table($table)->where('id', $row['id'])->update($data);

            return;
        }

        $this->db->table($table)->insert($data);
    }
}
