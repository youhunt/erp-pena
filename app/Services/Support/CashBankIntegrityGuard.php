<?php

namespace App\Services\Support;

use DateTimeImmutable;
use RuntimeException;

final class CashBankIntegrityGuard
{
    public function assertEntryPayload(
        string $entryNo,
        string $entryType,
        float $amount,
        string $counterAccountNo
    ): void {
        if (trim($entryNo) === '') {
            throw new RuntimeException('Cash/Bank entry number is required.');
        }
        if (! in_array($entryType, ['cash_in', 'cash_out', 'bank_in', 'bank_out'], true)) {
            throw new RuntimeException('Invalid cash/bank entry type.');
        }
        if (round($amount, 2) <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }
        if (trim($counterAccountNo) === '') {
            throw new RuntimeException('Counter GL account is required for cash/bank posting.');
        }
    }

    public function assertCurrency(string $accountCurrency, string $requestedCurrency): void
    {
        $accountCurrency = strtoupper(trim($accountCurrency));
        $requestedCurrency = strtoupper(trim($requestedCurrency));

        if ($requestedCurrency !== '' && $accountCurrency !== '' && $requestedCurrency !== $accountCurrency) {
            throw new RuntimeException(
                'Transaction currency ' . $requestedCurrency . ' does not match cash/bank account currency ' . $accountCurrency . '.'
            );
        }
    }

    public function assertEntryContext(string $cashBankCode, string $entryDate): void
    {
        if (trim($cashBankCode) === '') {
            throw new RuntimeException('Cash/Bank account is required.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $entryDate);
        if ($date === false || $date->format('Y-m-d') !== $entryDate) {
            throw new RuntimeException('Cash/Bank entry date must use a valid YYYY-MM-DD value.');
        }
    }

    public function assertReconciliationPayload(string $reconcileNo, string $statementDate): void
    {
        if (trim($reconcileNo) === '') {
            throw new RuntimeException('Bank reconciliation number is required.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $statementDate);
        if ($date === false || $date->format('Y-m-d') !== $statementDate) {
            throw new RuntimeException('Bank statement date must use a valid YYYY-MM-DD value.');
        }
    }

    public function assertBalancedReconciliation(float $difference): void
    {
        if (abs(round($difference, 2)) > 0.009) {
            throw new RuntimeException(
                'Bank reconciliation difference must be zero before posting. Current difference: '
                . number_format($difference, 2, '.', '') . '.'
            );
        }
    }
}
