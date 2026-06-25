<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$type = $type ?? 'cash';
$entryAmount = (float) ($entry['amount'] ?? 0);
$entryRate = (float) ($entry['exchange_rate'] ?? 1);
$baseCurrency = (string) ($entry['base_currency'] ?? 'IDR');
$baseAmount = array_key_exists('base_amount', $entry) ? (float) $entry['base_amount'] : $entryAmount * ($entryRate > 0 ? $entryRate : 1);
$sourceDocuments = [];
try {
    $db = \Config\Database::connect();
    $entryId = (int) ($entry['id'] ?? 0);
    if ($entryId > 0 && $db->tableExists('ap_payments')) {
        $builder = $db->table('ap_payments')->where('(cash_bank_entry_id = ' . $entryId . ' OR reversal_cash_bank_entry_id = ' . $entryId . ')', null, false);
        if ($db->fieldExists('deleted_at', 'ap_payments')) { $builder->where('deleted_at', null); }
        foreach ($builder->orderBy('payment_date', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray() as $payment) {
            $sourceDocuments[] = ['module' => 'AP Payment', 'date' => $payment['payment_date'] ?? '-', 'doc_no' => $payment['payment_no'] ?? ('#' . $payment['id']), 'doc_url' => site_url('ap/payments/' . (int) $payment['id']), 'invoice_no' => $payment['invoice_no'] ?? '-', 'invoice_url' => ! empty($payment['purchase_invoice_id']) ? site_url('ap/purchase-invoices/' . (int) $payment['purchase_invoice_id']) : null, 'amount' => (float) ($payment['payment_amount'] ?? 0), 'status' => $payment['status'] ?? '-', 'role' => ((int) ($payment['reversal_cash_bank_entry_id'] ?? 0) === $entryId) ? 'reversal' : 'posting'];
        }
    }
    if ($entryId > 0 && $db->tableExists('ar_receipts')) {
        $builder = $db->table('ar_receipts')->where('(cash_bank_entry_id = ' . $entryId . ' OR reversal_cash_bank_entry_id = ' . $entryId . ')', null, false);
        if ($db->fieldExists('deleted_at', 'ar_receipts')) { $builder->where('deleted_at', null); }
        foreach ($builder->orderBy('receipt_date', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray() as $receipt) {
            $sourceDocuments[] = ['module' => 'AR Receipt', 'date' => $receipt['receipt_date'] ?? '-', 'doc_no' => $receipt['receipt_no'] ?? ('#' . $receipt['id']), 'doc_url' => site_url('ar/receipts/' . (int) $receipt['id']), 'invoice_no' => $receipt['invoice_no'] ?? '-', 'invoice_url' => ! empty($receipt['sales_invoice_id']) ? site_url('ar/sales-invoices/' . (int) $receipt['sales_invoice_id']) : null, 'amount' => (float) ($receipt['receipt_amount'] ?? 0), 'status' => $receipt['status'] ?? '-', 'role' => ((int) ($receipt['reversal_cash_bank_entry_id'] ?? 0) === $entryId) ? 'reversal' : 'posting'];
        }
    }
} catch (\Throwable) { $sourceDocuments = []; }
?>
<div class="row">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
                    <div><h4 class="card-title mb-1"><?= esc($entry['entry_no'] ?? '-') ?></h4><p class="text-muted mb-0"><?= esc($entry['description'] ?? '-') ?></p></div>
                    <a href="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries')) ?>" class="btn btn-light">Back</a>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3"><div class="text-muted">Date</div><div class="fw-semibold"><?= esc($entry['entry_date'] ?? '-') ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Type</div><div class="fw-semibold"><?= esc($entry['entry_type'] ?? '-') ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Cash/Bank</div><div class="fw-semibold"><?= esc($entry['cash_bank_code'] ?? '-') ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Amount</div><div class="fw-semibold"><?= esc(($entry['currency_code'] ?? 'IDR') . ' ' . number_format($entryAmount, 2)) ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Rate Type</div><div class="fw-semibold"><?= esc($entry['rate_type'] ?? '-') ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Exchange Rate</div><div class="fw-semibold"><?= esc(number_format($entryRate > 0 ? $entryRate : 1, 12)) ?></div></div>
                    <div class="col-md-12 mb-3"><div class="text-muted">Base Amount</div><div class="fw-bold fs-5"><?= esc($baseCurrency . ' ' . number_format($baseAmount, 2)) ?></div></div>
                </div>

                <table class="table table-bordered mb-0"><tbody>
                    <tr><th style="width:220px;">Reference</th><td><?= esc($entry['reference_no'] ?? '-') ?></td></tr>
                    <tr><th>Counter Account</th><td><code><?= esc($entry['counter_account_no'] ?? '-') ?></code></td></tr>
                    <tr><th>GL Entry ID</th><td><?= ! empty($entry['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $entry['gl_entry_id']) . '">#' . esc($entry['gl_entry_id']) . '</a>' : '-' ?></td></tr>
                    <tr><th>Status</th><td><?= esc($entry['status'] ?? '-') ?></td></tr>
                    <tr><th>Posted At</th><td><?= esc($entry['posted_at'] ?? '-') ?></td></tr>
                </tbody></table>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><h4 class="card-title mb-1">Source Documents</h4><p class="text-muted mb-0">Dokumen AP/AR yang membuat atau membalik cash/bank entry ini.</p></div><span class="badge bg-light text-dark"><?= esc((string) count($sourceDocuments)) ?> document(s)</span></div>
            <div class="table-responsive"><table class="table table-nowrap align-middle mb-0"><thead class="table-light"><tr><th>Module</th><th>Date</th><th>Document</th><th>Invoice</th><th>Role</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($sourceDocuments as $source): ?><tr><td><?= esc($source['module']) ?></td><td><?= esc($source['date']) ?></td><td><a href="<?= esc($source['doc_url']) ?>" class="fw-semibold"><?= esc($source['doc_no']) ?></a></td><td><?= ! empty($source['invoice_url']) ? '<a href="' . esc($source['invoice_url']) . '">' . esc($source['invoice_no']) . '</a>' : esc($source['invoice_no']) ?></td><td><span class="badge bg-<?= $source['role'] === 'reversal' ? 'warning text-dark' : 'success' ?>"><?= esc($source['role']) ?></span></td><td class="text-end fw-semibold"><?= esc(number_format((float) $source['amount'], 2)) ?></td><td><?= esc($source['status']) ?></td></tr><?php endforeach ?>
                <?php if ($sourceDocuments === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No AP/AR source document linked to this entry.</td></tr><?php endif ?>
            </tbody></table></div>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
