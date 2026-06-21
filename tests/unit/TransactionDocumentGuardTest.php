<?php

namespace Tests\Unit;

use App\Services\Support\TransactionDocumentGuard;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

final class TransactionDocumentGuardTest extends CIUnitTestCase
{
    public function testSameCompanyAndSitePasses(): void
    {
        (new TransactionDocumentGuard())->assertSameTenant(
            ['company_id' => 10, 'site_id' => 20],
            ['company_id' => '10', 'site_id' => '20'],
            'Document'
        );

        $this->addToAssertionCount(1);
    }

    public function testCompanyWideDocumentsPassWhenBothSitesAreEmpty(): void
    {
        (new TransactionDocumentGuard())->assertSameTenant(
            ['company_id' => 10, 'site_id' => null],
            ['company_id' => 10, 'site_id' => 0],
            'Document'
        );

        $this->addToAssertionCount(1);
    }

    public function testDifferentCompanyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Purchase order belongs to a different company.');

        (new TransactionDocumentGuard())->assertSameTenant(
            ['company_id' => 10, 'site_id' => 20],
            ['company_id' => 11, 'site_id' => 20],
            'Purchase order'
        );
    }

    public function testDifferentSiteIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Purchase receipt belongs to a different site.');

        (new TransactionDocumentGuard())->assertSameTenant(
            ['company_id' => 10, 'site_id' => 20],
            ['company_id' => 10, 'site_id' => 21],
            'Purchase receipt'
        );
    }

    public function testMissingTargetSiteCannotBypassSiteGuard(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A/P payable belongs to a different site.');

        (new TransactionDocumentGuard())->assertSameTenant(
            ['company_id' => 10, 'site_id' => 20],
            ['company_id' => 10, 'site_id' => null],
            'A/P payable'
        );
    }
}
