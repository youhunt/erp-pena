<?php

namespace App\Controllers\Ai;

use App\Controllers\BaseController;
use App\Models\DocumentUploadModel;
use App\Services\Ai\DocumentProcessingService;
use App\Services\Ai\DocumentProcessorService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class DocumentController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $documents = $this->scopedDocuments($tenant);

        return view('ai/documents/index', [
            'title' => 'AI Documents',
            'documents' => $documents->orderBy('created_at', 'DESC')->findAll(100),
        ]);
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $document = $this->scopedDocuments($tenant)->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('ai/documents/show', [
            'title' => 'Document Detail',
            'document' => $document,
            'ocrResult' => $this->latestRow('document_ocr_results', $id),
            'extraction' => $this->latestRow('document_extractions', $id),
            'processingLogs' => Database::connect()->table('document_processing_logs')
                ->where('document_upload_id', $id)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray(),
        ]);
    }

    public function process(int $id)
    {
        $tenant = new TenantContext(session());
        $document = $this->scopedDocuments($tenant)->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            (new DocumentProcessorService())->process($id, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->to('/ai-documents/' . $id)->with('error', $exception->getMessage());
        }

        return redirect()->to('/ai-documents/' . $id)->with('message', 'Document OCR/AI processing completed.');
    }

    public function upload(): string
    {
        return view('ai/documents/upload', ['title' => 'Upload Document']);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->with('error', 'Active company is required before uploading documents.');
        }

        $file = $this->request->getFile('document');
        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Please select a valid PDF or image document.');
        }

        try {
            $id = (new DocumentProcessingService())->registerUpload(
                $file,
                $companyId,
                $tenant->activeSiteId(),
                auth()->id(),
            );
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/ai-documents/' . $id)->with('message', 'Document uploaded and queued for OCR processing.');
    }

    private function scopedDocuments(TenantContext $tenant): DocumentUploadModel
    {
        $documents = new DocumentUploadModel();

        if ($tenant->activeCompanyId() !== null) {
            $documents->where('company_id', $tenant->activeCompanyId());
        }

        if ($tenant->activeSiteId() !== null) {
            $documents->where('site_id', $tenant->activeSiteId());
        }

        return $documents;
    }

    private function latestRow(string $table, int $documentId): ?array
    {
        return Database::connect()->table($table)
            ->where('document_upload_id', $documentId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();
    }
}
