<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4"><div class="card"><div class="card-body">
        <h4 class="card-title mb-1">Routing Detail</h4><p class="text-muted"><?= esc($routing['item_code']) ?></p>
        <table class="table table-sm mb-0"><tbody>
            <tr><th>Item</th><td><?= esc($routing['item_code']) ?></td></tr>
            <tr><th>Site</th><td><?= esc($routing['site_code']) ?></td></tr>
            <tr><th>Department</th><td><?= esc($routing['department_code']) ?></td></tr>
            <tr><th>Warehouse</th><td><?= esc($routing['warehouse_code'] ?? '-') ?></td></tr>
        </tbody></table>
        <div class="d-flex gap-2 mt-3"><a href="<?= site_url('production/routings') ?>" class="btn btn-light">Back</a><a href="<?= site_url('production/routings/' . $routing['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a></div>
    </div></div></div>
    <div class="col-xl-8"><div class="card"><div class="card-body">
        <h4 class="card-title mb-3">Operations</h4>
        <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
            <thead class="table-light"><tr><th>Route No</th><th>Name</th><th>Work Center</th><th>Type</th><th class="text-end">Hour</th><th>Speed</th></tr></thead>
            <tbody><?php foreach ($lines as $line): ?><tr>
                <td><?= esc($line['route_no']) ?></td><td><?= esc($line['routing_name'] ?? '-') ?></td><td><?= esc($line['work_center_code']) ?></td><td><?= esc($line['operation_type']) ?></td>
                <td class="text-end"><?= esc(number_format((float) $line['hour_qty'], 4)) ?> <?= esc($line['hour_uom']) ?></td>
                <td><?= esc(number_format((float) $line['std_speed'], 4) . ' ' . $line['speed_uom']) ?></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
    </div></div></div>
</div>
<?= $this->endSection() ?>
