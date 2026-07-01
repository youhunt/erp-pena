<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$displayHeaders = $config['headers'] ?? [];
$lineInfo = match ($resource ?? '') {
    'work-orders' => 'Work Order import only requires the WO header. BOM and Routing will be generated automatically from the master data using parent_item_code.',
    'work-centers' => 'Work Center import does not use line_no.',
    default => 'BOM and Routing import require line_no for detail lines.',
};

if (($resource ?? '') === 'work-orders') {
    $displayHeaders = [
        'wo_code',
        'wo_no',
        'wo_date',
        'site_code',
        'department_code',
        'warehouse_code',
        'work_center_code',
        'parent_item_code',
        'parent_item_name',
        'batch_qty',
        'wo_qty',
        'uom_code',
        'std_qty_finished',
        'act_qty_finished',
        'description',
    ];
}
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title ?? 'Production Import') ?></h4>
                <p class="text-muted mb-0">Upload a file to preview and validate it first. Data will not be saved until <strong>Commit Import</strong> is clicked.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/imports/' . esc($resource, 'url') . '/template') ?>" class="btn btn-outline-primary"><i class="bx bx-download me-1"></i> Download Template</a>
                <a href="<?= site_url($config['return_to'] ?? 'production') ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <form method="post" action="<?= site_url('production/imports/' . esc($resource, 'url')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Excel / CSV File</label>
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                    <small class="text-muted">Maximum 10 MB. The recommended format is the downloaded .xlsx template.</small>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bx bx-search-alt me-1"></i> Preview & Validate</button>
                </div>
            </div>
        </form>

        <div class="alert alert-info mb-0">
            <div class="fw-semibold mb-2">Template columns:</div>
            <div class="small text-break"><?= esc(implode(', ', $displayHeaders)) ?></div>
            <hr>
            <div class="small mb-0">
                All production imports must include <strong>site_code</strong>. <?= esc($lineInfo) ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
