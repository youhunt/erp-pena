<?php

namespace App\Services\Ai;

class OcrResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $text,
        public readonly float $confidence,
        public readonly array $metadata = [],
    ) {
    }
}
