<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldQtys = old('qty_delivered', []);
if (! is_array($oldQtys)) {
    $oldQtys = [];
}
?>
<form method="post" action="<?= site_url('sales/orders/' . (int) $so['id'] . '/deliver') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="allocation_id" value="<?= (int) $allocation['id'] ?>">

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Create Delivery from Allocation</h4>
                    <p class="text-muted mb-0">
                        Allocation: <strong><?= esc($allocation['allocnumb'] ?? '-') ?></strong> |
                        SO: <strong><?= esc($so['so_no'] ?? '-') ?></strong> |
                        Customer: <?= esc($so['customer_name'] ?? '-') ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= site_url('sales/allocations/' . (int) $allocation['id']) ?>" class="btn btn-light">Back to Allocation</a>
                    <a href="<?= site_url('sales/orders/' . (int) $so['id']) ?>" class="btn btn-outline-primary">Open SO</a>
                </div>
            </div>

            <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
            <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>

            <div class="alert alert-info">
                Delivery ini mengambil qty dari <strong>Allocation Line</strong>. Warehouse, Location, dan Batch mengikuti hasil allocation supaya reserved stock yang sama langsung dilepas dan dikeluarkan.
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Delivery No</label>
                    <input type="text" name="delivery_no" class="form-control" placeholder="<?= esc(($suggestedDeliveryNo ?? '') !== '' ? $suggestedDeliveryNo : 'Auto if blank', 'attr') ?>" value="<?= esc(old('delivery_no')) ?>">
                    <small class="text-muted">Kosongkan untuk nomor otomatis.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" name="delivery_date" class="form-control" required value="<?= esc(old('delivery_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes', 'Delivery from allocation ' . ($allocation['allocnumb'] ?? ''))) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">Allocated Lines</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0" id="allocationDeliveryLinesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Alloc Line</th>
                            <th>SO Line</th>
                            <th>Item</th>
                            <th>Whs</th>
                            <th>Loc</th>
                            <th>Batch</th>
                            <th class="text-end">Allocated</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-end" style="min-width:150px;">Deliver Now</th>
                            <th>UoM</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $index => $line): ?>
                        <?php
                            $remaining = (float) ($line['remaining_qty'] ?? 0);
                            $qtyValue = array_key_exists($index, $oldQtys) ? $oldQtys[$index] : $remaining;
                        ?>
                        <tr>
                            <td>
                                <?= esc($line['line'] ?? '-') ?>
                                <input type="hidden" name="allocationline_id[]" value="<?= (int) $line['id'] ?>">
                            </td>
                            <td><?= esc($line['soline'] ?? '-') ?></td>
                            <td><div class="fw-semibold"><?= esc($line['itemcode'] ?? '-') ?></div><small class="text-muted"><?= esc($line['itemname'] ?? '-') ?></small></td>
                            <td><?= esc($line['whs'] ?? '-') ?></td>
                            <td><?= esc($line['loc'] ?? '-') ?></td>
                            <td><?= esc($line['batchno'] ?? '-') ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['allocateqty'] ?? 0), 6)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['delivered_qty'] ?? 0), 6)) ?></td>
                            <td class="text-end fw-semibold remaining-qty"><?= esc(number_format($remaining, 6, '.', '')) ?></td>
                            <td>
                                <input type="text" inputmode="decimal" name="qty_delivered[]" class="form-control text-end deliver-now" data-remaining="<?= esc((string) $remaining, 'attr') ?>" value="<?= esc((string) $qtyValue) ?>">
                            </td>
                            <td><?= esc($line['allocateuom'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($lines === []): ?><tr><td colspan="11" class="text-center text-muted py-4">No remaining allocation line to deliver.</td></tr><?php endif ?>
                    </tbody>
                    <?php if ($lines !== []): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="9" class="text-end">Total Deliver Now</th>
                            <th class="text-end" id="totalDeliverNow">0.000000</th>
                            <th></th>
                        </tr>
                    </tfoot>
                    <?php endif ?>
                </table>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success" <?= $lines === [] ? 'disabled' : '' ?> onclick="return confirm('Post delivery dari allocation ini? Reserved stock akan dilepas dan stock inventory akan keluar.')"><i class="bx bx-send me-1"></i> Post Delivery from Allocation</button>
                <a href="<?= site_url('sales/allocations/' . (int) $allocation['id']) ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const total = document.getElementById('totalDeliverNow');
    function number(value) {
        value = String(value || '').trim();
        if (value.indexOf(',') >= 0 && value.indexOf('.') < 0) value = value.replace(',', '.');
        else value = value.replace(/,/g, '');
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    }
    function recalc() {
        let sum = 0;
        document.querySelectorAll('.deliver-now').forEach(function (input) {
            const remaining = number(input.dataset.remaining);
            const value = number(input.value);
            input.classList.toggle('is-invalid', value < 0 || value > remaining);
            sum += value;
        });
        if (total) total.textContent = sum.toLocaleString(undefined, {minimumFractionDigits: 6, maximumFractionDigits: 6});
    }
    document.querySelectorAll('.deliver-now').forEach(function (input) {
        input.addEventListener('input', recalc);
    });
    recalc();
});
</script>
<?= $this->endSection() ?>
