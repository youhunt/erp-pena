<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Bank Reconcile</h4>
                <p class="text-muted mb-0">Posted bank reconciliation batches and matched bank entries.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('cash-bank/statements') ?>" class="btn btn-outline-secondary">
                    <i class="bx bx-spreadsheet me-1"></i> Statement Imports
                </a>
                <a href="<?= site_url('cash-bank/reconciliations/new') ?>" class="btn btn-primary">
                    <i class="bx bx-plus me-1"></i> New Reconcile
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reconcile No</th>
                        <th>Statement Date</th>
                        <th>Bank</th>
                        <th>Reference</th>
                        <th class="text-end">Book Balance</th>
                        <th class="text-end">Statement Balance</th>
                        <th class="text-end">Difference</th>
                        <th class="text-end">Entries</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reconciliations as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($row['reconcile_no'] ?? '-') ?></td>
                        <td><?= esc($row['statement_date'] ?? '-') ?></td>
                        <td><?= esc($row['cash_bank_code'] ?? '-') ?></td>
                        <td><?= esc($row['statement_ref'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['book_balance'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['statement_balance'] ?? 0), 2)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['difference_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc($row['entry_count'] ?? 0) ?></td>
                        <td class="text-end"><a href="<?= site_url('cash-bank/reconciliations/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($reconciliations === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No bank reconciliation posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
