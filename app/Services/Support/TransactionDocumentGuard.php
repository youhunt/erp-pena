<?php

namespace App\Services\Support;

use RuntimeException;

final class TransactionDocumentGuard
{
    /**
     * Transaction documents in one chain must belong to the same company and site.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $target
     */
    public function assertSameTenant(array $source, array $target, string $label): void
    {
        $sourceCompanyId = (int) ($source['company_id'] ?? 0);
        $targetCompanyId = (int) ($target['company_id'] ?? 0);

        if ($sourceCompanyId < 1 || $targetCompanyId < 1 || $sourceCompanyId !== $targetCompanyId) {
            throw new RuntimeException($label . ' belongs to a different company.');
        }

        $sourceSiteId = $this->nullableId($source['site_id'] ?? null);
        $targetSiteId = $this->nullableId($target['site_id'] ?? null);
        if ($sourceSiteId !== $targetSiteId) {
            throw new RuntimeException($label . ' belongs to a different site.');
        }
    }

    private function nullableId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
