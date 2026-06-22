<?php

namespace App\Services\Support;

use DateTimeImmutable;
use RuntimeException;

class PeriodCloseIntegrityGuard
{
    /**
     * @param list<string> $allowedModules
     */
    public function assertCloseContext(int $companyId, string $module, string $period, array $allowedModules): void
    {
        if ($companyId < 1) {
            throw new RuntimeException('Company is required for period close.');
        }
        if (! in_array($module, $allowedModules, true)) {
            throw new RuntimeException('Invalid period close module.');
        }
        if (! $this->isValidPeriod($period)) {
            throw new RuntimeException('Period must use a valid YYYY-MM value.');
        }
    }

    /**
     * @param list<string> $allowedModules
     */
    public function postingPeriod(string $module, int $companyId, ?string $date, array $allowedModules): string
    {
        if ($companyId < 1) {
            throw new RuntimeException('Company is required for period validation.');
        }
        if (! in_array($module, $allowedModules, true)) {
            throw new RuntimeException('Invalid period close module: ' . $module . '.');
        }

        $dateValue = substr(trim((string) $date), 0, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if ($parsed === false || $parsed->format('Y-m-d') !== $dateValue) {
            throw new RuntimeException('Transaction date must use a valid YYYY-MM-DD value for period validation.');
        }

        return $parsed->format('Y-m');
    }

    public function siteScopeId(?int $siteId): int
    {
        if ($siteId !== null && $siteId < 1) {
            throw new RuntimeException('Site ID must be a positive integer when provided.');
        }

        return $siteId ?? 0;
    }

    public function assertCanClose(?string $status): void
    {
        if (strtolower(trim((string) $status)) === 'closed') {
            throw new RuntimeException('This period is already closed.');
        }
    }

    public function assertCanReopen(?string $status): void
    {
        if (strtolower(trim((string) $status)) !== 'closed') {
            throw new RuntimeException('Only a closed period can be reopened.');
        }
    }

    private function isValidPeriod(string $period): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m', $period);

        return $parsed !== false && $parsed->format('Y-m') === $period;
    }
}
