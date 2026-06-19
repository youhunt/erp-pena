<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Bank Statement Imports</h4>
                <p class="text-muted mb-0">Imported bank-side statement lines for future reconciliation matching.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('cash-bank/statements/template') ?>" class="btn btn-outline-secondary">
                    <i class="bx bx-download me-1"></i> Template
                </a>
                <a href="<?= site_url('cash-bank/statements/import') ?>" class="btn btn-primary">
                    <i class="bx bx-upload me-1"></i> Import Excel
                </a>
            </div>
        </div>

        <div class="alert alert-light border">
            <div class="fw-semibold mb-2">Bank Statement Data Flow</div>
            <div class="row g-3 small text-muted">
                <div class="col-md-3"><span class="badge bg-primary me-1">1</span> Import rekening koran Excel sebagai data bank-side.</div>
                <div class="col-md-3"><span class="badge bg-primary me-1">2</span> Auto Match cocokkan mutasi bank dengan Bank Entry yang sudah diposting.</div>
                <div class="col-md-3"><span class="badge bg-primary me-1">3</span> Buat Bank Entry adjustment untuk baris yang belum match seperti biaya admin atau bunga.</div>
                <div class="col-md-3"><span class="badge bg-primary me-1">4</span> Jika semua baris matched, buat Bank Reconcile untuk mengunci hasil rekonsiliasi.</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Statement Date</th>
                        <th>Bank</th>
                        <th>Reference</th>
                        <th>File</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Net</th>
                        <th class="text-end">Lines</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($imports as $row): ?>
                    <tr>
                        <td><?= esc($row['statement_date'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= esc($row['cash_bank_code'] ?? '-') ?></td>
                        <td><?= esc($row['statement_ref'] ?? '-') ?></td>
                        <td><?= esc($row['source_filename'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['debit_total'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['credit_total'] ?? 0), 2)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['net_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc($row['line_count'] ?? 0) ?></td>
                        <td><span class="badge bg-info"><?= esc($row['status'] ?? 'imported') ?></span></td>
                        <td class="text-end">
                            <a href="<?= site_url('cash-bank/statements/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($imports === []): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No bank statement imported yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
