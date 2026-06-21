<?php

namespace App\Services\Support;

use RuntimeException;

final class PostingIntegrityGuard
{
    public function assertGlEntryForAmount(float $amount, ?int $glEntryId, string $documentLabel): void
    {
        if (abs(round($amount, 2)) > 0 && ($glEntryId === null || $glEntryId < 1)) {
            throw new RuntimeException($documentLabel . ' GL posting is required for a valued transaction.');
        }
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    public function assertReversalLines(array $lines, string $documentLabel): void
    {
        if ($lines === []) {
            throw new RuntimeException($documentLabel . ' original GL entry has no lines and cannot be reversed safely.');
        }
    }
}
