<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$displayDate = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '-';
    }
    if ($value === '9999-12-31' || $value === '9999-12-31 00:00:00') {
        return '99/99/9999';
    }
    $date = substr($value, 0, 10);
    $parts = explode('-', $date);
    if (count($parts) === 3) {
        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }
    return $value;
};
?>
<div class="row">
    <div class="col-xl-4"><div class="card"><div class="card-body">
        <h4 class="card-title mb-1">Routing Detail</h4><p class="text-muted"><?= esc($routing['item_code']) ?></p>
        <table class="table table-sm mb-0"><tbody>
            <tr><th>Item Code</th><td><?= esc($routing['item_code']) ?></td></tr>
            <tr><th>Site</th><td><?= esc($routing['site_code']) ?></td></tr>
            <tr><th>Department</th><td><?= esc($routing['department_code']) ?></td></tr>
            <tr><th>Warehouse</th><td><?= esc($routing['warehouse_code'] ?? '-') ?></td></tr>
            <tr><th>Description</th><td><?= esc($routing['description'] ?? '-') ?></td></tr>
        </tbody></table>
        <div class="d-flex gap-2 mt-3"><a href="<?= site_url('production/routings') ?>" class="btn btn-light">Back</a><a href="<?= site_url('production/routings/' . $routing['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a></div>
    </div></div></div>
    <div class="col-xl-8"><div class="card"><div class="card-body">
        <h4 class="card-title mb-3">Operations</h4>
        <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
            <thead class="table-light"><tr><th>Route No.</th><th>Routing Name</th><th>Work Center</th><th>Type</th><th class="text-end">Hour</th><th>Hour UoM</th><th class="text-end">Std Speed</th><th>Speed UoM</th><th>Notes</th><th>Active Date</th><th>Inactive Date</th></tr></thead>
            <tbody><?php foreach ($lines as $line): ?><tr>
                <td><?= esc($line['route_no']) ?></td>
                <td><?= esc($line['routing_name'] ?? '-') ?></td>
                <td><?= esc($line['work_center_code']) ?></td>
                <td><span class="badge bg-<?= strcasecmp((string) ($line['operation_type'] ?? ''), 'Main') === 0 ? 'primary' : 'secondary' ?>"><?= esc($line['operation_type'] ?? '-') ?></span></td>
                <td class="text-end"><?= esc(number_format((float) $line['hour_qty'], 4)) ?></td>
                <td><?= esc($line['hour_uom']) ?></td>
                <td class="text-end"><?= esc(number_format((float) $line['std_speed'], 4)) ?></td>
                <td><?= esc($line['speed_uom']) ?></td>
                <td><?= esc($line['notes'] ?? '-') ?></td>
                <td><?= esc($displayDate($line['active_date'] ?? null)) ?></td>
                <td><?= esc($displayDate($line['inactive_date'] ?? null)) ?></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
    </div></div></div>
</div>
<?= $this->endSection() ?>
