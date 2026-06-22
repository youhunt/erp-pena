<?php

namespace Tests\Unit;

use App\Services\Support\GlPostingIntegrityGuard;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

final class GlPostingIntegrityGuardTest extends CIUnitTestCase
{
    public function testValidTransactionSourcePasses(): void
    {
        (new GlPostingIntegrityGuard())->assertHeader(
            1,
            'GL-SI-001',
            '2026-06-22',
            'IDR',
            1,
            'ar',
            'sales_invoice',
            10
        );

        $this->addToAssertionCount(1);
    }

    public function testManualJournalWithoutSourceIdPasses(): void
    {
        (new GlPostingIntegrityGuard())->assertHeader(
            1,
            'JE-001',
            '2026-06-22',
            'IDR',
            1,
            'manual',
            'manual_journal',
            null
        );

        $this->addToAssertionCount(1);
    }

    public function testInvalidJournalDateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GL journal date must use a valid YYYY-MM-DD value.');

        (new GlPostingIntegrityGuard())->assertHeader(1, 'JE-001', '2026-02-30', 'IDR', 1, 'manual', 'manual_journal', null);
    }

    public function testNonPositiveExchangeRateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GL exchange rate must be greater than zero.');

        (new GlPostingIntegrityGuard())->assertHeader(1, 'JE-001', '2026-06-22', 'IDR', 0, 'manual', 'manual_journal', null);
    }

    public function testIncompleteTransactionSourceIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GL source module and source type are required when source ID is provided.');

        (new GlPostingIntegrityGuard())->assertHeader(1, 'JE-001', '2026-06-22', 'IDR', 1, '', 'sales_invoice', 10);
    }

    public function testSourceKeyIsOnlyBuiltForTransactionSource(): void
    {
        $guard = new GlPostingIntegrityGuard();

        $this->assertNull($guard->sourceKey('manual', 'manual_journal', null));
        $this->assertSame(
            ['module' => 'ar', 'type' => 'sales_invoice', 'id' => 10],
            $guard->sourceKey('ar', 'sales_invoice', 10)
        );
    }
}
