<?php

namespace App\Services\Finance;

use App\Models\BankReconciliationModel;
use App\Models\CashBankEntryModel;
use App\Services\AuditLogService;
use App\Services\Support\CashBankIntegrityGuard;
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
        $statementImportId = (int) ($data['bank_statement_import_id'] ?? 0);
        $reconcileNo = trim((string) ($data['reconcile_no'] ?? ''));
        $siteId = ! empty($data['site_id']) ? (int) $data['site_id'] : null;
        $entryIds = array_values(array_unique(array_map('intval', $entryIds)));

        if ($companyId < 1 || $cashBankCode === '' || $statementDate === '') {
            throw new RuntimeException('Company, bank account, and statement date are required.');
        }
        if ($entryIds === []) {
            throw new RuntimeException('Choose at least one bank entry to reconcile.');
        }
        (new CashBankIntegrityGuard())->assertReconciliationPayload($reconcileNo, $statementDate);

        $db = Database::connect();
        $db->transBegin();

        try {
            $account = $this->bankAccount($companyId, $siteId, $cashBankCode);
            if ($account === null) {
                throw new RuntimeException('Bank account not found or inactive.');
            }
            $this->assertUniqueReconcileNo($companyId, $reconcileNo);

            $statementBalance = round((float) ($data['statement_balance'] ?? 0), 2);
            $statementRef = $data['statement_ref'] ?? null;
            if ($statementImportId > 0) {
                $statementImport = $this->statementImport($statementImportId, $companyId, $siteId, (int) $account['id'], $cashBankCode);
                $statementDate = (string) $statementImport['statement_date'];
                $statementBalance = round((float) ($statementImport['closing_balance'] ?? 0), 2);
                $statementRef = $statementImport['statement_ref'] ?? null;
                $this->assertStatementMatched($statementImportId, $entryIds);
            }

            (new CashBankIntegrityGuard())->assertReconciliationPayload($reconcileNo, $statementDate);
            (new PeriodCloseService())->assertOpen('cashbank', $companyId, $statementDate, $siteId);

            $entries = $this->lockedEntries($companyId, $siteId, (int) $account['id'], $entryIds);
            if (count($entries) !== count($entryIds)) {
                throw new RuntimeException('Some selected bank entries are not posted, belong to another site, or are already reconciled.');
            }

            $reconciledAmount = $this->signedAmount($entries);
            $bookBalance = round((float) ($account['current_balance'] ?? 0), 2);
            $difference = round($statementBalance - $bookBalance, 2);
            (new CashBankIntegrityGuard())->assertBalancedReconciliation($difference);

            $entryModel = new CashBankEntryModel();
            $reconciliationModel = new BankReconciliationModel();
            $reconciliationModel->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'cash_bank_account_id' => (int) $account['id'],
                'bank_statement_import_id' => $statementImportId > 0 ? $statementImportId : null,
                'cash_bank_code' => $cashBankCode,
                'reconcile_no' => $reconcileNo,
                'statement_date' => $statementDate,
                'statement_ref' => $statementRef,
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
            ]);
            $reconciliationId = (int) $reconciliationModel->getInsertID();
            if ($reconciliationId < 1) {
                throw new RuntimeException('Failed to create bank reconciliation header.');
            }

            foreach ($entries as $entry) {
                $entryModel->update((int) $entry['id'], [
                    'bank_reconciliation_id' => $reconciliationId,
                    'reconciled_at' => date('Y-m-d H:i:s'),
                    'reconciled_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            if ($statementImportId > 0) {
                $db->table('bank_statement_imports')
                    ->where('id', $statementImportId)
                    ->where('company_id', $companyId)
                    ->update([
                        'status' => 'reconciled',
                        'updated_by' => $userId,
                        'updated_at' => date('Y-m-d H:i:s'),
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
                'record_code' => $reconcileNo,
                'description' => 'Bank reconciliation posted.',
                'new_values' => ['header' => $data, 'entry_ids' => $entryIds, 'reconciled_amount' => $reconciledAmount],
            ]);

            return $reconciliationId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function bankAccount(int $companyId, ?int $siteId, string $cashBankCode): ?array
    {
        $builder = Database::connect()->table('cash_bank_accounts')
            ->where('company_id', $companyId)
            ->where('cash_bank_code', $cashBankCode)
            ->where('account_type', 'bank')
            ->where('is_active', 1);

        if ($siteId !== null) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd()
                ->orderBy('site_id', 'DESC');
        }

        $sql = $builder->limit(1)->getCompiledSelect();

        return Database::connect()->query($sql . ' FOR UPDATE')->getRowArray();
    }

    private function statementImport(
        int $statementImportId,
        int $companyId,
        ?int $siteId,
        int $accountId,
        string $cashBankCode
    ): array {
        $builder = Database::connect()->table('bank_statement_imports')
            ->where('id', $statementImportId)
            ->where('company_id', $companyId)
            ->where('cash_bank_account_id', $accountId)
            ->where('cash_bank_code', $cashBankCode)
            ->where('deleted_at', null);
        if ($siteId !== null) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd();
        }

        $sql = $builder->limit(1)->getCompiledSelect();
        $statementImport = Database::connect()->query($sql . ' FOR UPDATE')->getRowArray();
        if ($statementImport === null) {
            throw new RuntimeException('Selected bank statement import does not match this bank account or active site.');
        }
        if (($statementImport['status'] ?? '') === 'reconciled') {
            throw new RuntimeException('Selected bank statement import is already reconciled.');
        }

        return $statementImport;
    }

    private function assertStatementMatched(int $statementImportId, array $entryIds): void
    {
        $db = Database::connect();
        $lineCount = (int) $db->table('bank_statement_lines')
            ->where('bank_statement_import_id', $statementImportId)
            ->where('deleted_at', null)
            ->countAllResults();
        $matchedRows = $db->table('bank_statement_lines')
            ->select('cash_bank_entry_id')
            ->where('bank_statement_import_id', $statementImportId)
            ->where('match_status', 'matched')
            ->where('cash_bank_entry_id IS NOT NULL', null, false)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();
        if ($lineCount < 1 || count($matchedRows) !== $lineCount) {
            throw new RuntimeException('All bank statement lines must be matched before reconciliation can be posted.');
        }

        $matchedEntryIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['cash_bank_entry_id'], $matchedRows)));
        if (count($matchedEntryIds) !== $lineCount) {
            throw new RuntimeException('Each bank statement line must be matched to a different bank entry.');
        }
        sort($matchedEntryIds);
        $selectedEntryIds = $entryIds;
        sort($selectedEntryIds);
        if ($selectedEntryIds !== $matchedEntryIds) {
            throw new RuntimeException('Selected bank entries must match the bank statement matched entries.');
        }
    }

    private function lockedEntries(int $companyId, ?int $siteId, int $accountId, array $entryIds): array
    {
        $builder = Database::connect()->table('cash_bank_entries')
            ->where('company_id', $companyId)
            ->where('cash_bank_account_id', $accountId)
            ->whereIn('id', $entryIds)
            ->whereIn('entry_type', ['bank_in', 'bank_out'])
            ->where('status', 'posted')
            ->where('bank_reconciliation_id', null)
            ->where('deleted_at', null);
        if ($siteId !== null) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd();
        }

        $sql = $builder->getCompiledSelect();

        return Database::connect()->query($sql . ' FOR UPDATE')->getResultArray();
    }

    private function assertUniqueReconcileNo(int $companyId, string $reconcileNo): void
    {
        if (Database::connect()->table('bank_reconciliations')
            ->where('company_id', $companyId)
            ->where('reconcile_no', $reconcileNo)
            ->countAllResults() > 0) {
            throw new RuntimeException('Bank reconciliation number already exists: ' . $reconcileNo . '.');
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
