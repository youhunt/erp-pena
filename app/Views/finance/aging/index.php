<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$filters ??= ['partner_code' => '', 'partner_group' => '', 'aging_bucket' => ''];
$bucketOptions ??= ['' => 'All Aging', 'current' => 'Current', 'days_1_30' => '1-30', 'days_31_60' => '31-60', 'days_over_90' => '> 90'];
$partnerLabel = (string) ($config['partnerLabel'] ?? 'Partner');
$codeField = (string) ($config['partnerCodeField'] ?? 'partner_code');
$nameField = (string) ($config['partnerNameField'] ?? 'partner_name');
$currentPartner = (string) ($filters['partner_code'] ?? '');
$query = array_filter(['as_of' => $asOf, 'partner_code' => $currentPartner, 'partner_group' => $filters['partner_group'] ?? '', 'aging_bucket' => $filters['aging_bucket'] ?? ''], static fn ($v) => (string) $v !== '');
$exportUrl = current_url() . '?' . http_build_query($query + ['export' => 'xlsx']);
$partnerOptions = [];
foreach (($summary ?? []) as $row) {
    $code = trim((string) ($row['partner_code'] ?? ''));
    if ($code !== '') $partnerOptions[$code] = trim((string) ($row['partner_name'] ?? ''));
}
foreach (($rows ?? []) as $row) {
    $code = trim((string) ($row[$codeField] ?? ''));
    if ($code !== '' && ! isset($partnerOptions[$code])) $partnerOptions[$code] = trim((string) ($row[$nameField] ?? ''));
}
if ($currentPartner !== '' && ! isset($partnerOptions[$currentPartner])) $partnerOptions[$currentPartner] = '(selected)';
ksort($partnerOptions, SORT_NATURAL | SORT_FLAG_CASE);
$bucketLabels = ['current' => 'Current', 'days_1_30' => '1-30', 'days_31_60' => '31-60', 'days_61_90' => '61-90', 'days_over_90' => '> 90'];
?>
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div><h4 class="card-title mb-1"><?= esc($title) ?></h4><p class="text-muted mb-0">Outstanding invoice aging by active company/site.</p></div>
        <div class="d-flex flex-wrap gap-2"><a href="<?= current_url() ?>" class="btn btn-light"><i class="bx bx-reset me-1"></i> Reset</a><a href="<?= esc($exportUrl) ?>" class="btn btn-success"><i class="bx bx-download me-1"></i> Export XLSX</a></div>
    </div>

    <form method="get" action="<?= current_url() ?>" class="row g-3 align-items-end mb-4">
        <div class="col-xl-2 col-md-4"><label class="form-label">As Of</label><input type="date" name="as_of" value="<?= esc($asOf) ?>" class="form-control"></div>
        <div class="col-xl-3 col-md-4">
            <label class="form-label"><?= esc($partnerLabel) ?> Code / Name</label>
            <select name="partner_code" id="agingPartnerSelect" class="form-select select2-basic" data-placeholder="Pilih / cari <?= esc($partnerLabel, 'attr') ?>">
                <option value="">All <?= esc($partnerLabel) ?></option>
                <?php foreach ($partnerOptions as $code => $name): ?>
                    <?php $label = trim($code . ' - ' . $name, ' -'); ?>
                    <option value="<?= esc((string) $code, 'attr') ?>" <?= $currentPartner === (string) $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-xl-3 col-md-4"><label class="form-label"><?= esc($partnerLabel) ?> Group</label><input type="text" name="partner_group" value="<?= esc($filters['partner_group'] ?? '') ?>" class="form-control" placeholder="Group / reference"></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Aging</label><select name="aging_bucket" class="form-select select2-basic"><?php foreach ($bucketOptions as $value => $label): ?><option value="<?= esc((string) $value, 'attr') ?>" <?= (string) ($filters['aging_bucket'] ?? '') === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></div>
        <div class="col-xl-2 col-md-4"><button type="submit" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i> Search</button></div>
    </form>

    <div class="row"><?php foreach ($bucketLabels as $field => $label): ?><div class="col-xl col-md-4 col-sm-6"><div class="card border shadow-none mb-3"><div class="card-body py-3"><p class="text-muted mb-1"><?= esc($label) ?></p><h5 class="mb-0">Rp <?= esc(number_format((float) ($totals[$field] ?? 0), 0, ',', '.')) ?></h5></div></div></div><?php endforeach ?></div>
</div></div>

<div class="card"><div class="card-body"><h5 class="card-title mb-3"><?= esc($partnerLabel) ?> Summary</h5><div class="table-responsive"><table class="table table-sm table-nowrap table-hover align-middle mb-0">
    <thead class="table-light"><tr><th><?= esc($partnerLabel) ?></th><th>Group</th><?php foreach ($bucketLabels as $label): ?><th class="text-end"><?= esc($label) ?></th><?php endforeach ?><th class="text-end">Total</th></tr></thead>
    <tbody>
    <?php foreach ($summary as $row): ?><tr>
        <td><div class="fw-semibold"><?= esc($row['partner_name'] ?? '-') ?></div><small class="text-muted"><?= esc($row['partner_code'] ?? '-') ?></small></td>
        <td><?= esc(($row['partner_group'] ?? '') !== '' ? $row['partner_group'] : '-') ?></td>
        <?php foreach (array_keys($bucketLabels) as $field): ?><td class="text-end"><?= esc(number_format((float) ($row[$field] ?? 0), 0, ',', '.')) ?></td><?php endforeach ?>
        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['total'] ?? 0), 0, ',', '.')) ?></td>
    </tr><?php endforeach ?>
    <?php if ($summary === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No outstanding invoice found.</td></tr><?php endif ?>
    </tbody>
    <?php if ($summary !== []): ?><tfoot class="table-light"><tr><th>Total</th><th></th><?php foreach (array_keys($bucketLabels) as $field): ?><th class="text-end"><?= esc(number_format((float) ($totals[$field] ?? 0), 0, ',', '.')) ?></th><?php endforeach ?><th class="text-end"><?= esc(number_format((float) ($totals['total'] ?? 0), 0, ',', '.')) ?></th></tr></tfoot><?php endif ?>
</table></div></div></div>

<div class="card"><div class="card-body"><h5 class="card-title mb-3">Outstanding Invoice Detail</h5><div class="table-responsive"><table class="table table-sm table-nowrap table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Invoice No</th><th>Date</th><th>Due Date</th><th><?= esc($partnerLabel) ?></th><th>Group</th><th>Bucket</th><th class="text-end">Age Days</th><th class="text-end">Outstanding</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?><tr>
        <td><a href="<?= esc($row['document_url'] ?? '#') ?>" class="fw-semibold"><?= esc($row['invoice_no'] ?? '-') ?></a></td>
        <td><?= esc($row['invoice_date'] ?? '-') ?></td><td><?= esc($row['due_date'] ?? '-') ?></td>
        <td><div><?= esc($row[$nameField] ?? '-') ?></div><small class="text-muted"><?= esc($row[$codeField] ?? '-') ?></small></td>
        <td><?= esc(($row['partner_group'] ?? '') !== '' ? $row['partner_group'] : '-') ?></td><td><span class="badge bg-secondary"><?= esc($row['bucket'] ?? '-') ?></span></td>
        <td class="text-end"><?= esc(number_format((float) ($row['age_days'] ?? 0), 0, ',', '.')) ?></td><td class="text-end fw-semibold"><?= esc(number_format((float) ($row['outstanding_amount'] ?? 0), 0, ',', '.')) ?></td>
    </tr><?php endforeach ?>
    <?php if ($rows === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No outstanding invoice found.</td></tr><?php endif ?>
    </tbody>
</table></div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('agingPartnerSelect');
    if (!select || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
    var $select = window.jQuery(select);
    if ($select.data('select2')) $select.select2('destroy');
    $select.select2({width: '100%', allowClear: true, placeholder: select.dataset.placeholder || 'Pilih / cari data'});
});
</script>
<?= $this->endSection() ?>
