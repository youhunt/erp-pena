<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isEdit = $user !== null;
$permissionDescriptions = $permissions ?? [];
$permissionMatrix = $permissionMatrix ?? [];
$selectedPermissions = $selectedPermissions ?? [];
$selectedGroups = $selectedGroups ?? [];
$selectedCompanyIds = $selectedCompanyIds ?? [];
$selectedSiteIds = $selectedSiteIds ?? [];
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$defaultSiteId = (int) ($defaultSiteId ?? 0);
$menuAccess = $menuAccess ?? [];
$companyNames = [];

foreach ($companies as $company) {
    $companyNames[(int) $company['id']] = ($company['code'] ?? '') . ' - ' . ($company['name'] ?? '');
}
?>

<form method="post" action="<?= $isEdit ? site_url('admin/users/' . $user->id) : site_url('admin/users') ?>">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-xl-4">
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

                    <div class="mb-0">
                        <label class="form-label">Password <?= $isEdit ? '<span class="text-muted">(optional)</span>' : '' ?></label>
                        <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?> minlength="8">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h4 class="card-title mb-0">Roles</h4>
                        <span class="badge bg-light text-dark" id="rolePermissionCount">0 permissions</span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($groups as $key => $group): ?>
                            <div class="col-md-6 col-xl-4">
                                <label class="border rounded d-block p-3 h-100">
                                    <span class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" name="groups[]" value="<?= esc($key) ?>" id="group_<?= esc($key) ?>" <?= in_array($key, $selectedGroups, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label fw-semibold"><?= esc($group['title'] ?? $key) ?></span>
                                    </span>
                                    <span class="d-block small text-muted mt-1"><?= esc($group['description'] ?? '') ?></span>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>

                    <div class="mt-3">
                        <div class="small text-muted mb-2">Role permission preview</div>
                        <div class="d-flex flex-wrap gap-1" id="rolePermissionPreview"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h4 class="card-title mb-0">Company Access</h4>
                        <button class="btn btn-sm btn-light" type="button" id="selectAllCompanies">
                            <i class="bx bx-check-double me-1"></i> All
                        </button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Default Company</label>
                        <select name="default_company_id" class="form-select" id="defaultCompanySelect">
                            <option value="0">Select default company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= (int) $company['id'] ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>>
                                    <?= esc($company['code'] . ' - ' . $company['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" id="companySearch" placeholder="Search company...">
                    </div>

                    <div class="border rounded p-3" style="max-height: 360px; overflow:auto;">
                        <?php foreach ($companies as $company): ?>
                            <div class="form-check mb-2 company-row" data-search="<?= esc(strtolower($company['code'] . ' ' . $company['name'])) ?>">
                                <input class="form-check-input company-checkbox" type="checkbox" name="company_ids[]" value="<?= (int) $company['id'] ?>" id="company_<?= (int) $company['id'] ?>" <?= in_array((int) $company['id'], $selectedCompanyIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="company_<?= (int) $company['id'] ?>">
                                    <?= esc($company['code'] . ' - ' . $company['name']) ?>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h4 class="card-title mb-0">Site Access</h4>
                        <button class="btn btn-sm btn-light" type="button" id="selectVisibleSites">
                            <i class="bx bx-check-double me-1"></i> Visible
                        </button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Default Site</label>
                        <select name="default_site_id" class="form-select" id="defaultSiteSelect">
                            <option value="0">Select default site</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= (int) $site['id'] ?>" data-company-id="<?= (int) $site['company_id'] ?>" <?= $defaultSiteId === (int) $site['id'] ? 'selected' : '' ?>>
                                    <?= esc(($companyNames[(int) $site['company_id']] ?? 'Company') . ' / ' . $site['code'] . ' - ' . $site['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" id="siteSearch" placeholder="Search site...">
                    </div>

                    <div class="border rounded p-3" style="max-height: 360px; overflow:auto;">
                        <?php foreach ($sites as $site): ?>
                            <div class="form-check mb-2 site-row" data-company-id="<?= (int) $site['company_id'] ?>" data-search="<?= esc(strtolower(($companyNames[(int) $site['company_id']] ?? '') . ' ' . $site['code'] . ' ' . $site['name'])) ?>">
                                <input class="form-check-input site-checkbox" type="checkbox" name="site_ids[]" value="<?= (int) $site['id'] ?>" id="site_<?= (int) $site['id'] ?>" <?= in_array((int) $site['id'], $selectedSiteIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="site_<?= (int) $site['id'] ?>">
                                    <span class="fw-semibold"><?= esc($site['code']) ?></span>
                                    <span><?= esc(' - ' . $site['name']) ?></span>
                                    <span class="badge bg-light text-dark ms-1"><?= esc($companyNames[(int) $site['company_id']] ?? '-') ?></span>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h4 class="card-title mb-1">Direct Menu Access</h4>
                    <p class="text-muted mb-0">Additional user-level permissions outside selected roles.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-light" type="button" id="clearPermissions">
                        <i class="bx bx-x me-1"></i> Clear
                    </button>
                    <button class="btn btn-sm btn-light" type="button" id="selectVisiblePermissions">
                        <i class="bx bx-check-double me-1"></i> Visible
                    </button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" id="permissionSearch" placeholder="Search menu or permission...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="permissionModuleFilter">
                        <option value="all">All modules</option>
                        <?php foreach (array_unique(array_column($menuAccess, 'module')) as $module): ?>
                            <option value="<?= esc($module) ?>"><?= esc($module) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <span class="badge bg-primary" id="directPermissionCount">0 selected</span>
                </div>
            </div>

            <div class="table-responsive border rounded" style="max-height: 420px; overflow:auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th style="width: 48px;"></th>
                            <th>Module</th>
                            <th>Menu</th>
                            <th>Permission</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($menuAccess as $access): ?>
                        <?php $permission = $access['permission']; ?>
                        <tr class="permission-row" data-module="<?= esc($access['module']) ?>" data-search="<?= esc(strtolower($access['module'] . ' ' . $access['label'] . ' ' . $permission . ' ' . ($permissionDescriptions[$permission] ?? ''))) ?>">
                            <td>
                                <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="<?= esc($permission) ?>" <?= in_array($permission, $selectedPermissions, true) ? 'checked' : '' ?>>
                            </td>
                            <td><?= esc($access['module']) ?></td>
                            <td><?= esc($access['label']) ?></td>
                            <td><code><?= esc($permission) ?></code></td>
                            <td class="text-muted"><?= esc($permissionDescriptions[$permission] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
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
    const permissionMatrix = <?= json_encode($permissionMatrix, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const permissionDescriptions = <?= json_encode($permissionDescriptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const allPermissions = Object.keys(permissionDescriptions);
    const roleChecks = Array.from(document.querySelectorAll('.role-checkbox'));
    const companyChecks = Array.from(document.querySelectorAll('.company-checkbox'));
    const siteChecks = Array.from(document.querySelectorAll('.site-checkbox'));
    const permissionChecks = Array.from(document.querySelectorAll('.permission-checkbox'));
    const defaultCompany = document.getElementById('defaultCompanySelect');
    const defaultSite = document.getElementById('defaultSiteSelect');

    function expandPermissions(patterns) {
        const resolved = new Set();
        patterns.forEach(function (pattern) {
            if (pattern === '*') {
                allPermissions.forEach(permission => resolved.add(permission));
                return;
            }
            if (pattern.endsWith('.*')) {
                const prefix = pattern.slice(0, -1);
                allPermissions.filter(permission => permission.startsWith(prefix)).forEach(permission => resolved.add(permission));
                return;
            }
            resolved.add(pattern);
        });
        return Array.from(resolved).filter(permission => allPermissions.includes(permission)).sort();
    }

    function selectedCompanyIds() {
        return companyChecks.filter(input => input.checked).map(input => input.value);
    }

    function syncDefaultCompany() {
        if (!defaultCompany.value || defaultCompany.value === '0') return;
        companyChecks.forEach(function (input) {
            if (input.value === defaultCompany.value) {
                input.checked = true;
            }
        });
    }

    function syncDefaultSite() {
        if (!defaultSite.value || defaultSite.value === '0') return;
        const option = defaultSite.selectedOptions[0];
        if (option && option.dataset.companyId) {
            companyChecks.forEach(function (input) {
                if (input.value === option.dataset.companyId) {
                    input.checked = true;
                }
            });
        }
        siteChecks.forEach(function (input) {
            if (input.value === defaultSite.value) {
                input.checked = true;
            }
        });
    }

    function filterCompanies() {
        const keyword = (document.getElementById('companySearch').value || '').toLowerCase().trim();
        document.querySelectorAll('.company-row').forEach(function (row) {
            row.classList.toggle('d-none', keyword !== '' && !row.dataset.search.includes(keyword));
        });
    }

    function filterSites() {
        const companies = selectedCompanyIds();
        const keyword = (document.getElementById('siteSearch').value || '').toLowerCase().trim();

        document.querySelectorAll('.site-row').forEach(function (row) {
            const companyMatch = companies.length === 0 || companies.includes(row.dataset.companyId);
            const keywordMatch = keyword === '' || row.dataset.search.includes(keyword);
            row.classList.toggle('d-none', !companyMatch || !keywordMatch);
            if (!companyMatch) {
                const input = row.querySelector('input');
                if (input) input.checked = false;
            }
        });

        Array.from(defaultSite.options).forEach(function (option) {
            if (!option.value || option.value === '0') return;
            option.hidden = companies.length > 0 && !companies.includes(option.dataset.companyId);
            if (option.hidden && option.selected) {
                defaultSite.value = '0';
            }
        });
    }

    function filterPermissions() {
        const keyword = (document.getElementById('permissionSearch').value || '').toLowerCase().trim();
        const module = document.getElementById('permissionModuleFilter').value;

        document.querySelectorAll('.permission-row').forEach(function (row) {
            const keywordMatch = keyword === '' || row.dataset.search.includes(keyword);
            const moduleMatch = module === 'all' || row.dataset.module === module;
            row.classList.toggle('d-none', !keywordMatch || !moduleMatch);
        });
    }

    function updatePermissionPreview() {
        const selectedRoles = roleChecks.filter(input => input.checked).map(input => input.value);
        const permissions = expandPermissions(selectedRoles.flatMap(role => permissionMatrix[role] || []));
        const preview = document.getElementById('rolePermissionPreview');
        preview.innerHTML = '';

        permissions.slice(0, 24).forEach(function (permission) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-light text-dark';
            badge.textContent = permission;
            preview.appendChild(badge);
        });

        if (permissions.length > 24) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary';
            badge.textContent = '+' + (permissions.length - 24) + ' more';
            preview.appendChild(badge);
        }

        document.getElementById('rolePermissionCount').textContent = permissions.length + ' permissions';
    }

    function updateDirectPermissionCount() {
        const selected = permissionChecks.filter(input => input.checked).length;
        document.getElementById('directPermissionCount').textContent = selected + ' selected';
    }

    defaultCompany.addEventListener('change', function () {
        syncDefaultCompany();
        filterSites();
    });
    defaultSite.addEventListener('change', function () {
        syncDefaultSite();
        filterSites();
    });
    companyChecks.forEach(input => input.addEventListener('change', filterSites));
    siteChecks.forEach(input => input.addEventListener('change', updateDirectPermissionCount));
    roleChecks.forEach(input => input.addEventListener('change', updatePermissionPreview));
    permissionChecks.forEach(input => input.addEventListener('change', updateDirectPermissionCount));

    document.getElementById('companySearch').addEventListener('input', filterCompanies);
    document.getElementById('siteSearch').addEventListener('input', filterSites);
    document.getElementById('permissionSearch').addEventListener('input', filterPermissions);
    document.getElementById('permissionModuleFilter').addEventListener('change', filterPermissions);

    document.getElementById('selectAllCompanies').addEventListener('click', function () {
        companyChecks.forEach(input => input.checked = true);
        filterSites();
    });
    document.getElementById('selectVisibleSites').addEventListener('click', function () {
        document.querySelectorAll('.site-row:not(.d-none) .site-checkbox').forEach(input => input.checked = true);
    });
    document.getElementById('selectVisiblePermissions').addEventListener('click', function () {
        document.querySelectorAll('.permission-row:not(.d-none) .permission-checkbox').forEach(input => input.checked = true);
        updateDirectPermissionCount();
    });
    document.getElementById('clearPermissions').addEventListener('click', function () {
        permissionChecks.forEach(input => input.checked = false);
        updateDirectPermissionCount();
    });

    syncDefaultCompany();
    syncDefaultSite();
    filterCompanies();
    filterSites();
    filterPermissions();
    updatePermissionPreview();
    updateDirectPermissionCount();
});
</script>
<?= $this->endSection() ?>
