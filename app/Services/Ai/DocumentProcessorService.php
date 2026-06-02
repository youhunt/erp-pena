<?php

namespace App\Services\Ai;

use App\Models\DocumentUploadModel;
use App\Services\Ai\Extraction\AiExtractionInterface;
use App\Services\Ai\Extraction\NullAiExtraction;
use App\Services\Ai\Ocr\NullOcrEngine;
use App\Services\Ai\Ocr\OcrEngineInterface;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

final class DocumentProcessorService
{
    public function __construct(
        private readonly OcrEngineInterface $ocrEngine = new NullOcrEngine(),
        private readonly AiExtractionInterface $aiExtraction = new NullAiExtraction(),
        private readonly DocumentUploadModel $documents = new DocumentUploadModel(),
    ) {
    }

    public function process(int $documentId, ?int $userId = null): void
    {
        $document = $this->documents->find($documentId);
        if ($document === null) {
            throw new RuntimeException('Document was not found.');
        }

        if (($document['status'] ?? '') === 'duplicate') {
            throw new RuntimeException('Duplicate documents are not processed. Open the original document instead.');
        }

        $filePath = (string) ($document['stored_path'] ?? '');
        if ($filePath === '' || ! is_file($filePath)) {
            $this->markFailed($document, 'Original file was not found in secure storage.', $userId);
            throw new RuntimeException('Original file was not found in secure storage.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $this->logStep($documentId, 'ocr', 'started', 'OCR processing started.');
            $ocr = $this->ocrEngine->extractText($filePath, (string) ($document['mime_type'] ?? ''));

            $db->table('document_ocr_results')->insert([
                'document_upload_id' => $documentId,
                'provider' => $ocr['provider'],
                'ocr_text' => $ocr['text'],
                'confidence_score' => $ocr['confidence'],
                'raw_response' => $this->json($ocr['raw_response']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->documents->update($documentId, ['status' => 'ocr_completed']);
            $this->logStep($documentId, 'ocr', 'completed', 'OCR processing completed.');

            $this->logStep($documentId, 'ai_extraction', 'started', 'AI extraction started.');
            $extraction = $this->aiExtraction->extractFields((string) $ocr['text'], [
                'document_id' => $documentId,
                'original_name' => $document['original_name'] ?? null,
                'mime_type' => $document['mime_type'] ?? null,
            ]);

            $db->table('document_extractions')->insert([
                'document_upload_id' => $documentId,
                'provider' => $extraction['provider'],
                'document_type' => $extraction['document_type'],
                'extracted_fields' => $this->json($extraction['fields']),
                'line_items' => $this->json($extraction['line_items']),
                'confidence_score' => $extraction['confidence'],
                'raw_response' => $this->json($extraction['raw_response']),
                'review_status' => 'pending_review',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->documents->update($documentId, [
                'status' => 'extraction_completed',
                'document_type' => $extraction['document_type'],
            ]);
            $this->logStep($documentId, 'ai_extraction', 'completed', 'AI extraction completed and is pending human review.');

            if ($db->transStatus() === false) {
                throw new RuntimeException('Database transaction failed while processing document.');
            }

            $db->transCommit();

            (new AuditLogService())->log('ai.document', 'document.process', [
                'company_id' => $document['company_id'] ?? null,
                'site_id' => $document['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'document_uploads',
                'record_id' => $documentId,
                'record_code' => $document['original_name'] ?? null,
                'description' => 'Document OCR and AI extraction completed.',
                'new_values' => [
                    'ocr_provider' => $ocr['provider'],
                    'ai_provider' => $extraction['provider'],
                    'document_type' => $extraction['document_type'],
                    'ocr_confidence' => $ocr['confidence'],
                    'extraction_confidence' => $extraction['confidence'],
                ],
            ]);
        } catch (Throwable $exception) {
            $db->transRollback();
            $this->markFailed($document, $exception->getMessage(), $userId);
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function markFailed(array $document, string $message, ?int $userId): void
    {
        $documentId = (int) $document['id'];
        $this->documents->update($documentId, ['status' => 'failed']);
        $this->logStep($documentId, 'processing', 'failed', $message);

        (new AuditLogService())->log('ai.document', 'document.process_failed', [
            'company_id' => $document['company_id'] ?? null,
            'site_id' => $document['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'document_uploads',
            'record_id' => $documentId,
            'record_code' => $document['original_name'] ?? null,
            'description' => 'Document processing failed: ' . $message,
            'new_values' => ['error' => $message],
        ]);
    }

    private function logStep(int $documentId, string $step, string $status, string $message, array $context = []): void
    {
        Database::connect()->table('document_processing_logs')->insert([
            'document_upload_id' => $documentId,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $this->json($context),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function json(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
