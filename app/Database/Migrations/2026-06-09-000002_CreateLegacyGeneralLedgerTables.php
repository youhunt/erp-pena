<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLegacyGeneralLedgerTables extends Migration
{
    public function up(): void
    {
        $this->createGlBook();
        $this->createGlBookLine();
        $this->createGl();
        $this->createGlLine();
        $this->createGlColumn();
        $this->createGlColumnLine();
        $this->createCoa();
        $this->createCoaLine();
        $this->createRecurring();
        $this->createRecurringLine();
        $this->createGeneralLedger();
        $this->createGeneralLedgerLine();
    }

    public function down(): void
    {
        foreach ([
            'general_ledger_line', 'general_ledger', 'recurring_line', 'recurring',
            'coaline', 'coa', 'glcolumnline', 'glcolumn', 'glline', 'gl',
            'glbookline', 'glbook',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function auditFields(): array
    {
        return [
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => false, 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
            'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false, 'default' => 'system'],
            'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
        ];
    }

    private function createTableIfMissing(string $table, array $fields, array $keys = []): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        ], $fields));
        $this->forge->addKey('id', true);
        foreach ($keys as $key) {
            $this->forge->addKey($key);
        }
        $this->forge->createTable($table, true);
    }

    private function createGlBook(): void
    {
        $this->createTableIfMissing('glbook', [
            'booktype' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => false],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => true],
            'year' => ['type' => 'INT', 'constraint' => 4, 'null' => true],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ], ['booktype', 'company', 'site']);
    }

    private function createGlBookLine(): void
    {
        $this->createTableIfMissing('glbookline', [
            'fromdate' => ['type' => 'DATE', 'null' => true],
            'todate' => ['type' => 'DATE', 'null' => true],
            'closed' => ['type' => 'CHAR', 'constraint' => 1, 'null' => true],
        ]);
    }

    private function createGl(): void
    {
        $this->createTableIfMissing('gl', [
            'prefix' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
            'glno' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
            'transdate' => ['type' => 'DATE', 'null' => false],
            'transcode' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'remarks' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'postdate' => ['type' => 'DATE', 'null' => true],
        ], ['glno', 'transdate', 'transcode', 'site']);
    }

    private function createGlLine(): void
    {
        $this->createTableIfMissing('glline', [
            'transcode' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'dept' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'employee' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'employeename' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => false],
            'transamount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => false, 'default' => 0],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 1],
            'bookamount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 0],
            'adjust' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 0],
            'column' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false],
            'approval_1' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_2' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_3' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_4' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
        ], ['transcode', 'column']);
    }

    private function createGlColumn(): void
    {
        $this->createTableIfMissing('glcolumn', [
            'booktype' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => false],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => true],
            'remarks' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ], ['booktype', 'company', 'site']);
    }

    private function createGlColumnLine(): void
    {
        $this->createTableIfMissing('glcolumnline', [
            'code' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => false],
            'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ], ['code']);
    }

    private function createCoa(): void
    {
        $this->createTableIfMissing('coa', [
            'booktype' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => false],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false],
            'remarks' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ], ['booktype', 'company', 'site', 'code']);
    }

    private function createCoaLine(): void
    {
        $this->createTableIfMissing('coaline', [
            'column' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ], ['column']);
    }

    private function createRecurring(): void
    {
        $this->createTableIfMissing('recurring', array_merge([
            'prefix' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'recno' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
            'transdate' => ['type' => 'DATE', 'null' => false],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false],
            'remarks' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'postdate' => ['type' => 'DATE', 'null' => false],
        ], $this->auditFields()), ['recno', 'transdate', 'site']);
    }

    private function createRecurringLine(): void
    {
        $this->createTableIfMissing('recurring_line', array_merge([
            'transcode' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'dept' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'employee' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'employeename' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => false],
            'transamount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => false, 'default' => 0],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 1],
            'bookamount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 0],
            'adjust' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 0],
            'day' => ['type' => 'DECIMAL', 'constraint' => '3,0', 'null' => true],
            'date' => ['type' => 'DECIMAL', 'constraint' => '2,0', 'null' => true],
            'column' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false],
            'approval_1' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_2' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_3' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_4' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
        ], $this->auditFields()), ['transcode', 'column']);
    }

    private function createGeneralLedger(): void
    {
        $this->createTableIfMissing('general_ledger', array_merge([
            'prefix' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'glno' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
            'transdate' => ['type' => 'DATE', 'null' => false],
            'transcode' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'remarks' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'postdate' => ['type' => 'DATE', 'null' => false],
        ], $this->auditFields()), ['glno', 'transdate', 'transcode', 'site']);
    }

    private function createGeneralLedgerLine(): void
    {
        $this->createTableIfMissing('general_ledger_line', array_merge([
            'transcode' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => false],
            'dept' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'employee' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'employeename' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => false],
            'transamount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => false, 'default' => 0],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 1],
            'bookamount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 0],
            'adjust' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'null' => true, 'default' => 0],
            'column' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false],
            'approval_1' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_2' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_3' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'approval_4' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
        ], $this->auditFields()), ['transcode', 'column']);
    }
}
