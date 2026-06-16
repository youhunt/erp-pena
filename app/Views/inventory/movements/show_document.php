<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($document['document_no']) ?></h4>
                <p class="text-muted mb-0"><?= esc($document['document_type']) ?> / <?= esc($document['status']) ?></p>
            </div>
            <a href="<?= site_url($document['document_type'] === 'stock_opname' ? 'inventory/stock-opname' : 'inventory/in-out') ?>" class="btn btn-light">Back</a>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="text-muted">Date</label><div class="fw-semibold"><?= esc($document['document_date']) ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Direction</label><div class="fw-semibold"><?= esc($document['direction'] ?? '-') ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Warehouse</label><div class="fw-semibold"><?= esc(($document['warehouse_code'] ?? '-') . ' ' . ($document['warehouse_name'] ?? '')) ?></div></div>
            <div class="col-md-3 mb-3"><label class="text-muted">Location</label><div class="fw-semibold"><?= esc(($document['location_code'] ?? '-') . ' ' . ($document['location_name'] ?? '')) ?></div></div>
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
                    </tr>
                <?php endforeach ?>

                <?php if ($lines === []): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No line found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
