<?php

namespace App\Services\Ai\Ocr;

interface OcrEngineInterface
{
    /**
     * @return array{provider:string,text:string,confidence:?float,raw_response:array<string,mixed>|null}
     */
    public function extractText(string $filePath, string $mimeType): array;
}
