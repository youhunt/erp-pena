<?php

namespace App\Services\Support;

use App\Services\TenantContext;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Applies the active company/site context to models and query builders.
 *
 * This helper is intentionally small and safe so existing controllers can be
 * migrated gradually without changing the current TenantContext contract.
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
     * Apply active tenant filters to a CodeIgniter model.
     *
     * @template T of Model
     *
     * @param T $model
     * @return T
     */
    public function applyToModel(Model $model, bool $includeSite = true): Model
    {
        $companyId = $this->activeCompanyId();
        $siteId = $this->activeSiteId();

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
     * Use table alias when the query uses aliases, for example: `sales_orders`
     * or `so`. The helper will generate `alias.company_id` and `alias.site_id`.
     */
    public function applyToBuilder(BaseBuilder $builder, string $tableOrAlias, bool $includeSite = true): BaseBuilder
    {
        $prefix = trim($tableOrAlias);
        $prefix = $prefix === '' ? '' : rtrim($prefix, '.') . '.';

        $companyId = $this->activeCompanyId();
        $siteId = $this->activeSiteId();

        if ($companyId !== null && $companyId > 0) {
            $builder->where($prefix . 'company_id', $companyId);
        }

        if ($includeSite && $siteId !== null && $siteId > 0) {
            $builder->where($prefix . 'site_id', $siteId);
        }

        return $builder;
    }

    /**
     * Guard that active company exists before creating tenant-owned records.
     */
    public function requireCompany(): int
    {
        $companyId = $this->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            throw new \RuntimeException('Active company is required.');
        }

        return $companyId;
    }

    /**
     * Return active site ID, or null when the record is company-level only.
     */
    public function optionalSite(): ?int
    {
        $siteId = $this->activeSiteId();

        return $siteId !== null && $siteId > 0 ? $siteId : null;
    }

    private function context(): TenantContext
    {
        return $this->tenant ?? new TenantContext(session());
    }

    private function modelHasField(Model $model, string $field): bool
    {
        $allowedFields = $model->allowedFields ?? [];

        return in_array($field, $allowedFields, true);
    }
}
