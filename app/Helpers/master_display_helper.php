<?php

use Config\Database;

if (! function_exists('erp_master_label')) {
    function erp_master_label(string $table, mixed $rawValue = null, mixed $idValue = null, array $codeFields = ['code'], array $nameFields = ['name']): string
    {
        $raw = trim((string) ($rawValue ?? ''));
        $id = (int) ($idValue ?? 0);

        try {
            $db = Database::connect();
            if (! $db->tableExists($table)) {
                return $raw !== '' ? $raw : ($id > 0 ? (string) $id : '-');
            }

            $builder = $db->table($table);
            if ($id > 0) {
                $builder->where('id', $id);
            } elseif ($raw !== '') {
                $usable = [];
                foreach ($codeFields as $field) {
                    if ($db->fieldExists($field, $table)) {
                        $usable[] = $field;
                    }
                }
                if ($usable === []) {
                    return $raw;
                }
                $builder->groupStart();
                foreach ($usable as $index => $field) {
                    $index === 0 ? $builder->where($field, $raw) : $builder->orWhere($field, $raw);
                }
                $builder->groupEnd();
            } else {
                return '-';
            }

            if ($db->fieldExists('deleted_at', $table)) {
                $builder->where('deleted_at', null);
            }

            $row = $builder->get(1)->getRowArray();
            if (! $row) {
                return $raw !== '' ? $raw : ($id > 0 ? (string) $id : '-');
            }

            $code = '';
            foreach ($codeFields as $field) {
                if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                    $code = trim((string) $row[$field]);
                    break;
                }
            }
            $name = '';
            foreach ($nameFields as $field) {
                if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                    $name = trim((string) $row[$field]);
                    break;
                }
            }

            if ($code !== '' && $name !== '' && strcasecmp($code, $name) !== 0) {
                return $code . ' - ' . $name;
            }
            return $code !== '' ? $code : ($name !== '' ? $name : ($raw !== '' ? $raw : (string) $id));
        } catch (\Throwable) {
            return $raw !== '' ? $raw : ($id > 0 ? (string) $id : '-');
        }
    }
}

if (! function_exists('erp_company_label')) {
    function erp_company_label(array $row): string
    {
        return erp_master_label('companies', $row['company'] ?? null, $row['company_id'] ?? null, ['code', 'company_code'], ['name', 'company_name']);
    }
}

if (! function_exists('erp_site_label')) {
    function erp_site_label(array $row): string
    {
        return erp_master_label('sites', $row['site'] ?? null, $row['site_id'] ?? null, ['code', 'site_code'], ['name', 'site_name']);
    }
}

if (! function_exists('erp_warehouse_label')) {
    function erp_warehouse_label(array $row, string $rawField = 'warehouse', string $idField = 'warehouse_id'): string
    {
        return erp_master_label('warehouses', $row[$rawField] ?? $row['whs'] ?? null, $row[$idField] ?? null, ['code', 'warehouse_code', 'whs'], ['name', 'warehouse_name', 'description']);
    }
}

if (! function_exists('erp_location_label')) {
    function erp_location_label(array $row, string $rawField = 'location', string $idField = 'location_id'): string
    {
        return erp_master_label('locations', $row[$rawField] ?? $row['loc'] ?? null, $row[$idField] ?? null, ['code', 'location_code', 'loc'], ['name', 'location_name', 'description']);
    }
}
