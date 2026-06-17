<?php
use App\Services\TenantContext;

$tenantContext = new TenantContext(session());
$currentUser = auth()->user();
$companies = $currentUser === null ? [] : $tenantContext->accessibleCompanies((int) $currentUser->id);
$activeCompanyId = $tenantContext->activeCompanyId();
$activeSiteId = $tenantContext->activeSiteId();
$sitesByCompany = [];
$activeCompanyCode = '';
$activeSiteCode = '';

if ($currentUser !== null) {
    foreach ($companies as $company) {
        $companyId = (int) $company['id'];
        $sitesByCompany[$companyId] = $tenantContext->accessibleSites((int) $currentUser->id, $companyId);

        if ($companyId === (int) $activeCompanyId) {
            $activeCompanyCode = (string) ($company['code'] ?? '');
        }
    }
}

$sites = $activeCompanyId === null ? [] : ($sitesByCompany[(int) $activeCompanyId] ?? []);
foreach ($sites as $site) {
    if ((int) $site['id'] === (int) $activeSiteId) {
        $activeSiteCode = (string) ($site['code'] ?? '');
        break;
    }
}

$tenantLabel = trim(($activeCompanyCode ?: 'Company') . ($activeSiteCode !== '' ? ' / ' . $activeSiteCode : ''));
?>

<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <div class="navbar-brand-box">
                <a href="<?= site_url('dashboard') ?>" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="<?= base_url('assets/skote/images/logo-sm-dark.png') ?>" alt="PENA ERP" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="<?= base_url('assets/skote/images/logo-dark.png') ?>" alt="Pena ERP" height="22">
                    </span>
                </a>

                <a href="<?= site_url('dashboard') ?>" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="<?= base_url('assets/skote/images/logo-sm-light.png') ?>" alt="PENA ERP" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="<?= base_url('assets/skote/images/logo-light.png') ?>" alt="Pena ERP" height="22">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item waves-effect" id="vertical-menu-btn" aria-label="Toggle menu">
                <i class="fa fa-fw fa-bars"></i>
            </button>
        </div>

        <div class="d-flex align-items-center">
            <?php if ($companies !== []): ?>
                <form class="tenant-switch-form d-none d-lg-flex align-items-center gap-2 me-3" action="<?= site_url('tenant/switch') ?>" method="post" id="tenantSwitchFormDesktop" data-auto-submit="1">
                    <?= csrf_field() ?>
                    <select class="form-select form-select-sm select2 tenant-select" name="company_id" aria-label="Active company" data-placeholder="Company">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc((string) $company['id']) ?>" <?= (int) $company['id'] === (int) $activeCompanyId ? 'selected' : '' ?>>
                                <?= esc($company['code']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <select class="form-select form-select-sm select2 tenant-select" name="site_id" aria-label="Active site" data-placeholder="Site">
                        <option value="">All Sites</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?= esc((string) $site['id']) ?>" <?= (int) $site['id'] === (int) $activeSiteId ? 'selected' : '' ?>>
                                <?= esc($site['code']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </form>

                <div class="dropdown d-inline-block d-lg-none">
                    <button type="button" class="btn header-item waves-effect px-2" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Switch tenant">
                        <i class="bx bx-buildings font-size-22 align-middle"></i>
                        <span class="badge bg-primary-subtle text-primary ms-1"><?= esc($activeSiteCode !== '' ? $activeSiteCode : ($activeCompanyCode ?: 'Tenant')) ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 290px;" onclick="event.stopPropagation();">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <div class="fw-semibold">Active Tenant</div>
                                <div class="text-muted small"><?= esc($tenantLabel) ?></div>
                            </div>
                        </div>

                        <form class="tenant-switch-form" action="<?= site_url('tenant/switch') ?>" method="post" id="tenantSwitchFormMobile" data-auto-submit="0">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label small mb-1">Company</label>
                                <select class="form-select form-select-sm select2 tenant-select" name="company_id" aria-label="Active company mobile" data-placeholder="Company">
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= esc((string) $company['id']) ?>" <?= (int) $company['id'] === (int) $activeCompanyId ? 'selected' : '' ?>>
                                            <?= esc($company['code']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small mb-1">Site</label>
                                <select class="form-select form-select-sm select2 tenant-select" name="site_id" aria-label="Active site mobile" data-placeholder="Site">
                                    <option value="">All Sites</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?= esc((string) $site['id']) ?>" <?= (int) $site['id'] === (int) $activeSiteId ? 'selected' : '' ?>>
                                            <?= esc($site['code']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bx bx-check me-1"></i> Apply Tenant
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif ?>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item waves-effect px-2 px-xl-3" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-user-circle font-size-22 align-middle d-inline-block d-xl-none"></i>
                    <span class="d-none d-xl-inline-block ms-1"><?= esc(auth()->user()?->username ?? 'User') ?></span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= site_url('dashboard') ?>">
                        <i class="bx bx-home-circle font-size-16 align-middle me-1"></i> Dashboard
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?= site_url('logout') ?>">
                        <i class="bx bx-power-off font-size-16 align-middle me-1 text-danger"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<?php if ($companies !== []): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.tenant-switch-form');
    const sitesByCompany = <?= json_encode($sitesByCompany, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    if (!forms.length) {
        return;
    }

    function ensureTenantSelect2(form) {
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
            return;
        }

        if (window.PenaSelect && typeof window.PenaSelect.init === 'function') {
            window.PenaSelect.init(form);
            return;
        }

        jQuery(form).find('select.form-select').each(function () {
            const $select = jQuery(this);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    width: '100%',
                    allowClear: !this.required,
                    placeholder: $select.data('placeholder') || 'Pilih / cari data',
                    dropdownParent: jQuery(document.body)
                });
            }
        });
    }

    function refreshTenantSelect2(form, select) {
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            const $select = jQuery(select);
            if (!$select.hasClass('select2-hidden-accessible')) {
                ensureTenantSelect2(form);
            }
            $select.trigger('change.select2');
        }
    }

    function rebuildSites(form, companyId, selectedSiteId) {
        const siteSelect = form.querySelector('select[name="site_id"]');
        if (!siteSelect) {
            return;
        }

        form.dataset.rebuildingSites = '1';
        const sites = sitesByCompany[String(companyId)] || sitesByCompany[companyId] || [];
        siteSelect.innerHTML = '';

        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'All Sites';
        siteSelect.appendChild(allOption);

        let hasSelectedSite = selectedSiteId === '' || selectedSiteId === null;
        sites.forEach(function (site) {
            const option = document.createElement('option');
            option.value = String(site.id);
            option.textContent = site.code;
            if (String(site.id) === String(selectedSiteId)) {
                option.selected = true;
                hasSelectedSite = true;
            }
            siteSelect.appendChild(option);
        });

        if (!hasSelectedSite) {
            siteSelect.value = '';
        }

        refreshTenantSelect2(form, siteSelect);
        form.dataset.rebuildingSites = '0';
    }

    function submitTenantSwitch(form) {
        if (form.dataset.submittingTenant === '1') {
            return;
        }

        form.dataset.submittingTenant = '1';
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            jQuery(form).find('select.form-select').select2('close');
        }
        form.submit();
    }

    forms.forEach(function (form) {
        const companySelect = form.querySelector('select[name="company_id"]');
        const siteSelect = form.querySelector('select[name="site_id"]');
        const autoSubmit = form.dataset.autoSubmit === '1';

        if (!companySelect || !siteSelect) {
            return;
        }

        ensureTenantSelect2(form);

        if (window.jQuery) {
            jQuery(companySelect).off('change.tenantSwitch').on('change.tenantSwitch', function () {
                if (form.dataset.rebuildingSites === '1') {
                    return;
                }
                rebuildSites(form, companySelect.value, '');
                if (autoSubmit) {
                    submitTenantSwitch(form);
                }
            });

            jQuery(siteSelect).off('change.tenantSwitch').on('change.tenantSwitch', function () {
                if (form.dataset.rebuildingSites === '1') {
                    return;
                }
                if (autoSubmit) {
                    submitTenantSwitch(form);
                }
            });
        } else {
            companySelect.addEventListener('change', function () {
                rebuildSites(form, companySelect.value, '');
                if (autoSubmit) {
                    submitTenantSwitch(form);
                }
            });

            siteSelect.addEventListener('change', function () {
                if (autoSubmit) {
                    submitTenantSwitch(form);
                }
            });
        }
    });
});
</script>
<?php endif ?>
