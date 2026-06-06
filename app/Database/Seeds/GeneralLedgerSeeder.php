<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class GeneralLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $companies = $this->db->table('companies')->where('is_active', 1)->get()->getResultArray();

        foreach ($companies as $company) {
            $companyId = (int) $company['id'];
            $this->upsert('gl_books', ['company_id' => $companyId, 'book_code' => 'MAIN'], [
                'company_id' => $companyId,
                'book_code' => 'MAIN',
                'book_name' => 'Main Ledger',
                'currency_code' => $company['base_currency'] ?? 'IDR',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($this->accounts() as $account) {
                $this->upsert('chart_accounts', ['company_id' => $companyId, 'account_no' => $account[0]], [
                    'company_id' => $companyId,
                    'account_no' => $account[0],
                    'account_name' => $account[1],
                    'account_type' => $account[2],
                    'normal_balance' => $account[3],
                    'parent_account_no' => $account[4],
                    'is_postable' => $account[5],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function accounts(): array
    {
        return [
            ['1000', 'Assets', 'asset', 'debit', null, 0],
            ['1100', 'Cash and Bank', 'asset', 'debit', '1000', 1],
            ['1200', 'Accounts Receivable', 'asset', 'debit', '1000', 1],
            ['1300', 'Inventory', 'asset', 'debit', '1000', 1],
            ['2000', 'Liabilities', 'liability', 'credit', null, 0],
            ['2100', 'Accounts Payable', 'liability', 'credit', '2000', 1],
            ['2200', 'Tax Payable', 'liability', 'credit', '2000', 1],
            ['3000', 'Equity', 'equity', 'credit', null, 0],
            ['3100', 'Capital', 'equity', 'credit', '3000', 1],
            ['4000', 'Revenue', 'revenue', 'credit', null, 0],
            ['4100', 'Sales Revenue', 'revenue', 'credit', '4000', 1],
            ['5000', 'Cost of Goods Sold', 'expense', 'debit', null, 1],
            ['6000', 'Operating Expense', 'expense', 'debit', null, 0],
            ['6100', 'Salary Expense', 'expense', 'debit', '6000', 1],
            ['6200', 'Office Expense', 'expense', 'debit', '6000', 1],
        ];
    }

    private function upsert(string $table, array $where, array $data): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $row = $this->db->table($table)->where($where)->get()->getRowArray();
        if ($row !== null) {
            $this->db->table($table)->where('id', $row['id'])->update($data);

            return;
        }

        $this->db->table($table)->insert($data);
    }
}
