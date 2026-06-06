<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($reconciliation['reconcile_no'] ?? '-') ?></h4>
                        <p class="text-muted mb-0"><?= esc($reconciliation['cash_bank_code'] ?? '-') ?></p>
                    </div>
                    <span class="badge bg-success"><?= esc($reconciliation['status'] ?? 'posted') ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th>Statement Date</th><td><?= esc($reconciliation['statement_date'] ?? '-') ?></td></tr>
                    <tr><th>Reference</th><td><?= esc($reconciliation['statement_ref'] ?? '-') ?></td></tr>
                    <tr><th>Book Balance</th><td class="text-end"><?= esc(number_format((float) ($reconciliation['book_balance'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Statement Balance</th><td class="text-end"><?= esc(number_format((float) ($reconciliation['statement_balance'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Difference</th><td class="text-end fw-semibold"><?= esc(number_format((float) ($reconciliation['difference_amount'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Reconciled Amount</th><td class="text-end"><?= esc(number_format((float) ($reconciliation['reconciled_amount'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Entries</th><td class="text-end"><?= esc($reconciliation['entry_count'] ?? 0) ?></td></tr>
                    <tr><th>Posted At</th><td><?= esc($reconciliation['posted_at'] ?? '-') ?></td></tr>
                </table>
                <div class="mt-3">
                    <a href="<?= site_url('cash-bank/reconciliations') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                </div>
            </div>
        </div>
        <?php if (! empty($reconciliation['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-2">Notes</h4><p class="text-muted mb-0"><?= esc($reconciliation['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Matched Entries</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Entry No</th><th>Type</th><th>Reference</th><th class="text-end">Amount</th><th>GL</th></tr></thead>
                        <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?= esc($entry['entry_date'] ?? '-') ?></td>
                                <td><a href="<?= site_url('cash-bank/bank-entries/' . $entry['id']) ?>"><?= esc($entry['entry_no'] ?? '-') ?></a></td>
                                <td><?= esc($entry['entry_type'] ?? '-') ?></td>
                                <td><?= esc($entry['reference_no'] ?? '-') ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) ($entry['amount'] ?? 0), 2)) ?></td>
                                <td><?= ! empty($entry['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $entry['gl_entry_id']) . '">#' . esc($entry['gl_entry_id']) . '</a>' : '-' ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($entries === []): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No matched entry.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
