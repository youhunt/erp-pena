<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $beforeInsert = ['normalizeMaster'];
    protected $beforeUpdate = ['normalizeMaster'];

    protected $allowedFields = [
        'company_id', 'site_id', 'code', 'name', 'terms_code', 'currency_code', 'tax_number', 'address', 'phone', 'email', 'is_active',
        'company', 'site', 'customer', 'customern', 'customerr', 'contactnar', 'description', 'shipwhs',
        'officeaddre', 'officecity', 'officeprovir', 'officecount', 'officeposta', 'officeconta', 'officephon', 'officehp',
        'taxcode', 'taxnumber', 'vat', 'limitamound', 'limitqty', 'terms', 'limitdays', 'salescode', 'salesname',
        'bank1', 'bankaccou', 'bank2', 'bankaccou2',
        'billingcust', 'billingtoc', 'billingaddre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp',
        'mailcustom', 'mailcode', 'mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp',
        'shiptocust', 'shiptocode', 'shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocour', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp',
        'active', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected function normalizeMaster(array $payload): array
    {
        $data = $payload['data'] ?? [];

        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }

        $code = $this->firstNonEmpty($data, ['customer', 'code']);
        $name = $this->firstNonEmpty($data, ['customern', 'name']);

        if ($code !== '') {
            $code = strtoupper($code);
            $data['customer'] = $data['customer'] ?? $code;
            $data['code'] = $data['code'] ?? $code;
        }

        if ($name !== '') {
            $data['customern'] = $data['customern'] ?? $name;
            $data['name'] = $data['name'] ?? $name;
        }

        if (! isset($data['terms_code']) && ! empty($data['terms'])) {
            $data['terms_code'] = strtoupper((string) $data['terms']);
        }
        if (! isset($data['tax_number']) && ! empty($data['taxnumber'])) {
            $data['tax_number'] = (string) $data['taxnumber'];
        }
        if (! isset($data['address']) && ! empty($data['officeaddre'])) {
            $data['address'] = (string) $data['officeaddre'];
        }
        if (! isset($data['phone']) && ! empty($data['officephon'])) {
            $data['phone'] = (string) $data['officephon'];
        }
        if (array_key_exists('active', $data) && ! array_key_exists('is_active', $data)) {
            $data['is_active'] = (int) (bool) $data['active'];
        }
        if (! array_key_exists('is_active', $data) && ! array_key_exists('active', $data)) {
            $data['is_active'] = 1;
            $data['active'] = 1;
        }

        $payload['data'] = $data;
        return $this->assertUniqueCode($payload);
    }

    private function firstNonEmpty(array $data, array $fields): string
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && trim((string) $data[$field]) !== '') {
                return trim((string) $data[$field]);
            }
        }

        return '';
    }

    private function assertUniqueCode(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '' || empty($data['company_id'])) {
            return $payload;
        }

        $builder = db_connect()->table($this->table)
            ->where('company_id', (int) $data['company_id'])
            ->where('code', $code)
            ->where('deleted_at', null);

        if (array_key_exists('site_id', $data)) {
            empty($data['site_id']) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $data['site_id']);
        }

        $currentId = $this->callbackId($payload['id'] ?? null);
        if ($currentId !== null) {
            $builder->where('id !=', $currentId);
        }

        if ($builder->countAllResults() > 0) {
            throw new \RuntimeException('Customer code already exists for active company/site: ' . $code);
        }

        return $payload;
    }

    private function callbackId(mixed $id): ?int
    {
        if (is_array($id)) {
            $id = reset($id);
        }
        $id = (int) $id;

        return $id > 0 ? $id : null;
    }
}
