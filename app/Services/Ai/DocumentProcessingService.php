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
        $clientName = $file->getClientName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
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

        if ($hash === false) {
            throw new RuntimeException('Failed to calculate document hash.');
        }

        $duplicate = $this->documents
            ->where('company_id', $companyId)
            ->where('sha256_hash', $hash)
            ->first();

        $status = $duplicate === null ? 'uploaded' : 'duplicate';

        $this->documents->insert([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'uploaded_by' => $userId,
            'original_name' => $clientName,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
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
            'record_code' => $clientName,
            'description' => $status === 'duplicate'
                ? 'Duplicate ERP document uploaded.'
                : 'ERP document uploaded and queued for OCR.',
            'new_values' => [
                'id' => $id,
                'original_name' => $clientName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'sha256_hash' => $hash,
                'status' => $status,
                'duplicate_of_id' => $duplicate['id'] ?? null,
            ],
        ]);

        return $id;
    }
}
