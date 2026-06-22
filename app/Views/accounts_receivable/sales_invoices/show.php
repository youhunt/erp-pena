<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($invoice['status'] ?? 'open');
$statusClass = match ($status) {
    'open' => 'bg-success',
    'partial' => 'bg-info',
    'paid' => 'bg-primary',
    'cancelled' => 'bg-danger',
    default => 'bg-secondary',
};
$outstandingAmount = (float) ($receivable['outstanding_amount'] ?? $invoice['outstanding_amount'] ?? 0);
$paidAmount = (float) ($receivable['paid_amount'] ?? $invoice['paid_amount'] ?? 0);
$receipts = $receipts ?? [];
$sourceDelivery = null;
$cogsGl = null;
$cogsAmount = 0.0;
$invoiceAmount = (float) ($invoice['total_amount'] ?? 0);

try {
    $db = \Config\Database::connect();
    if ($receipts === [] && $db->tableExists('ar_receipts')) {
        $receiptBuilder = $db->table('ar_receipts')->where('sales_invoice_id', (int) $invoice['id']);
        if ($db->fieldExists('deleted_at', 'ar_receipts')) {
            $receiptBuilder->where('deleted_at', null);
        }
        $receipts = $receiptBuilder->orderBy('receipt_date', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray();
    }

    if (! empty($invoice['sales_delivery_id']) && $db->tableExists('sales_deliveries')) {
        $sourceDelivery = $db->table('sales_deliveries')->where('id', (int) $invoice['sales_delivery_id'])->get(1)->getRowArray();
        if ($sourceDelivery !== null && ! empty($sourceDelivery['gl_entry_id']) && $db->tableExists('gl_entries')) {
            $cogsGl = $db->table('gl_entries')->where('id', (int) $sourceDelivery['gl_entry_id'])->get(1)->getRowArray();
            $cogsAmount = (float) ($cogsGl['total_debit'] ?? 0);
        }
    }
} catch (\Throwable) {
    $receipts = $receipts ?? [];
    $sourceDelivery = null;
    $cogsGl = null;
    $cogsAmount = 0.0;
}

$hasCogs = $cogsGl !== null;
$grossProfit = $hasCogs ? $invoiceAmount - $cogsAmount : null;
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
                        <h4 class="card-title mb-1">Sales Invoice</h4>
                        <p class="text-muted mb-0"><?= esc($invoice['invoice_no']) ?></p>
                    </div>
                    <span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Invoice No</th><td><?= esc($invoice['invoice_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($invoice['invoice_date']) ?></td></tr>
                        <tr><th>Due Date</th><td><?= esc($invoice['due_date'] ?? '-') ?></td></tr>
                        <tr><th>Source</th><td><?= esc($invoice['source_type'] ?? (! empty($invoice['sales_delivery_id']) ? 'delivery' : 'system')) ?></td></tr>
                        <tr><th>SO No</th><td><?= ! empty($invoice['sales_order_id']) ? '<a href="' . site_url('sales/orders/' . $invoice['sales_order_id']) . '">' . esc($invoice['so_no'] ?? '-') . '</a>' : '-' ?></td></tr>
                        <tr><th>DO No</th><td><?= ! empty($invoice['sales_delivery_id']) ? '<a href="' . site_url('sales/deliveries/' . $invoice['sales_delivery_id']) . '">' . esc($invoice['delivery_no'] ?? '-') . '</a>' : '-' ?></td></tr>
                        <tr><th>GL Entry</th><td><?= ! empty($invoice['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $invoice['gl_entry_id']) . '">#' . esc($invoice['gl_entry_id']) . '</a>' : '-' ?></td></tr>
                        <tr><th>COGS GL</th><td><?= $hasCogs ? '<a href="' . site_url('gl/entries/' . (int) $cogsGl['id']) . '">#' . esc($cogsGl['id']) . '</a>' : '<span class="text-muted">Not linked</span>' ?></td></tr>
                        <tr><th>Reversal GL</th><td><?= ! empty($invoice['reversal_gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $invoice['reversal_gl_entry_id']) . '">#' . esc($invoice['reversal_gl_entry_id']) . '</a>' : '-' ?></td></tr>
                        <tr><th>Customer</th><td><?= esc(($invoice['customer_code'] ?? '-') . ' ' . ($invoice['customer_name'] ?? '')) ?></td></tr>
                        <tr><th>Total</th><td class="fw-semibold"><?= esc(number_format((float) ($invoice['total_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Received</th><td class="fw-semibold text-success"><?= esc(number_format($paidAmount, 2)) ?></td></tr>
                        <tr><th>Outstanding</th><td class="fw-semibold <?= $outstandingAmount > 0 ? 'text-danger' : 'text-success' ?>"><?= esc(number_format($outstandingAmount, 2)) ?></td></tr>
                        <?php if ($status === 'cancelled'): ?>
                            <tr><th>Cancelled</th><td><?= esc($invoice['cancelled_at'] ?? '-') ?></td></tr>
                            <tr><th>Reason</th><td><?= esc($invoice['cancel_reason'] ?? '-') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('ar/sales-invoices') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <?php if (! empty($invoice['sales_delivery_id'])): ?>
                        <a href="<?= site_url('sales/deliveries/' . (int) $invoice['sales_delivery_id']) ?>" class="btn btn-outline-info"><i class="bx bx-package me-1"></i> Open DO</a>
                    <?php endif ?>
                    <a href="<?= site_url('print/sales-invoices/' . (int) $invoice['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer me-1"></i> Print</a>
                    <?php if (in_array($status, ['open', 'partial'], true) && $outstandingAmount > 0): ?>
                        <a href="<?= site_url('ar/sales-invoices/' . $invoice['id'] . '/receipt') ?>" class="btn btn-primary"><i class="bx bx-money-withdraw me-1"></i> Post Receipt</a>
                    <?php endif ?>
                    <?php if ($status === 'open' && $paidAmount <= 0): ?>
                        <form method="post" action="<?= site_url('ar/sales-invoices/' . (int) $invoice['id'] . '/cancel') ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Cancel reason" style="max-width: 170px;">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancel this sales invoice and post reversal GL?')">Cancel</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <?php if ($hasCogs): ?>
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
        <?php elseif (! empty($invoice['sales_delivery_id'])): ?>
            <div class="alert alert-warning small">COGS GL is not linked from the source Delivery Order yet.</div>
        <?php endif ?>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Invoice Lines</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty_invoiced'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="5" class="text-end">Subtotal</th><th class="text-end"><?= esc(number_format((float) $invoice['subtotal_amount'], 2)) ?></th></tr>
                            <tr><th colspan="5" class="text-end">Discount</th><th class="text-end"><?= esc(number_format((float) $invoice['discount_amount'], 2)) ?></th></tr>
                            <tr><th colspan="5" class="text-end">Tax</th><th class="text-end"><?= esc(number_format((float) $invoice['tax_amount'], 2)) ?></th></tr>
                            <tr><th colspan="5" class="text-end">Total</th><th class="text-end"><?= esc(number_format((float) $invoice['total_amount'], 2)) ?></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title mb-0">Receipt History</h4>
                    <span class="badge bg-light text-dark"><?= esc((string) count($receipts)) ?> receipt(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Receipt No</th><th>Method</th><th>Cash/Bank</th><th class="text-end">Amount</th><th>Status</th><th>GL</th></tr></thead>
                        <tbody>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><?= esc($receipt['receipt_date'] ?? '-') ?></td>
                                    <td><a href="<?= site_url('ar/receipts/' . (int) $receipt['id']) ?>" class="fw-semibold"><?= esc($receipt['receipt_no'] ?? ('#' . $receipt['id'])) ?></a></td>
                                    <td><?= esc($receipt['receipt_method'] ?? '-') ?></td>
                                    <td><?= esc($receipt['cash_bank_code'] ?? '-') ?></td>
                                    <td class="text-end fw-semibold"><?= esc(number_format((float) ($receipt['receipt_amount'] ?? 0), 2)) ?></td>
                                    <td><span class="badge bg-<?= ($receipt['status'] ?? '') === 'cancelled' ? 'danger' : 'success' ?>"><?= esc($receipt['status'] ?? '-') ?></span></td>
                                    <td><?= ! empty($receipt['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $receipt['gl_entry_id']) . '">#' . esc($receipt['gl_entry_id']) . '</a>' : '-' ?></td>
                                </tr>
                            <?php endforeach ?>
                            <?php if ($receipts === []): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No receipt posted yet.</td></tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (! empty($invoice['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($invoice['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
