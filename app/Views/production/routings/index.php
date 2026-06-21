<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="card-title mb-1">Routing</h4><p class="text-muted mb-0">Production operation sequence per item.</p></div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('production/imports/routings') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
            <a href="<?= site_url('production/routings/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Routing</a>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-nowrap table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Item</th><th>Site</th><th>Department</th><th>Warehouse</th><th>Description</th><th class="text-end">Action</th></tr></thead>
        <tbody><?php foreach ($rows as $row): ?><tr>
            <td class="fw-semibold"><?= esc($row['item_code']) ?></td><td><?= esc($row['site_code']) ?></td><td><?= esc($row['department_code']) ?></td><td><?= esc($row['warehouse_code'] ?? '-') ?></td><td><?= esc($row['description'] ?? '-') ?></td>
            <td class="text-end"><a href="<?= site_url('production/routings/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
        </tr><?php endforeach ?><?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No routing yet.</td></tr><?php endif ?></tbody>
    </table></div>
</div></div>
<?= $this->endSection() ?>
