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
                'manual_expense' => '6200',
            ],
            'ar' => [
                'receivable' => '1200',
                'sales_revenue' => '4100',
            ],
            'cashbank' => [
                'cash_bank' => '1100',
            ],
        ];
    }

    public static function label(string $moduleCode, string $postingKey): string
    {
        return match ($moduleCode . '.' . $postingKey) {
            'ap.payable' => 'Accounts Payable',
            'ap.manual_expense' => 'Manual A/P Expense',
            'ar.receivable' => 'Accounts Receivable',
            'ar.sales_revenue' => 'Sales Revenue',
            'cashbank.cash_bank' => 'Cash and Bank',
            default => ucwords(str_replace('_', ' ', $postingKey)),
        };
    }
}
