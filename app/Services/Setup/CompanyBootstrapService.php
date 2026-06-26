<?php

namespace App\Services\Setup;

use Config\Database;
use Throwable;

class CompanyBootstrapService
{
    public function bootstrapCompany(int $companyId, ?int $userId = null): void
    {
        if ($companyId < 1) {
            return;
        }

        $db = Database::connect();
        if (! $db->tableExists('companies')) {
            return;
        }

        $company = $db->table('companies')->where('id', $companyId)->get(1)->getRowArray();
        if ($company === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        try {
            $this->seedTransactionCodes($now, $userId);
            $this->seedCurrencies($now, $userId);
            $this->seedUoms($companyId, $now, $userId);
            $this->seedGlBook($companyId, $company, $now, $userId);
            $this->seedChartAccounts($companyId, $now, $userId);
            $this->seedPostingProfiles($companyId, $now, $userId);
            $this->seedCashBankAccounts($companyId, $company, $now, $userId);
        } catch (Throwable) {
            // Bootstrap must never block company/site creation.
            // Missing tables are reported by /system/core-health and can be fixed by migration.
        }
    }

    private function seedTransactionCodes(string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('transaction_codes')) {
            return;
        }

        $rows = [
            ['PO', 'Purchase Order', 'Purchase order document numbering'],
            ['PR', 'Purchase Receipt', 'Purchase receipt document numbering'],
            ['PI', 'Purchase Invoice', 'Purchase invoice document numbering'],
            ['APP', 'A/P Payment', 'Accounts payable payment document numbering'],
            ['SO', 'Sales Order', 'Sales order document numbering'],
            ['SD', 'Sales Delivery', 'Sales delivery document numbering'],
            ['SI', 'Sales Invoice', 'Sales invoice document numbering'],
            ['ARP', 'A/R Receipt', 'Accounts receivable receipt document numbering'],
            ['JV', 'Journal Voucher', 'General ledger journal voucher numbering'],
            ['CB', 'Cash/Bank Entry', 'Cash and bank entry document numbering'],
        ];

        foreach ($rows as [$code, $name, $description]) {
            $payload = $this->filterColumns('transaction_codes', [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'is_active' => 1,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            $existing = $db->table('transaction_codes')->where('code', $code)->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('transaction_codes')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }
            $payload['created_by'] = $userId;
            $payload['created_at'] = $now;
            $db->table('transaction_codes')->insert($this->filterColumns('transaction_codes', $payload));
        }
    }

    private function seedCurrencies(string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('currencies')) {
            return;
        }

        foreach ([['IDR', 'Indonesian Rupiah', 0], ['USD', 'US Dollar', 0.01]] as [$code, $name, $rounding]) {
            $payload = $this->filterColumns('currencies', [
                'company_id' => null,
                'code' => $code,
                'name' => $name,
                'rounding' => $rounding,
                'is_active' => 1,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            $existing = $db->table('currencies')->where('code', $code)->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('currencies')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }
            $payload['created_by'] = $userId;
            $payload['created_at'] = $now;
            $db->table('currencies')->insert($this->filterColumns('currencies', $payload));
        }
    }

    private function seedUoms(int $companyId, string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('uoms')) {
            return;
        }

        $rows = [
            ['PCS', 'Pieces', 'Default stock unit'],
            ['KG', 'Kilogram', 'Weight unit'],
            ['GR', 'Gram', 'Weight unit'],
            ['MTR', 'Meter', 'Length unit'],
            ['LTR', 'Liter', 'Volume unit'],
            ['BOX', 'Box', 'Packing unit'],
            ['SET', 'Set', 'Set unit'],
        ];

        foreach ($rows as [$code, $name, $description]) {
            $payload = $this->filterColumns('uoms', [
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'rate' => 1,
                'description' => $description,
                'is_active' => 1,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            $existing = $db->table('uoms')->where('company_id', $companyId)->where('code', $code)->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('uoms')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }
            $payload['created_by'] = $userId;
            $payload['created_at'] = $now;
            $db->table('uoms')->insert($this->filterColumns('uoms', $payload));
        }
    }

    private function seedGlBook(int $companyId, array $company, string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('gl_books')) {
            return;
        }

        $payload = $this->filterColumns('gl_books', [
            'company_id' => $companyId,
            'book_code' => 'MAIN',
            'book_name' => 'Main Ledger Book',
            'currency_code' => strtoupper((string) ($company['base_currency'] ?? 'IDR')) ?: 'IDR',
            'is_default' => 1,
            'is_active' => 1,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);
        $existing = $db->table('gl_books')->where('company_id', $companyId)->where('book_code', 'MAIN')->get(1)->getRowArray();
        if ($existing !== null) {
            $db->table('gl_books')->where('id', (int) $existing['id'])->update($payload);
            return;
        }
        $payload['created_by'] = $userId;
        $payload['created_at'] = $now;
        $db->table('gl_books')->insert($this->filterColumns('gl_books', $payload));
    }

    private function seedChartAccounts(int $companyId, string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('chart_accounts')) {
            return;
        }

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
            $payload = $this->filterColumns('chart_accounts', [
                'company_id' => $companyId,
                'account_no' => $no,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'parent_account_no' => $parent,
                'is_postable' => $postable,
                'is_active' => 1,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            $existing = $db->table('chart_accounts')->where('company_id', $companyId)->where('account_no', $no)->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('chart_accounts')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }
            $payload['created_by'] = $userId;
            $payload['created_at'] = $now;
            $db->table('chart_accounts')->insert($this->filterColumns('chart_accounts', $payload));
        }
    }

    private function seedPostingProfiles(int $companyId, string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('gl_posting_profiles')) {
            return;
        }

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
            ['cashbank', 'cash_bank', '1120', 'Default bank account'],
            ['pos', 'cash_receipt', '1110', 'Default POS cash receipt account'],
            ['pos', 'sales_revenue', '4100', 'Default POS sales revenue account'],
            ['pos', 'output_vat', '2200', 'Default POS output VAT account'],
            ['pos', 'cogs', '5000', 'Default POS COGS account'],
            ['pos', 'inventory', '1300', 'Default POS inventory account'],
        ];

        foreach ($profiles as [$module, $key, $accountNo, $description]) {
            $payload = $this->filterColumns('gl_posting_profiles', [
                'company_id' => $companyId,
                'module_code' => $module,
                'posting_key' => $key,
                'account_no' => $accountNo,
                'description' => $description,
                'is_active' => 1,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            $existing = $db->table('gl_posting_profiles')
                ->where('company_id', $companyId)
                ->where('module_code', $module)
                ->where('posting_key', $key)
                ->get(1)
                ->getRowArray();

            if ($existing !== null) {
                $db->table('gl_posting_profiles')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }
            $payload['created_by'] = $userId;
            $payload['created_at'] = $now;
            $db->table('gl_posting_profiles')->insert($this->filterColumns('gl_posting_profiles', $payload));
        }
    }

    private function seedCashBankAccounts(int $companyId, array $company, string $now, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('cash_bank_accounts')) {
            return;
        }

        $baseCurrency = strtoupper((string) ($company['base_currency'] ?? 'IDR')) ?: 'IDR';
        $rows = [
            ['CASH-HO', 'Cash on Hand - HO', 'cash', '1110'],
            ['BNK-HO', 'Bank Account - HO', 'bank', '1120'],
        ];

        foreach ($rows as [$code, $name, $type, $accountNo]) {
            $payload = $this->filterColumns('cash_bank_accounts', [
                'company_id' => $companyId,
                'site_id' => null,
                'cash_bank_code' => $code,
                'cash_bank_name' => $name,
                'account_type' => $type,
                'currency_code' => $baseCurrency,
                'gl_account_no' => $accountNo,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => 1,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            $existing = $db->table('cash_bank_accounts')
                ->where('company_id', $companyId)
                ->where('cash_bank_code', $code)
                ->get(1)
                ->getRowArray();
            if ($existing !== null) {
                $db->table('cash_bank_accounts')->where('id', (int) $existing['id'])->update($payload);
                continue;
            }
            $payload['created_by'] = $userId;
            $payload['created_at'] = $now;
            $db->table('cash_bank_accounts')->insert($this->filterColumns('cash_bank_accounts', $payload));
        }
    }

    /** @param array<string, mixed> $payload */
    private function filterColumns(string $table, array $payload): array
    {
        $db = Database::connect();
        foreach (array_keys($payload) as $column) {
            if (! $db->fieldExists($column, $table)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }
}
