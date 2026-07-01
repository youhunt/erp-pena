<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
use App\Models\ProductionBomLineModel;
use App\Models\ProductionBomModel;
use App\Models\ProductionRoutingLineModel;
use App\Models\ProductionRoutingModel;
use App\Models\ProductionWorkCenterModel;
use App\Services\TenantContext;

$rows = $preview['rows'] ?? [];
$headers = $config['headers'] ?? [];
$hasErrors = (bool) ($preview['has_errors'] ?? true);
$isWorkOrderImport = ($resource ?? '') === 'work-orders';

if ($isWorkOrderImport) {
    $headers = [
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

$tenant = new TenantContext(session());
$companyId = $tenant->activeCompanyId();
$workOrderGeneratedPreviews = [];

if ($isWorkOrderImport && $companyId !== null) {
    $bomModel = new ProductionBomModel();
    $bomLineModel = new ProductionBomLineModel();
    $routingModel = new ProductionRoutingModel();
    $routingLineModel = new ProductionRoutingLineModel();
    $workCenterModel = new ProductionWorkCenterModel();

    foreach ($rows as $previewRow) {
        $data = $previewRow['data'] ?? [];
        $siteCode = trim((string) ($data['site_code'] ?? ''));
        $parentItemCode = trim((string) ($data['parent_item_code'] ?? ''));
        $woNo = trim((string) ($data['wo_no'] ?? ''));
        $woQty = (float) ($data['wo_qty'] ?? 0);
        $batchQty = (float) ($data['batch_qty'] ?? 1);
        $batchQty = $batchQty > 0 ? $batchQty : 1.0;
        $scale = $woQty > 0 ? ($woQty / $batchQty) : 0;

        $detail = [
            'wo_no' => $woNo,
            'parent_item_code' => $parentItemCode,
            'components' => [],
            'routings' => [],
            'bom_found' => false,
            'routing_found' => false,
        ];

        if ($siteCode !== '' && $parentItemCode !== '') {
            $bom = $bomModel
                ->where('company_id', (int) $companyId)
                ->where('site_code', $siteCode)
                ->where('parent_item_code', $parentItemCode)
                ->first();

            if ($bom) {
                $detail['bom_found'] = true;
                foreach ($bomLineModel->where('production_bom_id', (int) $bom['id'])->orderBy('child_no', 'ASC')->findAll(1000) as $line) {
                    $qtyUsed = (float) ($line['qty_used'] ?? 0);
                    $detail['components'][] = [
                        'line_no' => $line['child_no'] ?? '',
                        'component_item_code' => $line['child_item_code'] ?? '',
                        'component_item_name' => $line['child_item_name'] ?? '',
                        'qty_used' => $qtyUsed,
                        'booking_qty' => $scale > 0 ? round($qtyUsed * $scale, 6) : $qtyUsed,
                        'uom_code' => $line['uom_code'] ?? '',
                    ];
                }
            }

            $routing = $routingModel
                ->where('company_id', (int) $companyId)
                ->where('site_code', $siteCode)
                ->where('item_code', $parentItemCode)
                ->first();

            if ($routing) {
                $detail['routing_found'] = true;
                foreach ($routingLineModel->where('production_routing_id', (int) $routing['id'])->orderBy('route_no', 'ASC')->findAll(1000) as $line) {
                    $workCenterCode = (string) ($line['work_center_code'] ?? '');
                    $workCenter = $workCenterCode !== ''
                        ? $workCenterModel->where('company_id', (int) $companyId)->where('work_center_code', $workCenterCode)->first()
                        : null;
                    $hourQty = (float) ($line['hour_qty'] ?? 0);
                    $detail['routings'][] = [
                        'line_no' => $line['route_no'] ?? '',
                        'routing_name' => $line['routing_name'] ?? '',
                        'work_center_code' => $workCenterCode,
                        'work_center_name' => $workCenter['description'] ?? $workCenterCode,
                        'hour_qty' => $scale > 0 ? round($hourQty * $scale, 6) : $hourQty,
                        'uom_code' => $line['hour_uom'] ?? '',
                    ];
                }
            }
        }

        $workOrderGeneratedPreviews[] = $detail;
    }
}
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title ?? 'Preview Import') ?></h4>
                <p class="text-muted mb-0">Review the validation result. Data will not be saved until <strong>Commit Import</strong> is clicked.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/imports/' . esc($resource, 'url')) ?>" class="btn btn-light">Upload Again</a>
                <a href="<?= site_url($config['return_to'] ?? 'production') ?>" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3 mb-2"><div class="border rounded p-3"><div class="text-muted small">Total Rows</div><div class="h4 mb-0"><?= esc($preview['total'] ?? 0) ?></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-3"><div class="text-muted small">Valid</div><div class="h4 mb-0 text-success"><?= esc($preview['valid'] ?? 0) ?></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-3"><div class="text-muted small">Error</div><div class="h4 mb-0 text-danger"><?= esc($preview['error'] ?? 0) ?></div></div></div>
            <div class="col-md-3 mb-2 d-flex align-items-center">
                <?php if (! $hasErrors && ! empty($token)): ?>
                    <form method="post" action="<?= site_url('production/imports/' . esc($resource, 'url')) ?>" class="w-100">
                        <?= csrf_field() ?>
                        <input type="hidden" name="commit_token" value="<?= esc($token, 'attr') ?>">
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Commit <?= esc($config['title'] ?? 'Production') ?> import now?')"><i class="bx bx-check me-1"></i> Commit Import</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Commit Disabled</button>
                <?php endif ?>
            </div>
        </div>

        <?php if ($hasErrors): ?>
            <div class="alert alert-danger">Errors still exist. Fix the file and upload it again. Data cannot be committed until all rows are valid.</div>
        <?php else: ?>
            <div class="alert alert-success">All rows are valid. Click <strong>Commit Import</strong> to save the data.</div>
        <?php endif ?>

        <?php if ($isWorkOrderImport): ?>
            <div class="alert alert-info">The main Work Order preview only shows the <strong>WO header</strong>. BOM and Routing lines that will be generated automatically from master data are shown below for review.</div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Excel Row</th>
                        <th>Status</th>
                        <th>Message</th>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="<?= ($row['status'] ?? '') === 'error' ? 'table-danger' : '' ?>">
                            <td><?= esc($row['row_number'] ?? '-') ?></td>
                            <td>
                                <?php if (($row['status'] ?? '') === 'valid'): ?>
                                    <span class="badge bg-success">VALID</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ERROR</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if (! empty($row['errors'])): ?>
                                    <div class="text-danger"><?= esc(implode('; ', $row['errors'])) ?></div>
                                <?php endif ?>
                                <?php if (! empty($row['warnings'])): ?>
                                    <div class="text-warning"><?= esc(implode('; ', $row['warnings'])) ?></div>
                                <?php endif ?>
                                <?php if (empty($row['errors']) && empty($row['warnings'])): ?>
                                    <span class="text-muted">-</span>
                                <?php endif ?>
                            </td>
                            <?php foreach ($headers as $header): ?>
                                <td><?= esc($row['data'][$header] ?? '') ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="<?= count($headers) + 3 ?>" class="text-center text-muted py-4">No preview rows.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isWorkOrderImport): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Automatic BOM & Routing Preview</h5>
            <?php foreach ($workOrderGeneratedPreviews as $detail): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-semibold"><?= esc($detail['wo_no'] ?: '-') ?></div>
                            <small class="text-muted">Parent Item: <?= esc($detail['parent_item_code'] ?: '-') ?></small>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge <?= $detail['bom_found'] ? 'bg-success' : 'bg-warning text-dark' ?>">BOM <?= $detail['bom_found'] ? 'Found' : 'Not Found' ?></span>
                            <span class="badge <?= $detail['routing_found'] ? 'bg-success' : 'bg-warning text-dark' ?>">Routing <?= $detail['routing_found'] ? 'Found' : 'Not Found' ?></span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-7 mb-3">
                            <div class="fw-semibold mb-2">BOM Components</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light"><tr><th>No</th><th>Component</th><th>Name</th><th class="text-end">Qty Used</th><th class="text-end">Booking Qty</th><th>UoM</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($detail['components'] as $component): ?>
                                        <tr>
                                            <td><?= esc($component['line_no']) ?></td>
                                            <td><?= esc($component['component_item_code']) ?></td>
                                            <td><?= esc($component['component_item_name']) ?></td>
                                            <td class="text-end"><?= esc(number_format((float) $component['qty_used'], 6)) ?></td>
                                            <td class="text-end fw-semibold"><?= esc(number_format((float) $component['booking_qty'], 6)) ?></td>
                                            <td><?= esc($component['uom_code']) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if ($detail['components'] === []): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No BOM component preview.</td></tr>
                                    <?php endif ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-xl-5 mb-3">
                            <div class="fw-semibold mb-2">Routing</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light"><tr><th>No</th><th>Routing</th><th>Work Center</th><th class="text-end">Hour</th><th>UoM</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($detail['routings'] as $routing): ?>
                                        <tr>
                                            <td><?= esc($routing['line_no']) ?></td>
                                            <td><?= esc($routing['routing_name']) ?></td>
                                            <td><div><?= esc($routing['work_center_code']) ?></div><small class="text-muted"><?= esc($routing['work_center_name']) ?></small></td>
                                            <td class="text-end"><?= esc(number_format((float) $routing['hour_qty'], 6)) ?></td>
                                            <td><?= esc($routing['uom_code']) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if ($detail['routings'] === []): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No routing preview.</td></tr>
                                    <?php endif ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
