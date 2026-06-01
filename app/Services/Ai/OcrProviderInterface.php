<?php

namespace App\Services\Ai;

interface OcrProviderInterface
{
    public function extractText(string $absoluteFilePath, array $options = []): OcrResult;
}
