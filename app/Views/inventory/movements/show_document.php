<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($document['status'] ?? 'posted');
$isReversal = str_ends_with((string) ($document['document_type'] ?? ''), '_reversal');
$statusClass = match ($status) {
    'posted' => 'bg-success-subtle text-success',
    'reversed' => 'bg-warning-subtle text-warning',
    'cancelled' => 'bg-danger-subtle text-danger',
    default => 'bg-secondary-subtle text-secondary',
};
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($document['document_no']) ?></h4>
                <p class="text-muted mb-0">
                    <?= esc($document['document_type']) ?> /
                    <span class="badge <?= esc($statusClass) ?>"><?= esc(ucfirst($status)) ?></span>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($status === 'posted' && ! $isReversal && in_array((string) $document['document_type'], ['inventory_in_out', 'stock_opname'], true)): ?>
                    <form method="post" action="<?= site_url('inventory/movement-documents/' . (int) $document['id'] . '/reverse') ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="text" name="reversal_reason" class="form-control form-control-sm" placeholder="Reverse reason" style="max-width: 200px;">
                        <button class="btn btn-warning" type="submit" onclick="return confirm('Reverse this inventory document and post opposite stock movement?')">Reverse</button>
                    </form>
                <?php endif ?>
                <?php if (! empty($document['reversal_document_id'])): ?>
                    <a href="<?= site_url('inventory/movement-documents/' . (int) $document['reversal_document_id']) ?>" class="btn btn-outline-warning">View Reversal</a>
                <?php endif ?>
                <a href="<?= site_url($document['document_type'] === 'stock_opname' ? 'inventory/stock-opname' : 'inventory/in-out') ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="text-muted">Date</label><div class="fw-semibold"><?= esc($document['document_date']) ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Direction</label><div class="fw-semibold"><?= esc($document['direction'] ?? '-') ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Warehouse</label><div class="fw-semibold"><?= esc(($document['warehouse_code'] ?? '-') . ' ' . ($document['warehouse_name'] ?? '')) ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Location</label><div class="fw-semibold"><?= esc(($document['location_code'] ?? '-') . ' ' . ($document['location_name'] ?? '')) ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Posted At</label><div class="fw-semibold"><?= esc($document['posted_at'] ?? '-') ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Posted By</label><div class="fw-semibold"><?= esc($document['posted_by'] ?? '-') ?></div></div>
            <?php if ($status === 'reversed'): ?>
                <div class="col-md-3 mb-3"><label class="text-muted">Reversed At</label><div class="fw-semibold"><?= esc($document['reversed_at'] ?? '-') ?></div></div>
                <div class="col-md-3 mb-3"><label class="text-muted">Reversed By</label><div class="fw-semibold"><?= esc($document['reversed_by'] ?? '-') ?></div></div>
                <div class="col-md-12 mb-3"><label class="text-muted">Reversal Reason</label><div><?= esc($document['reversal_reason'] ?? '-') ?></div></div>
            <?php endif ?>
            <div class="col-md-12 mb-3"><label class="text-muted">Notes</label><div><?= esc($document['notes'] ?? '-') ?></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">Lines</h4>
        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Batch</th>
                        <th>Dir</th>
                        <th class="text-end">System Qty</th>
                        <th class="text-end">Counted Qty</th>
                        <th class="text-end">Posted Qty</th>
                        <th>UoM</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Value</th>
                        <th>GL</th>
                        <th>Reversal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?= esc($line['line_no']) ?></td>
                        <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                        <td><?= esc(($line['batch_no'] ?? '') !== '' ? $line['batch_no'] : '-') ?></td>
                        <td><span class="badge bg-<?= ($line['movement_direction'] ?? '') === 'in' ? 'success' : 'danger' ?>"><?= esc($line['movement_direction'] ?? '-') ?></span></td>
                        <td class="text-end"><?= $line['system_qty'] === null ? '-' : esc(number_format((float) $line['system_qty'], 4)) ?></td>
                        <td class="text-end"><?= $line['counted_qty'] === null ? '-' : esc(number_format((float) $line['counted_qty'], 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($line['qty'] ?? 0), 4)) ?></td>
                        <td><?= esc($line['uom_code'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($line['unit_cost'] ?? 0), 6)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($line['stock_value'] ?? 0), 2)) ?></td>
                        <td><?= ! empty($line['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $line['gl_entry_id']) . '">#' . esc($line['gl_entry_id']) . '</a>' : '-' ?></td>
                        <td><?= ! empty($line['reversal_movement_id']) ? '#' . esc($line['reversal_movement_id']) : '-' ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($lines === []): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">No line found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
