<?php

namespace App\Controllers\Ai;

use App\Controllers\BaseController;
use App\Models\DocumentUploadModel;
use App\Services\Ai\ConvertToPurchaseOrderService;
use App\Services\Ai\DocumentProcessingService;
use App\Services\Ai\DocumentProcessorService;
use App\Services\Ai\DocumentReviewService;
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

    public function review(int $id): string
    {
        $tenant = new TenantContext(session());
        $document = $this->scopedDocuments($tenant)->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $extraction = $this->latestRow('document_extractions', $id);
        if ($extraction === null) {
            return redirect()->to('/ai-documents/' . $id)->with('error', 'Process OCR/AI before reviewing this document.');
        }

        return view('ai/documents/review', [
            'title' => 'Review AI Extraction',
            'document' => $document,
            'extraction' => $extraction,
            'fieldsJson' => $this->prettyJson($extraction['extracted_fields'] ?? null),
            'lineItemsJson' => $this->prettyJson($extraction['line_items'] ?? null),
        ]);
    }

    public function saveReview(int $id)
    {
        $tenant = new TenantContext(session());
        $document = $this->scopedDocuments($tenant)->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $extraction = $this->latestRow('document_extractions', $id);
        if ($extraction === null) {
            return redirect()->to('/ai-documents/' . $id)->with('error', 'Extraction result was not found.');
        }

        $fields = json_decode((string) $this->request->getPost('extracted_fields'), true);
        $lineItems = json_decode((string) $this->request->getPost('line_items'), true);

        if (! is_array($fields) || ! is_array($lineItems)) {
            return redirect()->back()->withInput()->with('error', 'Fields and line items must be valid JSON.');
        }

        $status = (string) ($this->request->getPost('review_status') ?: 'pending_review');

        try {
            (new DocumentReviewService())->updateReview((int) $extraction['id'], $fields, $lineItems, $status, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/ai-documents/' . $id)->with('message', 'Document review saved.');
    }

    public function convertToPo(int $id)
    {
        $tenant = new TenantContext(session());
        $document = $this->scopedDocuments($tenant)->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $poId = (new ConvertToPurchaseOrderService())->convert($id, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->to('/ai-documents/' . $id)->with('error', $exception->getMessage());
        }

        return redirect()->to('/purchase/orders/' . $poId)->with('message', 'Reviewed document converted to Purchase Order.');
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
            $id = (new DocumentProcessingService())->registerUpload($file, $companyId, $tenant->activeSiteId(), auth()->id());
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

    private function prettyJson(?string $json): string
    {
        if ($json === null || trim($json) === '') {
            return "[]";
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $json;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: "[]";
    }
}
