<?php

namespace App\Services\Ai\Extraction;

final class NullAiExtraction implements AiExtractionInterface
{
    public function extractFields(string $ocrText, array $documentContext = []): array
    {
        return [
            'provider' => 'null_ai_extraction',
            'document_type' => null,
            'fields' => [],
            'line_items' => [],
            'confidence' => 0.0,
            'raw_response' => [
                'message' => 'No AI extraction provider configured yet.',
                'ocr_text_length' => strlen($ocrText),
                'context' => $documentContext,
            ],
        ];
    }
}
