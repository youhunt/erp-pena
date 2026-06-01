<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class MasterDataTransferController extends BaseController
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $resources = [
        'transaction-codes' => ['title' => 'Transaction Codes', 'table' => 'transaction_codes', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active']],
        'prefix-codes' => ['title' => 'Prefix Codes', 'table' => 'prefix_codes', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active']],
        'companies' => ['title' => 'Companies', 'table' => 'companies', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'legal_name', 'tax_number', 'base_currency', 'address', 'is_active']],
        'sites' => ['title' => 'Sites', 'table' => 'sites', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'address', 'is_active']],
        'departments' => ['title' => 'Departments', 'table' => 'departments', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'description', 'is_active']],
        'warehouses' => ['title' => 'Warehouses', 'table' => 'warehouses', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'description', 'is_active']],
        'locations' => ['title' => 'Locations', 'table' => 'locations', 'tenant' => true, 'site' => true, 'fields' => ['warehouse_id', 'code', 'name', 'description', 'is_active']],
        'countries' => ['title' => 'Countries', 'table' => 'countries', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'is_active']],
        'provinces' => ['title' => 'Provinces', 'table' => 'provinces', 'tenant' => false, 'site' => false, 'fields' => ['parent_id', 'code', 'name', 'is_active']],
        'cities' => ['title' => 'Cities', 'table' => 'cities', 'tenant' => false, 'site' => false, 'fields' => ['parent_id', 'code', 'name', 'is_active']],
        'postal-codes' => ['title' => 'Postal Codes', 'table' => 'postal_codes', 'tenant' => false, 'site' => false, 'fields' => ['country_id', 'province_id', 'city_id', 'code', 'name', 'district', 'village', 'is_active']],
        'currencies' => ['title' => 'Currencies', 'table' => 'currencies', 'tenant' => false, 'site' => false, 'fields' => ['code', 'name', 'rounding', 'is_active']],
        'uoms' => ['title' => 'UoM', 'table' => 'uoms', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'description', 'is_active']],
        'uom-conversions' => ['title' => 'UoM Conversions', 'table' => 'uom_conversions', 'tenant' => true, 'site' => false, 'fields' => ['from_uom_id', 'to_uom_id', 'multiplier', 'divider', 'is_active']],
        'vat' => ['title' => 'VAT', 'table' => 'vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active']],
        'wht' => ['title' => 'WHT / PPH', 'table' => 'wht_rates', 'tenant' => true, 'site' => false, 'fields' => ['code', 'name', 'rate', 'description', 'is_active']],
        'item-vat' => ['title' => 'Item VAT', 'table' => 'item_vat_rates', 'tenant' => true, 'site' => false, 'fields' => ['item_id', 'vat_rate_id', 'is_active']],
        'address-master' => ['title' => 'Address Master', 'table' => 'addresses', 'tenant' => true, 'site' => true, 'fields' => ['address_type', 'owner_type', 'owner_code', 'code', 'name', 'country_id', 'province_id', 'city_id', 'postal_code_id', 'address_line1', 'address_line2', 'phone', 'email', 'is_active']],
        'customers' => ['title' => 'Customers', 'table' => 'customers', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'terms_code', 'currency_code', 'tax_number', 'phone', 'email', 'address', 'is_active'], 'view_permission' => 'sales.customer.view', 'manage_permission' => 'sales.customer.manage'],
        'suppliers' => ['title' => 'Suppliers', 'table' => 'suppliers', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'terms_code', 'currency_code', 'tax_number', 'phone', 'email', 'address', 'is_active'], 'view_permission' => 'purchase.supplier.view', 'manage_permission' => 'purchase.supplier.manage'],
        'items' => ['title' => 'Items', 'table' => 'items', 'tenant' => true, 'site' => true, 'fields' => ['code', 'name', 'item_type', 'brand', 'stock_uom_id', 'sales_uom_id', 'purchase_uom_id', 'standard_cost', 'sales_price', 'shelf_life_days', 'is_active'], 'view_permission' => 'inventory.item.view', 'manage_permission' => 'inventory.item.manage'],
    ];

    public function importForm(string $resource): string
    {
        $config = $this->config($resource, 'manage');

        return view('setup/master/import', [
            'title' => 'Import ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'headers' => $this->headers($config),
        ]);
    }

    public function template(string $resource)
    {
        $config = $this->config($resource, 'manage');

        return $this->csvResponse(
            $this->slug($config['title']) . '-template.csv',
            [$this->headers($config), $this->sampleRow($config)]
        );
    }

    public function export(string $resource)
    {
        $config = $this->config($resource, 'view');
        $db = Database::connect();
        $builder = $db->table($config['table']);
        $headers = $this->headers($config);
        $tenant = new TenantContext(session());

        if ($config['tenant'] && $tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }

        if ($config['site'] && $tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        if ($db->fieldExists('deleted_at', $config['table'])) {
            $builder->where('deleted_at', null);
        }

        $rows = [$headers];
        foreach ($builder->orderBy('id', 'ASC')->get()->getResultArray() as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = (string) ($row[$header] ?? '');
            }
            $rows[] = $line;
        }

        return $this->csvResponse($this->slug($config['title']) . '-export.csv', $rows);
    }

    public function import(string $resource)
    {
        $config = $this->config($resource, 'manage');

        if (! $this->hasRequiredTenant($config)) {
            return redirect()->back()->with('error', 'Active company is required before importing this master data.');
        }

        $file = $this->request->getFile('csv_file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Please upload a valid CSV file.');
        }

        if (! in_array($file->getClientExtension(), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'Only CSV files are supported for now.');
        }

        try {
            $result = $this->importCsv($config, $file->getTempName());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->to(site_url('setup/' . $resource))
            ->with('message', "Import finished. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    private function importCsv(array $config, string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new RuntimeException('CSV file is empty.');
        }

        $headers = array_map(static fn ($value) => trim((string) $value), $headers);
        $allowed = $this->headers($config);
        $missing = array_diff(['code'], $allowed) === [] && in_array('code', $allowed, true) && ! in_array('code', $headers, true);
        if ($missing) {
            fclose($handle);
            throw new RuntimeException('CSV header must include code column.');
        }

        $db = Database::connect();
        $tenant = new TenantContext(session());
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $data = [];
            foreach ($headers as $index => $header) {
                if (! in_array($header, $allowed, true)) {
                    continue;
                }

                $value = trim((string) ($row[$index] ?? ''));
                $data[$header] = $value === '' ? null : $value;
            }

            if ($data === []) {
                $skipped++;
                continue;
            }

            if (isset($data['is_active']) && $data['is_active'] === null) {
                $data['is_active'] = 1;
            }

            if ($config['tenant']) {
                $data['company_id'] = $tenant->activeCompanyId();
            }

            if ($config['site']) {
                $data['site_id'] = $tenant->activeSiteId();
            }

            $data['updated_by'] = auth()->id();
            $data['updated_at'] = $now;

            $existing = $this->findExisting($config, $data);
            if ($existing !== null) {
                $db->table($config['table'])->where('id', $existing['id'])->update($data);
                $updated++;
                continue;
            }

            $data['created_by'] = auth()->id();
            $data['created_at'] = $now;
            $db->table($config['table'])->insert($data);
            $created++;
        }

        fclose($handle);

        return compact('created', 'updated', 'skipped');
    }

    private function findExisting(array $config, array $data): ?array
    {
        $db = Database::connect();
        $builder = $db->table($config['table']);

        if (! empty($data['code'])) {
            $builder->where('code', $data['code']);
        } elseif (! empty($data['id'])) {
            $builder->where('id', $data['id']);
        } else {
            return null;
        }

        if ($config['tenant']) {
            $builder->where('company_id', $data['company_id'] ?? 0);
        }

        if ($config['site']) {
            $builder->where('site_id', $data['site_id'] ?? null);
        }

        return $builder->get()->getRowArray() ?: null;
    }

    private function config(string $resource, string $mode): array
    {
        if (! isset($this->resources[$resource])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $config = $this->resources[$resource] + [
            'view_permission' => 'setup.master.view',
            'manage_permission' => 'setup.master.manage',
        ];

        $permission = $mode === 'view' ? $config['view_permission'] : $config['manage_permission'];
        if (! auth()->user()?->can($permission)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $config;
    }

    private function headers(array $config): array
    {
        return $config['fields'];
    }

    private function sampleRow(array $config): array
    {
        $sample = [];
        foreach ($this->headers($config) as $header) {
            $sample[] = match ($header) {
                'code' => 'EXAMPLE',
                'name' => 'Example ' . $config['title'],
                'is_active' => '1',
                'rate' => '0',
                'multiplier', 'divider' => '1',
                default => '',
            };
        }

        return $sample;
    }

    private function hasRequiredTenant(array $config): bool
    {
        return ! $config['tenant'] || (new TenantContext(session()))->activeCompanyId() !== null;
    }

    private function csvResponse(string $filename, array $rows)
    {
        $handle = fopen('php://temp', 'wb+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    private function slug(string $value): string
    {
        return trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-');
    }
}
