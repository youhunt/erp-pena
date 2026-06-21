<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="card-title mb-1">Work Center</h4><p class="text-muted mb-0">Machine, capacity, and cost settings for production operations.</p></div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('production/imports/work-centers') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
            <a href="<?= site_url('production/work-centers/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Work Center</a>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-nowrap table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Work Center</th><th>Site</th><th>Department</th><th>Warehouse</th><th>Machine</th><th class="text-end">Capacity %</th><th class="text-end">Cost</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><div class="fw-semibold"><a href="<?= site_url('production/work-centers/' . $row['id']) ?>"><?= esc($row['work_center_code']) ?></a></div><small class="text-muted"><?= esc($row['description'] ?? '-') ?></small></td>
            <td><?= esc($row['site_code']) ?></td><td><?= esc($row['department_code']) ?></td><td><?= esc($row['warehouse_code']) ?></td>
            <td><?= esc($row['machine_code']) ?></td>
            <td class="text-end"><?= esc(number_format((float) $row['capacity_percent'], 3)) ?></td>
            <td class="text-end"><?= esc(number_format((float) $row['cost_amount'], 2)) ?> <?= esc($row['cost_uom'] ?? '') ?></td>
        </tr><?php endforeach ?>
        <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No work center yet.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</div></div>
<?= $this->endSection() ?>
