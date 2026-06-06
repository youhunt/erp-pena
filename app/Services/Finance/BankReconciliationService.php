<?php

namespace App\Services\Finance;

use App\Models\BankReconciliationModel;
use App\Models\CashBankAccountModel;
use App\Models\CashBankEntryModel;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

class BankReconciliationService
{
    public function post(array $data, array $entryIds, ?int $userId = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $cashBankCode = trim((string) ($data['cash_bank_code'] ?? ''));
        $statementDate = (string) ($data['statement_date'] ?? '');
        $entryIds = array_values(array_unique(array_map('intval', $entryIds)));

        if ($companyId < 1 || $cashBankCode === '' || $statementDate === '') {
            throw new RuntimeException('Company, bank account, and statement date are required.');
        }
        if ($entryIds === []) {
            throw new RuntimeException('Choose at least one bank entry to reconcile.');
        }

        $account = (new CashBankAccountModel())
            ->where('company_id', $companyId)
            ->where('cash_bank_code', $cashBankCode)
            ->where('account_type', 'bank')
            ->where('is_active', 1)
            ->first();
        if ($account === null) {
            throw new RuntimeException('Bank account not found or inactive.');
        }

        $entryModel = new CashBankEntryModel();
        $entries = $entryModel
            ->where('company_id', $companyId)
            ->where('cash_bank_account_id', (int) $account['id'])
            ->whereIn('id', $entryIds)
            ->where('bank_reconciliation_id', null)
            ->where('deleted_at', null)
            ->findAll();
        if (count($entries) !== count($entryIds)) {
            throw new RuntimeException('Some selected bank entries are not available for reconciliation.');
        }

        $reconciledAmount = $this->signedAmount($entries);
        $bookBalance = round((float) ($account['current_balance'] ?? 0), 2);
        $statementBalance = round((float) ($data['statement_balance'] ?? 0), 2);
        $difference = round($statementBalance - $bookBalance, 2);

        $db = Database::connect();
        $db->transBegin();

        try {
            $reconciliationModel = new BankReconciliationModel();
            $reconciliationId = (int) $reconciliationModel->insert([
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'cash_bank_account_id' => (int) $account['id'],
                'cash_bank_code' => $cashBankCode,
                'reconcile_no' => trim((string) ($data['reconcile_no'] ?? 'BR-' . date('Ymd-His'))),
                'statement_date' => $statementDate,
                'statement_ref' => $data['statement_ref'] ?? null,
                'book_balance' => $bookBalance,
                'statement_balance' => $statementBalance,
                'reconciled_amount' => $reconciledAmount,
                'difference_amount' => $difference,
                'entry_count' => count($entries),
                'status' => 'posted',
                'notes' => $data['notes'] ?? null,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ], true);

            foreach ($entries as $entry) {
                $entryModel->update((int) $entry['id'], [
                    'bank_reconciliation_id' => $reconciliationId,
                    'reconciled_at' => date('Y-m-d H:i:s'),
                    'reconciled_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post bank reconciliation.');
            }

            $db->transCommit();

            (new AuditLogService())->log('cashbank', 'bank_reconciliation.post', [
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'bank_reconciliations',
                'record_id' => $reconciliationId,
                'record_code' => $data['reconcile_no'] ?? null,
                'description' => 'Bank reconciliation posted.',
                'new_values' => ['header' => $data, 'entry_ids' => $entryIds, 'reconciled_amount' => $reconciledAmount],
            ]);

            return $reconciliationId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function signedAmount(array $entries): float
    {
        $amount = 0.0;
        foreach ($entries as $entry) {
            $entryAmount = round((float) ($entry['amount'] ?? 0), 2);
            $amount += str_ends_with((string) ($entry['entry_type'] ?? ''), '_in') ? $entryAmount : -$entryAmount;
        }

        return round($amount, 2);
    }
}
