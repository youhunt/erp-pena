<?php
use App\Services\TenantContext;

$tenantContext = new TenantContext(session());
$currentUser = auth()->user();
$companies = $currentUser === null ? [] : $tenantContext->accessibleCompanies((int) $currentUser->id);
$sites = $currentUser === null ? [] : $tenantContext->accessibleSites((int) $currentUser->id);
$activeCompanyId = $tenantContext->activeCompanyId();
$activeSiteId = $tenantContext->activeSiteId();
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
                <form class="d-none d-lg-flex align-items-center gap-2 me-3" action="<?= site_url('tenant/switch') ?>" method="post">
                    <?= csrf_field() ?>
                    <select class="form-select form-select-sm" name="company_id" onchange="this.form.submit()" aria-label="Active company">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc((string) $company['id']) ?>" <?= (int) $company['id'] === (int) $activeCompanyId ? 'selected' : '' ?>>
                                <?= esc($company['code']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <select class="form-select form-select-sm" name="site_id" onchange="this.form.submit()" aria-label="Active site">
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
