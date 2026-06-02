<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentProcessingTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('document_ocr_results')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'provider' => ['type' => 'VARCHAR', 'constraint' => 80],
                'ocr_text' => ['type' => 'LONGTEXT', 'null' => true],
                'confidence_score' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'raw_response' => ['type' => 'JSON', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('document_upload_id');
            $this->forge->addKey('provider');
            $this->forge->addForeignKey('document_upload_id', 'document_uploads', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->createTable('document_ocr_results');
        }

        if (! $this->db->tableExists('document_extractions')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'provider' => ['type' => 'VARCHAR', 'constraint' => 80],
                'document_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'extracted_fields' => ['type' => 'JSON', 'null' => true],
                'line_items' => ['type' => 'JSON', 'null' => true],
                'confidence_score' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'raw_response' => ['type' => 'JSON', 'null' => true],
                'review_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'pending_review'],
                'reviewed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('document_upload_id');
            $this->forge->addKey('document_type');
            $this->forge->addKey('review_status');
            $this->forge->addForeignKey('document_upload_id', 'document_uploads', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->createTable('document_extractions');
        }

        if (! $this->db->tableExists('document_processing_logs')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'step' => ['type' => 'VARCHAR', 'constraint' => 80],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40],
                'message' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'context' => ['type' => 'JSON', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('document_upload_id');
            $this->forge->addKey(['step', 'status']);
            $this->forge->addForeignKey('document_upload_id', 'document_uploads', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->createTable('document_processing_logs');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('document_processing_logs', true);
        $this->forge->dropTable('document_extractions', true);
        $this->forge->dropTable('document_ocr_results', true);
    }
}
