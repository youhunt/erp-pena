<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$workOrder ??= [];
$components ??= [];
$routings ??= [];
$nextWoNo ??= 'WO-' . date('Ymd-His');
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('production/work-orders/' . (int) ($workOrder['id'] ?? 0)) : site_url('production/work-orders');
$val = static fn (string $field, mixed $default = ''): string => (string) old($field, $workOrder[$field] ?? $default);
$componentRows = $components !== [] ? $components : array_fill(0, 6, []);
$routingRows = $routings !== [] ? $routings : array_fill(0, 6, []);
$itemLabel = static function (array $item): string {
    $code = (string) ($item['item_code'] ?? $item['code'] ?? '');
    $name = (string) ($item['item_name'] ?? $item['name'] ?? '');
    return trim($code . ($name !== '' ? ' - ' . $name : ''));
};
?>
<form method="post" action="<?= esc($action, 'attr') ?>" id="workOrderForm" data-preview-url="<?= esc(site_url('production/work-orders/bom-preview'), 'attr') ?>">
    <?= csrf_field() ?>
    <?php if (! $isEdit): ?>
        <input type="hidden" name="wo_no_auto" value="1">
    <?php endif ?>
    <input type="hidden" name="bom_id" id="bom_id" value="<?= esc($val('bom_id')) ?>">
    <input type="hidden" name="routing_id" id="routing_id" value="<?= esc($val('routing_id')) ?>">

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">WORK ORDER</h4>
                    <p class="text-muted mb-0">Pilih parent item, sistem otomatis preview BOM component dan routing.</p>
                </div>
                <a href="<?= $isEdit ? site_url('production/work-orders/' . (int) ($workOrder['id'] ?? 0)) : site_url('production/work-orders') ?>" class="btn btn-light">
                    <i class="bx bx-arrow-back me-1"></i> Back
                </a>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">WO Code</label>
                    <input name="wo_code" class="form-control" value="<?= esc($val('wo_code', 'WO')) ?>" <?= ! $isEdit ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label">WO No.</label>
                    <input name="wo_no" class="form-control" required value="<?= esc($val('wo_no', $nextWoNo)) ?>" <?= ! $isEdit ? 'readonly' : '' ?>>
                    <?php if (! $isEdit): ?><div class="form-text">Nomor final diambil dari Document Numbering saat Save.</div><?php endif ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Site</label>
                    <select name="site_code" class="form-select js-wo-source" required>
                        <option value="">Select Site</option>
                        <?php foreach ($sites as $site): ?>
                            <?php $code = (string) ($site['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('site_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($site['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">WO Date</label>
                    <input type="date" name="wo_date" class="form-control" required value="<?= esc($val('wo_date', date('Y-m-d'))) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Item Parent</label>
                    <select name="parent_item_code" class="form-select js-wo-source" required>
                        <option value="">Select Item Parent</option>
                        <?php foreach ($items as $item): ?>
                            <?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('parent_item_code') === $code ? 'selected' : '' ?>><?= esc($itemLabel($item)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dept</label>
                    <select name="department_code" class="form-select js-wo-source" required>
                        <option value="">Select Dept</option>
                        <?php foreach ($departments as $dept): ?>
                            <?php $code = (string) ($dept['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('department_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($dept['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Work Center</label>
                    <select name="work_center_code" class="form-select">
                        <option value="">Auto / Select</option>
                        <?php foreach ($workCenters as $wc): ?>
                            <?php $code = (string) ($wc['work_center_code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('work_center_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($wc['description'] ?? $wc['work_center_name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Batch Qty</label>
                    <input type="number" step="0.000001" name="batch_qty" class="form-control text-end" value="<?= esc($val('batch_qty', '1')) ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Qty WO</label>
                    <input type="number" step="0.000001" name="wo_qty" class="form-control text-end js-wo-source" required value="<?= esc($val('wo_qty', '1')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">UoM</label>
                    <input name="uom_code" class="form-control" value="<?= esc($val('uom_code', 'PCS')) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Std Qty Finished</label>
                    <input type="number" step="0.000001" name="std_qty_finished" class="form-control text-end" value="<?= esc($val('std_qty_finished', $val('wo_qty', '1'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Act Qty Finished</label>
                    <input type="number" step="0.000001" name="act_qty_finished" class="form-control text-end" value="<?= esc($val('act_qty_finished', '0')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Default Whs</label>
                    <select name="warehouse_code" class="form-select js-wo-source">
                        <option value="">From BOM</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <?php $code = (string) ($wh['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('warehouse_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($wh['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Description</label>
                    <input name="description" class="form-control" maxlength="500" value="<?= esc($val('description')) ?>">
                </div>
            </div>

            <?php if (! $isEdit): ?>
                <div class="alert alert-info mt-4 mb-0" id="woPreviewStatus">
                    Pilih Site dan Item Parent, lalu sistem akan menampilkan komponen BOM dan routing sebelum WO disimpan.
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">BOM</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">No.</th>
                            <th style="min-width:160px">Component</th>
                            <th style="min-width:220px">Name</th>
                            <th class="text-end" style="width:130px">Qty Used</th>
                            <th style="width:100px">UoM</th>
                            <th style="width:120px">Whs</th>
                            <th style="width:120px">Loc</th>
                            <th style="width:140px">Batch No.</th>
                            <th class="text-end" style="width:140px">Booking Qty</th>
                        </tr>
                    </thead>
                    <tbody id="woComponentRows">
                    <?php foreach ($componentRows as $i => $component): ?>
                        <tr>
                            <td><input name="component_line_no[]" class="form-control form-control-sm text-center" value="<?= esc($component['line_no'] ?? ($i + 1)) ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="component_item_code[]" class="form-control form-control-sm" value="<?= esc($component['component_item_code'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="component_item_name[]" class="form-control form-control-sm" value="<?= esc($component['component_item_name'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input type="number" step="0.000001" name="component_qty_used[]" class="form-control form-control-sm text-end" value="<?= esc($component['qty_used'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="component_uom_code[]" class="form-control form-control-sm" value="<?= esc($component['uom_code'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="component_warehouse_code[]" class="form-control form-control-sm" value="<?= esc($component['warehouse_code'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="component_location_code[]" class="form-control form-control-sm" value="<?= esc($component['location_code'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="component_batch_no[]" class="form-control form-control-sm" value="<?= esc($component['batch_no'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input type="number" step="0.000001" name="component_booking_qty[]" class="form-control form-control-sm text-end" value="<?= esc($component['booking_qty'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">Routing</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">No.</th>
                            <th style="min-width:260px">Routing Name</th>
                            <th style="min-width:220px">Work Center Name</th>
                            <th class="text-end" style="width:130px">Hour</th>
                            <th style="width:110px">UoM</th>
                        </tr>
                    </thead>
                    <tbody id="woRoutingRows">
                    <?php foreach ($routingRows as $i => $routing): ?>
                        <tr>
                            <td><input name="routing_line_no[]" class="form-control form-control-sm text-center" value="<?= esc($routing['line_no'] ?? ($i + 1)) ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="wo_routing_name[]" class="form-control form-control-sm" value="<?= esc($routing['routing_name'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td>
                                <?php if ($isEdit): ?>
                                    <select name="wo_work_center_code[]" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        <?php foreach ($workCenters as $wc): ?>
                                            <?php $code = (string) ($wc['work_center_code'] ?? ''); ?>
                                            <option value="<?= esc($code) ?>" <?= ($routing['work_center_code'] ?? '') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($wc['description'] ?? $wc['work_center_name'] ?? '')) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                <?php else: ?>
                                    <input name="wo_work_center_code[]" type="hidden" value="<?= esc($routing['work_center_code'] ?? '') ?>">
                                    <input name="wo_work_center_name[]" class="form-control form-control-sm" readonly value="<?= esc(($routing['work_center_code'] ?? '') . (($routing['work_center_name'] ?? '') !== '' ? ' - ' . $routing['work_center_name'] : '')) ?>">
                                <?php endif ?>
                            </td>
                            <td><input type="number" step="0.000001" name="wo_hour_qty[]" class="form-control form-control-sm text-end" value="<?= esc($routing['hour_qty'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                            <td><input name="wo_route_uom[]" class="form-control form-control-sm" value="<?= esc($routing['uom_code'] ?? '') ?>" <?= ! $isEdit ? 'readonly' : '' ?>></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit ? 'Update Work Order' : 'Save Work Order' ?></button>
                <a class="btn btn-light" href="<?= $isEdit ? site_url('production/work-orders/' . (int) ($workOrder['id'] ?? 0)) : site_url('production/work-orders') ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?php if (! $isEdit): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('workOrderForm');
    if (!form) return;

    const status = document.getElementById('woPreviewStatus');
    const componentBody = document.getElementById('woComponentRows');
    const routingBody = document.getElementById('woRoutingRows');
    const emptyComponentRows = componentBody ? componentBody.innerHTML : '';
    const emptyRoutingRows = routingBody ? routingBody.innerHTML : '';

    function field(name) {
        return form.querySelector('[name="' + name + '"]');
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[char];
        });
    }

    function numberValue(value) {
        const number = Number(value || 0);
        return Number.isFinite(number) ? String(number) : '0';
    }

    function setStatus(message, type) {
        if (!status) return;
        status.className = 'alert mt-4 mb-0 alert-' + (type || 'info');
        status.textContent = message;
    }

    function resetPreview() {
        field('bom_id').value = '';
        field('routing_id').value = '';
        if (componentBody) componentBody.innerHTML = emptyComponentRows;
        if (routingBody) routingBody.innerHTML = emptyRoutingRows;
    }

    function renderComponents(rows) {
        if (!componentBody) return;
        if (!rows || rows.length === 0) {
            componentBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada component dari BOM.</td></tr>';
            return;
        }
        componentBody.innerHTML = rows.map(function (row, index) {
            return '<tr>'
                + '<td><input name="component_line_no[]" class="form-control form-control-sm text-center" readonly value="' + esc(row.line_no || (index + 1)) + '"></td>'
                + '<td><input name="component_item_code[]" class="form-control form-control-sm" readonly value="' + esc(row.component_item_code) + '"></td>'
                + '<td><input name="component_item_name[]" class="form-control form-control-sm" readonly value="' + esc(row.component_item_name) + '"></td>'
                + '<td><input type="number" step="0.000001" name="component_qty_used[]" class="form-control form-control-sm text-end" readonly value="' + esc(numberValue(row.qty_used)) + '"></td>'
                + '<td><input name="component_uom_code[]" class="form-control form-control-sm" readonly value="' + esc(row.uom_code) + '"></td>'
                + '<td><input name="component_warehouse_code[]" class="form-control form-control-sm" readonly value="' + esc(row.warehouse_code) + '"></td>'
                + '<td><input name="component_location_code[]" class="form-control form-control-sm" readonly value="' + esc(row.location_code) + '"></td>'
                + '<td><input name="component_batch_no[]" class="form-control form-control-sm" readonly value="' + esc(row.batch_no) + '"></td>'
                + '<td><input type="number" step="0.000001" name="component_booking_qty[]" class="form-control form-control-sm text-end" readonly value="' + esc(numberValue(row.booking_qty)) + '"></td>'
                + '</tr>';
        }).join('');
    }

    function renderRoutings(rows) {
        if (!routingBody) return;
        if (!rows || rows.length === 0) {
            routingBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada routing untuk item ini.</td></tr>';
            return;
        }
        routingBody.innerHTML = rows.map(function (row, index) {
            const wcLabel = String(row.work_center_code || '') + (row.work_center_name ? ' - ' + row.work_center_name : '');
            return '<tr>'
                + '<td><input name="routing_line_no[]" class="form-control form-control-sm text-center" readonly value="' + esc(row.line_no || (index + 1)) + '"></td>'
                + '<td><input name="wo_routing_name[]" class="form-control form-control-sm" readonly value="' + esc(row.routing_name) + '"></td>'
                + '<td><input name="wo_work_center_code[]" type="hidden" value="' + esc(row.work_center_code) + '"><input name="wo_work_center_name[]" class="form-control form-control-sm" readonly value="' + esc(wcLabel) + '"></td>'
                + '<td><input type="number" step="0.000001" name="wo_hour_qty[]" class="form-control form-control-sm text-end" readonly value="' + esc(numberValue(row.hour_qty)) + '"></td>'
                + '<td><input name="wo_route_uom[]" class="form-control form-control-sm" readonly value="' + esc(row.uom_code) + '"></td>'
                + '</tr>';
        }).join('');
    }

    let timer = null;
    function loadPreview() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            const site = field('site_code').value;
            const parent = field('parent_item_code').value;
            if (!site || !parent) {
                resetPreview();
                setStatus('Pilih Site dan Item Parent, lalu sistem akan menampilkan BOM dan Routing.', 'info');
                return;
            }

            const params = new URLSearchParams({
                site_code: site,
                department_code: field('department_code').value,
                warehouse_code: field('warehouse_code').value,
                parent_item_code: parent,
                wo_qty: field('wo_qty').value || '1'
            });
            setStatus('Mengambil BOM dan Routing...', 'info');

            fetch(form.dataset.previewUrl + '?' + params.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) throw new Error(payload.error || 'Gagal mengambil BOM/Routing.');
                        return payload;
                    });
                })
                .then(function (payload) {
                    field('bom_id').value = payload.bom ? payload.bom.id : '';
                    field('routing_id').value = payload.routing ? payload.routing.id : '';
                    if (payload.bom) {
                        field('batch_qty').value = numberValue(payload.bom.batch_qty || 1);
                        field('uom_code').value = payload.bom.uom_code || 'PCS';
                        if (!field('description').value && payload.bom.description) field('description').value = payload.bom.description;
                    }
                    if (payload.routing && payload.routing.work_center_code && !field('work_center_code').value) {
                        field('work_center_code').value = payload.routing.work_center_code;
                    }
                    renderComponents(payload.components || []);
                    renderRoutings(payload.routings || []);
                    setStatus('BOM dan Routing berhasil dimuat. Saat Save, detail ini akan ikut tersimpan ke Work Order.', 'success');
                })
                .catch(function (error) {
                    resetPreview();
                    setStatus(error.message || 'BOM/Routing tidak ditemukan.', 'warning');
                });
        }, 250);
    }

    form.querySelectorAll('.js-wo-source').forEach(function (element) {
        element.addEventListener('change', loadPreview);
        element.addEventListener('input', loadPreview);
    });

    if (field('site_code').value && field('parent_item_code').value) {
        loadPreview();
    }
});
</script>
<?php endif ?>
<?= $this->endSection() ?>
