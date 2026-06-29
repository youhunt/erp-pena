<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isEdit = isset($row['id']);
$action = $isEdit ? site_url('setup/' . $resource . '/' . (int) $row['id']) : site_url('setup/' . $resource);
$tenantLabels ??= ['company' => '', 'site' => ''];
$glOptions ??= [];
$valueOf = static fn (string $field, mixed $default = ''): string => (string) old($field, $row[$field] ?? $default);
?>
<div class="row">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0">Company/Site mengikuti pilihan aktif di header.</p>
                    </div>
                    <a class="btn btn-light" href="<?= site_url('setup/' . $resource) ?>"><i class="bx bx-arrow-back me-1"></i> Back</a>
                </div>

                <?php if (session('error')): ?>
                    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
                <?php endif ?>

                <div class="alert alert-info py-2">
                    Company/Site aktif: <strong><?= esc($tenantLabels['company'] ?: '-') ?></strong> / <strong><?= esc($tenantLabels['site'] ?: '-') ?></strong>
                </div>

                <form action="<?= esc($action, 'attr') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company" value="<?= esc($tenantLabels['company'], 'attr') ?>">
                    <input type="hidden" name="site" value="<?= esc($tenantLabels['site'], 'attr') ?>">

                    <div class="row">
                        <?php foreach ($config['fields'] as $name => $field): ?>
                            <?php
                            $type = (string) ($field['type'] ?? 'text');
                            if ($type === 'hidden_tenant') {
                                continue;
                            }
                            $value = $valueOf($name, $field['default'] ?? '');
                            ?>
                            <?php if ($type === 'textarea'): ?>
                                <div class="col-12 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <textarea class="form-control" id="<?= esc($name) ?>" name="<?= esc($name) ?>" rows="3" maxlength="<?= esc((string) ($field['max'] ?? 500)) ?>"><?= esc($value) ?></textarea>
                                </div>
                            <?php elseif ($type === 'gl'): ?>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <select class="form-select select2-basic" id="<?= esc($name) ?>" name="<?= esc($name) ?>">
                                        <option value="">Select GL Code</option>
                                        <?php foreach ($glOptions as $accountNo => $label): ?>
                                            <option value="<?= esc((string) $accountNo, 'attr') ?>" <?= (string) $value === (string) $accountNo ? 'selected' : '' ?>><?= esc($label) ?></option>
                                        <?php endforeach ?>
                                        <?php if ($value !== '' && ! isset($glOptions[$value])): ?>
                                            <option value="<?= esc($value, 'attr') ?>" selected><?= esc($value) ?></option>
                                        <?php endif ?>
                                    </select>
                                    <div class="form-text">Ambil dari Chart of Account / GL Code.</div>
                                </div>
                            <?php elseif ($type === 'number'): ?>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <input class="form-control text-end" id="<?= esc($name) ?>" name="<?= esc($name) ?>" type="number" step="0.01" min="0" max="999.99" value="<?= esc($value) ?>">
                                </div>
                            <?php else: ?>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <input class="form-control" id="<?= esc($name) ?>" name="<?= esc($name) ?>" type="text" value="<?= esc($value) ?>" maxlength="<?= esc((string) ($field['max'] ?? 500)) ?>" <?= ! empty($field['required']) ? 'required' : '' ?>>
                                </div>
                            <?php endif ?>
                        <?php endforeach ?>

                        <div class="col-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $row['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> Save</button>
                        <a class="btn btn-light" href="<?= site_url('setup/' . $resource) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.select2-basic').select2({ width: '100%' });
    }
});
</script>
<?= $this->endSection() ?>
