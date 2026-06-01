<?php

namespace App\Controllers\Ai;

use App\Controllers\BaseController;
use App\Models\DocumentUploadModel;
use App\Services\Ai\DocumentProcessingService;
use App\Services\TenantContext;
use RuntimeException;

class DocumentController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $documents = new DocumentUploadModel();

        if ($tenant->activeCompanyId() !== null) {
            $documents->where('company_id', $tenant->activeCompanyId());
        }

        if ($tenant->activeSiteId() !== null) {
            $documents->where('site_id', $tenant->activeSiteId());
        }

        return view('ai/documents/index', [
            'title' => 'AI Documents',
            'documents' => $documents->orderBy('created_at', 'DESC')->findAll(50),
        ]);
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
            (new DocumentProcessingService())->registerUpload(
                $file,
                $companyId,
                $tenant->activeSiteId(),
                auth()->id(),
            );
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/ai-documents')->with('message', 'Document uploaded and queued for OCR processing.');
    }
}
