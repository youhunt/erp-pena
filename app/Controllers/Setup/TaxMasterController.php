<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Models\ChargeVatRateModel;
use App\Models\ItemVatRateModel;
use App\Models\VatRateModel;
use App\Models\WithholdingTaxRateModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;
use Config\Database;
use RuntimeException;

class TaxMasterController extends BaseController
{
    private array $configs;

    public function __construct()
    {
        $this->configs = [
            'vat' => [
                'title' => 'VAT Master',
                'table' => 'vat_rates',
                'model' => VatRateModel::class,
                'code_label' => 'VAT Code',
                'description_label' => 'VAT Description',
                'display_fields' => ['site', 'company', 'vat', 'description', 'vatpctg', 'scpctg', 'otherpctg', 'optionalpctg', 'gl'],
                'fields' => [
                    'site' => ['label' => 'Site', 'type' => 'hidden_tenant', 'required' => true, 'max' => 12],
                    'company' => ['label' => 'Company', 'type' => 'hidden_tenant', 'required' => false, 'max' => 12],
                    'vat' => ['label' => 'VAT Code', 'type' => 'text', 'required' => true, 'max' => 12],
                    'description' => ['label' => 'VAT Description', 'type' => 'textarea', 'required' => false, 'max' => 500],
                    'vatpctg' => ['label' => 'VAT %', 'type' => 'number', 'default' => 0],
                    'scpctg' => ['label' => 'Service Charge %', 'type' => 'number', 'default' => 0],
                    'otherpctg' => ['label' => 'Other %', 'type' => 'number', 'default' => 0],
                    'optionalpctg' => ['label' => 'Optional %', 'type' => 'number', 'default' => 0],
                    'gl' => ['label' => 'GL Code', 'type' => 'gl', 'required' => false, 'max' => 30],
                ],
            ],
            'item-vat' => [
                'title' => 'Item VAT Master',
                'table' => 'item_vat_rates',
                'model' => ItemVatRateModel::class,
                'code_label' => 'Item VAT Code',
                'description_label' => 'Item VAT Description',
                'display_fields' => ['site', 'company', 'vat', 'description', 'vatpctg', 'scpctg', 'whtpctg', 'otherpctg', 'optionalpctg', 'gl'],
                'fields' => [
                    'site' => ['label' => 'Site', 'type' => 'hidden_tenant', 'required' => true, 'max' => 12],
                    'company' => ['label' => 'Company', 'type' => 'hidden_tenant', 'required' => false, 'max' => 12],
                    'vat' => ['label' => 'Item VAT Code', 'type' => 'text', 'required' => true, 'max' => 12],
                    'description' => ['label' => 'Item VAT Description', 'type' => 'textarea', 'required' => false, 'max' => 500],
                    'vatpctg' => ['label' => 'Item VAT %', 'type' => 'number', 'default' => 0],
                    'scpctg' => ['label' => 'Item Service Charge %', 'type' => 'number', 'default' => 0],
                    'whtpctg' => ['label' => 'Item WHT %', 'type' => 'number', 'default' => 0],
                    'otherpctg' => ['label' => 'Item Other %', 'type' => 'number', 'default' => 0],
                    'optionalpctg' => ['label' => 'Item Optional %', 'type' => 'number', 'default' => 0],
                    'gl' => ['label' => 'GL Code', 'type' => 'gl', 'required' => false, 'max' => 30],
                ],
            ],
            'other-charge-vat' => [
                'title' => 'Other Charge VAT Master',
                'table' => 'charge_vat_rates',
                'model' => ChargeVatRateModel::class,
                'code_label' => 'Charge VAT Code',
                'description_label' => 'Charge VAT Description',
                'display_fields' => ['site', 'company', 'vat', 'description', 'vatpctg1', 'vatpctg2', 'vatpctg3', 'vatpctg4', 'vatpctg5', 'gl'],
                'fields' => [
                    'site' => ['label' => 'Site', 'type' => 'hidden_tenant', 'required' => true, 'max' => 12],
                    'company' => ['label' => 'Company', 'type' => 'hidden_tenant', 'required' => false, 'max' => 12],
                    'vat' => ['label' => 'Charge VAT Code', 'type' => 'text', 'required' => true, 'max' => 12],
                    'description' => ['label' => 'Charge VAT Description', 'type' => 'textarea', 'required' => false, 'max' => 500],
                    'vatpctg1' => ['label' => 'Charge VAT 1 %', 'type' => 'number', 'default' => 0],
                    'vatpctg2' => ['label' => 'Charge VAT 2 %', 'type' => 'number', 'default' => 0],
                    'vatpctg3' => ['label' => 'Charge VAT 3 %', 'type' => 'number', 'default' => 0],
                    'vatpctg4' => ['label' => 'Charge VAT 4 %', 'type' => 'number', 'default' => 0],
                    'vatpctg5' => ['label' => 'Charge VAT 5 %', 'type' => 'number', 'default' => 0],
                    'gl' => ['label' => 'GL Code', 'type' => 'gl', 'required' => false, 'max' => 30],
                ],
            ],
            'charge-vat' => [],
            'wht' => [
                'title' => 'WHT Master',
                'table' => 'wht_rates',
                'model' => WithholdingTaxRateModel::class,
                'code_label' => 'WHT Code',
                'description_label' => 'WHT Description',
                'display_fields' => ['site', 'company', 'vat', 'description', 'vatpctg1', 'vatpctg2', 'vatpctg3', 'vatpctg4', 'vatpctg5', 'gl'],
                'fields' => [
                    'site' => ['label' => 'Site', 'type' => 'hidden_tenant', 'required' => true, 'max' => 12],
                    'company' => ['label' => 'Company', 'type' => 'hidden_tenant', 'required' => false, 'max' => 12],
                    'vat' => ['label' => 'WHT Code', 'type' => 'text', 'required' => true, 'max' => 12],
                    'description' => ['label' => 'WHT Description', 'type' => 'textarea', 'required' => false, 'max' => 500],
                    'vatpctg1' => ['label' => 'WHT 1 %', 'type' => 'number', 'default' => 0],
                    'vatpctg2' => ['label' => 'WHT 2 %', 'type' => 'number', 'default' => 0],
                    'vatpctg3' => ['label' => 'WHT 3 %', 'type' => 'number', 'default' => 0],
                    'vatpctg4' => ['label' => 'WHT 4 %', 'type' => 'number', 'default' => 0],
                    'vatpctg5' => ['label' => 'WHT 5 %', 'type' => 'number', 'default' => 0],
                    'gl' => ['label' => 'GL Code', 'type' => 'gl', 'required' => false, 'max' => 30],
                ],
            ],
        ];
        $this->configs['charge-vat'] = $this->configs['other-charge-vat'];
    }

    public function index(string $resource): string
    {
        $config = $this->config($resource);
        $this->assertCanView();

        $rows = $this->scopedModel($config)->orderBy('site', 'ASC')->orderBy('vat', 'ASC')->findAll(500);

        return view('setup/tax_master/index', [
            'title' => $config['title'],
            'resource' => $resource,
            'config' => $config,
            'rows' => $rows,
            'canManage' => $this->canManage(),
        ]);
    }

    public function create(string $resource): string
    {
        $config = $this->config($resource);
        $this->assertCanManage();

        return view('setup/tax_master/form', [
            'title' => 'Create ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => [],
            'glOptions' => $this->glOptions(),
            'tenantLabels' => $this->tenantLabels(),
        ]);
    }

    public function store(string $resource)
    {
        $config = $this->config($resource);
        $this->assertCanManage();
        $this->assertTableExists($config);

        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = $this->payload($config, true);
        if ($this->duplicateExists($config, $payload)) {
            return redirect()->back()->withInput()->with('error', $config['code_label'] . ' already exists for active Company/Site.');
        }

        $id = (int) $this->model($config)->insert($payload, true);
        $this->audit('master.create', $config, $id, null, $payload);

        return redirect()->to(site_url('setup/' . $this->canonicalResource($resource)))->with('message', $config['title'] . ' created.');
    }

    public function show(string $resource, int $id): string
    {
        $config = $this->config($resource);
        $this->assertCanView();
        $row = $this->scopedModel($config)->find($id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('setup/tax_master/show', [
            'title' => $config['title'] . ' Detail',
            'resource' => $resource,
            'config' => $config,
            'row' => $row,
            'canManage' => $this->canManage(),
            'glLabel' => $this->glLabel((string) ($row['gl'] ?? '')),
        ]);
    }

    public function edit(string $resource, int $id): string
    {
        $config = $this->config($resource);
        $this->assertCanManage();
        $row = $this->scopedModel($config)->find($id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('setup/tax_master/form', [
            'title' => 'Edit ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'row' => $row,
            'glOptions' => $this->glOptions(),
            'tenantLabels' => $this->tenantLabels(),
        ]);
    }

    public function update(string $resource, int $id)
    {
        $config = $this->config($resource);
        $this->assertCanManage();
        $this->assertTableExists($config);

        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model = $this->scopedModel($config);
        $old = $model->find($id);
        if ($old === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $payload = $this->payload($config, false);
        if ($this->duplicateExists($config, $payload, $id)) {
            return redirect()->back()->withInput()->with('error', $config['code_label'] . ' already exists for active Company/Site.');
        }

        $model->update($id, $payload);
        $this->audit('master.update', $config, $id, $old, $payload);

        return redirect()->to(site_url('setup/' . $this->canonicalResource($resource)))->with('message', $config['title'] . ' updated.');
    }

    public function delete(string $resource, int $id)
    {
        $config = $this->config($resource);
        $this->assertCanManage();
        $model = $this->scopedModel($config);
        $old = $model->find($id);
        if ($old === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        $this->audit('master.delete', $config, $id, $old, null);

        return redirect()->to(site_url('setup/' . $this->canonicalResource($resource)))->with('message', $config['title'] . ' deleted.');
    }

    private function config(string $resource): array
    {
        $resource = $this->canonicalResource($resource);
        if (! isset($this->configs[$resource]) || $this->configs[$resource] === []) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->configs[$resource];
    }

    private function canonicalResource(string $resource): string
    {
        return $resource === 'charge-vat' ? 'other-charge-vat' : $resource;
    }

    private function model(array $config): Model
    {
        return new $config['model']();
    }

    private function scopedModel(array $config): Model
    {
        $model = $this->model($config);
        $tenant = new TenantContext(session());
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        return $model;
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $name => $field) {
            $rule = ! empty($field['required']) ? 'required' : 'permit_empty';
            if (($field['type'] ?? 'text') === 'number') {
                $rule .= '|decimal';
            } else {
                $rule .= '|max_length[' . (int) ($field['max'] ?? 500) . ']';
            }
            $rules[$name] = $rule;
        }

        return $rules;
    }

    private function payload(array $config, bool $isCreate): array
    {
        $tenant = new TenantContext(session());
        $labels = $this->tenantLabels();
        $payload = [
            'company_id' => $tenant->activeCompanyId(),
            'site_id' => $tenant->activeSiteId(),
            'company' => $labels['company'],
            'site' => $labels['site'],
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'updated_by' => auth()->id(),
        ];

        foreach ($config['fields'] as $name => $field) {
            if (in_array($name, ['company', 'site'], true)) {
                continue;
            }
            $value = $this->request->getPost($name);
            if (($field['type'] ?? 'text') === 'number') {
                $value = $this->toNumber($value);
            } else {
                $value = trim((string) $value);
            }
            $payload[$name] = $value;
        }

        if ($config['table'] === 'vat_rates') {
            $payload['code'] = $payload['vat'] ?? '';
            $payload['name'] = $payload['description'] ?? '';
            $payload['rate'] = $payload['vatpctg'] ?? 0;
        }

        if ($config['table'] === 'wht_rates') {
            $payload['code'] = $payload['vat'] ?? '';
            $payload['name'] = $payload['description'] ?? '';
            $payload['rate'] = $payload['vatpctg1'] ?? 0;
        }

        if ($isCreate) {
            $payload['created_by'] = auth()->id();
        }

        return $payload;
    }

    private function duplicateExists(array $config, array $payload, ?int $ignoreId = null): bool
    {
        $builder = Database::connect()->table($config['table'])
            ->where('company_id', $payload['company_id'])
            ->where('site_id', $payload['site_id'])
            ->where('vat', $payload['vat']);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }
        if (Database::connect()->fieldExists('deleted_at', $config['table'])) {
            $builder->where('deleted_at', null);
        }

        return $builder->countAllResults() > 0;
    }

    private function glOptions(): array
    {
        $db = Database::connect();
        if (! $db->tableExists('chart_accounts')) {
            return [];
        }

        $tenant = new TenantContext(session());
        $builder = $db->table('chart_accounts')
            ->select('account_no, account_name')
            ->orderBy('account_no', 'ASC');
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'chart_accounts')) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($db->fieldExists('is_active', 'chart_accounts')) {
            $builder->where('is_active', 1);
        }
        if ($db->fieldExists('is_postable', 'chart_accounts')) {
            $builder->where('is_postable', 1);
        }
        if ($db->fieldExists('deleted_at', 'chart_accounts')) {
            $builder->where('deleted_at', null);
        }

        $options = [];
        foreach ($builder->get(1000)->getResultArray() as $row) {
            $code = (string) ($row['account_no'] ?? '');
            if ($code === '') {
                continue;
            }
            $options[$code] = trim($code . ' - ' . (string) ($row['account_name'] ?? ''));
        }

        return $options;
    }

    private function glLabel(string $gl): string
    {
        if ($gl === '') {
            return '-';
        }
        $options = $this->glOptions();
        return $options[$gl] ?? $gl;
    }

    private function tenantLabels(): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $company = '';
        $site = '';
        if ($tenant->activeCompanyId() !== null && $db->tableExists('companies')) {
            $row = $db->table('companies')->select('code')->where('id', $tenant->activeCompanyId())->get(1)->getRowArray();
            $company = (string) ($row['code'] ?? '');
        }
        if ($tenant->activeSiteId() !== null && $db->tableExists('sites')) {
            $row = $db->table('sites')->select('code')->where('id', $tenant->activeSiteId())->get(1)->getRowArray();
            $site = (string) ($row['code'] ?? '');
        }

        return ['company' => $company, 'site' => $site];
    }

    private function assertTableExists(array $config): void
    {
        if (! Database::connect()->tableExists($config['table'])) {
            throw new RuntimeException('Table ' . $config['table'] . ' does not exist. Run migration or SQL installer first.');
        }
    }

    private function assertCanView(): void
    {
        if (! $this->canView()) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function assertCanManage(): void
    {
        if (! $this->canManage()) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function canView(): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->can('setup.master.view') || $user->inGroup('superadmin'));
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->can('setup.master.manage') || $user->inGroup('superadmin'));
    }

    private function toNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return round((float) $value, 2);
    }

    private function audit(string $action, array $config, int $id, ?array $oldValues, ?array $newValues): void
    {
        $record = $newValues ?? $oldValues ?? [];
        (new AuditLogService())->log('setup.master', $action, [
            'company_id' => $record['company_id'] ?? null,
            'site_id' => $record['site_id'] ?? null,
            'user_id' => auth()->id(),
            'table_name' => $config['table'],
            'record_id' => $id,
            'record_code' => $record['vat'] ?? null,
            'description' => $config['title'] . ' ' . str_replace('master.', '', $action),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
