<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($entry['entry_no'] ?? '-') ?></h4>
                <p class="text-muted mb-0"><?= esc($entry['description'] ?? '-') ?></p>
            </div>
            <a href="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries')) ?>" class="btn btn-light">Back</a>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><div class="text-muted">Date</div><div class="fw-semibold"><?= esc($entry['entry_date'] ?? '-') ?></div></div>
            <div class="col-md-3 mb-3"><div class="text-muted">Type</div><div class="fw-semibold"><?= esc($entry['entry_type'] ?? '-') ?></div></div>
            <div class="col-md-3 mb-3"><div class="text-muted">Cash/Bank</div><div class="fw-semibold"><?= esc($entry['cash_bank_code'] ?? '-') ?></div></div>
            <div class="col-md-3 mb-3"><div class="text-muted">Amount</div><div class="fw-semibold"><?= esc(number_format((float) ($entry['amount'] ?? 0), 2)) ?></div></div>
        </div>

        <table class="table table-bordered mb-0">
            <tbody>
                <tr><th style="width:220px;">Reference</th><td><?= esc($entry['reference_no'] ?? '-') ?></td></tr>
                <tr><th>Counter Account</th><td><code><?= esc($entry['counter_account_no'] ?? '-') ?></code></td></tr>
                <tr><th>GL Entry ID</th><td><?= ! empty($entry['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $entry['gl_entry_id']) . '">#' . esc($entry['gl_entry_id']) . '</a>' : '-' ?></td></tr>
                <tr><th>Status</th><td><?= esc($entry['status'] ?? '-') ?></td></tr>
                <tr><th>Posted At</th><td><?= esc($entry['posted_at'] ?? '-') ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
