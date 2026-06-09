<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FinanceGlSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = $this->firstId('companies');
        if ($companyId === null) {
            echo "FinanceGlSeeder skipped: no company found.\n";
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->seedGlBook($companyId, $now);
        $this->seedChartAccounts($companyId, $now);
        $this->seedPostingProfiles($companyId, $now);
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
            ['SALES', 'AR_CONTROL', '1200', 'Default A/R control account'],
            ['SALES', 'SALES_REVENUE', '4100', 'Default sales revenue account'],
            ['SALES', 'OUTPUT_VAT', '2200', 'Default output VAT account'],
            ['SALES', 'COGS', '5000', 'Default COGS account'],
            ['SALES', 'INVENTORY', '1300', 'Default inventory account'],
            ['PURCHASE', 'AP_CONTROL', '2100', 'Default A/P control account'],
            ['PURCHASE', 'INVENTORY', '1300', 'Default purchased inventory account'],
            ['PURCHASE', 'INPUT_VAT', '1400', 'Default input VAT account'],
            ['CASHBANK', 'CASH_ON_HAND', '1110', 'Default cash account'],
            ['CASHBANK', 'BANK', '1120', 'Default bank account'],
            ['POS', 'CASH_RECEIPT', '1110', 'Default POS cash receipt account'],
            ['POS', 'SALES_REVENUE', '4100', 'Default POS sales revenue account'],
            ['POS', 'OUTPUT_VAT', '2200', 'Default POS output VAT account'],
            ['POS', 'COGS', '5000', 'Default POS COGS account'],
            ['POS', 'INVENTORY', '1300', 'Default POS inventory account'],
        ];

        foreach ($profiles as [$module, $key, $accountNo, $description]) {
            $exists = $this->db->table('gl_posting_profiles')
                ->where('company_id', $companyId)
                ->where('module_code', $module)
                ->where('posting_key', $key)
                ->get()->getRowArray();

            if ($exists !== null) {
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

    private function firstId(string $table): ?int
    {
        if (! $this->db->tableExists($table)) {
            return null;
        }

        $row = $this->db->table($table)->orderBy('id', 'ASC')->get(1)->getRowArray();
        return $row !== null ? (int) $row['id'] : null;
    }
}
