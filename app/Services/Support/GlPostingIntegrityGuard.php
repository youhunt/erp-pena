<?php

namespace App\Services\Support;

use DateTimeImmutable;
use RuntimeException;

class GlPostingIntegrityGuard
{
    public function assertHeader(
        int $companyId,
        string $journalNo,
        string $journalDate,
        string $currencyCode,
        float $exchangeRate,
        string $sourceModule,
        string $sourceType,
        ?int $sourceId
    ): void {
        if ($companyId < 1) {
            throw new RuntimeException('Company is required for GL entry.');
        }
        if ($journalNo === '') {
            throw new RuntimeException('GL journal number is required.');
        }
        if (strlen($journalNo) > 80) {
            throw new RuntimeException('GL journal number may not exceed 80 characters.');
        }
        if (! $this->isValidDate($journalDate)) {
            throw new RuntimeException('GL journal date must use a valid YYYY-MM-DD value.');
        }
        if ($currencyCode === '' || strlen($currencyCode) > 10) {
            throw new RuntimeException('GL currency code is required and may not exceed 10 characters.');
        }
        if ($exchangeRate <= 0) {
            throw new RuntimeException('GL exchange rate must be greater than zero.');
        }
        if ($sourceId !== null && $sourceId < 1) {
            throw new RuntimeException('GL source ID must be a positive integer.');
        }
        if ($sourceId !== null && ($sourceModule === '' || $sourceType === '')) {
            throw new RuntimeException('GL source module and source type are required when source ID is provided.');
        }
    }

    /**
     * @return array{module:string,type:string,id:int}|null
     */
    public function sourceKey(string $sourceModule, string $sourceType, ?int $sourceId): ?array
    {
        if ($sourceId === null) {
            return null;
        }

        return [
            'module' => $sourceModule,
            'type' => $sourceType,
            'id' => $sourceId,
        ];
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
