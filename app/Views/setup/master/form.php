<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isEdit = isset($row['id']);
$action = $isEdit ? site_url("setup/{$resource}/{$row['id']}") : site_url("setup/{$resource}");
$addressTemplates ??= [];
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
                            <?php elseif ($type === 'address_template'): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <select
                                        class="form-select js-address-template"
                                        id="<?= esc($name) ?>"
                                        data-targets='<?= esc(json_encode($field['targets'] ?? [], JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>'
                                    >
                                        <option value="">Select address template</option>
                                        <?php foreach ($addressTemplates as $templateId => $template): ?>
                                            <option value="<?= esc((string) $templateId) ?>"><?= esc((string) ($template['label'] ?? $templateId)) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <div class="form-text">Filled from Address Master. You can still edit the copied values.</div>
                                </div>
                            <?php elseif ($type === 'textarea'): ?>
                                <div class="col-12 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <textarea class="form-control" id="<?= esc($name) ?>" name="<?= esc($name) ?>" rows="3"><?= esc($value) ?></textarea>
                                </div>
                            <?php elseif ($type === 'select'): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="<?= esc($name) ?>"><?= esc($field['label']) ?></label>
                                    <select
                                        class="form-select <?= ! empty($field['depends_on']) ? 'js-dependent-select' : '' ?>"
                                        id="<?= esc($name) ?>"
                                        name="<?= esc($name) ?>"
                                        data-depends-on="<?= esc((string) ($field['depends_on'] ?? ''), 'attr') ?>"
                                        data-options-url="<?= ! empty($field['options_endpoint']) ? esc(site_url($field['options_endpoint']), 'attr') : '' ?>"
                                        data-current-value="<?= esc((string) $value, 'attr') ?>"
                                    >
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
<?php if ($addressTemplates !== []): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const templates = <?= json_encode($addressTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const sourceAliases = {
        main_address: 'address',
        main_phone: 'phone',
        main_email: 'email'
    };

    function valueFor(template, source) {
        return template[sourceAliases[source] || source] || '';
    }

    document.querySelectorAll('.js-address-template').forEach(function (select) {
        select.addEventListener('change', function () {
            const template = templates[select.value];
            const targets = JSON.parse(select.dataset.targets || '{}');

            if (!template) {
                return;
            }

            Object.keys(targets).forEach(function (source) {
                const targetId = targets[source];
                const input = document.getElementById(targetId);
                if (!input) {
                    return;
                }

                input.value = valueFor(template, source);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });
});
</script>
<?php endif ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-dependent-select').forEach(function (select) {
        const parent = document.getElementById(select.dataset.dependsOn || '');
        const url = select.dataset.optionsUrl || '';

        if (!parent || !url) {
            return;
        }

        function resetOptions(placeholder) {
            select.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            select.appendChild(option);
        }

        function loadOptions(keepCurrent) {
            const parentValue = parent.value || '';
            const currentValue = keepCurrent ? (select.dataset.currentValue || select.value || '') : '';

            if (parentValue === '') {
                resetOptions('Select parent first');
                select.dataset.currentValue = '';
                return;
            }

            resetOptions('Loading...');

            fetch(url + '?' + new URLSearchParams({ province_id: parentValue }).toString(), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (rows) {
                    resetOptions('Select ' + (select.closest('.mb-3').querySelector('label')?.textContent || 'option'));

                    rows.forEach(function (row) {
                        const option = document.createElement('option');
                        option.value = row.value;
                        option.textContent = row.label;
                        if (currentValue !== '' && currentValue === String(row.value)) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });

                    select.dataset.currentValue = '';
                })
                .catch(function () {
                    resetOptions('Unable to load options');
                });
        }

        parent.addEventListener('change', function () {
            loadOptions(false);
        });

        loadOptions(true);
    });
});
</script>
<?= $this->endSection() ?>
