<?php

namespace App\Services\Ai;

class ExtractionResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $documentType,
        public readonly array $fields,
        public readonly float $confidence,
        public readonly array $metadata = [],
    ) {
    }
}
