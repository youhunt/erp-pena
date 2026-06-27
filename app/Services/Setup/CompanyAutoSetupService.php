<?php

namespace App\Services\Setup;

use Config\Database;
use Throwable;

class CompanyAutoSetupService
{
    public function run(int $companyId, ?int $userId = null): void
    {
        if ($companyId < 1) {
            return;
        }

        foreach ([
            fn () => $this->currencies($userId),
            fn () => $this->transactionCodes($userId),
            fn () => $this->uoms($companyId, $userId),
            fn () => $this->glBook($companyId, $userId),
            fn () => $this->coa($companyId, $userId),
            fn () => $this->postingProfiles($companyId, $userId),
            fn () => $this->cashBank($companyId, $userId),
        ] as $job) {
            try {
                $job();
            } catch (Throwable) {
                // Keep the bootstrap resilient. Core Health exposes missing tables/columns.
            }
        }
    }

    private function currencies(?int $userId): void
    {
        $this->upsertGlobal('currencies', 'code', [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'rounding' => 0, 'is_active' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'rounding' => 0.01, 'is_active' => 1],
        ], $userId);
    }

    private function transactionCodes(?int $userId): void
    {
        $this->upsertGlobal('transaction_codes', 'code', [
            ['code' => 'PO', 'name' => 'Purchase Order', 'description' => 'Purchase order numbering', 'is_active' => 1],
            ['code' => 'PR', 'name' => 'Purchase Receipt', 'description' => 'Purchase receipt numbering', 'is_active' => 1],
            ['code' => 'PI', 'name' => 'Purchase Invoice', 'description' => 'Purchase invoice numbering', 'is_active' => 1],
            ['code' => 'APP', 'name' => 'A/P Payment', 'description' => 'A/P payment numbering', 'is_active' => 1],
            ['code' => 'SO', 'name' => 'Sales Order', 'description' => 'Sales order numbering', 'is_active' => 1],
            ['code' => 'SD', 'name' => 'Sales Delivery', 'description' => 'Sales delivery numbering', 'is_active' => 1],
            ['code' => 'SI', 'name' => 'Sales Invoice', 'description' => 'Sales invoice numbering', 'is_active' => 1],
            ['code' => 'ARP', 'name' => 'A/R Receipt', 'description' => 'A/R receipt numbering', 'is_active' => 1],
            ['code' => 'JV', 'name' => 'Journal Voucher', 'description' => 'Journal voucher numbering', 'is_active' => 1],
            ['code' => 'CB', 'name' => 'Cash/Bank Entry', 'description' => 'Cash and bank numbering', 'is_active' => 1],
        ], $userId);
    }

    private function uoms(int $companyId, ?int $userId): void
    {
        $rows = [];
        foreach ([
            ['PCS', 'Pieces'], ['KG', 'Kilogram'], ['GR', 'Gram'], ['MTR', 'Meter'], ['LTR', 'Liter'], ['BOX', 'Box'], ['SET', 'Set'],
        ] as [$code, $name]) {
            $rows[] = ['company_id' => $companyId, 'code' => $code, 'name' => $name, 'rate' => 1, 'description' => 'Default UOM', 'is_active' => 1];
        }
        $this->upsertTenant('uoms', ['company_id', 'code'], $rows, $userId);
    }

    private function glBook(int $companyId, ?int $userId): void
    {
        $this->upsertTenant('gl_books', ['company_id', 'book_code'], [[
            'company_id' => $companyId,
            'book_code' => 'MAIN',
            'book_name' => 'Main Ledger Book',
            'currency_code' => 'IDR',
            'is_default' => 1,
            'is_active' => 1,
        ]], $userId);
    }

    private function coa(int $companyId, ?int $userId): void
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

        $rows = [];
        foreach ($accounts as [$no, $name, $type, $normal, $parent, $postable]) {
            $rows[] = [
                'company_id' => $companyId,
                'account_no' => $no,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'parent_account_no' => $parent,
                'is_postable' => $postable,
                'is_active' => 1,
            ];
        }
        $this->upsertTenant('chart_accounts', ['company_id', 'account_no'], $rows, $userId);
    }

    private function postingProfiles(int $companyId, ?int $userId): void
    {
        $profiles = [
            ['ap', 'inventory', '1300', 'Purchased Inventory'],
            ['ap', 'grni', '2300', 'Goods Received Not Invoiced'],
            ['ap', 'payable', '2100', 'Accounts Payable'],
            ['ap', 'manual_expense', '6200', 'Manual A/P Expense'],
            ['ap', 'input_vat', '1400', 'Input VAT'],
            ['ar', 'receivable', '1200', 'Accounts Receivable'],
            ['ar', 'sales_revenue', '4100', 'Sales Revenue'],
            ['ar', 'output_vat', '2200', 'Output VAT'],
            ['sales', 'cogs', '5000', 'Cost of Goods Sold'],
            ['sales', 'inventory', '1300', 'Inventory'],
            ['inventory', 'inventory', '1300', 'Inventory'],
            ['inventory', 'adjustment_gain', '7000', 'Inventory Adjustment Gain'],
            ['inventory', 'adjustment_loss', '8000', 'Inventory Adjustment Loss'],
            ['cashbank', 'cash_bank', '1120', 'Default bank account'],
        ];

        $rows = [];
        foreach ($profiles as [$module, $key, $account, $description]) {
            $rows[] = ['company_id' => $companyId, 'module_code' => $module, 'posting_key' => $key, 'account_no' => $account, 'description' => $description, 'is_active' => 1];
        }
        $this->upsertTenant('gl_posting_profiles', ['company_id', 'module_code', 'posting_key'], $rows, $userId);
    }

    private function cashBank(int $companyId, ?int $userId): void
    {
        $rows = [
            ['company_id' => $companyId, 'site_id' => null, 'cash_bank_code' => 'CASH-HO', 'cash_bank_name' => 'Cash on Hand - HO', 'account_type' => 'cash', 'currency_code' => 'IDR', 'gl_account_no' => '1110', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => 1],
            ['company_id' => $companyId, 'site_id' => null, 'cash_bank_code' => 'BNK-HO', 'cash_bank_name' => 'Bank Account - HO', 'account_type' => 'bank', 'currency_code' => 'IDR', 'gl_account_no' => '1120', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => 1],
        ];
        $this->upsertTenant('cash_bank_accounts', ['company_id', 'cash_bank_code'], $rows, $userId);
    }

    private function upsertGlobal(string $table, string $key, array $rows, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) return;
        foreach ($rows as $row) {
            $row = $this->stamp($this->filter($table, $row), $userId);
            $existing = $db->table($table)->where($key, $row[$key] ?? null)->get(1)->getRowArray();
            if ($existing) {
                $db->table($table)->where('id', (int) $existing['id'])->update($this->filter($table, $row));
            } else {
                $db->table($table)->insert($this->filter($table, $row + ['created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]));
            }
        }
    }

    private function upsertTenant(string $table, array $keys, array $rows, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) return;
        foreach ($rows as $row) {
            $row = $this->stamp($this->filter($table, $row), $userId);
            $builder = $db->table($table);
            foreach ($keys as $key) {
                $builder->where($key, $row[$key] ?? null);
            }
            if ($db->fieldExists('deleted_at', $table)) {
                $builder->where('deleted_at', null);
            }
            $existing = $builder->get(1)->getRowArray();
            if ($existing) {
                $db->table($table)->where('id', (int) $existing['id'])->update($this->filter($table, $row));
            } else {
                $db->table($table)->insert($this->filter($table, $row + ['created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]));
            }
        }
    }

    private function stamp(array $row, ?int $userId): array
    {
        $row['updated_by'] = $userId;
        $row['updated_at'] = date('Y-m-d H:i:s');
        return $row;
    }

    private function filter(string $table, array $row): array
    {
        $db = Database::connect();
        foreach (array_keys($row) as $column) {
            if (! $db->fieldExists($column, $table)) {
                unset($row[$column]);
            }
        }
        return $row;
    }
}
