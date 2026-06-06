<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($entry['journal_no'] ?? '-') ?></h4>
                <p class="text-muted mb-0"><?= esc($entry['description'] ?? '-') ?></p>
            </div>
            <a href="<?= site_url('gl/entries') ?>" class="btn btn-light">Back</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-3"><div class="text-muted">Date</div><div class="fw-semibold"><?= esc($entry['journal_date'] ?? '-') ?></div></div>
            <div class="col-md-3"><div class="text-muted">Period</div><div class="fw-semibold"><?= esc($entry['period'] ?? '-') ?></div></div>
            <div class="col-md-3"><div class="text-muted">Debit</div><div class="fw-semibold"><?= esc(number_format((float) ($entry['total_debit'] ?? 0), 2)) ?></div></div>
            <div class="col-md-3"><div class="text-muted">Credit</div><div class="fw-semibold"><?= esc(number_format((float) ($entry['total_credit'] ?? 0), 2)) ?></div></div>
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
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
