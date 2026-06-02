<?php

namespace App\Services\Ai;

use App\Models\DocumentUploadModel;
use App\Services\AuditLogService;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class DocumentProcessingService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/tiff',
    ];

    public function __construct(private readonly DocumentUploadModel $documents = new DocumentUploadModel())
    {
    }

    public function registerUpload(UploadedFile $file, int $companyId, ?int $siteId, ?int $userId): int
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Unsupported document type. Upload PDF or image files only.');
        }

        $targetDir = WRITEPATH . 'secure_uploads' . DIRECTORY_SEPARATOR . 'erp-documents';
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $storedName = $file->getRandomName();
        $file->move($targetDir, $storedName);
        $storedPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;
        $hash = hash_file('sha256', $storedPath);

        $duplicate = $this->documents
            ->where('company_id', $companyId)
            ->where('sha256_hash', $hash)
            ->first();

        $status = $duplicate === null ? 'uploaded' : 'duplicate';

        $this->documents->insert([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'uploaded_by' => $userId,
            'original_name' => $file->getClientName(),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sha256_hash' => $hash,
            'status' => $status,
            'duplicate_of_id' => $duplicate['id'] ?? null,
        ]);

        $id = (int) $this->documents->getInsertID();

        (new AuditLogService())->log('ai.document', $status === 'duplicate' ? 'document.duplicate' : 'document.upload', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'user_id' => $userId,
            'table_name' => 'document_uploads',
            'record_id' => $id,
            'record_code' => $file->getClientName(),
            'description' => $status === 'duplicate'
                ? 'Duplicate ERP document uploaded.'
                : 'ERP document uploaded and queued for OCR.',
            'new_values' => [
                'id' => $id,
                'original_name' => $file->getClientName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'sha256_hash' => $hash,
                'status' => $status,
                'duplicate_of_id' => $duplicate['id'] ?? null,
            ],
        ]);

        return $id;
    }
}
