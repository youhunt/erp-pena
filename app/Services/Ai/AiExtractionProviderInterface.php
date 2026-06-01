<?php

namespace App\Services\Ai;

interface AiExtractionProviderInterface
{
    public function extractFields(string $documentType, string $rawText, array $options = []): ExtractionResult;
}
