<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $previewRows ??= []; ?>
<form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/allocate') ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-1">Sales Order</h4>
                    <p class="text-muted mb-3"><?= esc($order['so_no'] ?? $order['document_no'] ?? '-') ?> - <?= esc($order['customer_name'] ?? '-') ?></p>
                    <table class="table table-sm mb-0">
                        <tr><th>Status</th><td><?= esc($order['document_status'] ?? $order['status'] ?? '-') ?></td></tr>
                        <tr><th>Date</th><td><?= esc($order['so_date'] ?? $order['document_date'] ?? '-') ?></td></tr>
                        <tr><th>Customer</th><td><?= esc(($order['customer_code'] ?? $order['customer'] ?? '-') . ' ' . ($order['customer_name'] ?? '')) ?></td></tr>
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
                            <p class="text-muted mb-0">Allocation akan memilih Inventory Location/Batch yang available dan belum expired.</p>
                        </div>
                        <a href="<?= site_url('sales/orders/' . $order['id']) ?>" class="btn btn-light">Back</a>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Allocation No</label><input name="allocnumb" class="form-control" value="<?= esc(old('allocnumb', 'ALC-' . date('Ymd-His'))) ?>"><div class="form-text">Kosongkan jika ingin auto fallback.</div></div>
                        <div class="col-md-4"><label class="form-label">Allocation Date</label><input type="date" name="allocdate" class="form-control" required value="<?= esc(old('allocdate', date('Y-m-d'))) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Ship Date</label><input type="date" name="shipdate" class="form-control" value="<?= esc(old('shipdate')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Department</label><input name="dept" class="form-control" maxlength="12" value="<?= esc(old('dept')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Warehouse Filter</label><input name="whs" class="form-control" maxlength="12" value="<?= esc(old('whs')) ?>" placeholder="Optional"></div>
                        <div class="col-md-3"><label class="form-label">Location Filter</label><input name="loc" class="form-control" maxlength="12" value="<?= esc(old('loc')) ?>" placeholder="Optional"></div>
                        <div class="col-md-3"><label class="form-label">Ship To</label><input name="shipto" class="form-control" maxlength="12" value="<?= esc(old('shipto')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Batch Filter</label><input name="batchno" class="form-control" maxlength="30" value="<?= esc(old('batchno')) ?>" placeholder="Optional"></div>
                        <div class="col-md-8"><label class="form-label">Remarks</label><input name="remarks" class="form-control" maxlength="500" value="<?= esc(old('remarks')) ?>"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Allocation Preview</h4>
                    <div class="alert alert-info">Condition: SO belum Closed, SO available qty &gt; 0, batch belum expired, dan Inventory Location available qty &gt; 0.</div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>SO Line</th>
                                    <th>Item</th>
                                    <th class="text-end">SO Qty</th>
                                    <th class="text-end">Allocated</th>
                                    <th class="text-end">Available SO</th>
                                    <th class="text-end">Stock Available</th>
                                    <th>Inventory Loc / Batch</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($previewRows as $row): ?>
                                <?php $line = $row['line'] ?? []; ?>
                                <tr>
                                    <td><?= esc($line['so_line'] ?? $line['line_no'] ?? '-') ?></td>
                                    <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                    <td class="text-end"><?= esc(number_format((float) ($row['ordered'] ?? 0), 6)) ?></td>
                                    <td class="text-end"><?= esc(number_format((float) ($row['allocated'] ?? 0), 6)) ?></td>
                                    <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['available_so'] ?? 0), 6)) ?></td>
                                    <td class="text-end <?= (float) ($row['stock_available'] ?? 0) > 0 ? 'text-success' : 'text-danger' ?>"><?= esc(number_format((float) ($row['stock_available'] ?? 0), 6)) ?></td>
                                    <td>
                                        <?php $stocks = $row['stock_rows'] ?? []; ?>
                                        <?php if ($stocks === []): ?>
                                            <span class="text-danger">No available stock/batch.</span>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead><tr><th>Whs</th><th>Loc</th><th>Batch</th><th>Exp Date</th><th class="text-end">Available</th><th>UoM</th></tr></thead>
                                                    <tbody>
                                                    <?php foreach ($stocks as $stock): ?>
                                                        <tr>
                                                            <td><?= esc($stock['warehouse_code'] ?? '-') ?></td>
                                                            <td><?= esc($stock['location_code'] ?? '-') ?></td>
                                                            <td><?= esc($stock['batch_no'] ?? '-') ?></td>
                                                            <td><?= esc($stock['expiry_date'] ?? '-') ?></td>
                                                            <td class="text-end"><?= esc(number_format((float) ($stock['qty_available'] ?? 0), 6)) ?></td>
                                                            <td><?= esc($stock['uom_code'] ?? '-') ?></td>
                                                        </tr>
                                                    <?php endforeach ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            <?php if ($previewRows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No SO line to allocate.</td></tr><?php endif ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post this allocation and reserve stock by location/batch?')"><i class="bx bx-lock-alt me-1"></i> Post Allocation</button>
                        <a href="<?= site_url('sales/orders/' . $order['id']) ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
