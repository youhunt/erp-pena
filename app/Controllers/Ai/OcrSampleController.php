<?php

namespace App\Controllers\Ai;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

class OcrSampleController extends BaseController
{
    public function index(): string
    {
        return view('ai/ocr/samples/index', ['title' => 'OCR Sample Documents']);
    }

    public function show(string $type): string
    {
        if (! in_array($type, ['po', 'so'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('ai/ocr/samples/' . $type, [
            'title' => strtoupper($type) . ' OCR Sample',
        ]);
    }
}
