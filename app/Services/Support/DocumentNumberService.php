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
 * The service uses a dedicated sequence table and a database transaction so
 * PO/SO/Invoice/Receipt/Journal numbers are generated consistently per tenant,
 * transaction code, prefix, and reset period.
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
     * - prefix: string|null, defaults to transaction code or setup config
     * - format: string|null, defaults to `{PREFIX}/{YYYY}{MM}/{SEQ}`
     * - reset_period: daily|monthly|yearly|never, defaults to monthly
     * - padding: int, defaults to 5
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
            throw new RuntimeException('Table document_number_sequences does not exist. Run php spark migrate --all first.');
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
        $setup = $this->resolveSetupConfig($transactionCode);

        $tenant = $this->tenant();
        $companyId = (int) ($options['company_id'] ?? $tenant->requireCompany());
        $siteId = (int) ($options['site_id'] ?? ($tenant->optionalSite() ?? 0));

        if ($companyId < 1) {
            throw new RuntimeException('Active company is required to generate document number.');
        }

        $resetPeriod = strtolower((string) ($options['reset_period'] ?? $setup['reset_period'] ?? 'monthly'));
        if (! in_array($resetPeriod, ['daily', 'monthly', 'yearly', 'never'], true)) {
            throw new InvalidArgumentException('Invalid reset_period. Use daily, monthly, yearly, or never.');
        }

        $padding = (int) ($options['padding'] ?? $setup['padding'] ?? 5);
        if ($padding < 1 || $padding > 12) {
            throw new InvalidArgumentException('Document number padding must be between 1 and 12.');
        }

        $prefix = trim((string) ($options['prefix'] ?? $setup['prefix'] ?? $transactionCode));
        if ($prefix === '') {
            $prefix = $transactionCode;
        }

        $format = trim((string) ($options['format'] ?? $setup['format'] ?? '{PREFIX}/{YYYY}{MM}/{SEQ}'));
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
     * Try to read existing setup tables if the current database has them.
     *
     * This method is intentionally defensive because the Excel-origin setup
     * schema may evolve. Explicit options passed to next()/preview() always win.
     *
     * @return array<string, mixed>
     */
    private function resolveSetupConfig(string $transactionCode): array
    {
        $db = $this->connection();
        $config = [];

        foreach (['prefix_codes', 'prefix_code'] as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $fields = $db->getFieldNames($table);
            $builder = $db->table($table);

            if (in_array('transaction_code', $fields, true)) {
                $builder->where('transaction_code', $transactionCode);
            } elseif (in_array('code', $fields, true)) {
                $builder->where('code', $transactionCode);
            } else {
                continue;
            }

            if (in_array('is_active', $fields, true)) {
                $builder->where('is_active', 1);
            }

            $row = $builder->get(1)->getRowArray();
            if ($row === null) {
                continue;
            }

            foreach (['prefix', 'format', 'reset_period', 'padding'] as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    $config[$key] = $row[$key];
                }
            }

            break;
        }

        return $config;
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
