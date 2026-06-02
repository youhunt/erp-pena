<?php

namespace App\Services\Ai\Extraction;

interface AiExtractionInterface
{
    /**
     * @return array{provider:string,document_type:?string,fields:array<string,mixed>,line_items:list<array<string,mixed>>,confidence:?float,raw_response:array<string,mixed>|null}
     */
    public function extractFields(string $ocrText, array $documentContext = []): array;
}
