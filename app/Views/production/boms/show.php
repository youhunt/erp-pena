<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$routingLabel = '-';
if (! empty($bom['routing_id'])) {
    try {
        $db = db_connect();
        if ($db->tableExists('production_routings')) {
            $routingRow = $db->table('production_routings')
                ->select('id, item_code, site_code, department_code, warehouse_code, description')
                ->where('id', (int) $bom['routing_id'])
                ->get(1)
                ->getRowArray();
            if ($routingRow !== null) {
                $routingLabel = trim(($routingRow['item_code'] ?? '') . ' / ' . ($routingRow['site_code'] ?? '') . ' / ' . ($routingRow['department_code'] ?? '') . ' / ' . ($routingRow['warehouse_code'] ?? ''), ' /');
                if (($routingRow['description'] ?? '') !== '') {
                    $routingLabel .= ' - ' . $routingRow['description'];
                }
            }
        }
    } catch (Throwable) {
        $routingLabel = (string) $bom['routing_id'];
    }
}
?>
<div class="row">
    <div class="col-xl-4"><div class="card"><div class="card-body">
        <h4 class="card-title mb-1">BOM Detail</h4><p class="text-muted"><?= esc($bom['parent_item_code']) ?></p>
        <table class="table table-sm mb-0"><tbody>
            <tr><th>Parent</th><td><?= esc(($bom['parent_item_code'] ?? '-') . ' ' . ($bom['parent_item_name'] ?? '')) ?></td></tr>
            <tr><th>Site</th><td><?= esc($bom['site_code']) ?></td></tr>
            <tr><th>Department</th><td><?= esc($bom['department_code']) ?></td></tr>
            <tr><th>Warehouse</th><td><?= esc($bom['warehouse_code'] ?? '-') ?></td></tr>
            <tr><th>Routing Link</th><td><?= ! empty($bom['routing_id']) ? '<a href="' . site_url('production/routings/' . (int) $bom['routing_id']) . '">' . esc($routingLabel) . '</a>' : '-' ?></td></tr>
            <tr><th>Qty/Batch</th><td><?= esc(number_format((float) $bom['qty_batch'], 4)) ?> <?= esc($bom['uom_code']) ?></td></tr>
        </tbody></table>
        <div class="d-flex gap-2 mt-3"><a href="<?= site_url('production/boms') ?>" class="btn btn-light">Back</a><a href="<?= site_url('production/boms/' . $bom['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a></div>
    </div></div></div>
    <div class="col-xl-8"><div class="card"><div class="card-body">
        <h4 class="card-title mb-3">Components</h4>
        <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
            <thead class="table-light"><tr><th>No</th><th>Child Item</th><th>Type</th><th class="text-end">Qty Used</th><th>UoM</th><th class="text-end">Factor</th></tr></thead>
            <tbody><?php foreach ($lines as $line): ?><tr>
                <td><?= esc($line['child_no']) ?></td>
                <td><div class="fw-semibold"><?= esc($line['child_item_code']) ?></div><small class="text-muted"><?= esc($line['child_item_name'] ?? '-') ?></small></td>
                <td><?= esc($line['component_type']) ?></td>
                <td class="text-end"><?= esc(number_format((float) $line['qty_used'], 6)) ?></td>
                <td><?= esc($line['uom_code']) ?></td>
                <td class="text-end"><?= esc(number_format((float) $line['factor'], 5)) ?></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
    </div></div></div>
</div>
<?= $this->endSection() ?>
