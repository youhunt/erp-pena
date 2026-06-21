<?php

namespace App\Services\Finance;

use App\Models\CashBankAccountModel;
use App\Models\CashBankEntryModel;
use App\Services\AuditLogService;
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

        if ($companyId < 1) {
            throw new RuntimeException('Company is required.');
        }
        if (! in_array($entryType, ['cash_in', 'cash_out', 'bank_in', 'bank_out'], true)) {
            throw new RuntimeException('Invalid cash/bank entry type.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }
        (new PeriodCloseService())->assertOpen('cashbank', $companyId, (string) ($data['entry_date'] ?? date('Y-m-d')), ! empty($data['site_id']) ? (int) $data['site_id'] : null);

        $accountModel = new CashBankAccountModel();
        $accountQuery = $accountModel
            ->where('company_id', $companyId)
            ->where('cash_bank_code', (string) ($data['cash_bank_code'] ?? ''))
            ->where('is_active', 1);

        if (! empty($data['site_id'])) {
            $accountQuery->groupStart()
                ->where('site_id', (int) $data['site_id'])
                ->orWhere('site_id', null)
                ->groupEnd()
                ->orderBy('site_id', 'DESC');
        } else {
            $accountQuery->where('site_id', null);
        }

        $account = $accountQuery->first();

        if ($account === null) {
            throw new RuntimeException('Cash/Bank account not found or inactive.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $isIn = str_ends_with($entryType, '_in');
            $newBalance = (float) $account['current_balance'] + ($isIn ? $amount : -$amount);
            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient cash/bank balance.');
            }

            $accountModel->update((int) $account['id'], ['current_balance' => $newBalance]);

            $entryModel = new CashBankEntryModel();
            $entryModel->insert([
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'cash_bank_account_id' => (int) $account['id'],
                'entry_no' => trim((string) ($data['entry_no'] ?? 'CB-' . date('Ymd-His'))),
                'entry_date' => $data['entry_date'] ?? date('Y-m-d'),
                'entry_type' => $entryType,
                'cash_bank_code' => $account['cash_bank_code'],
                'currency_code' => $data['currency_code'] ?? $account['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => $data['counter_account_no'] ?? null,
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
                'entry_date' => $data['entry_date'] ?? date('Y-m-d'),
                'entry_type' => $entryType,
                'amount' => $amount,
                'cash_bank_gl_account_no' => $cashBankGlAccount !== '' ? $cashBankGlAccount : (new PostingProfileService())->account($companyId, 'cashbank', 'cash_bank', '1100'),
                'cash_bank_name' => $account['cash_bank_name'] ?? $account['cash_bank_code'],
                'currency_code' => $data['currency_code'] ?? $account['currency_code'] ?? 'IDR',
            ], $entryId, $userId);

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
                'record_code' => $data['entry_no'] ?? null,
                'description' => 'Cash/Bank entry posted.',
                'new_values' => ['entry' => $data, 'new_balance' => $newBalance, 'gl_entry_id' => $glEntryId],
            ]);

            return $entryId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function postGl(array $data, int $entryId, ?int $userId): ?int
    {
        $cashBankAccount = trim((string) ($data['cash_bank_gl_account_no'] ?? ''));
        $counterAccount = trim((string) ($data['counter_account_no'] ?? ''));
        if ($cashBankAccount === '' || $counterAccount === '') {
            return null;
        }

        $amount = round((float) $data['amount'], 2);
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
            'currency_code' => $data['currency_code'] ?? 'IDR',
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
