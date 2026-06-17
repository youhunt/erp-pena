<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$listFields = $config['list_fields'] ?? array_keys($config['fields'] ?? []);
if ($resource === 'customer-terms') {
    $listFields = ['customer', 'customer_name', 'terms_code', 'terms_name', 'terms_days', 'promo_code'];
} elseif ($resource === 'supplier-terms') {
    $listFields = ['supplier', 'supplier_name', 'terms_code', 'terms_name', 'terms_days', 'promo_code'];
}
$listFields = array_values(array_filter($listFields, static fn (string $field): bool => $field !== 'is_active'));
$listFields = array_slice($listFields, 0, 6);

$fieldLabel = static function (string $field, array $config): string {
    if (isset($config['fields'][$field]['label'])) {
        return (string) $config['fields'][$field]['label'];
    }

    return ucwords(str_replace('_', ' ', $field));
};

$relationTables = [
    'department_id' => 'departments',
    'warehouse_id' => 'warehouses',
    'location_id' => 'locations',
    'from_uom_id' => 'uoms',
    'to_uom_id' => 'uoms',
    'stock_uom_id' => 'uoms',
    'sales_uom_id' => 'uoms',
    'purchase_uom_id' => 'uoms',
    'item_id' => 'items',
    'vat_rate_id' => 'vat_rates',
    'country_id' => 'countries',
    'province_id' => 'provinces',
    'city_id' => 'cities',
    'postal_code_id' => 'postal_codes',
];

$relationCache = [];
$relationLabel = static function (string $field, mixed $value) use (&$relationCache, $relationTables): ?string {
    if ($value === null || $value === '' || ! isset($relationTables[$field])) {
        return null;
    }

    $table = $relationTables[$field];
    $id = (int) $value;
    $cacheKey = $table . ':' . $id;

    if (array_key_exists($cacheKey, $relationCache)) {
        return $relationCache[$cacheKey];
    }

    $row = db_connect()->table($table)->where('id', $id)->get()->getRowArray();
    if ($row === null) {
        return $relationCache[$cacheKey] = (string) $value;
    }

    $code = trim((string) ($row['item_code'] ?? $row['customer'] ?? $row['supplier'] ?? $row['terms_code'] ?? $row['promo_code'] ?? $row['code'] ?? $row['id'] ?? $value));
    $name = trim((string) ($row['item_name'] ?? $row['customern'] ?? $row['supplierna'] ?? $row['terms_name'] ?? $row['promo_description'] ?? $row['name'] ?? ''));

    return $relationCache[$cacheKey] = $name !== '' ? $code . ' - ' . $name : $code;
};

$formatValue = static function (string $field, mixed $value) use ($relationLabel): string {
    $relation = $relationLabel($field, $value);
    if ($relation !== null) {
        return $relation;
    }

    if ($value === null || $value === '') {
        return '-';
    }

    if (is_numeric($value) && str_contains((string) $value, '.')) {
        return rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
    }

    return (string) $value;
};
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($config['title']) ?></h4>
                <p class="text-muted mb-0">Manage master data and bulk update using Excel files.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($canManage && ! empty($config['sync_action'])): ?>
                    <form action="<?= site_url($config['sync_action']) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-info waves-effect waves-light" type="submit">
                            <i class="bx bx-refresh me-1"></i> Sync API
                        </button>
                    </form>
                <?php endif ?>

                <a class="btn btn-outline-secondary waves-effect waves-light" href="<?= site_url("setup/{$resource}/export") ?>">
                    <i class="bx bx-download me-1"></i> Export Excel
                </a>

                <?php if ($canManage): ?>
                    <a class="btn btn-outline-secondary waves-effect waves-light" href="<?= site_url("setup/{$resource}/template") ?>">
                        <i class="bx bx-file me-1"></i> Excel Template
                    </a>
                    <a class="btn btn-outline-success waves-effect waves-light" href="<?= site_url("setup/{$resource}/import") ?>">
                        <i class="bx bx-upload me-1"></i> Import Excel
                    </a>
                    <a class="btn btn-primary waves-effect waves-light" href="<?= site_url("setup/{$resource}/new") ?>">
                        <i class="bx bx-plus me-1"></i> New
                    </a>
                <?php endif ?>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
            <i class="bx bx-info-circle fs-5"></i>
            <div>
                <div class="fw-semibold">Excel bulk update</div>
                <div>Download the Excel template first, keep the first-row headers unchanged, then upload the filled <code>.xlsx</code> file using Import Excel.</div>
            </div>
        </div>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-lg-5 col-md-6">
                <label class="form-label" for="masterSearch">Search</label>
                <input type="text" class="form-control" id="masterSearch" placeholder="Search code, name, description...">
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="form-label" for="masterStatusFilter">Status</label>
                <select class="form-select" id="masterStatusFilter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label" for="masterPerPage">Rows</label>
                <select class="form-select" id="masterPerPage">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-lg-2 text-lg-end">
                <button class="btn btn-light w-100" type="button" id="masterResetFilter">
                    <i class="bx bx-reset me-1"></i> Reset
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0" id="masterDataTable">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($listFields as $field): ?>
                            <th><?= esc($fieldLabel($field, $config)) ?></th>
                        <?php endforeach ?>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $rowStatus = (int) ($row['is_active'] ?? 1) === 1 ? 'active' : 'inactive'; ?>
                    <tr data-status="<?= esc($rowStatus) ?>">
                        <?php foreach ($listFields as $index => $field): ?>
                            <td class="<?= $index === 0 ? 'fw-semibold' : '' ?>">
                                <?= esc($formatValue($field, $row[$field] ?? null)) ?>
                            </td>
                        <?php endforeach ?>
                        <td>
                            <span class="badge bg-<?= $rowStatus === 'active' ? 'success' : 'secondary' ?>">
                                <?= $rowStatus === 'active' ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url("setup/{$resource}/{$row['id']}") ?>" title="View">
                                <i class="bx bx-show"></i>
                            </a>
                            <?php if ($canManage): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url("setup/{$resource}/{$row['id']}/edit") ?>" title="Edit">
                                    <i class="bx bx-edit"></i>
                                </a>
                                <form class="d-inline" action="<?= site_url("setup/{$resource}/{$row['id']}/delete") ?>" method="post" onsubmit="return confirm('Delete this record?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">View only</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <div class="text-muted small" id="masterPaginationInfo"></div>
            <nav aria-label="Master data pagination">
                <ul class="pagination pagination-sm mb-0" id="masterPagination"></ul>
            </nav>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const table = document.getElementById('masterDataTable');
    const search = document.getElementById('masterSearch');
    const status = document.getElementById('masterStatusFilter');
    const perPage = document.getElementById('masterPerPage');
    const reset = document.getElementById('masterResetFilter');
    const pagination = document.getElementById('masterPagination');
    const paginationInfo = document.getElementById('masterPaginationInfo');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    let currentPage = 1;

    function pageButton(label, page, disabled, active) {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');

        const button = document.createElement('button');
        button.className = 'page-link';
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', function () {
            currentPage = page;
            applyFilter();
        });

        li.appendChild(button);
        return li;
    }

    function renderPagination(totalMatched, limit) {
        if (!pagination || !paginationInfo) return;

        pagination.innerHTML = '';
        const totalPages = Math.max(1, Math.ceil(totalMatched / limit));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);
        const start = totalMatched === 0 ? 0 : ((currentPage - 1) * limit) + 1;
        const end = Math.min(currentPage * limit, totalMatched);
        paginationInfo.textContent = `Showing ${start}-${end} of ${totalMatched} rows`;

        pagination.appendChild(pageButton('Previous', Math.max(1, currentPage - 1), currentPage === 1, false));

        const pages = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
        let lastPage = 0;
        Array.from(pages)
            .filter(page => page >= 1 && page <= totalPages)
            .sort((a, b) => a - b)
            .forEach(page => {
                if (lastPage && page - lastPage > 1) {
                    const li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = '<span class="page-link">...</span>';
                    pagination.appendChild(li);
                }
                pagination.appendChild(pageButton(String(page), page, false, page === currentPage));
                lastPage = page;
            });

        pagination.appendChild(pageButton('Next', Math.min(totalPages, currentPage + 1), currentPage === totalPages, false));
    }

    function applyFilter(resetPage) {
        if (resetPage) currentPage = 1;
        const keyword = (search.value || '').toLowerCase();
        const statusValue = status.value;
        const limit = parseInt(perPage.value, 10) || 25;
        const matchedRows = [];

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const rowStatus = row.dataset.status || 'active';
            const matchedKeyword = keyword === '' || text.includes(keyword);
            const matchedStatus = statusValue === 'all' || rowStatus === statusValue;
            if (matchedKeyword && matchedStatus) matchedRows.push(row);
            row.style.display = 'none';
        });

        const totalPages = Math.max(1, Math.ceil(matchedRows.length / limit));
        currentPage = Math.min(currentPage, totalPages);
        const startIndex = (currentPage - 1) * limit;
        matchedRows.slice(startIndex, startIndex + limit).forEach(row => row.style.display = '');
        renderPagination(matchedRows.length, limit);
    }

    [search, status, perPage].forEach(el => el && el.addEventListener('input', () => applyFilter(true)));
    if (reset) {
        reset.addEventListener('click', function () {
            search.value = '';
            status.value = 'all';
            perPage.value = '25';
            applyFilter(true);
        });
    }

    applyFilter(true);
})();
</script>
<?= $this->endSection() ?>
