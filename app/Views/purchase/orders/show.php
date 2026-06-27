<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($order['document_status'] ?? $order['status'] ?? 'draft');
$poNo = trim((string) ($order['po_no'] ?? '-'));
$hasReceivedLine = false;
foreach ($lines as $line) {
    if ((float) ($line['qty_received'] ?? 0) > 0) {
        $hasReceivedLine = true;
        break;
    }
}
$canEditPo = $status === 'draft' && ! $hasReceivedLine;
$canReturnToDraft = in_array($status, ['submitted', 'approved'], true) && ! $hasReceivedLine;
$subtotal = (float) ($order['subtotal_amount'] ?? 0);
$discountPercent = (float) ($order['discount_percent'] ?? 0);
$discountPercentAmount = round($subtotal * $discountPercent / 100, 2);
$manualDiscountAmount = (float) ($order['discount_amount'] ?? 0);
$totalDiscountAmount = round($discountPercentAmount + $manualDiscountAmount, 2);
$db = \Config\Database::connect();
$formatCodeName = static function (?string $code, ?string $name): string {
    $code = trim((string) $code);
    $name = trim((string) $name);
    if ($code !== '' && $name !== '') {
        return $code . ' - ' . $name;
    }
    return $code !== '' ? $code : ($name !== '' ? $name : '-');
};
$masterDisplay = static function (string $table, mixed $id, mixed $storedValue, string $codeField = 'code', string $nameField = 'name') use ($db, $formatCodeName): string {
    $storedValue = trim((string) ($storedValue ?? ''));
    $id = (int) ($id ?? 0);

    if ($db->tableExists($table)) {
        $row = null;
        if ($id > 0) {
            $row = $db->table($table)->where('id', $id)->get(1)->getRowArray();
        }
        if ($row === null && $storedValue !== '' && ! is_numeric($storedValue) && $db->fieldExists($codeField, $table)) {
            $row = $db->table($table)->where($codeField, $storedValue)->get(1)->getRowArray();
        }
        if ($row !== null) {
            return $formatCodeName((string) ($row[$codeField] ?? ''), (string) ($row[$nameField] ?? ''));
        }
    }

    if ($storedValue !== '' && ! is_numeric($storedValue)) {
        return $storedValue;
    }
    return $id > 0 ? '#' . $id : '-';
};
$supplierCode = trim((string) ($order['supplier_code'] ?? $order['supplier'] ?? ''));
$supplierName = trim((string) ($order['supplier_name'] ?? ''));
$supplierDisplay = $formatCodeName($supplierCode, $supplierName);
$companyDisplay = $masterDisplay('companies', $order['company_id'] ?? null, $order['company'] ?? null, 'code', 'name');
$siteDisplay = $masterDisplay('sites', $order['site_id'] ?? null, $order['site'] ?? null, 'code', 'name');
$itemDisplay = static function (array $line): array {
    $code = trim((string) ($line['item_code'] ?? $line['item'] ?? $line['item_no'] ?? ''));
    $name = trim((string) ($line['item_name'] ?? $line['description'] ?? ''));
    if ($code === '' && $name !== '') {
        $code = $name;
        $name = '';
    }
    return [$code !== '' ? $code : '-', $name !== '' ? $name : '-'];
};
?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Purchase Order</h4>
                        <p class="text-muted mb-0 font-monospace"><?= esc($poNo) ?></p>
                    </div>
                    <span class="badge bg-<?= match ($status) { 'draft' => 'secondary', 'submitted' => 'info', 'approved' => 'success', 'partial_received' => 'warning', 'received' => 'primary', 'closed' => 'dark', 'cancelled' => 'danger', default => 'secondary' } ?>"><?= esc($status) ?></span>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>PO No</th><td class="font-monospace"><?= esc($poNo) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($order['po_date']) ?></td></tr>
                        <tr><th>Delivery</th><td><?= esc($order['delivery_date'] ?? '-') ?></td></tr>
                        <tr><th>Arrive</th><td><?= esc($order['arrive_date'] ?? '-') ?></td></tr>
                        <tr><th>Supplier</th><td><?= esc($supplierDisplay) ?></td></tr>
                        <tr><th>Terms</th><td><?= esc($order['terms_code'] ?? '-') ?></td></tr>
                        <tr><th>Currency</th><td><?= esc($order['currency_code']) ?></td></tr>
                        <tr><th>VAT Code</th><td><?= esc($order['vat_code'] ?? '-') ?></td></tr>
                        <tr><th>WHT Code</th><td><?= esc($order['wht_code'] ?? '-') ?></td></tr>
                        <tr><th>Company</th><td><?= esc($companyDisplay) ?></td></tr>
                        <tr><th>Site</th><td><?= esc($siteDisplay) ?></td></tr>
                        <tr><th>Submitted</th><td><?= esc($order['submitted_at'] ?? '-') ?></td></tr>
                        <tr><th>Approved</th><td><?= esc($order['approved_at'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= site_url('purchase/orders') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <a href="<?= site_url('print/purchase-orders/' . (int) $order['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer me-1"></i> Print</a>
                    <?php if ($canEditPo): ?>
                        <a href="<?= site_url('purchase/orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a>
                    <?php endif ?>
                    <?php if (in_array($status, ['cancelled', 'closed'], true)): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/activate') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-success" onclick="return confirm('Activate this PO? Closed PO will return to received/partial status based on receipt qty.')"><i class="bx bx-reset me-1"></i> Activate</button>
                        </form>
                    <?php endif ?>
                    <?php if ($canReturnToDraft): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/activate') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-warning" onclick="return confirm('Return this PO to draft so it can be edited?')"><i class="bx bx-undo me-1"></i> Return to Draft</button>
                        </form>
                    <?php endif ?>
                    <?php if ($status === 'draft'): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/submit') ?>"><?= csrf_field() ?><button class="btn btn-info" onclick="return confirm('Submit this PO?')">Submit</button></form>
                    <?php endif ?>
                    <?php if ($status === 'submitted'): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/approve') ?>"><?= csrf_field() ?><button class="btn btn-success" onclick="return confirm('Approve this PO?')">Approve</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['approved','partial_received'], true)): ?>
                        <a href="<?= site_url('purchase/orders/' . $order['id'] . '/receive') ?>" class="btn btn-primary"><i class="bx bx-package me-1"></i> Receive</a>
                    <?php endif ?>
                    <?php if ($status === 'received'): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/close') ?>"><?= csrf_field() ?><button class="btn btn-dark" onclick="return confirm('Close this PO?')">Close</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['draft','submitted'], true)): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/cancel') ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cancel_reason" value="Cancelled from PO detail">
                            <button class="btn btn-outline-danger" onclick="return confirm('Cancel this PO?')">Cancel</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Header Amount</h4>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Subtotal</th><td class="text-end"><?= esc(number_format($subtotal, 2)) ?></td></tr>
                        <tr class="table-light">
                            <th>Discount</th>
                            <td class="text-end">
                                <div class="fw-semibold"><?= esc(number_format($totalDiscountAmount, 2)) ?></div>
                                <small class="text-muted"><?= esc(number_format($discountPercent, 4)) ?>% = <?= esc(number_format($discountPercentAmount, 2)) ?> + amount <?= esc(number_format($manualDiscountAmount, 2)) ?></small>
                            </td>
                        </tr>
                        <tr><th>Freight</th><td class="text-end"><?= esc(number_format((float) ($order['freight_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Other Amount</th><td class="text-end"><?= esc(number_format((float) ($order['other_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Special Charge</th><td class="text-end"><?= esc(number_format((float) ($order['special_charge_amount'] ?? 0), 2)) ?></td></tr>
                        <tr class="table-light"><th>Total PO</th><td class="text-end fw-semibold"><?= esc(number_format((float) $order['total_amount'], 2)) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Line Items</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Item</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Outstanding</th><th>UoM</th><th class="text-end">Price</th><th class="text-end">Line Total</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <?php [$displayCode, $displayName] = $itemDisplay($line); ?>
                            <tr>
                                <td><?= esc($line['po_line'] ?? $line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($displayCode) ?></div><small class="text-muted"><?= esc($displayName) ?></small></td>
                                <td><?= esc($line['description'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_received'] ?? 0), 4)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($line['line_status'] ?? 'open') ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Related GL Entries</h4>
                        <p class="text-muted mb-0">PO does not post GL directly. Journals below come from receipt, invoice, payment, or reversal documents linked to this PO.</p>
                    </div>
                    <a href="<?= site_url('gl/entries') ?>" class="btn btn-sm btn-outline-secondary">Open GL</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Source</th>
                                <th>Document</th>
                                <th>Date</th>
                                <th>Role</th>
                                <th>Journal</th>
                                <th>Journal Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($relatedGlEntries ?? []) as $entry): ?>
                            <tr>
                                <td><?= esc($entry['module'] ?? '-') ?></td>
                                <td><a href="<?= esc($entry['document_url']) ?>"><?= esc($entry['document_no'] ?? '-') ?></a></td>
                                <td><?= esc($entry['document_date'] ?? '-') ?></td>
                                <td><span class="badge bg-<?= ($entry['role'] ?? '') === 'reversal' ? 'warning' : 'success' ?>"><?= esc($entry['role'] ?? '-') ?></span></td>
                                <td><a href="<?= esc($entry['gl_url']) ?>"><?= esc($entry['journal_no'] ?? ('#' . ($entry['gl_entry_id'] ?? ''))) ?></a></td>
                                <td><?= esc($entry['journal_date'] ?? '-') ?></td>
                                <td><?= esc($entry['status'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (($relatedGlEntries ?? []) === []): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No related GL entry yet. GL will appear after receipt, invoice, payment, or reversal posting.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (! empty($order['notes']) || ! empty($order['remarks'])): ?>
            <div class="card">
                <div class="card-body">
                    <?php if (! empty($order['notes'])): ?><h4 class="card-title mb-2">Notes</h4><p class="text-muted"><?= esc($order['notes']) ?></p><?php endif ?>
                    <?php if (! empty($order['remarks'])): ?><h4 class="card-title mb-2">Remarks</h4><p class="text-muted mb-0"><?= esc($order['remarks']) ?></p><?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
