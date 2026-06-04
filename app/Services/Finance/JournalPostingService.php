<?php

namespace App\Services\Finance;

use App\Services\Support\TenantScope;
use Config\Database;
use RuntimeException;

final class JournalPostingService
{
    /**
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $lines
     */
    public function post(array $header, array $lines, ?int $userId = null): int
    {
        if ($lines === []) {
            throw new RuntimeException('Journal requires at least one line.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $normalizedLines = [];

        foreach ($lines as $index => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new RuntimeException('Journal debit and credit cannot be negative.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException('A journal line cannot contain both debit and credit.');
            }

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $accountCode = trim((string) ($line['account_code'] ?? ''));
            if ($accountCode === '') {
                throw new RuntimeException('Journal account code is required.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $normalizedLines[] = [
                'line_no' => (int) ($line['line_no'] ?? ($index + 1)),
                'account_code' => $accountCode,
                'account_name' => $line['account_name'] ?? null,
                'description' => $line['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if ($normalizedLines === []) {
            throw new RuntimeException('Journal requires at least one valid line.');
        }

        if (round($totalDebit, 4) !== round($totalCredit, 4)) {
            throw new RuntimeException('Journal is not balanced.');
        }

        $scope = new TenantScope();
        $companyId = (int) ($header['company_id'] ?? $scope->requireCompany());
        $siteId = $header['site_id'] ?? $scope->optionalSite();

        $db = Database::connect();
        $db->transStart();

        $db->table('journal_entries')->insert([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'journal_no' => trim((string) ($header['journal_no'] ?? $this->generateNo('JE'))),
            'journal_date' => (string) ($header['journal_date'] ?? date('Y-m-d')),
            'source_type' => $header['source_type'] ?? null,
            'source_id' => $header['source_id'] ?? null,
            'description' => $header['description'] ?? null,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'status' => $header['status'] ?? 'posted',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $journalId = (int) $db->insertID();

        foreach ($normalizedLines as $line) {
            $line['journal_entry_id'] = $journalId;
            $db->table('journal_entry_lines')->insert($line);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('Failed to post journal.');
        }

        return $journalId;
    }

    private function generateNo(string $prefix): string
    {
        return $prefix . '-' . date('Ymd-His');
    }
}
