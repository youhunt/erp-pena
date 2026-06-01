<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$listFields = $config['list_fields'] ?? array_keys($config['fields'] ?? []);
$listFields = array_values(array_filter($listFields, static fn (string $field): bool => $field !== 'is_active'));
$listFields = array_slice($listFields, 0, 6);

$fieldLabel = static function (string $field, array $config): string {
    if (isset($config['fields'][$field]['label'])) {
        return (string) $config['fields'][$field]['label'];
    }

    return ucwords(str_replace('_', ' ', $field));
};

$formatValue = static function (mixed $value): string {
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
                <p class="text-muted mb-0">Manage, import, and export master data.</p>
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
                    <i class="bx bx-download me-1"></i> Export CSV
                </a>

                <?php if ($canManage): ?>
                    <a class="btn btn-outline-secondary waves-effect waves-light" href="<?= site_url("setup/{$resource}/template") ?>">
                        <i class="bx bx-file me-1"></i> Template
                    </a>
                    <a class="btn btn-outline-success waves-effect waves-light" href="<?= site_url("setup/{$resource}/import") ?>">
                        <i class="bx bx-upload me-1"></i> Import CSV
                    </a>
                    <a class="btn btn-primary waves-effect waves-light" href="<?= site_url("setup/{$resource}/new") ?>">
                        <i class="bx bx-plus me-1"></i> New
                    </a>
                <?php endif ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
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
                    <tr>
                        <?php foreach ($listFields as $index => $field): ?>
                            <td class="<?= $index === 0 ? 'fw-semibold' : '' ?>">
                                <?= esc($formatValue($row[$field] ?? null)) ?>
                            </td>
                        <?php endforeach ?>
                        <td>
                            <span class="badge bg-<?= (int) ($row['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?>">
                                <?= (int) ($row['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($canManage): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url("setup/{$resource}/{$row['id']}/edit") ?>">
                                    <i class="bx bx-edit"></i>
                                </a>
                                <form class="d-inline" action="<?= site_url("setup/{$resource}/{$row['id']}/delete") ?>" method="post" onsubmit="return confirm('Delete this record?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">View only</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="<?= count($listFields) + 2 ?>" class="text-center text-muted py-4">No records yet. Use New or Import CSV to add master data.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
