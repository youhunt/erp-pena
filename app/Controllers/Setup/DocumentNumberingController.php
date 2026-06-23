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
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $db = Database::connect();

        $this->ensureTables($db);
        $this->seedDefaults($db, $companyId, $siteId);

        $prefixes = $db->table('prefix_codes')
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('transaction_code', 'ASC')
            ->get()
            ->getResultArray();

        $sequences = [];
        if ($db->tableExists('document_number_sequences')) {
            $builder = $db->table('document_number_sequences')
                ->where('company_id', $companyId)
                ->orderBy('transaction_code', 'ASC')
                ->orderBy('period_key', 'DESC');
            if ($siteId !== null) {
                $builder->where('site_id', $siteId);
            }
            foreach ($builder->get()->getResultArray() as $row) {
                $sequences[(string) ($row['transaction_code'] ?? '')][] = $row;
            }
        }

        $previews = [];
        foreach ($prefixes as $row) {
            try {
                $previews[(string) $row['transaction_code']] = (new DocumentNumberService())->preview((string) $row['transaction_code'], new DateTimeImmutable(), [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                ]);
            } catch (Throwable $e) {
                $previews[(string) $row['transaction_code']] = 'ERROR: ' . $e->getMessage();
            }
        }

        return view('setup/document_numbering/index', [
            'title' => 'Document Numbering',
            'prefixes' => $prefixes,
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

        $rows = (array) $this->request->getPost('rows');
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $transactionCode = strtoupper(trim((string) ($row['transaction_code'] ?? '')));
            $prefix = trim((string) ($row['prefix'] ?? $transactionCode));
            $format = trim((string) ($row['format'] ?? '{PREFIX}/{YYYY}{MM}/{SEQ}'));
            $resetPeriod = strtolower(trim((string) ($row['reset_period'] ?? 'monthly')));
            $padding = (int) ($row['padding'] ?? 5);

            if ($transactionCode === '') {
                continue;
            }
            if (! in_array($resetPeriod, ['daily', 'monthly', 'yearly', 'never'], true)) {
                $resetPeriod = 'monthly';
            }
            if ($padding < 1 || $padding > 12) {
                $padding = 5;
            }
            if ($prefix === '') {
                $prefix = $transactionCode;
            }
            if ($format === '') {
                $format = '{PREFIX}/{YYYY}{MM}/{SEQ}';
            }

            $payload = [
                'company_id' => $companyId,
                'transaction_code' => $transactionCode,
                'code' => $transactionCode,
                'name' => trim((string) ($row['name'] ?? $transactionCode)),
                'prefix' => $prefix,
                'format' => $format,
                'reset_period' => $resetPeriod,
                'padding' => $padding,
                'description' => trim((string) ($row['description'] ?? '')),
                'is_active' => isset($row['is_active']) ? 1 : 0,
                'updated_by' => (string) auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($id > 0) {
                $db->table('prefix_codes')
                    ->where('id', $id)
                    ->where('company_id', $companyId)
                    ->update($payload);
                continue;
            }

            $payload['created_by'] = (string) auth()->id();
            $payload['created_at'] = date('Y-m-d H:i:s');
            $exists = $db->table('prefix_codes')
                ->where('company_id', $companyId)
                ->where('transaction_code', $transactionCode)
                ->get(1)
                ->getRowArray();

            if ($exists !== null) {
                $db->table('prefix_codes')->where('id', (int) $exists['id'])->update($payload);
            } else {
                $db->table('prefix_codes')->insert($payload);
            }
        }

        return redirect()->to(site_url('setup/document-numbering'))->with('message', 'Document numbering setup saved. New documents will use the updated format. Existing sequence rows are not deleted.');
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
        $db->query("CREATE TABLE IF NOT EXISTS prefix_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id INT NULL,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,
            transaction_code VARCHAR(50) NULL,
            prefix VARCHAR(50) NULL,
            format VARCHAR(150) NULL,
            reset_period VARCHAR(20) NULL,
            padding INT NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(50) NULL,
            updated_by VARCHAR(50) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_prefix_codes_company_transaction (company_id, transaction_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'company_id INT NULL',
            'transaction_code VARCHAR(50) NULL',
            'prefix VARCHAR(50) NULL',
            'format VARCHAR(150) NULL',
            'reset_period VARCHAR(20) NULL',
            'padding INT NULL',
            'description TEXT NULL',
            'is_active TINYINT(1) NOT NULL DEFAULT 1',
            'created_by VARCHAR(50) NULL',
            'updated_by VARCHAR(50) NULL',
            'created_at DATETIME NULL',
            'updated_at DATETIME NULL',
            'deleted_at DATETIME NULL',
        ] as $definition) {
            [$column] = explode(' ', $definition, 2);
            if (! $db->fieldExists($column, 'prefix_codes')) {
                $db->query('ALTER TABLE prefix_codes ADD COLUMN ' . $definition);
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

    private function seedDefaults($db, ?int $companyId, ?int $siteId): void
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
            $exists = $db->table('prefix_codes')
                ->where('company_id', $companyId)
                ->where('transaction_code', $code)
                ->get(1)
                ->getRowArray();

            if ($exists !== null) {
                continue;
            }

            $db->table('prefix_codes')->insert([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'transaction_code' => $code,
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
            ]);
        }
    }
}
