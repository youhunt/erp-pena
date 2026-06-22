<?php

namespace App\Services\Finance;

use App\Libraries\XlsxSheetReader;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;

class BankStatementImportService
{
    /**
     * @param array<string, mixed> $data
     */
    public function importXlsx(string $path, string $sourceFilename, array $data, ?int $userId = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $siteId = ! empty($data['site_id']) ? (int) $data['site_id'] : null;
        $cashBankCode = trim((string) ($data['cash_bank_code'] ?? ''));
        $statementDate = $this->parseDate((string) ($data['statement_date'] ?? ''));

        if ($companyId < 1 || $cashBankCode === '' || $statementDate === null) {
            throw new RuntimeException('Company, bank account, and statement date are required.');
        }

        $db = Database::connect();
        $account = $this->bankAccount($companyId, $siteId, $cashBankCode);
        if ($account === null) {
            throw new RuntimeException('Selected bank account was not found.');
        }

        $rows = (new XlsxSheetReader())->readFirstSheet($path);
        [$headers, $body] = $this->splitHeaderRows($rows);
        $map = $this->headerMap($headers);
        $lines = $this->statementLines($body, $headers, $map, [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'cash_bank_account_id' => (int) $account['id'],
            'cash_bank_code' => $cashBankCode,
            'statement_date' => $statementDate,
            'currency_code' => (string) ($account['currency_code'] ?? 'IDR'),
        ]);

        if ($lines === []) {
            throw new RuntimeException('No valid bank statement lines were found in the uploaded Excel file.');
        }

        $debitTotal = array_sum(array_column($lines, 'debit_amount'));
        $creditTotal = array_sum(array_column($lines, 'credit_amount'));
        $netAmount = array_sum(array_column($lines, 'signed_amount'));
        $closingBalance = (float) ($data['closing_balance'] ?? 0);
        if ($closingBalance == 0.0) {
            $balances = array_values(array_filter(array_column($lines, 'balance_amount'), static fn ($value): bool => (float) $value != 0.0));
            $closingBalance = $balances !== [] ? (float) end($balances) : 0.0;
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $db->table('bank_statement_imports')->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'cash_bank_account_id' => (int) $account['id'],
                'cash_bank_code' => $cashBankCode,
                'statement_ref' => trim((string) ($data['statement_ref'] ?? '')) ?: null,
                'statement_date' => $statementDate,
                'source_filename' => $sourceFilename,
                'opening_balance' => (float) ($data['opening_balance'] ?? 0),
                'closing_balance' => $closingBalance,
                'debit_total' => round((float) $debitTotal, 2),
                'credit_total' => round((float) $creditTotal, 2),
                'net_amount' => round((float) $netAmount, 2),
                'line_count' => count($lines),
                'matched_count' => 0,
                'status' => 'imported',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'imported_at' => $now,
                'imported_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $importId = (int) $db->insertID();
            foreach ($lines as &$line) {
                $line['bank_statement_import_id'] = $importId;
                $line['created_at'] = $now;
                $line['updated_at'] = $now;
            }
            unset($line);

            $db->table('bank_statement_lines')->insertBatch($lines);
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('Bank statement import transaction failed. No data was saved.');
        }

        $db->transCommit();

        (new AuditLogService())->log('cash_bank.bank_statement', 'bank_statement.import', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'table_name' => 'bank_statement_imports',
            'record_id' => $importId,
            'record_code' => $cashBankCode,
            'description' => 'Bank statement Excel imported.',
            'new_values' => [
                'filename' => $sourceFilename,
                'line_count' => count($lines),
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'net_amount' => $netAmount,
            ],
        ]);

        return $importId;
    }

    /**
     * @return array{matched:int, skipped:int}
     */
    public function autoMatch(int $importId, int $companyId, ?int $siteId = null, ?int $userId = null): array
    {
        $db = Database::connect();
        $matched = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $importBuilder = $db->table('bank_statement_imports')
                ->where('id', $importId)
                ->where('company_id', $companyId)
                ->where('deleted_at', null);
            if ($siteId !== null) {
                $importBuilder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd();
            }
            $importSql = $importBuilder->limit(1)->getCompiledSelect();
            $import = $db->query($importSql . ' FOR UPDATE')->getRowArray();
            if ($import === null) {
                throw new RuntimeException('Bank statement import was not found in the active company/site.');
            }
            if (($import['status'] ?? '') === 'reconciled') {
                throw new RuntimeException('A reconciled bank statement cannot be matched again.');
            }

            $lineBuilder = $db->table('bank_statement_lines')
                ->where('bank_statement_import_id', $importId)
                ->where('match_status', 'unmatched')
                ->where('deleted_at', null)
                ->orderBy('line_no', 'ASC');
            $lines = $db->query($lineBuilder->getCompiledSelect() . ' FOR UPDATE')->getResultArray();

            foreach ($lines as $line) {
                $entry = $this->matchEntry($line, $companyId, $siteId);
                if ($entry === null) {
                    $skipped++;
                    continue;
                }

                $db->table('bank_statement_lines')->where('id', $line['id'])->update([
                    'match_status' => 'matched',
                    'cash_bank_entry_id' => (int) $entry['id'],
                    'updated_at' => $now,
                ]);
                $matched++;
            }

            $totalMatched = (int) $db->table('bank_statement_lines')
                ->where('bank_statement_import_id', $importId)
                ->where('match_status', 'matched')
                ->countAllResults();
            $lineCount = (int) ($import['line_count'] ?? 0);
            $status = $totalMatched < 1 ? 'imported' : ($totalMatched >= $lineCount ? 'matched' : 'partial_matched');

            $db->table('bank_statement_imports')->where('id', $importId)->update([
                'matched_count' => $totalMatched,
                'status' => $status,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Bank statement matching transaction failed.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }

        (new AuditLogService())->log('cash_bank.bank_statement', 'bank_statement.auto_match', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'table_name' => 'bank_statement_imports',
            'record_id' => $importId,
            'record_code' => $import['cash_bank_code'] ?? null,
            'description' => 'Bank statement lines auto matched to cash bank entries.',
            'new_values' => ['matched' => $matched, 'skipped' => $skipped],
        ]);

        return ['matched' => $matched, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $entryData
     */
    public function postAdjustmentEntry(int $statementLineId, array $entryData, ?int $userId = null): int
    {
        $companyId = (int) ($entryData['company_id'] ?? 0);
        $requestedSiteId = ! empty($entryData['site_id']) ? (int) $entryData['site_id'] : null;
        if ($companyId < 1 || $statementLineId < 1) {
            throw new RuntimeException('Company and bank statement line are required.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $line = $this->lockedAdjustmentLine($statementLineId, $companyId, $requestedSiteId);
            $signed = round((float) ($line['signed_amount'] ?? 0), 2);
            if ($signed == 0.0) {
                throw new RuntimeException('A zero-value bank statement line cannot create an adjustment entry.');
            }

            $expectedDirection = $signed > 0 ? 'in' : 'out';
            $actualDirection = str_ends_with((string) ($entryData['entry_type'] ?? ''), '_in') ? 'in' : 'out';
            if ((string) $line['cash_bank_code'] !== trim((string) ($entryData['cash_bank_code'] ?? ''))
                || (string) $line['statement_date'] !== (string) ($entryData['entry_date'] ?? '')
                || $expectedDirection !== $actualDirection
                || round(abs($signed), 2) !== round((float) ($entryData['amount'] ?? 0), 2)) {
                throw new RuntimeException('Bank entry must keep the same bank, date, direction, and amount as the source statement line.');
            }

            $entryId = (new CashBankService())->post(array_replace($entryData, [
                'company_id' => (int) $line['company_id'],
                'site_id' => ! empty($line['site_id']) ? (int) $line['site_id'] : null,
                'entry_type' => $signed > 0 ? 'bank_in' : 'bank_out',
                'cash_bank_code' => (string) $line['cash_bank_code'],
                'entry_date' => (string) $line['statement_date'],
                'currency_code' => (string) ($line['currency_code'] ?? 'IDR'),
                'amount' => abs($signed),
                'reference_no' => $line['reference_no'] ?? ($entryData['reference_no'] ?? null),
            ]), $userId);

            $db->table('bank_statement_lines')
                ->where('id', $statementLineId)
                ->where('match_status !=', 'matched')
                ->where('cash_bank_entry_id', null)
                ->update([
                    'match_status' => 'matched',
                    'cash_bank_entry_id' => $entryId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($db->affectedRows() !== 1) {
                throw new RuntimeException('Bank statement line was matched by another process. Adjustment entry was rolled back.');
            }

            $importId = (int) $line['bank_statement_import_id'];
            $this->refreshImportStatus($importId, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Bank statement adjustment transaction failed.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }

        (new AuditLogService())->log('cash_bank.bank_statement', 'bank_statement.adjustment_post', [
            'company_id' => $line['company_id'] ?? $companyId,
            'site_id' => $line['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'bank_statement_lines',
            'record_id' => $statementLineId,
            'record_code' => $line['reference_no'] ?? null,
            'description' => 'Bank adjustment entry posted and statement line matched atomically.',
            'new_values' => ['cash_bank_entry_id' => $entryId, 'bank_statement_import_id' => $line['bank_statement_import_id'] ?? null],
        ]);

        return $entryId;
    }

    private function lockedAdjustmentLine(int $statementLineId, int $companyId, ?int $siteId): array
    {
        $builder = Database::connect()->table('bank_statement_lines l')
            ->select('l.*, i.status AS import_status')
            ->join('bank_statement_imports i', 'i.id = l.bank_statement_import_id', 'inner')
            ->where('l.id', $statementLineId)
            ->where('l.company_id', $companyId)
            ->where('l.match_status !=', 'matched')
            ->where('l.cash_bank_entry_id', null)
            ->where('l.deleted_at', null)
            ->where('i.deleted_at', null);
        if ($siteId !== null) {
            $builder->groupStart()->where('l.site_id', $siteId)->orWhere('l.site_id', null)->groupEnd();
        }

        $sql = $builder->limit(1)->getCompiledSelect();
        $line = Database::connect()->query($sql . ' FOR UPDATE')->getRowArray();
        if ($line === null) {
            throw new RuntimeException('Bank statement line is not available for adjustment entry in the active company/site.');
        }
        if (($line['import_status'] ?? '') === 'reconciled') {
            throw new RuntimeException('A reconciled bank statement cannot receive a new adjustment entry.');
        }

        return $line;
    }

    private function refreshImportStatus(int $importId, ?int $userId): void
    {
        $db = Database::connect();
        $total = (int) $db->table('bank_statement_lines')
            ->where('bank_statement_import_id', $importId)
            ->where('deleted_at', null)
            ->countAllResults();
        $matched = (int) $db->table('bank_statement_lines')
            ->where('bank_statement_import_id', $importId)
            ->where('match_status', 'matched')
            ->where('deleted_at', null)
            ->countAllResults();
        $status = $matched < 1 ? 'imported' : ($matched >= $total ? 'matched' : 'partial_matched');

        $db->table('bank_statement_imports')->where('id', $importId)->update([
            'matched_count' => $matched,
            'status' => $status,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function bankAccount(int $companyId, ?int $siteId, string $cashBankCode): ?array
    {
        $builder = Database::connect()->table('cash_bank_accounts')
            ->where('company_id', $companyId)
            ->where('cash_bank_code', $cashBankCode)
            ->where('account_type', 'bank')
            ->where('is_active', 1);

        if ($siteId !== null) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd();
        }

        return $builder->orderBy('site_id', 'DESC')->get(1)->getRowArray();
    }

    private function matchEntry(array $line, int $companyId, ?int $siteId): ?array
    {
        $signed = (float) ($line['signed_amount'] ?? 0);
        if ($signed == 0.0) {
            return null;
        }

        $db = Database::connect();
        $usedEntryIds = $db->table('bank_statement_lines')
            ->select('cash_bank_entry_id')
            ->where('company_id', $companyId)
            ->where('cash_bank_entry_id IS NOT NULL', null, false)
            ->where('match_status', 'matched')
            ->get()
            ->getResultArray();
        $usedEntryIds = array_values(array_filter(array_map(static fn (array $row): int => (int) $row['cash_bank_entry_id'], $usedEntryIds)));

        $base = $db->table('cash_bank_entries')
            ->where('company_id', $companyId)
            ->where('cash_bank_code', (string) $line['cash_bank_code'])
            ->where('entry_date', (string) $line['statement_date'])
            ->where('entry_type', $signed > 0 ? 'bank_in' : 'bank_out')
            ->where('amount', number_format(abs($signed), 2, '.', ''))
            ->where('bank_reconciliation_id', null)
            ->where('status', 'posted');

        if ($siteId !== null) {
            $base->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd();
        }
        if ($usedEntryIds !== []) {
            $base->whereNotIn('id', $usedEntryIds);
        }

        $referenceNo = trim((string) ($line['reference_no'] ?? ''));
        if ($referenceNo !== '') {
            $withReference = clone $base;
            $referenceMatch = $this->singleUnusedEntry($withReference->where('reference_no', $referenceNo));
            if ($referenceMatch !== null) {
                return $referenceMatch;
            }
        }

        return $this->singleUnusedEntry($base);
    }

    private function singleUnusedEntry($builder): ?array
    {
        $db = Database::connect();
        $matches = $db->query($builder->getCompiledSelect() . ' FOR UPDATE')->getResultArray();
        if (count($matches) !== 1) {
            return null;
        }

        $entry = $matches[0];
        $usedBuilder = $db->table('bank_statement_lines')
            ->select('id')
            ->where('cash_bank_entry_id', (int) $entry['id'])
            ->where('match_status', 'matched')
            ->where('deleted_at', null);
        $usedRows = $db->query($usedBuilder->getCompiledSelect() . ' FOR UPDATE')->getResultArray();

        return $usedRows === [] ? $entry : null;
    }

    /**
     * @param list<list<mixed>> $rows
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function splitHeaderRows(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('Excel file is empty.');
        }

        $headers = array_map(static fn ($value): string => trim((string) $value), array_shift($rows));
        if (implode('', $headers) === '') {
            throw new RuntimeException('Excel header row is empty.');
        }

        return [$headers, $rows];
    }

    /**
     * @param list<string> $headers
     * @return array<string, int>
     */
    private function headerMap(array $headers): array
    {
        $aliases = [
            'statement_date' => ['statementdate', 'date', 'tanggal', 'tgl', 'postingdate', 'transactiondate'],
            'value_date' => ['valuedate', 'effectivedate', 'tanggalefektif'],
            'reference_no' => ['referenceno', 'reference', 'ref', 'noref', 'nomorreferensi', 'docno'],
            'description' => ['description', 'keterangan', 'deskripsi', 'remark', 'remarks', 'narasi'],
            'debit_amount' => ['debit', 'debitamount', 'withdrawal', 'mutasidebit'],
            'credit_amount' => ['credit', 'kredit', 'creditamount', 'deposit', 'mutasikredit'],
            'amount' => ['amount', 'nominal', 'jumlah', 'mutation', 'mutasi'],
            'balance_amount' => ['balance', 'saldo', 'runningbalance', 'saldoakhir'],
            'currency_code' => ['currency', 'currencycode', 'ccy', 'mata uang', 'matauang'],
        ];

        $normalized = [];
        foreach ($headers as $index => $header) {
            $normalized[$this->normalizeHeader($header)] = $index;
        }

        $map = [];
        foreach ($aliases as $field => $candidates) {
            foreach ($candidates as $candidate) {
                $key = $this->normalizeHeader($candidate);
                if (array_key_exists($key, $normalized)) {
                    $map[$field] = $normalized[$key];
                    break;
                }
            }
        }

        if (! isset($map['debit_amount']) && ! isset($map['credit_amount']) && ! isset($map['amount'])) {
            throw new RuntimeException('Excel must contain debit/credit columns or an amount column.');
        }

        return $map;
    }

    /**
     * @param list<list<mixed>> $rows
     * @param list<string> $headers
     * @param array<string, int> $map
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function statementLines(array $rows, array $headers, array $map, array $context): array
    {
        $lines = [];
        $lineNo = 10;

        foreach ($rows as $rowIndex => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $statementDate = $this->parseDate($this->cell($row, $map, 'statement_date')) ?: (string) $context['statement_date'];
            $valueDate = $this->parseDate($this->cell($row, $map, 'value_date'));
            $debit = $this->amount($this->cell($row, $map, 'debit_amount'));
            $credit = $this->amount($this->cell($row, $map, 'credit_amount'));
            $amount = $this->amount($this->cell($row, $map, 'amount'));

            if ($debit == 0.0 && $credit == 0.0 && $amount != 0.0) {
                $credit = $amount > 0 ? abs($amount) : 0.0;
                $debit = $amount < 0 ? abs($amount) : 0.0;
            }

            $signed = round($credit - $debit, 2);
            if ($signed == 0.0) {
                continue;
            }

            $lines[] = [
                'company_id' => (int) $context['company_id'],
                'site_id' => $context['site_id'],
                'cash_bank_account_id' => (int) $context['cash_bank_account_id'],
                'cash_bank_code' => (string) $context['cash_bank_code'],
                'line_no' => $lineNo,
                'statement_date' => $statementDate,
                'value_date' => $valueDate,
                'reference_no' => $this->nullableText($this->cell($row, $map, 'reference_no')),
                'description' => $this->nullableText($this->cell($row, $map, 'description')),
                'debit_amount' => round($debit, 2),
                'credit_amount' => round($credit, 2),
                'signed_amount' => $signed,
                'balance_amount' => round($this->amount($this->cell($row, $map, 'balance_amount')), 2),
                'currency_code' => $this->nullableText($this->cell($row, $map, 'currency_code')) ?: (string) $context['currency_code'],
                'match_status' => 'unmatched',
                'cash_bank_entry_id' => null,
                'raw_payload' => $this->rawPayload($headers, $row, $rowIndex + 2),
            ];

            $lineNo += 10;
        }

        return $lines;
    }

    private function cell(array $row, array $map, string $field): string
    {
        if (! isset($map[$field])) {
            return '';
        }

        return trim((string) ($row[$map[$field]] ?? ''));
    }

    private function amount(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $negative = str_contains($value, '(') && str_contains($value, ')');
        $clean = str_replace(['Rp', 'IDR', ' ', ','], '', $value);
        $clean = str_replace(['(', ')'], '', $clean);
        if (! is_numeric($clean)) {
            $clean = preg_replace('/[^0-9.\-]/', '', $clean) ?? '';
        }

        $amount = is_numeric($clean) ? (float) $clean : 0.0;
        return $negative ? -abs($amount) : $amount;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            return gmdate('Y-m-d', ((int) floor((float) $value) - 25569) * 86400);
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $header));
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function rawPayload(array $headers, array $row, int $excelRow): string
    {
        $payload = ['excel_row' => $excelRow];
        foreach ($headers as $index => $header) {
            $payload[$header !== '' ? $header : 'column_' . ($index + 1)] = $row[$index] ?? null;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
