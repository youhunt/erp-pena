<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rows ??= [];
$displayFields = $config['display_fields'] ?? array_keys($config['fields'] ?? []);
$label = static fn (string $field): string => (string) ($config['fields'][$field]['label'] ?? ucwords(str_replace('_', ' ', $field)));
$format = static function (string $field, mixed $value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if (str_contains($field, 'pctg') || is_float($value)) {
        return number_format((float) $value, 2);
    }
    return (string) $value;
};
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($config['title']) ?></h4>
                <p class="text-muted mb-0">Manage tax master setup by active company and site.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($canManage): ?>
                    <a class="btn btn-primary" href="<?= site_url('setup/' . $resource . '/new') ?>"><i class="bx bx-plus me-1"></i> New</a>
                <?php endif ?>
            </div>
        </div>

        <?php if (session('error')): ?>
            <div class="alert alert-danger"><?= esc(session('error')) ?></div>
        <?php endif ?>
        <?php if (session('message')): ?>
            <div class="alert alert-success"><?= esc(session('message')) ?></div>
        <?php endif ?>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-lg-6 col-md-7">
                <label class="form-label" for="taxSearch">Search</label>
                <input type="text" class="form-control" id="taxSearch" placeholder="Search code, description, GL...">
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="form-label" for="taxStatusFilter">Status</label>
                <select class="form-select" id="taxStatusFilter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-2">
                <button class="btn btn-light w-100" type="button" id="taxResetFilter"><i class="bx bx-reset me-1"></i> Reset</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0" id="taxMasterTable">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($displayFields as $field): ?>
                            <th><?= esc($label($field)) ?></th>
                        <?php endforeach ?>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $rowStatus = (int) ($row['is_active'] ?? 1) === 1 ? 'active' : 'inactive'; ?>
                    <tr data-status="<?= esc($rowStatus) ?>">
                        <?php foreach ($displayFields as $index => $field): ?>
                            <td class="<?= $index === 2 || ($index === 0 && ! in_array('site', $displayFields, true)) ? 'fw-semibold' : '' ?>">
                                <?= esc($format($field, $row[$field] ?? null)) ?>
                            </td>
                        <?php endforeach ?>
                        <td><span class="badge bg-<?= $rowStatus === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($rowStatus) ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('setup/' . $resource . '/' . (int) $row['id']) ?>"><i class="bx bx-show"></i></a>
                            <?php if ($canManage): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url('setup/' . $resource . '/' . (int) $row['id'] . '/edit') ?>"><i class="bx bx-edit"></i></a>
                                <form class="d-inline" method="post" action="<?= site_url('setup/' . $resource . '/' . (int) $row['id'] . '/delete') ?>" onsubmit="return confirm('Delete this record?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bx bx-trash"></i></button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="<?= count($displayFields) + 2 ?>" class="text-center text-muted py-4">No data yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const search = document.getElementById('taxSearch');
    const status = document.getElementById('taxStatusFilter');
    const reset = document.getElementById('taxResetFilter');
    const rows = Array.from(document.querySelectorAll('#taxMasterTable tbody tr[data-status]'));
    function apply() {
        const q = (search.value || '').toLowerCase();
        const s = status.value || 'all';
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const okSearch = q === '' || text.includes(q);
            const okStatus = s === 'all' || row.dataset.status === s;
            row.style.display = okSearch && okStatus ? '' : 'none';
        });
    }
    search.addEventListener('input', apply);
    status.addEventListener('change', apply);
    reset.addEventListener('click', function () { search.value = ''; status.value = 'all'; apply(); });
})();
</script>
<?= $this->endSection() ?>
