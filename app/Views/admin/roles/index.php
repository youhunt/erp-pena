<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$menuAccess = $menuAccess ?? [];
$rolePermissionMap = $rolePermissionMap ?? [];
$permissions = $permissions ?? [];
$matrix = $matrix ?? [];
$modules = array_unique(array_column($menuAccess, 'module'));
?>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Roles & Permissions</h4>
                <p class="text-muted mb-0">Shield role matrix mapped to ERP menu access.</p>
            </div>
            <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">
                <i class="bx bx-user me-1"></i> Users
            </a>
        </div>

        <div class="row g-3">
            <?php foreach ($groups as $key => $group): ?>
                <?php $rolePermissions = $rolePermissionMap[$key] ?? []; ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div>
                                <h5 class="mb-1"><?= esc($group['title'] ?? $key) ?></h5>
                                <p class="text-muted small mb-0"><?= esc($group['description'] ?? '-') ?></p>
                            </div>
                            <span class="badge bg-primary"><?= esc(count($rolePermissions)) ?></span>
                        </div>
                        <div class="small text-muted mb-1">Raw matrix</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach (($matrix[$key] ?? []) as $pattern): ?>
                                <span class="badge bg-light text-dark border"><?= esc($pattern) ?></span>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h5 class="card-title mb-1">Menu Access Matrix</h5>
                <p class="text-muted mb-0">Centang berarti role tersebut bisa melihat menu dan membuka route yang dijaga permission.</p>
            </div>
            <span class="badge bg-light text-dark"><?= count($menuAccess) ?> menu entries</span>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                    <input type="text" class="form-control" id="menuMatrixSearch" placeholder="Search menu, route, permission...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" id="menuMatrixModule">
                    <option value="all">All modules</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= esc($module) ?>"><?= esc($module) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div class="table-responsive border rounded" style="max-height: 560px; overflow:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light" style="position: sticky; top: 0; z-index: 2;">
                    <tr>
                        <th>Module</th>
                        <th>Menu</th>
                        <th>Route</th>
                        <th>Permission</th>
                        <?php foreach ($groups as $key => $group): ?>
                            <th class="text-center" title="<?= esc($group['title'] ?? $key) ?>"><?= esc($key) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($menuAccess as $access): ?>
                    <?php $permission = $access['permission']; ?>
                    <tr class="menu-matrix-row" data-module="<?= esc($access['module']) ?>" data-search="<?= esc(strtolower($access['module'] . ' ' . $access['label'] . ' ' . $access['route'] . ' ' . $permission)) ?>">
                        <td class="fw-semibold"><?= esc($access['module']) ?></td>
                        <td><?= esc($access['label']) ?></td>
                        <td><code><?= esc($access['route']) ?></code></td>
                        <td><code><?= esc($permission) ?></code></td>
                        <?php foreach ($groups as $key => $group): ?>
                            <?php $allowed = in_array($permission, $rolePermissionMap[$key] ?? [], true); ?>
                            <td class="text-center">
                                <span class="badge bg-<?= $allowed ? 'success' : 'light text-muted border' ?>">
                                    <?= $allowed ? 'Yes' : '-' ?>
                                </span>
                            </td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
                <tr id="menuMatrixEmpty" class="d-none">
                    <td colspan="<?= 4 + count($groups) ?>" class="text-center text-muted py-4">No matching menu access found.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="card-title mb-0">Permission Catalog</h5>
            <div class="input-group input-group-sm" style="max-width: 360px;">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input type="text" class="form-control" id="permissionCatalogSearch" placeholder="Search permission...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Permission</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($permissions as $permission => $description): ?>
                    <tr class="permission-catalog-row" data-search="<?= esc(strtolower($permission . ' ' . $description)) ?>">
                        <td class="fw-semibold"><code><?= esc($permission) ?></code></td>
                        <td><?= esc($description) ?></td>
                    </tr>
                <?php endforeach ?>
                <tr id="permissionCatalogEmpty" class="d-none">
                    <td colspan="2" class="text-center text-muted py-4">No matching permissions found.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const matrixSearch = document.getElementById('menuMatrixSearch');
    const matrixModule = document.getElementById('menuMatrixModule');
    const matrixRows = Array.from(document.querySelectorAll('.menu-matrix-row'));
    const matrixEmpty = document.getElementById('menuMatrixEmpty');
    const catalogSearch = document.getElementById('permissionCatalogSearch');
    const catalogRows = Array.from(document.querySelectorAll('.permission-catalog-row'));
    const catalogEmpty = document.getElementById('permissionCatalogEmpty');

    function filterMatrix() {
        const keyword = (matrixSearch.value || '').toLowerCase().trim();
        const module = matrixModule.value;
        let visible = 0;

        matrixRows.forEach(function (row) {
            const keywordMatch = keyword === '' || row.dataset.search.includes(keyword);
            const moduleMatch = module === 'all' || row.dataset.module === module;
            const show = keywordMatch && moduleMatch;
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        matrixEmpty.classList.toggle('d-none', visible > 0);
    }

    function filterCatalog() {
        const keyword = (catalogSearch.value || '').toLowerCase().trim();
        let visible = 0;

        catalogRows.forEach(function (row) {
            const show = keyword === '' || row.dataset.search.includes(keyword);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        catalogEmpty.classList.toggle('d-none', visible > 0);
    }

    matrixSearch.addEventListener('input', filterMatrix);
    matrixModule.addEventListener('change', filterMatrix);
    catalogSearch.addEventListener('input', filterCatalog);
    filterMatrix();
    filterCatalog();
});
</script>
<?= $this->endSection() ?>
