<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div><h4 class="card-title mb-1">Work Order</h4><p class="text-muted mb-0"><?= esc($workOrder['wo_no']) ?></p></div>
        <span class="badge bg-secondary"><?= esc($workOrder['status']) ?></span>
    </div>
    <div class="row">
        <div class="col-md-3 mb-3"><label class="text-muted">WO Code</label><div class="fw-semibold"><?= esc($workOrder['wo_code']) ?></div></div>
        <div class="col-md-3 mb-3"><label class="text-muted">WO No.</label><div class="fw-semibold"><?= esc($workOrder['wo_no']) ?></div></div>
        <div class="col-md-3 mb-3"><label class="text-muted">WO Date</label><div class="fw-semibold"><?= esc($workOrder['wo_date']) ?></div></div>
        <div class="col-md-3 mb-3"><label class="text-muted">Site / Dept</label><div class="fw-semibold"><?= esc($workOrder['site_code'] . ' / ' . $workOrder['department_code']) ?></div></div>
        <div class="col-md-4 mb-3"><label class="text-muted">Item Parent</label><div class="fw-semibold"><?= esc($workOrder['parent_item_code']) ?></div><small><?= esc($workOrder['parent_item_name'] ?? '-') ?></small></div>
        <div class="col-md-2 mb-3"><label class="text-muted">Work Center</label><div class="fw-semibold"><?= esc($workOrder['work_center_code'] ?? '-') ?></div></div>
        <div class="col-md-2 mb-3"><label class="text-muted">Batch Qty</label><div class="fw-semibold"><?= esc(number_format((float) $workOrder['batch_qty'], 4)) ?></div></div>
        <div class="col-md-2 mb-3"><label class="text-muted">Qty WO</label><div class="fw-semibold"><?= esc(number_format((float) $workOrder['wo_qty'], 4)) ?></div></div>
        <div class="col-md-2 mb-3"><label class="text-muted">Finished Qty</label><div class="fw-semibold"><?= esc(number_format((float) $workOrder['act_qty_finished'], 4)) ?> / <?= esc(number_format((float) $workOrder['std_qty_finished'], 4)) ?></div></div>
        <div class="col-md-12"><label class="text-muted">Description</label><div><?= esc($workOrder['description'] ?? '-') ?></div></div>
    </div>
</div></div>

<div class="card"><div class="card-body">
    <h4 class="card-title mb-3">BOM</h4>
    <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
        <thead class="table-light"><tr><th>No.</th><th>Component</th><th>Name</th><th class="text-end">Qty Used</th><th>UoM</th><th>Whs</th><th>Loc</th><th>Batch No.</th><th class="text-end">Booking Qty</th></tr></thead>
        <tbody><?php foreach ($components as $line): ?><tr>
            <td><?= esc($line['line_no']) ?></td><td class="fw-semibold"><?= esc($line['component_item_code']) ?></td><td><?= esc($line['component_item_name'] ?? '-') ?></td>
            <td class="text-end"><?= esc(number_format((float) $line['qty_used'], 6)) ?></td><td><?= esc($line['uom_code']) ?></td><td><?= esc($line['warehouse_code'] ?? '-') ?></td><td><?= esc($line['location_code'] ?? '-') ?></td><td><?= esc($line['batch_no'] ?? '-') ?></td><td class="text-end"><?= esc(number_format((float) $line['booking_qty'], 6)) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div>
</div></div>

<div class="card"><div class="card-body">
    <h4 class="card-title mb-3">Routing</h4>
    <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
        <thead class="table-light"><tr><th>No.</th><th>Routing Name</th><th>Work Center Name</th><th class="text-end">Hour</th><th>UoM</th></tr></thead>
        <tbody><?php foreach ($routings as $line): ?><tr>
            <td><?= esc($line['line_no']) ?></td><td><?= esc($line['routing_name'] ?? '-') ?></td><td><?= esc(($line['work_center_code'] ?? '-') . ' - ' . ($line['work_center_name'] ?? '')) ?></td><td class="text-end"><?= esc(number_format((float) $line['hour_qty'], 4)) ?></td><td><?= esc($line['uom_code']) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div>
</div></div>
<?= $this->endSection() ?>
