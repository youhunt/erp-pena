<?php

namespace App\Services\Support;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Enterprise document number generator.
 *
 * The service uses transaction_codes as the document-numbering setup source
 * and document_number_sequences as the concurrency-safe running number table.
 */
final class DocumentNumberService
{
    private const TABLE = 'document_number_sequences';

    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly ?TenantScope $tenantScope = null,
    ) {
    }

    /**
     * Generate and persist the next document number.
     *
     * Supported options:
     * - company_id: int|null, defaults to active company
     * - site_id: int|null, defaults to active site or 0
     * - prefix: string|null, fallback when transaction code setup has no prefix
     * - format: string|null, fallback when transaction code setup has no format
     * - reset_period: daily|monthly|yearly|never, fallback when setup has no reset_period
     * - padding: int, fallback when setup has no padding
     * - meta: array<string, string|int|null>, optional extra token values
     *
     * @param array<string, mixed> $options
     */
    public function next(string $transactionCode, ?DateTimeInterface $documentDate = null, array $options = []): string
    {
        $transactionCode = $this->normalizeCode($transactionCode);
        $date = $documentDate ?? new DateTimeImmutable();
        $config = $this->resolveConfig($transactionCode, $options);

        $db = $this->connection();

        if (! $db->tableExists(self::TABLE)) {
            throw new RuntimeException('Table document_number_sequences does not exist. Run the document numbering hosting SQL first.');
        }

        $db->transStart();

        $periodKey = $this->periodKey((string) $config['reset_period'], $date);

        $row = $db->query(
            'SELECT * FROM ' . self::TABLE . ' WHERE company_id = ? AND site_id = ? AND transaction_code = ? AND prefix = ? AND period_key = ? FOR UPDATE',
            [
                $config['company_id'],
                $config['site_id'],
                $transactionCode,
                $config['prefix'],
                $periodKey,
            ]
        )->getRowArray();

        $nextNumber = $row === null ? 1 : ((int) $row['last_number']) + 1;
        $documentNo = $this->formatDocumentNo($transactionCode, $date, $nextNumber, $config, $periodKey);
        $now = date('Y-m-d H:i:s');

        if ($row === null) {
            $db->table(self::TABLE)->insert([
                'company_id'       => $config['company_id'],
                'site_id'          => $config['site_id'],
                'transaction_code' => $transactionCode,
                'prefix'           => $config['prefix'],
                'period_key'       => $periodKey,
                'last_number'      => $nextNumber,
                'padding'          => $config['padding'],
                'reset_period'     => $config['reset_period'],
                'last_document_no' => $documentNo,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        } else {
            $db->table(self::TABLE)
                ->where('id', (int) $row['id'])
                ->update([
                    'last_number'      => $nextNumber,
                    'padding'          => $config['padding'],
                    'reset_period'     => $config['reset_period'],
                    'last_document_no' => $documentNo,
                    'updated_at'       => $now,
                ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('Failed to generate document number for transaction code: ' . $transactionCode);
        }

        return $documentNo;
    }

    /**
     * Preview document number without incrementing sequence.
     *
     * @param array<string, mixed> $options
     */
    public function preview(string $transactionCode, ?DateTimeInterface $documentDate = null, array $options = []): string
    {
        $transactionCode = $this->normalizeCode($transactionCode);
        $date = $documentDate ?? new DateTimeImmutable();
        $config = $this->resolveConfig($transactionCode, $options);
        $periodKey = $this->periodKey((string) $config['reset_period'], $date);

        $nextNumber = 1;
        $db = $this->connection();

        if ($db->tableExists(self::TABLE)) {
            $row = $db->table(self::TABLE)
                ->select('last_number')
                ->where('company_id', $config['company_id'])
                ->where('site_id', $config['site_id'])
                ->where('transaction_code', $transactionCode)
                ->where('prefix', $config['prefix'])
                ->where('period_key', $periodKey)
                ->get()
                ->getRowArray();

            if ($row !== null) {
                $nextNumber = ((int) $row['last_number']) + 1;
            }
        }

        return $this->formatDocumentNo($transactionCode, $date, $nextNumber, $config, $periodKey);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{company_id:int, site_id:int, prefix:string, format:string, reset_period:string, padding:int, meta:array<string, mixed>}
     */
    private function resolveConfig(string $transactionCode, array $options): array
    {
        $tenant = $this->tenant();
        $companyId = (int) ($options['company_id'] ?? $tenant->requireCompany());
        $siteId = (int) ($options['site_id'] ?? ($tenant->optionalSite() ?? 0));

        if ($companyId < 1) {
            throw new RuntimeException('Active company is required to generate document number.');
        }

        $setup = $this->resolveSetupConfig($transactionCode, $companyId);

        // Transaction Code UI is the source of truth. Options are only fallbacks
        // so legacy controllers that still pass default formats do not override setup.
        $resetPeriod = strtolower((string) ($setup['reset_period'] ?? $options['reset_period'] ?? 'monthly'));
        if (! in_array($resetPeriod, ['daily', 'monthly', 'yearly', 'never'], true)) {
            throw new InvalidArgumentException('Invalid reset_period. Use daily, monthly, yearly, or never.');
        }

        $padding = (int) ($setup['padding'] ?? $options['padding'] ?? 5);
        if ($padding < 1 || $padding > 12) {
            throw new InvalidArgumentException('Document number padding must be between 1 and 12.');
        }

        $prefix = trim((string) ($setup['prefix'] ?? $options['prefix'] ?? $transactionCode));
        if ($prefix === '') {
            $prefix = $transactionCode;
        }

        $format = trim((string) ($setup['format'] ?? $options['format'] ?? '{PREFIX}/{YYYY}{MM}/{SEQ}'));
        if ($format === '') {
            $format = '{PREFIX}/{YYYY}{MM}/{SEQ}';
        }

        return [
            'company_id'   => $companyId,
            'site_id'      => max(0, $siteId),
            'prefix'       => $prefix,
            'format'       => $format,
            'reset_period' => $resetPeriod,
            'padding'      => $padding,
            'meta'         => is_array($options['meta'] ?? null) ? $options['meta'] : [],
        ];
    }

    /**
     * Read document-numbering setup from transaction_codes.
     *
     * @return array<string, mixed>
     */
    private function resolveSetupConfig(string $transactionCode, int $companyId): array
    {
        $db = $this->connection();

        foreach (['transaction_codes', 'transaction_code'] as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $fields = $db->getFieldNames($table);
            $hasCode = in_array('code', $fields, true);
            $hasTransactionCode = in_array('transaction_code', $fields, true);
            if (! $hasCode && ! $hasTransactionCode) {
                continue;
            }

            $scopeValues = in_array('company_id', $fields, true) && $companyId > 0
                ? [$companyId, null]
                : [null];

            foreach ($scopeValues as $scopeCompanyId) {
                $builder = $db->table($table);

                if ($hasCode && $hasTransactionCode) {
                    $builder->groupStart()
                        ->where('code', $transactionCode)
                        ->orWhere('transaction_code', $transactionCode)
                        ->groupEnd();
                } elseif ($hasCode) {
                    $builder->where('code', $transactionCode);
                } else {
                    $builder->where('transaction_code', $transactionCode);
                }

                if (in_array('company_id', $fields, true)) {
                    $scopeCompanyId === null
                        ? $builder->where('company_id', null)
                        : $builder->where('company_id', $scopeCompanyId);
                }
                if (in_array('is_active', $fields, true)) {
                    $builder->where('is_active', 1);
                }
                if (in_array('deleted_at', $fields, true)) {
                    $builder->where('deleted_at', null);
                }

                $row = $builder->get(1)->getRowArray();
                if ($row === null) {
                    continue;
                }

                $config = [];
                foreach (['prefix', 'format', 'reset_period', 'padding'] as $key) {
                    if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                        $config[$key] = $row[$key];
                    }
                }

                if (! isset($config['prefix']) && $hasCode && ! empty($row['code'])) {
                    $config['prefix'] = $row['code'];
                }

                return $config;
            }
        }

        return [];
    }

    private function periodKey(string $resetPeriod, DateTimeInterface $date): string
    {
        return match ($resetPeriod) {
            'daily'   => $date->format('Ymd'),
            'monthly' => $date->format('Ym'),
            'yearly'  => $date->format('Y'),
            'never'   => 'ALL',
            default   => $date->format('Ym'),
        };
    }

    /**
     * @param array{company_id:int, site_id:int, prefix:string, format:string, reset_period:string, padding:int, meta:array<string, mixed>} $config
     */
    private function formatDocumentNo(
        string $transactionCode,
        DateTimeInterface $date,
        int $sequence,
        array $config,
        string $periodKey
    ): string {
        $seq = str_pad((string) $sequence, (int) $config['padding'], '0', STR_PAD_LEFT);

        $tokens = [
            '{PREFIX}'  => (string) $config['prefix'],
            '{CODE}'    => $transactionCode,
            '{YYYY}'    => $date->format('Y'),
            '{YY}'      => $date->format('y'),
            '{MM}'      => $date->format('m'),
            '{DD}'      => $date->format('d'),
            '{PERIOD}'  => $periodKey,
            '{COMPANY}' => (string) $config['company_id'],
            '{SITE}'    => (string) $config['site_id'],
            '{SEQ}'     => $seq,
            '{N}'       => (string) $sequence,
        ];

        foreach ($config['meta'] as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $tokens['{' . strtoupper((string) $key) . '}'] = (string) $value;
            }
        }

        return strtr((string) $config['format'], $tokens);
    }

    private function normalizeCode(string $transactionCode): string
    {
        $transactionCode = strtoupper(trim($transactionCode));

        if ($transactionCode === '') {
            throw new InvalidArgumentException('Transaction code is required.');
        }

        if (! preg_match('/^[A-Z0-9_\-\.]+$/', $transactionCode)) {
            throw new InvalidArgumentException('Transaction code may only contain letters, numbers, underscore, dash, and dot.');
        }

        return $transactionCode;
    }

    private function connection(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }

    private function tenant(): TenantScope
    {
        return $this->tenantScope ?? new TenantScope();
    }
}
