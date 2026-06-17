<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerTermModel extends Model
{
    protected $table = 'customer_terms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'customer', 'customer_name',
        'terms_code', 'terms_name', 'terms_days', 'promo_code',
        'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];
    protected $beforeInsert = ['injectCustomerFromRequest'];
    protected $beforeUpdate = ['injectCustomerFromRequest'];

    protected function injectCustomerFromRequest(array $data): array
    {
        $request = service('request');
        $customer = trim((string) $request->getPost('customer'));
        $customerName = trim((string) $request->getPost('customer_name'));

        $data['data']['customer'] = $customer !== '' ? $customer : null;
        $data['data']['customer_name'] = $customer !== '' ? ($customerName !== '' ? $customerName : $this->customerName($customer)) : null;

        return $data;
    }

    private function customerName(string $customer): ?string
    {
        if ($customer === '') {
            return null;
        }

        $row = (new CustomerModel())->select('customern, name')->where('customer', $customer)->first();

        return $row['customern'] ?? $row['name'] ?? null;
    }
}
