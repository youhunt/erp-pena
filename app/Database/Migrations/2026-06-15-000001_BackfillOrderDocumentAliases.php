<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillOrderDocumentAliases extends Migration
{
    public function up(): void
    {
        $this->backfill('sales_orders', 'so_no', 'so_date');
        $this->backfill('purchase_orders', 'po_no', 'po_date');
    }

    public function down(): void
    {
        // No-op: backfill migration keeps legacy document aliases aligned.
    }

    private function backfill(string $table, string $numberField, string $dateField): void
    {
        if (! $this->db->tableExists($table)
            || ! $this->db->fieldExists('document_no', $table)
            || ! $this->db->fieldExists($numberField, $table)) {
            return;
        }

        $this->db->query(
            'UPDATE `' . $table . '` SET `document_no` = `' . $numberField . '` '
            . 'WHERE (`document_no` IS NULL OR `document_no` = \'\') AND `' . $numberField . '` IS NOT NULL AND `' . $numberField . '` <> \'\''
        );

        if (! $this->db->fieldExists('document_date', $table) || ! $this->db->fieldExists($dateField, $table)) {
            return;
        }

        $this->db->query(
            'UPDATE `' . $table . '` SET `document_date` = `' . $dateField . '` '
            . 'WHERE (`document_date` IS NULL OR `document_date` = \'0000-00-00\') AND `' . $dateField . '` IS NOT NULL'
        );
    }
}
