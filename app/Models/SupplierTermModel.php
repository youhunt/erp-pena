<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierTermModel extends Model
{
    protected $table = 'supplier_terms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'supplier', 'supplier_name',
        'terms_code', 'terms_name', 'terms_days', 'promo_code',
        'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];
    protected $beforeInsert = ['injectSupplierFromRequest'];
    protected $beforeUpdate = ['injectSupplierFromRequest'];

    protected function injectSupplierFromRequest(array $data): array
    {
        $request = service('request');
        $supplier = trim((string) $request->getPost('supplier'));
        $supplierName = trim((string) $request->getPost('supplier_name'));

        $data['data']['supplier'] = $supplier !== '' ? $supplier : null;
        $data['data']['supplier_name'] = $supplier !== '' ? ($supplierName !== '' ? $supplierName : $this->supplierName($supplier)) : null;

        return $data;
    }

    private function supplierName(string $supplier): ?string
    {
        if ($supplier === '') {
            return null;
        }

        $row = (new SupplierModel())->select('supplierna, name')->where('supplier', $supplier)->first();

        return $row['supplierna'] ?? $row['name'] ?? null;
    }
}
