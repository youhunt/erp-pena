<?php

namespace App\Services\Finance;

use App\Models\CashBankAccountModel;
use App\Models\CashBankEntryModel;
use App\Services\AuditLogService;
use App\Services\Support\CashBankIntegrityGuard;
use App\Services\Support\PostingIntegrityGuard;
use Config\Database;
use RuntimeException;
use Throwable;

class CashBankService
{
    /**
     * @param array<string, mixed> $data
     */
    public function post(array $data, ?int $userId = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $entryType = (string) ($data['entry_type'] ?? '');
        $entryNo = trim((string) ($data['entry_no'] ?? ''));
        $counterAccountNo = trim((string) ($data['counter_account_no'] ?? ''));

        if ($companyId < 1) {
            throw new RuntimeException('Company is required.');
        }
        $entryDate = (string) ($data['entry_date'] ?? date('Y-m-d'));
        $cashBankCode = trim((string) ($data['cash_bank_code'] ?? ''));
        $integrityGuard = new CashBankIntegrityGuard();
        $integrityGuard->assertEntryPayload($entryNo, $entryType, $amount, $counterAccountNo);
        $integrityGuard->assertEntryContext($cashBankCode, $entryDate);
        (new PeriodCloseService())->assertOpen('cashbank', $companyId, $entryDate, ! empty($data['site_id']) ? (int) $data['site_id'] : null);

        $db = Database::connect();
        $db->transBegin();

        try {
            $account = $this->lockedAccount(
                $companyId,
                ! empty($data['site_id']) ? (int) $data['site_id'] : null,
                $cashBankCode
            );
            if ($account === null) {
                throw new RuntimeException('Cash/Bank account not found or inactive.');
            }

            $expectedAccountType = str_starts_with($entryType, 'cash_') ? 'cash' : 'bank';
            if ((string) ($account['account_type'] ?? '') !== $expectedAccountType) {
                throw new RuntimeException('Cash/Bank entry type does not match the selected account type.');
            }

            $currencyCode = strtoupper(trim((string) ($account['currency_code'] ?? 'IDR')));
            (new CashBankIntegrityGuard())->assertCurrency($currencyCode, (string) ($data['currency_code'] ?? ''));
            $this->assertUniqueEntryNo($companyId, $entryNo);

            $baseCurrency = $this->baseCurrency($companyId);
            $rateType = trim((string) ($data['rate_type'] ?? 'BI')) ?: 'BI';
            $exchangeRate = $this->resolveExchangeRate($companyId, $currencyCode, $baseCurrency, $entryDate, $rateType, (float) ($data['exchange_rate'] ?? 0));
            $baseAmount = round($amount * $exchangeRate, 2);

            $isIn = str_ends_with($entryType, '_in');
            $newBalance = (float) $account['current_balance'] + ($isIn ? $amount : -$amount);
            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient cash/bank balance.');
            }

            $accountModel = new CashBankAccountModel();
            $accountModel->update((int) $account['id'], ['current_balance' => $newBalance]);

            $entryModel = new CashBankEntryModel();
            $entryModel->insert([
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'cash_bank_account_id' => (int) $account['id'],
                'entry_no' => $entryNo,
                'entry_date' => $entryDate,
                'entry_type' => $entryType,
                'cash_bank_code' => $account['cash_bank_code'],
                'currency_code' => $currencyCode,
                'rate_type' => $rateType,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_amount' => $baseAmount,
                'amount' => $amount,
                'counter_account_no' => $counterAccountNo,
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $entryId = (int) $entryModel->getInsertID();
            if ($entryId < 1) {
                throw new RuntimeException('Failed to create cash/bank entry.');
            }

            $cashBankGlAccount = trim((string) ($account['gl_account_no'] ?? ''));
            $glEntryId = $this->postGl($data + [
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'entry_no' => $data['entry_no'] ?? null,
                'entry_date' => $entryDate,
                'entry_type' => $entryType,
                'amount' => $amount,
                'base_amount' => $baseAmount,
                'cash_bank_gl_account_no' => $cashBankGlAccount !== '' ? $cashBankGlAccount : (new PostingProfileService())->account($companyId, 'cashbank', 'cash_bank', '1100'),
                'cash_bank_name' => $account['cash_bank_name'] ?? $account['cash_bank_code'],
                'currency_code' => $currencyCode,
                'base_currency' => $baseCurrency,
            ], $entryId, $userId);
            (new PostingIntegrityGuard())->assertGlEntryForAmount($baseAmount, $glEntryId, 'Cash/Bank entry');

            if ($glEntryId !== null) {
                $entryModel->update($entryId, ['gl_entry_id' => $glEntryId]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post cash/bank entry.');
            }

            $db->transCommit();

            (new AuditLogService())->log('cashbank', 'cashbank.post', [
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'cash_bank_entries',
                'record_id' => $entryId,
                'record_code' => $entryNo,
                'description' => 'Cash/Bank entry posted.',
                'new_values' => ['entry' => $data, 'new_balance' => $newBalance, 'exchange_rate' => $exchangeRate, 'base_amount' => $baseAmount, 'gl_entry_id' => $glEntryId],
            ]);

            return $entryId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function lockedAccount(int $companyId, ?int $siteId, string $cashBankCode): ?array
    {
        $db = Database::connect();
        $builder = $db->table('cash_bank_accounts')
            ->where('company_id', $companyId)
            ->where('cash_bank_code', $cashBankCode)
            ->where('is_active', 1);

        if ($siteId !== null) {
            $builder->groupStart()
                ->where('site_id', $siteId)
                ->orWhere('site_id', null)
                ->groupEnd()
                ->orderBy('site_id', 'DESC');
        } else {
            $builder->where('site_id', null);
        }

        $sql = $builder->limit(1)->getCompiledSelect();

        return $db->query($sql . ' FOR UPDATE')->getRowArray();
    }

    private function assertUniqueEntryNo(int $companyId, string $entryNo): void
    {
        $exists = Database::connect()->table('cash_bank_entries')
            ->where('company_id', $companyId)
            ->where('entry_no', $entryNo)
            ->countAllResults() > 0;

        if ($exists) {
            throw new RuntimeException('Cash/Bank entry number already exists: ' . $entryNo . '.');
        }
    }

    private function baseCurrency(int $companyId): string
    {
        $db = Database::connect();
        if ($db->tableExists('companies') && $db->fieldExists('base_currency', 'companies')) {
            $row = $db->table('companies')->select('base_currency')->where('id', $companyId)->get(1)->getRowArray();
            $base = strtoupper(trim((string) ($row['base_currency'] ?? '')));
            if ($base !== '') {
                return $base;
            }
        }

        return 'IDR';
    }

    private function resolveExchangeRate(int $companyId, string $fromCurrency, string $toCurrency, string $entryDate, string $rateType, float $manualRate): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }
        if ($manualRate > 0) {
            return $manualRate;
        }

        $db = Database::connect();
        if (! $db->tableExists('currency_rates')) {
            throw new RuntimeException('Currency rate table is not available.');
        }

        $row = $db->table('currency_rates')
            ->where('company_id', $companyId)
            ->where('rate_type', $rateType)
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('rate_date <=', $entryDate)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->orderBy('rate_date', 'DESC')
            ->get(1)
            ->getRowArray();

        if ($row === null) {
            $row = $db->table('currency_rates')
                ->groupStart()->where('company_id', null)->orWhere('company_id', $companyId)->groupEnd()
                ->where('rate_type', $rateType)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->where('rate_date <=', $entryDate)
                ->where('is_active', 1)
                ->where('deleted_at', null)
                ->orderBy('company_id', 'DESC')
                ->orderBy('rate_date', 'DESC')
                ->get(1)
                ->getRowArray();
        }

        $rate = (float) ($row['amount'] ?? 0);
        if ($rate <= 0) {
            throw new RuntimeException('Currency rate not found for ' . $fromCurrency . ' to ' . $toCurrency . ' on or before ' . $entryDate . '. Please fill Rate Master first.');
        }

        return $rate;
    }

    private function postGl(array $data, int $entryId, ?int $userId): ?int
    {
        $cashBankAccount = trim((string) ($data['cash_bank_gl_account_no'] ?? ''));
        $counterAccount = trim((string) ($data['counter_account_no'] ?? ''));
        $amount = round((float) ($data['base_amount'] ?? $data['amount']), 2);
        $isIn = str_ends_with((string) $data['entry_type'], '_in');

        return (new GeneralLedgerService())->post([
            'company_id' => $data['company_id'],
            'site_id' => $data['site_id'] ?? null,
            'journal_no' => 'GL-' . ($data['entry_no'] ?? date('Ymd-His')),
            'journal_date' => $data['entry_date'] ?? date('Y-m-d'),
            'source_module' => 'cashbank',
            'source_type' => $data['entry_type'],
            'source_id' => $entryId,
            'source_no' => $data['entry_no'] ?? null,
            'description' => $data['description'] ?? 'Cash/Bank posting',
            'currency_code' => $data['base_currency'] ?? 'IDR',
        ], [
            [
                'account_no' => $isIn ? $cashBankAccount : $counterAccount,
                'description' => $data['cash_bank_name'] ?? 'Cash/Bank',
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_no' => $isIn ? $counterAccount : $cashBankAccount,
                'description' => $data['description'] ?? 'Counter account',
                'debit' => 0,
                'credit' => $amount,
            ],
        ], $userId);
    }
}
