<?php
use App\Services\TenantContext;

$tenantContext = new TenantContext(session());
$currentUser = auth()->user();
$companies = $currentUser === null ? [] : $tenantContext->accessibleCompanies((int) $currentUser->id);
$activeCompanyId = $tenantContext->activeCompanyId();
$activeSiteId = $tenantContext->activeSiteId();
$sitesByCompany = [];

if ($currentUser !== null) {
    foreach ($companies as $company) {
        $sitesByCompany[(int) $company['id']] = $tenantContext->accessibleSites((int) $currentUser->id, (int) $company['id']);
    }
}

$sites = $activeCompanyId === null ? [] : ($sitesByCompany[(int) $activeCompanyId] ?? []);
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

        <div class="d-flex">
            <?php if ($companies !== []): ?>
                <form class="d-none d-lg-flex align-items-center gap-2 me-3" action="<?= site_url('tenant/switch') ?>" method="post" id="tenantSwitchForm">
                    <?= csrf_field() ?>
                    <select class="form-select form-select-sm" name="company_id" id="tenantCompanySelect" aria-label="Active company">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc((string) $company['id']) ?>" <?= (int) $company['id'] === (int) $activeCompanyId ? 'selected' : '' ?>>
                                <?= esc($company['code']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <select class="form-select form-select-sm" name="site_id" id="tenantSiteSelect" aria-label="Active site">
                        <option value="">All Sites</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?= esc((string) $site['id']) ?>" <?= (int) $site['id'] === (int) $activeSiteId ? 'selected' : '' ?>>
                                <?= esc($site['code']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </form>
            <?php endif ?>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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
    const form = document.getElementById('tenantSwitchForm');
    const companySelect = document.getElementById('tenantCompanySelect');
    const siteSelect = document.getElementById('tenantSiteSelect');
    const sitesByCompany = <?= json_encode($sitesByCompany, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function rebuildSites(companyId, selectedSiteId) {
        const sites = sitesByCompany[companyId] || [];
        siteSelect.innerHTML = '';

        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'All Sites';
        siteSelect.appendChild(allOption);

        sites.forEach(function (site) {
            const option = document.createElement('option');
            option.value = String(site.id);
            option.textContent = site.code;
            if (String(site.id) === String(selectedSiteId)) {
                option.selected = true;
            }
            siteSelect.appendChild(option);
        });
    }

    companySelect.addEventListener('change', function () {
        rebuildSites(companySelect.value, '');
        form.submit();
    });

    siteSelect.addEventListener('change', function () {
        form.submit();
    });
});
</script>
<?php endif ?>
