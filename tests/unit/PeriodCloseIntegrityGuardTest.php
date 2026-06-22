<?php

namespace Tests\Unit;

use App\Services\Support\PeriodCloseIntegrityGuard;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

final class PeriodCloseIntegrityGuardTest extends CIUnitTestCase
{
    private array $modules = ['sales', 'purchase', 'inventory', 'gl'];

    public function testValidCloseContextPasses(): void
    {
        (new PeriodCloseIntegrityGuard())->assertCloseContext(1, 'inventory', '2026-06', $this->modules);

        $this->addToAssertionCount(1);
    }

    public function testInvalidCalendarPeriodIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Period must use a valid YYYY-MM value.');

        (new PeriodCloseIntegrityGuard())->assertCloseContext(1, 'inventory', '2026-13', $this->modules);
    }

    public function testInvalidModuleIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid period close module.');

        (new PeriodCloseIntegrityGuard())->assertCloseContext(1, 'unknown', '2026-06', $this->modules);
    }

    public function testPostingDateReturnsPeriod(): void
    {
        $period = (new PeriodCloseIntegrityGuard())->postingPeriod('sales', 1, '2026-06-22 10:15:00', $this->modules);

        $this->assertSame('2026-06', $period);
    }

    public function testInvalidPostingDateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction date must use a valid YYYY-MM-DD value for period validation.');

        (new PeriodCloseIntegrityGuard())->postingPeriod('sales', 1, '2026-02-30', $this->modules);
    }

    public function testSiteScopeUsesZeroForAllSites(): void
    {
        $guard = new PeriodCloseIntegrityGuard();

        $this->assertSame(0, $guard->siteScopeId(null));
        $this->assertSame(14, $guard->siteScopeId(14));
    }

    public function testRepeatedCloseIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This period is already closed.');

        (new PeriodCloseIntegrityGuard())->assertCanClose('closed');
    }

    public function testOnlyClosedPeriodCanBeReopened(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only a closed period can be reopened.');

        (new PeriodCloseIntegrityGuard())->assertCanReopen('open');
    }
}
