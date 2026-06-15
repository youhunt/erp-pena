<?php

namespace App\Services\Finance;

use App\Models\ChartAccountModel;
use App\Models\GlEntryLineModel;
use App\Models\GlEntryModel;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

class GeneralLedgerService
{
    /**
     * @param array<string, mixed>        $header
     * @param list<array<string, mixed>>  $lines
     */
    public function post(array $header, array $lines, ?int $userId = null): int
    {
        $companyId = (int) ($header['company_id'] ?? 0);
        if ($companyId < 1) {
            throw new RuntimeException('Company is required for GL entry.');
        }
        (new PeriodCloseService())->assertOpen('gl', $companyId, (string) ($header['journal_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);

        $lines = $this->normalizeLines($companyId, $lines);
        if (count($lines) < 2) {
            throw new RuntimeException('GL entry requires at least two valid lines.');
        }

        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);
        if (abs($totalDebit - $totalCredit) > 0.009) {
            throw new RuntimeException('GL entry is not balanced. Debit: ' . $totalDebit . ', Credit: ' . $totalCredit);
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $entryModel = new GlEntryModel();
            $lineModel = new GlEntryLineModel();
            $journalDate = (string) ($header['journal_date'] ?? date('Y-m-d'));
            $entryModel->insert([
                'company_id' => $companyId,
                'site_id' => $header['site_id'] ?? null,
                'gl_book_id' => $header['gl_book_id'] ?? null,
                'journal_no' => trim((string) ($header['journal_no'] ?? 'JE-' . date('Ymd-His'))),
                'journal_date' => $journalDate,
                'period' => substr($journalDate, 0, 7),
                'source_module' => $header['source_module'] ?? 'manual',
                'source_type' => $header['source_type'] ?? 'manual_journal',
                'source_id' => $header['source_id'] ?? null,
                'source_no' => $header['source_no'] ?? null,
                'description' => $header['description'] ?? null,
                'currency_code' => $header['currency_code'] ?? 'IDR',
                'exchange_rate' => $header['exchange_rate'] ?? 1,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $entryId = (int) $entryModel->getInsertID();
            if ($entryId < 1) {
                throw new RuntimeException('Failed to create GL entry header.');
            }

            $lineNo = 10;
            foreach ($lines as $line) {
                $lineModel->insert($line + [
                    'gl_entry_id' => $entryId,
                    'company_id' => $companyId,
                    'site_id' => $header['site_id'] ?? null,
                    'line_no' => $lineNo,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $lineNo += 10;
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post GL entry.');
            }

            $db->transCommit();

            (new AuditLogService())->log('finance.gl', 'gl.post', [
                'company_id' => $companyId,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'gl_entries',
                'record_id' => $entryId,
                'record_code' => $header['journal_no'] ?? null,
                'description' => 'GL journal posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit],
            ]);

            return $entryId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(int $companyId, array $lines): array
    {
        $accountModel = new ChartAccountModel();
        $normalized = [];

        foreach ($lines as $line) {
            $accountNo = trim((string) ($line['account_no'] ?? ''));
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($accountNo === '' || ($debit <= 0 && $credit <= 0)) {
                continue;
            }

            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException('One GL line cannot have both debit and credit.');
            }

            $account = $accountModel
                ->where('company_id', $companyId)
                ->where('account_no', $accountNo)
                ->where('is_active', 1)
                ->first();

            if ($account === null) {
                throw new RuntimeException('Account not found or inactive: ' . $accountNo);
            }

            if ((int) ($account['is_postable'] ?? 1) !== 1) {
                throw new RuntimeException('Account is not postable: ' . $accountNo);
            }

            $normalized[] = [
                'account_id' => (int) $account['id'],
                'account_no' => $accountNo,
                'account_name' => $account['account_name'] ?? $line['account_name'] ?? null,
                'description' => trim((string) ($line['description'] ?? '')),
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return $normalized;
    }
}
