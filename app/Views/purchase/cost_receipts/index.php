<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rows ??= [];
$summary ??= ['receipts' => 0, 'qty' => 0, 'amount' => 0, 'invoiced' => 0];
$q ??= '';
$status ??= '';
$badge = static function (string $status): string {
    return match ($status) {
        'posted' => 'success',
        'reversed' => 'warning',
        'draft' => 'secondary',
        default => 'secondary',
    };
};
?>

<?php if (! $hasTable): ?>
    <div class="alert alert-danger">
        Tabel purchase receipt belum tersedia. Jalankan migration / SQL installer terlebih dahulu.
    </div>
<?php endif ?>

<div class="row">
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-1">Receipt</p><h4 class="mb-0"><?= esc(number_format((float) $summary['receipts'])) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-1">Total Qty</p><h4 class="mb-0"><?= esc(number_format((float) $summary['qty'], 4)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-1">Receipt Cost</p><h4 class="mb-0"><?= esc(number_format((float) $summary['amount'], 2)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-1">Invoiced</p><h4 class="mb-0"><?= esc(number_format((float) $summary['invoiced'])) ?></h4></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">Cost Purchase Receipt</h4>
                <p class="text-muted mb-0">Monitoring nilai receipt dari PO: qty received × unit cost, link GL receipt, dan status invoice.</p>
            </div>
            <a href="<?= site_url('purchase/receipts') ?>" class="btn btn-light"><i class="bx bx-package me-1"></i> Purchase Receipts</a>
        </div>

        <form class="row g-2 mb-3" method="get" action="<?= site_url('modules/cost-purchase-receipt') ?>">
            <div class="col-md-5">
                <input name="q" class="form-control" value="<?= esc($q) ?>" placeholder="Search receipt, PO, supplier, item...">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach (['posted', 'reversed', 'draft'] as $option): ?>
                        <option value="<?= esc($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= esc(ucfirst($option)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bx bx-search me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a class="btn btn-light w-100" href="<?= site_url('modules/cost-purchase-receipt') ?>">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt</th>
                        <th>Date</th>
                        <th>PO</th>
                        <th>Supplier</th>
                        <th>Items</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Cost</th>
                        <th>Invoice</th>
                        <th>GL</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a class="fw-semibold" href="<?= site_url('purchase/receipts/' . (int) $row['id']) ?>"><?= esc($row['receipt_no'] ?? '-') ?></a></td>
                            <td><?= esc($row['receipt_date'] ?? '-') ?></td>
                            <td><?= esc($row['po_no'] ?? '-') ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($row['supplier_code'] ?? '-') ?></div>
                                <small class="text-muted"><?= esc($row['supplier_name'] ?? '-') ?></small>
                            </td>
                            <td style="max-width:260px;white-space:normal"><?= esc($row['item_codes'] ?? '-') ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($row['line_count'] ?? 0))) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($row['total_qty'] ?? 0), 4)) ?></td>
                            <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['receipt_amount'] ?? 0), 2)) ?></td>
                            <td>
                                <?php if (! empty($row['is_invoiced'])): ?>
                                    <span class="badge bg-success-subtle text-success">Invoiced</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning">Not Yet</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if (! empty($row['gl_entry_id'])): ?>
                                    <a href="<?= site_url('gl/entries/' . (int) $row['gl_entry_id']) ?>">#<?= esc((string) $row['gl_entry_id']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif ?>
                                <?php if (! empty($row['reversal_gl_entry_id'])): ?>
                                    <small class="d-block">Rev: <a href="<?= site_url('gl/entries/' . (int) $row['reversal_gl_entry_id']) ?>">#<?= esc((string) $row['reversal_gl_entry_id']) ?></a></small>
                                <?php endif ?>
                            </td>
                            <td><span class="badge bg-<?= esc($badge((string) ($row['status'] ?? ''))) ?>"><?= esc($row['status'] ?? '-') ?></span></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Belum ada purchase receipt untuk active company/site atau filter ini.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            Catatan: halaman ini mengaktifkan monitoring cost receipt dasar. Tahap berikutnya bisa ditambah fitur alokasi landed cost/freight/handling ke item receipt.
        </div>
    </div>
</div>
<?= $this->endSection() ?>
