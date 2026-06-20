<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $beforeInsert = ['normalizeMaster'];
    protected $beforeUpdate = ['normalizeMaster'];
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'code', 'name',
        'item_code', 'item_name', 'item_coded', 'item_named',
        'shelf_life', 'stockuom', 'purchaseuom', 'sellinguom', 'stockwhs',
        'item_price', 'purchasep', 'sellingprice', 'vat',
        'item_length', 'item_width', 'item_heigh', 'item_diam',
        'item_lengt', 'item_widthh', 'item_heigh_uom', 'item_diam_uom',
        'out_length', 'out_width', 'out_height', 'out_diame',
        'out_lengt', 'out_widthh', 'out_height_uom', 'out_diame_uom',
        'item_group', 'item_subg', 'item_class', 'item_subc',
        'item_type', 'item_subty', 'item_atribu',
        'active', 'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected function normalizeMaster(array $payload): array
    {
        $data = $payload['data'] ?? [];
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }

        $code = $this->firstNonEmpty($data, ['item_code', 'code', 'item_coded']);
        $name = $this->firstNonEmpty($data, ['item_name', 'name', 'item_named']);
        if ($code !== '') {
            $code = strtoupper($code);
            $data['item_code'] = $data['item_code'] ?? $code;
            $data['code'] = $data['code'] ?? $code;
        }
        if ($name !== '') {
            $data['item_name'] = $data['item_name'] ?? $name;
            $data['name'] = $data['name'] ?? $name;
        }

        foreach (['stockuom', 'purchaseuom', 'sellinguom'] as $uomField) {
            if (! empty($data[$uomField])) {
                $data[$uomField] = strtoupper((string) $data[$uomField]);
            }
        }
        if (empty($data['purchaseuom']) && ! empty($data['stockuom'])) {
            $data['purchaseuom'] = $data['stockuom'];
        }
        if (empty($data['sellinguom']) && ! empty($data['stockuom'])) {
            $data['sellinguom'] = $data['stockuom'];
        }
        foreach (['item_price', 'purchasep', 'sellingprice'] as $amountField) {
            if (array_key_exists($amountField, $data)) {
                $data[$amountField] = $this->toNumber($data[$amountField]);
            }
        }
        if (! array_key_exists('item_price', $data) && array_key_exists('sellingprice', $data)) {
            $data['item_price'] = $data['sellingprice'];
        }
        if (! array_key_exists('sellingprice', $data) && array_key_exists('item_price', $data)) {
            $data['sellingprice'] = $data['item_price'];
        }
        if (! array_key_exists('purchasep', $data) && array_key_exists('item_price', $data)) {
            $data['purchasep'] = $data['item_price'];
        }
        if (array_key_exists('active', $data) && ! array_key_exists('is_active', $data)) {
            $data['is_active'] = (int) (bool) $data['active'];
        }
        if (! array_key_exists('is_active', $data) && ! array_key_exists('active', $data)) {
            $data['is_active'] = 1;
            $data['active'] = 1;
        }

        $payload['data'] = $data;
        return $this->guardDuplicateCode($payload);
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

    private function toNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        $value = str_contains($value, ',') && ! str_contains($value, '.') ? str_replace(',', '.', $value) : str_replace(',', '', $value);
        return (float) $value;
    }

    private function guardDuplicateCode(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '' || empty($data['company_id'])) {
            return $payload;
        }
        $builder = db_connect()->table($this->table)->where('company_id', (int) $data['company_id'])->where('code', $code)->where('deleted_at', null);
        if (array_key_exists('site_id', $data)) {
            empty($data['site_id']) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $data['site_id']);
        }
        $id = $payload['id'] ?? null;
        if (is_array($id)) {
            $id = reset($id);
        }
        if ((int) $id > 0) {
            $builder->where('id !=', (int) $id);
        }
        if ($builder->countAllResults() > 0) {
            throw new \RuntimeException('Item code already exists for active company/site: ' . $code);
        }
        return $payload;
    }
}
