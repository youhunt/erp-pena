<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use Config\Database;
use DateTimeImmutable;
use Throwable;

class DocumentNumberingController extends BaseController
{
    private const TABLE = 'transaction_codes';

    public function index(): string
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $db = Database::connect();

        $this->ensureTables($db);
        $this->seedDefaults($db, $companyId);

        $builder = $db->table(self::TABLE)->orderBy('code', 'ASC');
        if ($companyId !== null && $db->fieldExists('company_id', self::TABLE)) {
            $builder->where('company_id', $companyId);
        }
        if ($db->fieldExists('deleted_at', self::TABLE)) {
            $builder->where('deleted_at', null);
        }

        $codes = $builder->get()->getResultArray();

        $sequences = [];
        if ($db->tableExists('document_number_sequences')) {
            $sequenceBuilder = $db->table('document_number_sequences')
                ->where('company_id', $companyId)
                ->orderBy('transaction_code', 'ASC')
                ->orderBy('period_key', 'DESC');
            if ($siteId !== null) {
                $sequenceBuilder->where('site_id', $siteId);
            }
            foreach ($sequenceBuilder->get()->getResultArray() as $row) {
                $sequences[(string) ($row['transaction_code'] ?? '')][] = $row;
            }
        }

        $previews = [];
        foreach ($codes as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }

            try {
                $previews[$code] = (new DocumentNumberService())->preview($code, new DateTimeImmutable(), [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                ]);
            } catch (Throwable $e) {
                $previews[$code] = 'ERROR: ' . $e->getMessage();
            }
        }

        return view('setup/document_numbering/index', [
            'title' => 'Document Numbering',
            'codes' => $codes,
            'sequences' => $sequences,
            'previews' => $previews,
        ]);
    }

    public function save()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $db = Database::connect();

        $this->ensureTables($db);

        if ($companyId === null || $companyId < 1) {
            return redirect()->to(site_url('setup/document-numbering'))->with('error', 'Active company is required to save document numbering.');
        }

        $rows = (array) $this->request->getPost('rows');
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $code = strtoupper(trim((string) ($row['code'] ?? $row['transaction_code'] ?? '')));
            $prefix = trim((string) ($row['prefix'] ?? $code));
            $format = trim((string) ($row['format'] ?? '{PREFIX}/{YYYY}{MM}/{SEQ}'));
            $resetPeriod = strtolower(trim((string) ($row['reset_period'] ?? 'monthly')));
            $padding = (int) ($row['padding'] ?? 5);

            if ($code === '') {
                continue;
            }
            if (! preg_match('/^[A-Z0-9_\-\.]+$/', $code)) {
                return redirect()->to(site_url('setup/document-numbering'))->with('error', 'Invalid transaction code: ' . $code);
            }
            if (! in_array($resetPeriod, ['daily', 'monthly', 'yearly', 'never'], true)) {
                $resetPeriod = 'monthly';
            }
            if ($padding < 1 || $padding > 12) {
                $padding = 5;
            }
            if ($prefix === '') {
                $prefix = $code;
            }
            if ($format === '') {
                $format = '{PREFIX}/{YYYY}{MM}/{SEQ}';
            }

            $payload = $this->payloadForTable($db, self::TABLE, [
                'company_id' => $companyId,
                'code' => $code,
                'name' => trim((string) ($row['name'] ?? $code)),
                'prefix' => $prefix,
                'format' => $format,
                'reset_period' => $resetPeriod,
                'padding' => $padding,
                'description' => trim((string) ($row['description'] ?? '')),
                'is_active' => isset($row['is_active']) ? 1 : 0,
                'updated_by' => (string) (auth()->id() ?? 'system'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($id > 0) {
                $update = $db->table(self::TABLE)->where('id', $id);
                if ($db->fieldExists('company_id', self::TABLE)) {
                    $update->where('company_id', $companyId);
                }
                $update->update($payload);
                continue;
            }

            $payload = $this->payloadForTable($db, self::TABLE, $payload + [
                'created_by' => (string) (auth()->id() ?? 'system'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $exists = $db->table(self::TABLE);
            if ($db->fieldExists('company_id', self::TABLE)) {
                $exists->where('company_id', $companyId);
            }
            $exists = $exists->where('code', $code)->get(1)->getRowArray();

            if ($exists !== null) {
                $db->table(self::TABLE)->where('id', (int) $exists['id'])->update($payload);
            } else {
                $db->table(self::TABLE)->insert($payload);
            }
        }

        return redirect()->to(site_url('setup/document-numbering'))->with('message', 'Document numbering setup saved in Transaction Codes. New documents will use the updated format. Existing sequence rows are not deleted.');
    }

    public function resetSequence()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId() ?? 0;
        $transactionCode = strtoupper(trim((string) $this->request->getPost('transaction_code')));
        $periodKey = trim((string) $this->request->getPost('period_key'));

        if ($transactionCode === '' || $periodKey === '') {
            return redirect()->to(site_url('setup/document-numbering'))->with('error', 'Transaction code and period key are required to reset sequence.');
        }
        if ($companyId === null || $companyId < 1) {
            return redirect()->to(site_url('setup/document-numbering'))->with('error', 'Active company is required to reset sequence.');
        }

        Database::connect()->table('document_number_sequences')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->where('transaction_code', $transactionCode)
            ->where('period_key', $periodKey)
            ->delete();

        return redirect()->to(site_url('setup/document-numbering'))->with('message', 'Sequence reset for ' . $transactionCode . ' period ' . $periodKey . '.');
    }

    private function ensureTables($db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS transaction_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id INT NULL,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,
            prefix VARCHAR(50) NULL,
            format VARCHAR(150) NULL,
            reset_period VARCHAR(20) NULL,
            padding INT NULL,
            rate DECIMAL(18,6) NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(50) NULL,
            updated_by VARCHAR(50) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_transaction_codes_company_code (company_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'company_id INT NULL',
            'prefix VARCHAR(50) NULL',
            'format VARCHAR(150) NULL',
            'reset_period VARCHAR(20) NULL',
            'padding INT NULL',
            'rate DECIMAL(18,6) NULL',
            'description TEXT NULL',
            'is_active TINYINT(1) NOT NULL DEFAULT 1',
            'created_by VARCHAR(50) NULL',
            'updated_by VARCHAR(50) NULL',
            'created_at DATETIME NULL',
            'updated_at DATETIME NULL',
            'deleted_at DATETIME NULL',
        ] as $definition) {
            [$column] = explode(' ', $definition, 2);
            if (! $db->fieldExists($column, self::TABLE)) {
                $db->query('ALTER TABLE ' . self::TABLE . ' ADD COLUMN ' . $definition);
            }
        }

        if ($db->tableExists('document_number_sequences')) {
            return;
        }

        $db->query("CREATE TABLE document_number_sequences (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id INT NOT NULL,
            site_id INT NOT NULL DEFAULT 0,
            transaction_code VARCHAR(50) NOT NULL,
            prefix VARCHAR(50) NOT NULL,
            period_key VARCHAR(30) NOT NULL,
            last_number INT NOT NULL DEFAULT 0,
            padding INT NOT NULL DEFAULT 5,
            reset_period VARCHAR(20) NOT NULL DEFAULT 'monthly',
            last_document_no VARCHAR(150) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_document_number_sequences (company_id, site_id, transaction_code, prefix, period_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function seedDefaults($db, ?int $companyId): void
    {
        if ($companyId === null || $companyId < 1) {
            return;
        }

        $defaults = [
            ['PO', 'Purchase Order', 'PO', '{PREFIX}{SEQ}', 'never', 3, 'Purchase order number'],
            ['PR', 'Purchase Receipt', 'PR', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Purchase receipt number'],
            ['SO', 'Sales Order', 'SO', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Sales order number'],
            ['SD', 'Sales Delivery', 'SD', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Sales delivery number'],
            ['SI', 'Sales Invoice', 'SI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Sales invoice number'],
            ['PI', 'Purchase Invoice', 'PI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Purchase invoice number'],
            ['JV', 'Journal Voucher', 'JV', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Manual journal number'],
        ];

        foreach ($defaults as [$code, $name, $prefix, $format, $reset, $padding, $description]) {
            $exists = $db->table(self::TABLE)
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->get(1)
                ->getRowArray();

            if ($exists !== null) {
                continue;
            }

            $db->table(self::TABLE)->insert($this->payloadForTable($db, self::TABLE, [
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'prefix' => $prefix,
                'format' => $format,
                'reset_period' => $reset,
                'padding' => $padding,
                'description' => $description,
                'is_active' => 1,
                'created_by' => (string) (auth()->id() ?? 'system'),
                'updated_by' => (string) (auth()->id() ?? 'system'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payloadForTable($db, string $table, array $payload): array
    {
        $fields = $db->getFieldNames($table);

        return array_intersect_key($payload, array_flip($fields));
    }
}
