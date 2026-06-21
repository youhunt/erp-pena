<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$sourceModule = (string) ($entry['source_module'] ?? '');
$sourceType = (string) ($entry['source_type'] ?? '');
$sourceId = (int) ($entry['source_id'] ?? 0);
$sourceNo = (string) ($entry['source_no'] ?? '');
$sourceLabel = trim($sourceType !== '' ? $sourceType : $sourceModule);
$sourceUrl = null;

$sourceRoutes = [
    'purchase_receipt' => 'purchase/receipts/',
    'purchase_receipt_reversal' => 'purchase/receipts/',
    'purchase_invoice' => 'ap/purchase-invoices/',
    'manual_ap_invoice' => 'ap/purchase-invoices/',
    'ap_payment' => 'ap/payments/',
    'ap_payment_reversal' => 'ap/payments/',
    'sales_delivery' => 'sales/deliveries/',
    'sales_delivery_reversal' => 'sales/deliveries/',
    'sales_invoice' => 'ar/sales-invoices/',
    'manual_ar_invoice' => 'ar/sales-invoices/',
    'ar_receipt' => 'ar/receipts/',
    'ar_receipt_reversal' => 'ar/receipts/',
    'cash_bank' => 'cash-bank/bank-entries/',
    'cash_bank_reversal' => 'cash-bank/bank-entries/',
    'stock_adjustment' => 'inventory/stock-adjustment/',
    'stock_adjustment_reversal' => 'inventory/stock-adjustment/',
    'production_issue' => 'production/work-orders/',
    'production_receipt' => 'production/work-orders/',
];

$keyCandidates = array_values(array_filter([$sourceType, $sourceModule]));
foreach ($keyCandidates as $candidate) {
    if ($sourceId > 0 && isset($sourceRoutes[$candidate])) {
        $sourceUrl = site_url($sourceRoutes[$candidate] . $sourceId);
        break;
    }
}

if ($sourceUrl === null && $sourceId > 0) {
    try {
        $db = \Config\Database::connect();
        $searchMap = [
            ['table' => 'purchase_receipts', 'route' => 'purchase/receipts/', 'label' => 'Purchase Receipt'],
            ['table' => 'purchase_invoices', 'route' => 'ap/purchase-invoices/', 'label' => 'Purchase Invoice'],
            ['table' => 'ap_payments', 'route' => 'ap/payments/', 'label' => 'AP Payment'],
            ['table' => 'sales_deliveries', 'route' => 'sales/deliveries/', 'label' => 'Sales Delivery'],
            ['table' => 'sales_invoices', 'route' => 'ar/sales-invoices/', 'label' => 'Sales Invoice'],
            ['table' => 'ar_receipts', 'route' => 'ar/receipts/', 'label' => 'AR Receipt'],
            ['table' => 'cash_bank_entries', 'route' => 'cash-bank/bank-entries/', 'label' => 'Cash/Bank Entry'],
        ];
        foreach ($searchMap as $item) {
            if ($db->tableExists($item['table']) && $db->table($item['table'])->where('gl_entry_id', $entry['id'])->countAllResults() > 0) {
                $row = $db->table($item['table'])->where('gl_entry_id', $entry['id'])->get(1)->getRowArray();
                if ($row !== null) {
                    $sourceUrl = site_url($item['route'] . (int) $row['id']);
                    $sourceLabel = $item['label'];
                    $sourceNo = (string) ($row['receipt_no'] ?? $row['invoice_no'] ?? $row['payment_no'] ?? $row['delivery_no'] ?? $row['entry_no'] ?? $sourceNo);
                    break;
                }
            }
        }
    } catch (\Throwable) {
        $sourceUrl = null;
    }
}
?>
<div class="row">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($entry['journal_no'] ?? '-') ?></h4>
                        <p class="text-muted mb-0"><?= esc($entry['description'] ?? '-') ?></p>
                    </div>
                    <a href="<?= site_url('gl/entries') ?>" class="btn btn-light">Back</a>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3"><div class="text-muted">Date</div><div class="fw-semibold"><?= esc($entry['journal_date'] ?? '-') ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Period</div><div class="fw-semibold"><?= esc($entry['period'] ?? '-') ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Debit</div><div class="fw-semibold"><?= esc(number_format((float) ($entry['total_debit'] ?? 0), 2)) ?></div></div>
                    <div class="col-md-6 mb-3"><div class="text-muted">Credit</div><div class="fw-semibold"><?= esc(number_format((float) ($entry['total_credit'] ?? 0), 2)) ?></div></div>
                </div>

                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th style="width:180px;">Source Module</th><td><?= esc($sourceModule !== '' ? $sourceModule : '-') ?></td></tr>
                        <tr><th>Source Type</th><td><?= esc($sourceType !== '' ? $sourceType : '-') ?></td></tr>
                        <tr><th>Source No</th><td><?= esc($sourceNo !== '' ? $sourceNo : '-') ?></td></tr>
                        <tr><th>Status</th><td><?= esc($entry['status'] ?? '-') ?></td></tr>
                        <tr><th>Posted At</th><td><?= esc($entry['posted_at'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="mt-3 d-grid gap-2">
                    <?php if ($sourceUrl !== null): ?>
                        <a href="<?= esc($sourceUrl) ?>" class="btn btn-primary"><i class="bx bx-link-external me-1"></i> Open Source Document</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary" disabled>No source document link</button>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Journal Lines</h4>
                        <p class="text-muted mb-0">Debit/credit details for this journal.</p>
                    </div>
                    <span class="badge bg-light text-dark"><?= esc((string) count($lines)) ?> line(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Account</th>
                                <th>Description</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no'] ?? '-') ?></td>
                                <td><div class="fw-semibold"><?= esc($line['account_no'] ?? '-') ?></div><small class="text-muted"><?= esc($line['account_name'] ?? '-') ?></small></td>
                                <td><?= esc($line['description'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['debit'] ?? 0), 2)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['credit'] ?? 0), 2)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th class="text-end"><?= esc(number_format((float) ($entry['total_debit'] ?? 0), 2)) ?></th>
                                <th class="text-end"><?= esc(number_format((float) ($entry['total_credit'] ?? 0), 2)) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
