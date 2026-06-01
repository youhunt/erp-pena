<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;

final class PenaFoundationTest extends CIUnitTestCase
{
    public function testErpRolesAreConfigured(): void
    {
        $groups = new AuthGroups();

        $this->assertArrayHasKey('superadmin', $groups->groups);
        $this->assertArrayHasKey('company_admin', $groups->groups);
        $this->assertArrayHasKey('finance', $groups->groups);
        $this->assertArrayHasKey('ai.document.upload', $groups->permissions);
    }

    public function testFoundationMigrationExists(): void
    {
        $path = APPPATH . 'Database/Migrations/2026-06-01-000001_CreatePenaErpFoundation.php';

        $this->assertFileExists($path);
    }

    public function testCoreMasterModelsExist(): void
    {
        $this->assertTrue(class_exists(\App\Models\CustomerModel::class));
        $this->assertTrue(class_exists(\App\Models\SupplierModel::class));
        $this->assertTrue(class_exists(\App\Models\ItemModel::class));
        $this->assertTrue(class_exists(\App\Models\WarehouseModel::class));
    }
}
