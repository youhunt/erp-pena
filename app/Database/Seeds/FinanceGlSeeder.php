<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FinanceGlSeeder extends Seeder
{
    public function run(): void
    {
        $companies = $this->activeCompanies();
        if ($companies === []) {
            echo "FinanceGlSeeder skipped: no company found.\n";
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($companies as $company) {
            $companyId = (int) $company['id'];
            $this->seedGlBook($companyId, $now);
            $this->seedChartAccounts($companyId, $now);
            $this->seedPostingProfiles($companyId, $now);
        }
    }

    private function seedGlBook(int $companyId, string $now): void
    {
        $exists = $this->db->table('gl_books')
            ->where('company_id', $companyId)
            ->where('book_code', 'MAIN')
            ->get()->getRowArray();

        if ($exists !== null) {
            return;
        }

        $this->db->table('gl_books')->insert([
            'company_id' => $companyId,
            'book_code' => 'MAIN',
            'book_name' => 'Main Ledger Book',
            'currency_code' => 'IDR',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedChartAccounts(int $companyId, string $now): void
    {
        $accounts = [
            ['1000', 'Asset', 'asset', 'debit', null, 0],
            ['1100', 'Cash and Bank', 'asset', 'debit', '1000', 0],
            ['1110', 'Cash on Hand', 'asset', 'debit', '1100', 1],
            ['1120', 'Bank Account', 'asset', 'debit', '1100', 1],
            ['1200', 'Accounts Receivable', 'asset', 'debit', '1000', 1],
            ['1300', 'Inventory', 'asset', 'debit', '1000', 1],
            ['1400', 'Input VAT', 'asset', 'debit', '1000', 1],
            ['2000', 'Liability', 'liability', 'credit', null, 0],
            ['2100', 'Accounts Payable', 'liability', 'credit', '2000', 1],
            ['2200', 'Output VAT', 'liability', 'credit', '2000', 1],
            ['2300', 'Goods Received Not Invoiced', 'liability', 'credit', '2000', 1],
            ['3000', 'Equity', 'equity', 'credit', null, 0],
            ['3100', 'Owner Capital', 'equity', 'credit', '3000', 1],
            ['4000', 'Revenue', 'revenue', 'credit', null, 0],
            ['4100', 'Sales Revenue', 'revenue', 'credit', '4000', 1],
            ['5000', 'Cost of Goods Sold', 'expense', 'debit', null, 1],
            ['6000', 'Operating Expense', 'expense', 'debit', null, 0],
            ['6100', 'Salary Expense', 'expense', 'debit', '6000', 1],
            ['6200', 'General Expense', 'expense', 'debit', '6000', 1],
            ['7000', 'Other Income', 'revenue', 'credit', null, 1],
            ['8000', 'Other Expense', 'expense', 'debit', null, 1],
        ];

        foreach ($accounts as [$no, $name, $type, $normal, $parent, $postable]) {
            $exists = $this->db->table('chart_accounts')
                ->where('company_id', $companyId)
                ->where('account_no', $no)
                ->get()->getRowArray();

            if ($exists !== null) {
                $this->db->table('chart_accounts')
                    ->where('id', (int) $exists['id'])
                    ->update([
                        'account_name' => $name,
                        'account_type' => $type,
                        'normal_balance' => $normal,
                        'parent_account_no' => $parent,
                        'is_postable' => $postable,
                        'is_active' => 1,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            $this->db->table('chart_accounts')->insert([
                'company_id' => $companyId,
                'account_no' => $no,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'parent_account_no' => $parent,
                'is_postable' => $postable,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedPostingProfiles(int $companyId, string $now): void
    {
        $profiles = [
            ['ap', 'payable', '2100', 'Accounts Payable'],
            ['ap', 'grni', '2300', 'Goods Received Not Invoiced'],
            ['ap', 'manual_expense', '6200', 'Manual A/P Expense'],
            ['ap', 'inventory', '1300', 'Purchased Inventory'],
            ['ap', 'input_vat', '1400', 'Input VAT'],
            ['ar', 'receivable', '1200', 'Accounts Receivable'],
            ['ar', 'sales_revenue', '4100', 'Sales Revenue'],
            ['ar', 'output_vat', '2200', 'Output VAT'],
            ['sales', 'cogs', '5000', 'Cost of Goods Sold'],
            ['sales', 'inventory', '1300', 'Inventory'],
            ['inventory', 'inventory', '1300', 'Inventory'],
            ['inventory', 'adjustment_gain', '7000', 'Inventory Adjustment Gain'],
            ['inventory', 'adjustment_loss', '8000', 'Inventory Adjustment Loss'],
            ['cashbank', 'cash_bank', '1100', 'Cash and Bank'],
            ['pos', 'cash_receipt', '1110', 'Default POS cash receipt account'],
            ['pos', 'sales_revenue', '4100', 'Default POS sales revenue account'],
            ['pos', 'output_vat', '2200', 'Default POS output VAT account'],
            ['pos', 'cogs', '5000', 'Default POS COGS account'],
            ['pos', 'inventory', '1300', 'Default POS inventory account'],
        ];

        foreach ($profiles as [$module, $key, $accountNo, $description]) {
            $exists = $this->db->table('gl_posting_profiles')
                ->where('company_id', $companyId)
                ->where('module_code', $module)
                ->where('posting_key', $key)
                ->where('deleted_at', null)
                ->get()->getRowArray();

            if ($exists !== null) {
                $this->db->table('gl_posting_profiles')
                    ->where('id', (int) $exists['id'])
                    ->update([
                        'account_no' => $accountNo,
                        'description' => $description,
                        'is_active' => 1,
                        'updated_by' => 'system',
                        'updated_at' => $now,
                    ]);
                continue;
            }

            $this->db->table('gl_posting_profiles')->insert([
                'company_id' => $companyId,
                'module_code' => $module,
                'posting_key' => $key,
                'account_no' => $accountNo,
                'description' => $description,
                'is_active' => 1,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function activeCompanies(): array
    {
        if (! $this->db->tableExists('companies')) {
            return [];
        }

        $builder = $this->db->table('companies')->orderBy('id', 'ASC');
        if ($this->db->fieldExists('is_active', 'companies')) {
            $builder->where('is_active', 1);
        }

        return $builder->get()->getResultArray();
    }
}
