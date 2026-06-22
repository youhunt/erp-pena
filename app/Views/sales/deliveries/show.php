<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($delivery['status'] ?? 'posted');
$existingInvoice = $existingInvoice ?? null;
$statusClass = match ($status) {
    'posted' => 'bg-success',
    'invoiced' => 'bg-info',
    'reversed' => 'bg-warning text-dark',
    default => 'bg-secondary',
};
$hasCogsGl = ! empty($delivery['gl_entry_id']);
$hasInvoice = ! empty($existingInvoice);
$hasMovements = false;
foreach ($lines as $auditLine) {
    if (! empty($auditLine['stock_movement_id'])) {
        $hasMovements = true;
        break;
    }
}

$invoiceAmount = $hasInvoice ? (float) ($existingInvoice['total_amount'] ?? $existingInvoice['grand_total'] ?? 0) : 0.0;
$cogsAmount = 0.0;
if ($hasCogsGl) {
    try {
        $db = \Config\Database::connect();
        if ($db->tableExists('gl_entries')) {
            $gl = $db->table('gl_entries')->where('id', (int) $delivery['gl_entry_id'])->get(1)->getRowArray();
            $cogsAmount = (float) ($gl['total_debit'] ?? 0);
        }
    } catch (\Throwable) {
        $cogsAmount = 0.0;
    }
}
$grossProfit = ($hasInvoice && $hasCogsGl) ? $invoiceAmount - $cogsAmount : null;
$grossMargin = ($grossProfit !== null && $invoiceAmount > 0) ? ($grossProfit / $invoiceAmount) * 100 : null;
$marginBadge = $grossProfit === null ? 'bg-secondary' : ($grossProfit >= 0 ? 'bg-success' : 'bg-danger');
$marginLabel = $grossProfit === null ? 'not calculated' : ($grossProfit >= 0 ? 'profit' : 'loss');
?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Delivery Order</h4>
                        <p class="text-muted mb-0"><?= esc($delivery['delivery_no']) ?></p>
                    </div>
                    <span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span>
                </div>

                <?php if ($hasMovements && ! $hasCogsGl): ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <strong>Audit note:</strong> Stock movement already exists, but COGS GL Entry is not linked yet. Continue AR flow is allowed for UAT, but COGS posting should be reviewed.
                    </div>
                <?php elseif ($hasMovements && $hasCogsGl): ?>
                    <div class="alert alert-success py-2 small mb-3">
                        <strong>Audit complete:</strong> Stock movement and COGS GL Entry are linked.
                    </div>
                <?php endif ?>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Delivery No</th><td><?= esc($delivery['delivery_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($delivery['delivery_date']) ?></td></tr>
                        <tr><th>SO No</th><td><a href="<?= site_url('sales/orders/' . $delivery['sales_order_id']) ?>"><?= esc($delivery['so_no']) ?></a></td></tr>
                        <tr><th>Customer</th><td><?= esc(($delivery['customer_code'] ?? '-') . ' ' . ($delivery['customer_name'] ?? '')) ?></td></tr>
                        <tr><th>Stock Movement</th><td><?= $hasMovements ? '<span class="badge bg-success">posted</span>' : '<span class="badge bg-secondary">none</span>' ?></td></tr>
                        <tr><th>COGS GL Entry</th><td><?= $hasCogsGl ? '<a href="' . site_url('gl/entries/' . $delivery['gl_entry_id']) . '">#' . esc($delivery['gl_entry_id']) . '</a>' : '<span class="text-muted">Not posted / not linked</span>' ?></td></tr>
                        <tr><th>AR Invoice</th><td><?= $hasInvoice ? '<a href="' . site_url('ar/sales-invoices/' . (int) $existingInvoice['id']) . '">' . esc($existingInvoice['invoice_no'] ?? ('#' . $existingInvoice['id'])) . '</a>' : '-' ?></td></tr>
                        <tr><th>Reversal GL</th><td><?= ! empty($delivery['reversal_gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $delivery['reversal_gl_entry_id']) . '">#' . esc($delivery['reversal_gl_entry_id']) . '</a>' : '-' ?></td></tr>
                        <tr><th>Posted</th><td><?= esc($delivery['posted_at'] ?? '-') ?></td></tr>
                        <?php if ($status === 'reversed'): ?>
                            <tr><th>Reversed</th><td><?= esc($delivery['reversed_at'] ?? '-') ?></td></tr>
                            <tr><th>Reason</th><td><?= esc($delivery['reversal_reason'] ?? '-') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= site_url('sales/orders/' . $delivery['sales_order_id']) ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back to SO</a>
                    <a href="<?= site_url('inventory/stock-card?item_code=' . urlencode((string) ($lines[0]['item_code'] ?? ''))) ?>" class="btn btn-outline-info"><i class="bx bx-list-ul me-1"></i> Stock Card</a>
                    <a href="<?= site_url('print/sales-deliveries/' . (int) $delivery['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer me-1"></i> Print</a>
                    <?php if ($hasCogsGl): ?>
                        <a href="<?= site_url('gl/entries/' . (int) $delivery['gl_entry_id']) ?>" class="btn btn-outline-primary"><i class="bx bx-book me-1"></i> COGS GL</a>
                    <?php endif ?>
                    <?php if ($hasInvoice): ?>
                        <a href="<?= site_url('ar/sales-invoices/' . (int) $existingInvoice['id']) ?>" class="btn btn-info"><i class="bx bx-receipt me-1"></i> View AR Invoice</a>
                    <?php elseif ($status === 'posted'): ?>
                        <a href="<?= site_url('sales/deliveries/' . $delivery['id'] . '/invoice') ?>" class="btn btn-primary"><i class="bx bx-receipt me-1"></i> Create Invoice</a>
                        <form method="post" action="<?= site_url('sales/deliveries/' . (int) $delivery['id'] . '/reverse') ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="text" name="reversal_reason" class="form-control form-control-sm" placeholder="Reverse reason" style="max-width: 170px;">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Reverse this delivery and return stock?')">Reverse</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>
        <?php if ($hasInvoice && $hasCogsGl): ?>
            <div class="card border-<?= $grossProfit >= 0 ? 'success' : 'danger' ?>">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="card-title mb-1">Margin Audit</h5>
                            <p class="text-muted small mb-0">Invoice revenue vs delivery COGS.</p>
                        </div>
                        <span class="badge <?= esc($marginBadge) ?>"><?= esc($marginLabel) ?></span>
                    </div>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th>Invoice Amount</th><td class="text-end"><?= esc(number_format($invoiceAmount, 2)) ?></td></tr>
                            <tr><th>COGS Amount</th><td class="text-end"><?= esc(number_format($cogsAmount, 2)) ?></td></tr>
                            <tr><th>Gross Profit/Loss</th><td class="text-end fw-semibold <?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= esc(number_format((float) $grossProfit, 2)) ?></td></tr>
                            <tr><th>Gross Margin</th><td class="text-end fw-semibold <?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= esc($grossMargin !== null ? number_format($grossMargin, 2) . '%' : '-') ?></td></tr>
                        </tbody>
                    </table>
                    <?php if ($grossProfit < 0): ?>
                        <div class="alert alert-danger py-2 small mt-3 mb-0">COGS is higher than invoice value. Review item cost / sales price before production use.</div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
        <?php if ($hasInvoice): ?>
            <div class="card border-info">
                <div class="card-body py-3">
                    <h5 class="card-title mb-2">Next Step</h5>
                    <p class="text-muted small mb-2">Invoice already created. Continue to AR receipt / cash-bank audit from the invoice detail.</p>
                    <a href="<?= site_url('ar/sales-invoices/' . (int) $existingInvoice['id']) ?>" class="btn btn-sm btn-info">Open AR Invoice</a>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="card-title mb-1">Delivered Lines</h4>
                        <p class="text-muted mb-0">Stock movement audit per delivered item.</p>
                    </div>
                    <span class="badge <?= $hasMovements ? 'bg-success' : 'bg-secondary' ?>"><?= $hasMovements ? 'movement posted' : 'no movement' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th>Batch</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Price</th><th>Movement</th><th>Reversal</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td><?= esc(($line['batch_no'] ?? '') !== '' ? $line['batch_no'] : '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty_delivered'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 6)) ?></td>
                                <td><?= ! empty($line['stock_movement_id']) ? '<span class="badge bg-success">#' . esc($line['stock_movement_id']) . '</span>' : '-' ?></td>
                                <td><?= ! empty($line['reversal_movement_id']) ? '<span class="badge bg-warning text-dark">#' . esc($line['reversal_movement_id']) . '</span>' : '-' ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if (! empty($delivery['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($delivery['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
