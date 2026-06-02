<?php

namespace App\Controllers\Ai;

use App\Controllers\BaseController;
use App\Services\Ai\Ocr\OcrDiagnosticsService;

class OcrDiagnosticsController extends BaseController
{
    public function index(): string
    {
        return view('ai/ocr/diagnostics', [
            'title' => 'OCR Diagnostics',
            'checks' => (new OcrDiagnosticsService())->check(),
        ]);
    }
}
