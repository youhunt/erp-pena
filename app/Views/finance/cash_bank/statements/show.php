<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($import['cash_bank_code'] ?? '-') ?> Statement Import</h4>
                <p class="text-muted mb-0"><?= esc($import['source_filename'] ?? '-') ?></p>
            </div>
            <div class="d-flex gap-2">
                <form method="post" action="<?= site_url('cash-bank/statements/' . $import['id'] . '/match') ?>" onsubmit="return confirm('Auto match statement lines to posted bank entries?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-link me-1"></i> Auto Match</button>
                </form>
                <?php if (($import['status'] ?? '') !== 'reconciled' && (int) ($import['matched_count'] ?? 0) > 0): ?>
                    <a href="<?= site_url('cash-bank/reconciliations/new?statement_import_id=' . $import['id']) ?>" class="btn btn-success">
                        <i class="bx bx-check-circle me-1"></i> Create Reconcile
                    </a>
                <?php endif ?>
                <a href="<?= site_url('cash-bank/statements') ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Statement Date</div>
                    <div class="fw-semibold"><?= esc($import['statement_date'] ?? '-') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Debit Total</div>
                    <div class="fw-semibold text-end"><?= esc(number_format((float) ($import['debit_total'] ?? 0), 2)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Credit Total</div>
                    <div class="fw-semibold text-end"><?= esc(number_format((float) ($import['credit_total'] ?? 0), 2)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Lines</div>
                    <div class="fw-semibold text-end"><?= esc($import['line_count'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Date</th>
                        <th>Value Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">Cash/Bank Entry</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?= esc($line['line_no'] ?? '-') ?></td>
                        <td><?= esc($line['statement_date'] ?? '-') ?></td>
                        <td><?= esc($line['value_date'] ?? '-') ?></td>
                        <td><?= esc($line['reference_no'] ?? '-') ?></td>
                        <td><?= esc($line['description'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($line['debit_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($line['credit_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($line['balance_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= ! empty($line['cash_bank_entry_id']) ? esc($line['cash_bank_entry_id']) : '-' ?></td>
                        <td><span class="badge bg-secondary"><?= esc($line['match_status'] ?? 'unmatched') ?></span></td>
                        <td class="text-end">
                            <?php if (($line['match_status'] ?? '') === 'unmatched'): ?>
                                <a href="<?= site_url('cash-bank/bank-entries/new?statement_line_id=' . $line['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    Create Bank Entry
                                </a>
                            <?php else: ?>
                                -
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($lines === []): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No statement lines found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
