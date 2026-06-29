<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WorkOrderDocumentNumbering extends Migration
{
    public function up(): void
    {
        $this->ensureTables();
        $this->seedWorkOrderCode();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureTables(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS transaction_codes (
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
            'company_id' => 'INT NULL',
            'prefix' => 'VARCHAR(50) NULL',
            'format' => 'VARCHAR(150) NULL',
            'reset_period' => 'VARCHAR(20) NULL',
            'padding' => 'INT NULL',
            'rate' => 'DECIMAL(18,6) NULL',
            'description' => 'TEXT NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'created_by' => 'VARCHAR(50) NULL',
            'updated_by' => 'VARCHAR(50) NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
            'deleted_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            if (! $this->columnExists('transaction_codes', $column)) {
                $this->db->query('ALTER TABLE transaction_codes ADD COLUMN `' . $column . '` ' . $definition);
            }
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS document_number_sequences (
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

    private function seedWorkOrderCode(): void
    {
        $exists = $this->db->table('transaction_codes')
            ->where('code', 'WO')
            ->groupStart()
                ->where('company_id', null)
                ->orWhere('company_id', 0)
            ->groupEnd()
            ->get(1)
            ->getRowArray();

        $payload = [
            'company_id' => null,
            'code' => 'WO',
            'name' => 'Work Order',
            'prefix' => 'WO',
            'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
            'reset_period' => 'monthly',
            'padding' => 4,
            'description' => 'Production work order number',
            'is_active' => 1,
            'updated_by' => 'system',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exists !== null) {
            $this->db->table('transaction_codes')->where('id', (int) $exists['id'])->update($payload);
            return;
        }

        $payload['created_by'] = 'system';
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('transaction_codes')->insert($payload);
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->countAllResults() > 0;
    }
}
