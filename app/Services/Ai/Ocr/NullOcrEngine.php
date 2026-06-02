<?php

namespace App\Services\Ai\Ocr;

final class NullOcrEngine implements OcrEngineInterface
{
    public function extractText(string $filePath, string $mimeType): array
    {
        return [
            'provider' => 'null_ocr',
            'text' => '',
            'confidence' => 0.0,
            'raw_response' => [
                'message' => 'No OCR provider configured yet.',
                'file_path_exists' => is_file($filePath),
                'mime_type' => $mimeType,
            ],
        ];
    }
}
