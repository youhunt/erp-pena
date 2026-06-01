<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isEdit = isset($row['id']);
$action = $isEdit ? site_url("setup/{$resource}/{$row['id']}") : site_url("setup/{$resource}");
?>

<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4"><?= esc($title) ?></h4>

                <form action="<?= $action ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="row">
                        <?php foreach ($config['fields'] as $name => $field): ?>
                            <?php
                            $value = old($name, $row[$name] ?? ($field['default'] ?? ''));
                            $type = $field['type'];
                            ?>

                            <?php if ($type === 'checkbox'): ?>
                                <div class="col-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="<?= esc($name) ?>" name="<?= esc($name) ?>" value="1" <?= (int) $value === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    </div>
                                </div>
                            <?php elseif ($type === 'textarea'): ?>
                                <div class="col-12 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <textarea class="form-control" id="<?= esc($name) ?>" name="<?= esc($name) ?>" rows="3"><?= esc($value) ?></textarea>
                                </div>
                            <?php elseif ($type === 'select'): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <select class="form-select" id="<?= esc($name) ?>" name="<?= esc($name) ?>">
                                        <?php foreach ($field['options'] as $optionValue => $optionLabel): ?>
                                            <option value="<?= esc((string) $optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>>
                                                <?= esc($optionLabel) ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <input class="form-control" id="<?= esc($name) ?>" name="<?= esc($name) ?>" type="<?= esc($type) ?>" value="<?= esc((string) $value) ?>" <?= ! empty($field['required']) ? 'required' : '' ?>>
                                </div>
                            <?php endif ?>
                        <?php endforeach ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary waves-effect waves-light" type="submit">
                            <i class="bx bx-save me-1"></i> Save
                        </button>
                        <a class="btn btn-light" href="<?= site_url("setup/{$resource}") ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
