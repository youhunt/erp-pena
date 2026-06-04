<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div><h4 class="card-title mb-1">Work Order In</h4><p class="text-muted mb-0"><?= esc($order['wo_no']) ?></p></div>
                <span class="badge bg-success"><?= esc($order['status']) ?></span>
            </div>
            <table class="table table-sm mb-0"><tbody>
                <tr><th>WO No</th><td><?= esc($order['wo_no']) ?></td></tr>
                <tr><th>Date</th><td><?= esc($order['wo_date']) ?></td></tr>
                <tr><th>Finished Good</th><td><?= esc(($order['finished_item_code'] ?? '-') . ' ' . ($order['finished_item_name'] ?? '')) ?></td></tr>
                <tr><th>Good Qty</th><td><?= esc(number_format((float) ($order['qty_good'] ?? 0), 4)) ?> <?= esc($order['uom_code'] ?? '') ?></td></tr>
                <tr><th>Reject Qty</th><td><?= esc(number_format((float) ($order['qty_reject'] ?? 0), 4)) ?></td></tr>
                <tr><th>Unit Cost</th><td><?= esc(number_format((float) ($order['unit_cost'] ?? 0), 6)) ?></td></tr>
                <tr><th>Posted</th><td><?= esc($order['posted_at'] ?? '-') ?></td></tr>
            </tbody></table>
            <div class="mt-3"><a href="<?= site_url('production/work-order-in') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a></div>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card"><div class="card-body">
            <h4 class="card-title mb-3">Output Lines</h4>
            <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
                <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Good Qty</th><th class="text-end">Reject Qty</th><th>UoM</th><th class="text-end">Unit Cost</th><th>Movement</th></tr></thead>
                <tbody>
                <?php foreach ($outputs as $output): ?>
                    <tr>
                        <td><?= esc($output['line_no']) ?></td>
                        <td><div class="fw-semibold"><?= esc($output['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($output['item_name'] ?? '-') ?></small></td>
                        <td class="text-end"><?= esc(number_format((float) ($output['qty_good'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($output['qty_reject'] ?? 0), 4)) ?></td>
                        <td><?= esc($output['uom_code'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($output['unit_cost'] ?? 0), 6)) ?></td>
                        <td><?= esc($output['inventory_movement_id'] ?? '-') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
        </div></div>
        <?php if (! empty($order['notes'])): ?><div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($order['notes']) ?></p></div></div><?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
