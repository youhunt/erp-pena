<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$routingLabels = [];
try {
    $routingIds = array_values(array_unique(array_filter(array_map(static fn ($row): int => (int) ($row['routing_id'] ?? 0), $rows ?? []))));
    $db = db_connect();
    if ($routingIds !== [] && $db->tableExists('production_routings')) {
        foreach ($db->table('production_routings')->select('id, item_code, site_code, department_code, warehouse_code, description')->whereIn('id', $routingIds)->get()->getResultArray() as $routing) {
            $label = trim(($routing['item_code'] ?? '') . ' / ' . ($routing['site_code'] ?? '') . ' / ' . ($routing['department_code'] ?? '') . ' / ' . ($routing['warehouse_code'] ?? ''), ' /');
            $routingLabels[(int) $routing['id']] = $label !== '' ? $label : (string) $routing['id'];
        }
    }
} catch (Throwable) {
    $routingLabels = [];
}
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="card-title mb-1">Bill of Material</h4>
                <p class="text-muted mb-0">Parent item, material structure, and linked routing for production.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/imports/boms') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
                <a href="<?= site_url('production/boms/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New BOM</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Parent Item</th><th>Site</th><th>Department</th><th>Warehouse</th><th>Routing</th><th>Batch Qty</th><th>UoM</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $routingId = (int) ($row['routing_id'] ?? 0); ?>
                    <tr>
                        <td><div class="fw-semibold"><?= esc($row['parent_item_code']) ?></div><small class="text-muted"><?= esc($row['parent_item_name'] ?? '-') ?></small></td>
                        <td><?= esc($row['site_code']) ?></td>
                        <td><?= esc($row['department_code']) ?></td>
                        <td><?= esc($row['warehouse_code'] ?? '-') ?></td>
                        <td><?= $routingId > 0 ? '<a href="' . site_url('production/routings/' . $routingId) . '">' . esc($routingLabels[$routingId] ?? (string) $routingId) . '</a>' : '-' ?></td>
                        <td><?= esc(number_format((float) $row['qty_batch'], 4)) ?></td>
                        <td><?= esc($row['uom_code']) ?></td>
                        <td><span class="badge bg-<?= (int) $row['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $row['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
                        <td class="text-end"><a href="<?= site_url('production/boms/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($rows === []): ?><tr><td colspan="9" class="text-center text-muted py-4">No BOM yet.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
