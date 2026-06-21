<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div><h4 class="card-title mb-1">Work Order</h4><p class="text-muted mb-0"><?= esc($workOrder['wo_no']) ?></p></div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (($workOrder['status'] ?? 'draft') === 'draft'): ?>
                <a href="<?= site_url('production/work-orders/' . $workOrder['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a>
            <?php endif ?>
            <?php if (in_array($workOrder['status'] ?? 'draft', ['draft', 'partial_allocated'], true)): ?>
                <form method="post" action="<?= site_url('production/work-orders/' . $workOrder['id'] . '/allocate') ?>"><?= csrf_field() ?><button class="btn btn-primary" onclick="return confirm('Allocate material for this work order?')"><i class="bx bx-lock-alt me-1"></i> Allocate Material</button></form>
            <?php endif ?>
            <?php if (in_array($workOrder['status'] ?? 'draft', ['allocated', 'partial_issued'], true)): ?>
                <form method="post" action="<?= site_url('production/work-orders/' . $workOrder['id'] . '/issue-materials') ?>"><?= csrf_field() ?><button class="btn btn-warning" onclick="return confirm('Issue allocated material to production?')"><i class="bx bx-log-out-circle me-1"></i> Issue Material Out</button></form>
            <?php endif ?>
            <?php if (($workOrder['status'] ?? 'draft') === 'allocated'): ?>
                <?php $remainingFinishedQty = max(0, (float) ($workOrder['std_qty_finished'] ?? 0) - (float) ($workOrder['act_qty_finished'] ?? 0)); ?>
                <form class="d-flex gap-2" method="post" action="<?= site_url('production/work-orders/' . $workOrder['id'] . '/issue-receive') ?>"><?= csrf_field() ?><input class="form-control form-control-sm" style="width:130px" type="number" name="receive_qty" min="0.000001" step="0.000001" value="<?= esc(number_format($remainingFinishedQty, 6, '.', '')) ?>"><button class="btn btn-info" onclick="return confirm('Issue material and receive finished good together?')"><i class="bx bx-transfer me-1"></i> Issue + Receive</button></form>
            <?php endif ?>
            <?php if (in_array($workOrder['status'] ?? 'draft', ['material_issued', 'partial_finished'], true)): ?>
                <?php $remainingFinishedQty = max(0, (float) ($workOrder['std_qty_finished'] ?? 0) - (float) ($workOrder['act_qty_finished'] ?? 0)); ?>
                <form class="d-flex gap-2" method="post" action="<?= site_url('production/work-orders/' . $workOrder['id'] . '/receive-finished') ?>"><?= csrf_field() ?><input class="form-control form-control-sm" style="width:130px" type="number" name="receive_qty" min="0.000001" step="0.000001" value="<?= esc(number_format($remainingFinishedQty, 6, '.', '')) ?>"><button class="btn btn-success" onclick="return confirm('Receive finished good to inventory?')"><i class="bx bx-package me-1"></i> Receive Finished Good</button></form>
            <?php endif ?>
            <?php $statusColor = in_array($workOrder['status'] ?? '', ['allocated', 'material_issued', 'finished'], true) ? 'success' : (in_array($workOrder['status'] ?? '', ['partial_issued', 'partial_finished'], true) ? 'warning' : 'secondary'); ?>
            <span class="badge bg-<?= $statusColor ?>"><?= esc($workOrder['status']) ?></span>
        </div>
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

<div class="card"><div class="card-body"><h4 class="card-title mb-3">BOM</h4><div class="table-responsive"><table class="table table-nowrap align-middle mb-0"><thead class="table-light"><tr><th>No.</th><th>Component</th><th>Name</th><th class="text-end">Qty Used</th><th>UoM</th><th>Whs</th><th>Loc</th><th>Batch No.</th><th class="text-end">Booking Qty</th><th class="text-end">Allocated</th><th class="text-end">Issued</th><th>Status</th></tr></thead><tbody><?php foreach ($components as $line): ?><tr><td><?= esc($line['line_no']) ?></td><td class="fw-semibold"><?= esc($line['component_item_code']) ?></td><td><?= esc($line['component_item_name'] ?? '-') ?></td><td class="text-end"><?= esc(number_format((float) $line['qty_used'], 6)) ?></td><td><?= esc($line['uom_code']) ?></td><td><?= esc($line['warehouse_code'] ?? '-') ?></td><td><?= esc($line['location_code'] ?? '-') ?></td><td><?= esc($line['batch_no'] ?? '-') ?></td><td class="text-end"><?= esc(number_format((float) $line['booking_qty'], 6)) ?></td><td class="text-end"><?= esc(number_format((float) ($line['allocated_qty'] ?? 0), 6)) ?></td><td class="text-end"><?= esc(number_format((float) ($line['issued_qty'] ?? 0), 6)) ?></td><td><span class="badge bg-secondary"><?= esc($line['line_status'] ?? 'open') ?></span></td></tr><?php endforeach ?></tbody></table></div></div></div>
<div class="card"><div class="card-body"><h4 class="card-title mb-3">Routing</h4><div class="table-responsive"><table class="table table-nowrap align-middle mb-0"><thead class="table-light"><tr><th>No.</th><th>Routing Name</th><th>Work Center Name</th><th class="text-end">Hour</th><th>UoM</th></tr></thead><tbody><?php foreach ($routings as $line): ?><tr><td><?= esc($line['line_no']) ?></td><td><?= esc($line['routing_name'] ?? '-') ?></td><td><?= esc(($line['work_center_code'] ?? '-') . ' - ' . ($line['work_center_name'] ?? '')) ?></td><td class="text-end"><?= esc(number_format((float) $line['hour_qty'], 4)) ?></td><td><?= esc($line['uom_code']) ?></td></tr><?php endforeach ?></tbody></table></div></div></div>
<?= $this->endSection() ?>
