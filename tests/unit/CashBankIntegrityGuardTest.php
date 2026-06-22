<?php

namespace Tests\Unit;

use App\Services\Support\CashBankIntegrityGuard;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

final class CashBankIntegrityGuardTest extends CIUnitTestCase
{
    public function testValidEntryPayloadPasses(): void
    {
        (new CashBankIntegrityGuard())->assertEntryPayload('BANK-001', 'bank_in', 150000, '4100');

        $this->addToAssertionCount(1);
    }

    public function testCounterAccountIsRequired(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Counter GL account is required for cash/bank posting.');

        (new CashBankIntegrityGuard())->assertEntryPayload('BANK-001', 'bank_in', 150000, '');
    }

    public function testInvalidEntryTypeIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid cash/bank entry type.');

        (new CashBankIntegrityGuard())->assertEntryPayload('BANK-001', 'journal', 150000, '4100');
    }

    public function testAccountCurrencyMismatchIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction currency USD does not match cash/bank account currency IDR.');

        (new CashBankIntegrityGuard())->assertCurrency('IDR', 'USD');
    }

    public function testInvalidEntryDateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cash/Bank entry date must use a valid YYYY-MM-DD value.');

        (new CashBankIntegrityGuard())->assertEntryContext('BANK-IDR', '2026-13-01');
    }

    public function testValidReconciliationPayloadPasses(): void
    {
        (new CashBankIntegrityGuard())->assertReconciliationPayload('BR-001', '2026-06-22');

        $this->addToAssertionCount(1);
    }

    public function testInvalidStatementDateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bank statement date must use a valid YYYY-MM-DD value.');

        (new CashBankIntegrityGuard())->assertReconciliationPayload('BR-001', '2026-02-30');
    }

    public function testZeroReconciliationDifferencePasses(): void
    {
        (new CashBankIntegrityGuard())->assertBalancedReconciliation(0.0);

        $this->addToAssertionCount(1);
    }

    public function testNonZeroReconciliationDifferenceIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bank reconciliation difference must be zero before posting. Current difference: 125.50.');

        (new CashBankIntegrityGuard())->assertBalancedReconciliation(125.50);
    }
}
