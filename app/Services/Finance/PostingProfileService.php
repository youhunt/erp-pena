<?php

namespace App\Services\Finance;

use App\Models\GlPostingProfileModel;

class PostingProfileService
{
    public function account(int $companyId, string $moduleCode, string $postingKey, string $fallback): string
    {
        if ($companyId < 1) {
            return $fallback;
        }

        $profile = (new GlPostingProfileModel())
            ->where('company_id', $companyId)
            ->where('module_code', strtolower($moduleCode))
            ->where('posting_key', strtolower($postingKey))
            ->where('is_active', 1)
            ->first();

        $accountNo = trim((string) ($profile['account_no'] ?? ''));

        return $accountNo !== '' ? $accountNo : $fallback;
    }

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        return [
            'ap' => [
                'payable' => '2100',
                'grni' => '2300',
                'manual_expense' => '6200',
                'inventory' => '1300',
                'input_vat' => '1400',
            ],
            'ar' => [
                'receivable' => '1200',
                'sales_revenue' => '4100',
                'output_vat' => '2200',
            ],
            'sales' => [
                'cogs' => '5000',
                'inventory' => '1300',
            ],
            'inventory' => [
                'inventory' => '1300',
                'adjustment_gain' => '7000',
                'adjustment_loss' => '8000',
            ],
            'cashbank' => [
                // 1100 is a COA header/non-postable account. Use the postable bank account by default.
                'cash_bank' => '1120',
            ],
        ];
    }

    public static function label(string $moduleCode, string $postingKey): string
    {
        return match ($moduleCode . '.' . $postingKey) {
            'ap.payable' => 'Accounts Payable',
            'ap.grni' => 'Goods Received Not Invoiced',
            'ap.manual_expense' => 'Manual A/P Expense',
            'ap.inventory' => 'Purchased Inventory',
            'ap.input_vat' => 'Input VAT',
            'ar.receivable' => 'Accounts Receivable',
            'ar.sales_revenue' => 'Sales Revenue',
            'ar.output_vat' => 'Output VAT',
            'sales.cogs' => 'Cost of Goods Sold',
            'sales.inventory' => 'Inventory',
            'inventory.inventory' => 'Inventory',
            'inventory.adjustment_gain' => 'Inventory Adjustment Gain',
            'inventory.adjustment_loss' => 'Inventory Adjustment Loss',
            'cashbank.cash_bank' => 'Cash and Bank',
            default => ucwords(str_replace('_', ' ', $postingKey)),
        };
    }
}
