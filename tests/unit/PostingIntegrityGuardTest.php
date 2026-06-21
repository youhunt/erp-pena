<?php

namespace Tests\Unit;

use App\Services\Support\PostingIntegrityGuard;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

final class PostingIntegrityGuardTest extends CIUnitTestCase
{
    public function testZeroValueDoesNotRequireGlEntry(): void
    {
        (new PostingIntegrityGuard())->assertGlEntryForAmount(0.0, null, 'Purchase receipt');

        $this->addToAssertionCount(1);
    }

    public function testValuedTransactionWithGlEntryPasses(): void
    {
        (new PostingIntegrityGuard())->assertGlEntryForAmount(125000.0, 77, 'Sales delivery');

        $this->addToAssertionCount(1);
    }

    public function testValuedTransactionWithoutGlEntryIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Purchase receipt GL posting is required for a valued transaction.');

        (new PostingIntegrityGuard())->assertGlEntryForAmount(125000.0, null, 'Purchase receipt');
    }

    public function testReversalWithOriginalGlLinesPasses(): void
    {
        (new PostingIntegrityGuard())->assertReversalLines([
            ['account_no' => '1300', 'debit' => 125000, 'credit' => 0],
        ], 'Purchase receipt');

        $this->addToAssertionCount(1);
    }

    public function testReversalWithoutOriginalGlLinesIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sales delivery original GL entry has no lines and cannot be reversed safely.');

        (new PostingIntegrityGuard())->assertReversalLines([], 'Sales delivery');
    }
}
