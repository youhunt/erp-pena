<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                <p class="text-muted mb-0">Posted <?= esc($type) ?> transactions and linked GL posting when available.</p>
            </div>
            <a href="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries') . '/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New Entry
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Entry No</th>
                        <th>Type</th>
                        <th>Cash/Bank</th>
                        <th>Reference</th>
                        <th class="text-end">Amount</th>
                        <th>GL</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= esc($entry['entry_date'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= esc($entry['entry_no'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= str_ends_with((string) ($entry['entry_type'] ?? ''), '_in') ? 'success' : 'danger' ?>"><?= esc($entry['entry_type'] ?? '-') ?></span></td>
                        <td><?= esc($entry['cash_bank_code'] ?? '-') ?></td>
                        <td><?= esc($entry['reference_no'] ?? '-') ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($entry['amount'] ?? 0), 2)) ?></td>
                        <td><?= ! empty($entry['gl_entry_id']) ? '<span class="badge bg-success">Posted</span>' : '<span class="badge bg-secondary">No GL</span>' ?></td>
                        <td class="text-end"><a href="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries') . '/' . $entry['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($entries === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No entry found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
