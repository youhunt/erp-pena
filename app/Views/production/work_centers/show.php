<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div><h4 class="card-title mb-1">Work Center</h4><p class="text-muted mb-0"><?= esc($workCenter['work_center_code']) ?></p></div>
                    <span class="badge bg-<?= (int) ($workCenter['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($workCenter['is_active'] ?? 1) === 1 ? 'active' : 'inactive' ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th>Site</th><td><?= esc($workCenter['site_code']) ?></td></tr>
                    <tr><th>Department</th><td><?= esc($workCenter['department_code']) ?></td></tr>
                    <tr><th>Warehouse</th><td><?= esc($workCenter['warehouse_code']) ?></td></tr>
                    <tr><th>Description</th><td><?= esc($workCenter['description'] ?? '-') ?></td></tr>
                    <tr><th>Active Date</th><td><?= esc($workCenter['active_date'] ?? '-') ?></td></tr>
                </table>
                <div class="d-flex gap-2 mt-3"><a href="<?= site_url('production/work-centers') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a><a href="<?= site_url('production/work-centers/' . $workCenter['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a></div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card"><div class="card-body"><h4 class="card-title mb-3">Machine Detail</h4><div class="table-responsive"><table class="table table-nowrap align-middle mb-0"><thead class="table-light"><tr><th>No</th><th>Machine</th><th>Notes</th><th class="text-end">Speed</th><th class="text-end">Capacity</th><th class="text-end">Labor</th><th class="text-end">Hour</th></tr></thead><tbody><?php foreach ($machines as $machine): ?><tr><td><?= esc($machine['no']) ?></td><td class="fw-semibold"><?= esc($machine['machine']) ?></td><td><?= esc($machine['notes1'] ?? '-') ?></td><td class="text-end"><?= esc(number_format((float) $machine['speed'], 3)) ?></td><td class="text-end"><?= esc(number_format((float) $machine['capacity'], 3)) ?></td><td class="text-end"><?= esc(number_format((float) $machine['qtylabor'], 4)) ?></td><td class="text-end"><?= esc(number_format((float) $machine['workhour'], 3)) ?></td></tr><?php endforeach ?><?php if ($machines === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No machine detail.</td></tr><?php endif ?></tbody></table></div></div></div>
        <div class="card"><div class="card-body"><h4 class="card-title mb-3">Cost Detail</h4><div class="table-responsive"><table class="table table-nowrap align-middle mb-0"><thead class="table-light"><tr><th>Cost Type</th><th class="text-end">Amount</th><th>UoM</th><th>Notes</th></tr></thead><tbody><?php foreach ($costs as $cost): ?><tr><td class="fw-semibold"><?= esc($cost['costtype']) ?></td><td class="text-end"><?= esc(number_format((float) $cost['costamount'], 2)) ?></td><td><?= esc($cost['costuom'] ?? '-') ?></td><td><?= esc($cost['notes2'] ?? '-') ?></td></tr><?php endforeach ?><?php if ($costs === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No cost detail.</td></tr><?php endif ?></tbody></table></div></div></div>
    </div>
</div>
<?= $this->endSection() ?>
