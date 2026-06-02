<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $isEdit = $user !== null; ?>
<form method="post" action="<?= $isEdit ? site_url('admin/users/' . $user->id) : site_url('admin/users') ?>">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">User Account</h4>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required value="<?= esc(old('username', $user->username ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required value="<?= esc(old('email', $user->email ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <?= $isEdit ? '<span class="text-muted">(leave blank to keep current)</span>' : '' ?></label>
                        <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?> minlength="8">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Roles</h4>
                    <div class="row">
                        <?php foreach ($groups as $key => $group): ?>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="groups[]" value="<?= esc($key) ?>" id="group_<?= esc($key) ?>" <?= in_array($key, $selectedGroups, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="group_<?= esc($key) ?>">
                                        <?= esc($group['title'] ?? $key) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <div class="alert alert-light border mt-3 mb-0 small text-muted">
                        Role permissions follow <code>app/Config/AuthGroups.php</code>. Detailed permission preview will be added in the next UI pass.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Company Access</h4>
                    <div class="mb-3">
                        <label class="form-label">Default Company</label>
                        <select name="default_company_id" class="form-select" id="defaultCompanySelect">
                            <option value="0">Select default company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= (int) $company['id'] ?>" <?= (int) $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>>
                                    <?= esc($company['code'] . ' - ' . $company['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="border rounded p-3" style="max-height: 260px; overflow:auto;">
                        <?php foreach ($companies as $company): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input company-checkbox" type="checkbox" name="company_ids[]" value="<?= (int) $company['id'] ?>" id="company_<?= (int) $company['id'] ?>" <?= in_array((int) $company['id'], $selectedCompanyIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="company_<?= (int) $company['id'] ?>">
                                    <?= esc($company['code'] . ' - ' . $company['name']) ?>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <div class="form-text mt-2">Default company is automatically included in company access.</div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Site / Branch Access</h4>
                    <div class="mb-3">
                        <label class="form-label">Default Site</label>
                        <select name="default_site_id" class="form-select" id="defaultSiteSelect">
                            <option value="0">Select default site</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= (int) $site['id'] ?>" data-company-id="<?= (int) $site['company_id'] ?>" <?= (int) $defaultSiteId === (int) $site['id'] ? 'selected' : '' ?>>
                                    <?= esc($site['code'] . ' - ' . $site['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="border rounded p-3" style="max-height: 260px; overflow:auto;">
                        <?php foreach ($sites as $site): ?>
                            <div class="form-check mb-2 site-option" data-company-id="<?= (int) $site['company_id'] ?>">
                                <input class="form-check-input site-checkbox" type="checkbox" name="site_ids[]" value="<?= (int) $site['id'] ?>" id="site_<?= (int) $site['id'] ?>" <?= in_array((int) $site['id'], $selectedSiteIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="site_<?= (int) $site['id'] ?>">
                                    <?= esc($site['code'] . ' - ' . $site['name']) ?>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <div class="form-text mt-2">Sites are filtered by selected default company.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bx bx-save me-1"></i> Save User
        </button>
        <a href="<?= site_url('admin/users') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultCompany = document.getElementById('defaultCompanySelect');
    const defaultSite = document.getElementById('defaultSiteSelect');
    const companyChecks = Array.from(document.querySelectorAll('.company-checkbox'));
    const siteOptions = Array.from(document.querySelectorAll('.site-option'));

    function syncDefaultCompanyAccess() {
        const companyId = defaultCompany.value;
        if (!companyId || companyId === '0') return;

        companyChecks.forEach(function (input) {
            if (input.value === companyId) {
                input.checked = true;
            }
        });
    }

    function filterSites() {
        const companyId = defaultCompany.value;

        Array.from(defaultSite.options).forEach(function (option) {
            if (!option.value || option.value === '0') return;
            const show = !companyId || companyId === '0' || option.dataset.companyId === companyId;
            option.hidden = !show;
            if (!show && option.selected) {
                defaultSite.value = '0';
            }
        });

        siteOptions.forEach(function (wrapper) {
            const show = !companyId || companyId === '0' || wrapper.dataset.companyId === companyId;
            wrapper.classList.toggle('d-none', !show);
            const input = wrapper.querySelector('input');
            if (!show && input) {
                input.checked = false;
            }
        });
    }

    defaultCompany.addEventListener('change', function () {
        syncDefaultCompanyAccess();
        filterSites();
    });

    syncDefaultCompanyAccess();
    filterSites();
});
</script>
<?= $this->endSection() ?>
