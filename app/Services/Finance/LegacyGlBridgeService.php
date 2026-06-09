<?php

namespace App\Services\Finance;

use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class LegacyGlBridgeService
{
    public function syncCoaToChartAccounts(?int $companyId = null): array
    {
        $db = Database::connect();
        if (! $db->tableExists('coa') || ! $db->tableExists('chart_accounts')) {
            throw new RuntimeException('Legacy coa or modern chart_accounts table is missing.');
        }

        $companyId = $companyId ?? (new TenantContext(session()))->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required to sync COA.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $rows = $db->table('coa')->where('active', 1)->get()->getResultArray();

        foreach ($rows as $row) {
            $accountNo = trim((string) ($row['code'] ?? ''));
            if ($accountNo === '') {
                $skipped++;
                continue;
            }

            $payload = [
                'company_id' => $companyId,
                'account_no' => $accountNo,
                'account_name' => trim((string) ($row['remarks'] ?? '')) ?: ('Account ' . $accountNo),
                'account_type' => $this->inferAccountType($accountNo),
                'normal_balance' => $this->inferNormalBalance($accountNo),
                'parent_account_no' => null,
                'is_postable' => 1,
                'is_active' => 1,
                'updated_at' => $now,
            ];

            $existing = $db->table('chart_accounts')
                ->where('company_id', $companyId)
                ->where('account_no', $accountNo)
                ->get()->getRowArray();

            if ($existing === null) {
                $payload['created_at'] = $now;
                $db->table('chart_accounts')->insert($payload);
                $created++;
            } else {
                $db->table('chart_accounts')->where('id', $existing['id'])->update($payload);
                $updated++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    public function syncGlBookToModern(?int $companyId = null): array
    {
        $db = Database::connect();
        if (! $db->tableExists('glbook') || ! $db->tableExists('gl_books')) {
            throw new RuntimeException('Legacy glbook or modern gl_books table is missing.');
        }

        $companyId = $companyId ?? (new TenantContext(session()))->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required to sync GL Book.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $rows = $db->table('glbook')->get()->getResultArray();

        foreach ($rows as $row) {
            $bookCode = trim((string) ($row['booktype'] ?? ''));
            if ($bookCode === '') {
                $skipped++;
                continue;
            }

            $payload = [
                'company_id' => $companyId,
                'book_code' => $bookCode,
                'book_name' => trim((string) ($row['description'] ?? '')) ?: ('Ledger Book ' . $bookCode),
                'currency_code' => trim((string) ($row['currency'] ?? '')) ?: 'IDR',
                'is_default' => $bookCode === '1' || strtoupper($bookCode) === 'MAIN' ? 1 : 0,
                'is_active' => 1,
                'updated_at' => $now,
            ];

            $existing = $db->table('gl_books')
                ->where('company_id', $companyId)
                ->where('book_code', $bookCode)
                ->get()->getRowArray();

            if ($existing === null) {
                $payload['created_at'] = $now;
                $db->table('gl_books')->insert($payload);
                $created++;
            } else {
                $db->table('gl_books')->where('id', $existing['id'])->update($payload);
                $updated++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    private function inferAccountType(string $accountNo): string
    {
        return match (substr($accountNo, 0, 1)) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4', '7' => 'revenue',
            default => 'expense',
        };
    }

    private function inferNormalBalance(string $accountNo): string
    {
        return in_array(substr($accountNo, 0, 1), ['2', '3', '4', '7'], true) ? 'credit' : 'debit';
    }
}
