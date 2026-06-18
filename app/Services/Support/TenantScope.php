<?php

namespace App\Services\Support;

use App\Services\TenantContext;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;
use Config\Database;

/**
 * Reusable helper for applying active company/site scope.
 *
 * The existing TenantContext owns session state and access rules. This helper is
 * intentionally small and additive so controllers, models, and services can be
 * migrated gradually without rewriting existing ERP modules.
 */
final class TenantScope
{
    public function __construct(
        private readonly ?TenantContext $tenant = null,
    ) {
    }

    public function activeCompanyId(): ?int
    {
        return $this->context()->activeCompanyId();
    }

    public function activeSiteId(): ?int
    {
        return $this->context()->activeSiteId();
    }

    /**
     * Require an active company before creating tenant-owned records.
     */
    public function requireCompany(): int
    {
        $companyId = $this->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            throw new \RuntimeException('Active company is required for this ERP operation.');
        }

        return $companyId;
    }

    /**
     * Return active site ID, or null for company-level records.
     */
    public function optionalSite(): ?int
    {
        $siteId = $this->activeSiteId();

        return $siteId !== null && $siteId > 0 ? $siteId : null;
    }

    /**
     * Apply active tenant filters to a CodeIgniter model when the table has the
     * matching tenant columns.
     *
     * @template T of Model
     * @param T $model
     * @return T
     */
    public function applyToModel(Model $model, bool $includeSite = true): Model
    {
        $companyId = $this->activeCompanyId();
        $siteId    = $this->activeSiteId();

        if ($companyId !== null && $companyId > 0 && $this->modelHasField($model, 'company_id')) {
            $model->where('company_id', $companyId);
        }

        if ($includeSite && $siteId !== null && $siteId > 0 && $this->modelHasField($model, 'site_id')) {
            $model->where('site_id', $siteId);
        }

        return $model;
    }

    /**
     * Apply active tenant filters to a query builder.
     *
     * Pass an alias when the query uses aliases, for example `so` to generate
     * `so.company_id` and `so.site_id` conditions.
     */
    public function applyToBuilder(BaseBuilder $builder, string $tableOrAlias = '', bool $includeSite = true): BaseBuilder
    {
        $prefix = trim($tableOrAlias);
        $prefix = $prefix === '' ? '' : rtrim($prefix, '.') . '.';

        $companyId = $this->activeCompanyId();
        $siteId    = $this->activeSiteId();

        if ($companyId !== null && $companyId > 0) {
            $builder->where($prefix . 'company_id', $companyId);
        }

        if ($includeSite && $siteId !== null && $siteId > 0) {
            $builder->where($prefix . 'site_id', $siteId);
        }

        return $builder;
    }

    /**
     * Merge active tenant columns into insert/update payloads.
     *
     * This is useful when services create tenant-owned transaction headers.
     * Existing keys are preserved unless $overrideExisting is true.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function withTenantColumns(array $data, bool $includeSite = true, bool $overrideExisting = false): array
    {
        $companyId = $this->requireCompany();
        $siteId    = $this->optionalSite();

        if ($overrideExisting || ! array_key_exists('company_id', $data)) {
            $data['company_id'] = $companyId;
        }

        if ($includeSite && ($overrideExisting || ! array_key_exists('site_id', $data))) {
            $data['site_id'] = $siteId;
        }

        return $data;
    }

    private function context(): TenantContext
    {
        return $this->tenant ?? new TenantContext(session());
    }

    private function modelHasField(Model $model, string $field): bool
    {
        if (! method_exists($model, 'getTable')) {
            return true;
        }

        $table = (string) $model->getTable();

        if ($table === '') {
            return true;
        }

        return Database::connect()->fieldExists($field, $table);
    }
}
