<?php

namespace App\Services\Ai;

use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;

final class DocumentReviewService
{
    public function updateReview(int $extractionId, array $fields, array $lineItems, string $status, ?int $userId = null): void
    {
        if (! in_array($status, ['pending_review', 'reviewed'], true)) {
            throw new RuntimeException('Invalid review status.');
        }

        $db = Database::connect();
        $extraction = $db->table('document_extractions')->where('id', $extractionId)->get()->getRowArray();
        if ($extraction === null) {
            throw new RuntimeException('Extraction result was not found.');
        }

        $document = $db->table('document_uploads')->where('id', $extraction['document_upload_id'])->get()->getRowArray();
        if ($document === null) {
            throw new RuntimeException('Document was not found.');
        }

        $oldValues = [
            'extracted_fields' => json_decode((string) ($extraction['extracted_fields'] ?? ''), true),
            'line_items' => json_decode((string) ($extraction['line_items'] ?? ''), true),
            'review_status' => $extraction['review_status'] ?? null,
        ];

        $db->table('document_extractions')->where('id', $extractionId)->update([
            'extracted_fields' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'line_items' => json_encode($lineItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'review_status' => $status,
            'reviewed_by' => $status === 'reviewed' ? $userId : null,
            'reviewed_at' => $status === 'reviewed' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->table('document_processing_logs')->insert([
            'document_upload_id' => (int) $extraction['document_upload_id'],
            'step' => 'human_review',
            'status' => $status,
            'message' => $status === 'reviewed' ? 'Document extraction reviewed by user.' : 'Document extraction saved as pending review.',
            'context' => json_encode(['extraction_id' => $extractionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditLogService())->log('ai.document', $status === 'reviewed' ? 'document.review' : 'document.review_save', [
            'company_id' => $document['company_id'] ?? null,
            'site_id' => $document['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'document_extractions',
            'record_id' => $extractionId,
            'record_code' => $document['original_name'] ?? null,
            'description' => $status === 'reviewed'
                ? 'Document extraction reviewed and marked ready.'
                : 'Document extraction review draft saved.',
            'old_values' => $oldValues,
            'new_values' => [
                'extracted_fields' => $fields,
                'line_items' => $lineItems,
                'review_status' => $status,
            ],
        ]);
    }
}
