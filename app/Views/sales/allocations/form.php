<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/allocate') ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-1">Sales Order</h4>
                    <p class="text-muted mb-3"><?= esc($order['so_no']) ?> - <?= esc($order['customer_name'] ?? '-') ?></p>
                    <table class="table table-sm mb-0">
                        <tr><th>Status</th><td><?= esc($order['document_status'] ?? $order['status'] ?? '-') ?></td></tr>
                        <tr><th>Date</th><td><?= esc($order['so_date']) ?></td></tr>
                        <tr><th>Customer</th><td><?= esc(($order['customer_code'] ?? '-') . ' ' . ($order['customer_name'] ?? '')) ?></td></tr>
                        <tr><th>Site</th><td><?= esc($order['site'] ?? $order['site_id'] ?? '-') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h4 class="card-title mb-1">Create Allocation Order</h4>
                            <p class="text-muted mb-0">Allocation will reserve available stock and create allocationorder/allocationline records.</p>
                        </div>
                        <a href="<?= site_url('sales/orders/' . $order['id']) ?>" class="btn btn-light">Back</a>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Allocation No</label><input name="allocnumb" class="form-control" required value="<?= esc(old('allocnumb', 'ALC-' . date('Ymd-His'))) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Allocation Date</label><input type="date" name="allocdate" class="form-control" required value="<?= esc(old('allocdate', date('Y-m-d'))) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Ship Date</label><input type="date" name="shipdate" class="form-control" value="<?= esc(old('shipdate')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Department</label><input name="dept" class="form-control" maxlength="12" value="<?= esc(old('dept')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Warehouse</label><input name="whs" class="form-control" maxlength="12" value="<?= esc(old('whs')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Location</label><input name="loc" class="form-control" maxlength="12" value="<?= esc(old('loc')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Ship To</label><input name="shipto" class="form-control" maxlength="12" value="<?= esc(old('shipto')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Batch No</label><input name="batchno" class="form-control" maxlength="30" value="<?= esc(old('batchno')) ?>"></div>
                        <div class="col-md-8"><label class="form-label">Remarks</label><input name="remarks" class="form-control" maxlength="50" value="<?= esc(old('remarks')) ?>"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">SO Lines To Allocate</h4>
                    <div class="table-responsive">
                        <table class="table table-nowrap align-middle mb-0">
                            <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Reserved</th><th class="text-end">To Allocate</th><th>UoM</th></tr></thead>
                            <tbody>
                            <?php foreach ($lines as $line): ?>
                                <?php $toAllocate = max(0, (float) ($line['qty_ordered'] ?? $line['qty'] ?? 0) - (float) ($line['qty_reserved'] ?? 0)); ?>
                                <tr>
                                    <td><?= esc($line['line_no']) ?></td>
                                    <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                    <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                    <td class="text-end"><?= esc(number_format((float) ($line['qty_reserved'] ?? 0), 4)) ?></td>
                                    <td class="text-end fw-semibold"><?= esc(number_format($toAllocate, 4)) ?></td>
                                    <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post this allocation and reserve stock?')"><i class="bx bx-lock-alt me-1"></i> Post Allocation</button>
                        <a href="<?= site_url('sales/orders/' . $order['id']) ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
