<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$displayFields = $config['display_fields'] ?? array_keys($config['fields'] ?? []);
$label = static fn (string $field): string => (string) ($config['fields'][$field]['label'] ?? ucwords(str_replace('_', ' ', $field)));
$format = static function (string $field, mixed $value, string $glLabel): string {
    if ($field === 'gl') {
        return $glLabel;
    }
    if ($value === null || $value === '') {
        return '-';
    }
    if (str_contains($field, 'pctg')) {
        return number_format((float) $value, 2);
    }
    return (string) $value;
};
?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0 font-monospace"><?= esc($row['vat'] ?? '-') ?></p>
                    </div>
                    <span class="badge bg-<?= (int) ($row['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?>">
                        <?= (int) ($row['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                    </span>
                </div>

                <table class="table table-sm mb-0">
                    <?php foreach ($displayFields as $field): ?>
                        <tr>
                            <th style="width:260px"><?= esc($label($field)) ?></th>
                            <td><?= esc($format($field, $row[$field] ?? null, $glLabel ?? '-')) ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>

                <div class="d-flex gap-2 mt-4">
                    <a class="btn btn-light" href="<?= site_url('setup/' . $resource) ?>"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <?php if ($canManage): ?>
                        <a class="btn btn-outline-primary" href="<?= site_url('setup/' . $resource . '/' . (int) $row['id'] . '/edit') ?>"><i class="bx bx-edit me-1"></i> Edit</a>
                        <form method="post" action="<?= site_url('setup/' . $resource . '/' . (int) $row['id'] . '/delete') ?>" onsubmit="return confirm('Delete this record?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-danger" type="submit"><i class="bx bx-trash me-1"></i> Delete</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
