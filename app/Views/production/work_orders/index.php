<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="card-title mb-1">Work Order</h4><p class="text-muted mb-0">Production orders generated from BOM and Routing.</p></div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('production/imports/work-orders') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
            <a href="<?= site_url('production/work-orders/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Work Order</a>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-nowrap table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>WO No</th><th>Date</th><th>Parent Item</th><th>Site</th><th>Dept</th><th>Work Center</th><th class="text-end">WO Qty</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody><?php foreach ($rows as $row): ?><tr>
            <td class="fw-semibold"><?= esc($row['wo_no']) ?></td><td><?= esc($row['wo_date']) ?></td>
            <td><div class="fw-semibold"><?= esc($row['parent_item_code']) ?></div><small class="text-muted"><?= esc($row['parent_item_name'] ?? '-') ?></small></td>
            <td><?= esc($row['site_code']) ?></td><td><?= esc($row['department_code']) ?></td><td><?= esc($row['work_center_code'] ?? '-') ?></td>
            <td class="text-end"><?= esc(number_format((float) $row['wo_qty'], 4)) ?></td><td><span class="badge bg-secondary"><?= esc($row['status']) ?></span></td>
            <td class="text-end"><a href="<?= site_url('production/work-orders/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
        </tr><?php endforeach ?><?php if ($rows === []): ?><tr><td colspan="9" class="text-center text-muted py-4">No work order yet.</td></tr><?php endif ?></tbody>
    </table></div>
</div></div>
<?= $this->endSection() ?>
